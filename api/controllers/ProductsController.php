<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../../core/ProductContent.php';

/**
 * One catalogue route; the role decides visibility.
 *
 * `active` is a field on the product, not a side-channel set — which is why
 * PATCH covers both the edit form and the listed/delisted switch, and why
 * setProductActive() and delistedProductIds() both disappear.
 *
 * Delisting never deletes: a report that already references a product keeps
 * reading correctly because report_sales denormalises the name and colour.
 *
 * There is deliberately no price field.
 */
final class ProductsController extends V1Controller
{
    private const WRITABLE = [
        'category'         => 'category',
        'brand'            => 'brand',
        'name'             => 'name',
        'modelCode'        => 'model_code',
        'rating'           => 'rating',
        'warrantyYears'    => 'warranty_years',
        'warrantyNote'     => 'warranty_note',
        'rangeKm'          => 'range_km',
        'topSpeedKmph'     => 'top_speed_kmph',
        'chargingTime'     => 'charging_time',
        'batteryCapacity'  => 'battery_capacity',
        'motorPower'       => 'motor_power',
        'loadCapacityKg'   => 'load_capacity_kg',
        'isFeatured'       => 'is_featured',
        'is_featured'      => 'is_featured',
        'featuredOrder'    => 'featured_order',
        'featured_order'   => 'featured_order',
        'slug'             => 'slug',
        'heroImage'        => 'hero_image',
        'hero_image'       => 'hero_image',
    ];

    public function index(): never
    {
        $where = ['p.org_id = ?'];
        $params = [Ctx::orgId()];

        // An employee sees listed products only. includeDelisted is ignored
        // rather than rejected — it is a no-op for a role that could never see
        // them, not an error.
        if (!Ctx::isAdmin() || !Wire::bool($this->query('includeDelisted', false))) {
            $where[] = 'p.active = 1';
        }

        if ($category = Wire::enumIn((string) $this->query('category', ''), ['scooty', 'bike', 'bicycle', 'others'])) {
            $where[] = 'p.category = ?';
            $params[] = $category;
        }

        if ($query = $this->query('query')) {
            $where[] = '(p.brand LIKE ? OR p.name LIKE ? OR p.model_code LIKE ?)';
            array_push($params, "%{$query}%", "%{$query}%", "%{$query}%");
        }

        if ($since = Wire::ts((string) $this->query('updatedSince', ''))) {
            $where[] = 'p.updated_at > ?';
            $params[] = $since;
        }

        $clause = implode(' AND ', $where);

        $stamp = db_fetch_one(
            "SELECT COUNT(*) AS n, COALESCE(MAX(p.updated_at), '') AS latest FROM products p WHERE {$clause}",
            $params,
        );

        Envelope::freshness('products-' . $stamp['n'] . '-' . $stamp['latest']);

        $total = (int) $stamp['n'];
        $limitRaw = $this->query('limit');

        if (Cursor::isCountOnly($limitRaw)) {
            Envelope::ok([], ['total' => $total]);
        }

        $limit = Cursor::limit($limitRaw);
        $cursor = Cursor::decode($this->query('cursor'));

        if ($cursor !== null) {
            $clause .= ' AND p.id > ?';
            $params[] = $cursor['id'];
        }

        $rows = db_fetch_all(
            "SELECT p.* FROM products p WHERE {$clause} ORDER BY p.id LIMIT ?",
            [...$params, $limit + 1],
        );

        $next = null;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $next = Cursor::encode(['id' => $rows[count($rows) - 1]['id']]);
        }

        Envelope::ok($this->withColors($rows), ['total' => $total, 'nextCursor' => $next]);
    }

    public function show(): never
    {
        $product = $this->find((string) $this->param('id'));

        Envelope::ok($this->withColors([$product])[0]);
    }

    public function store(): never
    {
        $this->requireAdmin();

        $body = $this->normalizeDraft(ApiRequest::body());
        $this->validateDraft($body, true);
        $body = $this->validateContent($body);

        $id = Uuid::v4();
        $now = Wire::now();

        $columns = ['id', 'org_id', 'listed_at', 'created_by', 'updated_at'];
        $values  = [$id, Ctx::orgId(), $now, Ctx::id(), $now];

        $written = [];
        foreach (self::WRITABLE as $wire => $column) {
            if (isset($written[$column])) continue;
            $written[$column] = true;
            $columns[] = $column;
            $values[] = $this->columnValue($wire, $body[$wire] ?? null);
        }

        $columns[] = 'highlights';
        $values[] = json_encode(array_values((array) ($body['highlights'] ?? [])));
        foreach (['page_content', 'feature_cards', 'default_color_id'] as $key) {
            $columns[] = $key;
            $values[] = $key === 'default_color_id' ? ($body[$key] ?? null) : json_encode($body[$key] ?? ($key === 'page_content' ? ProductContent::DEFAULTS : []));
        }
        $columns[] = 'active';
        $values[] = Wire::bool($body['active'] ?? true) ? 1 : 0;

        global $pdo;
        $pdo->beginTransaction();
        try {
            db_execute(
                'INSERT INTO products (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $values,
            );
            $this->writeColors($id, $body['colors'] ?? []);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->saveError($e);
        }

        $product = $this->withColors([$this->find($id)])[0];

        Ctx::audit('product', $id, 'create', null, $product);

        // Listing a product is the whole point of the screen, so the fan-out is
        // a server-side side effect rather than a second client call.
        Engine::notifyTeam(
            'new_product',
            'New product listed',
            $product['brand'] . ' ' . $product['name'] . ' is now available to log.',
            ['productId' => $id],
        );

        Envelope::created($product);
    }

    public function update(): never
    {
        $this->requireAdmin();

        $id = (string) $this->param('id');
        $before = $this->withColors([$this->find($id)])[0];
        $body = $this->normalizeDraft(ApiRequest::body());
        $this->validateDraft($body, false);
        $body = $this->validateContent($body, $before);

        $sets = [];
        $params = [];

        $written = [];
        foreach (self::WRITABLE as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }
            if (isset($written[$column])) continue;
            $written[$column] = true;

            $sets[] = "{$column} = ?";
            $params[] = $this->columnValue($wire, $body[$wire]);
        }

        if (array_key_exists('highlights', $body)) {
            $sets[] = 'highlights = ?';
            $params[] = json_encode(array_values((array) $body['highlights']));
        }
        foreach (['page_content', 'feature_cards', 'default_color_id'] as $key) {
            if (!array_key_exists($key, $body)) continue;
            $sets[] = "{$key} = ?";
            $params[] = $key === 'default_color_id' ? $body[$key] : json_encode($body[$key]);
        }

        // The listed/delisted switch is this, not a second route.
        $delisting = false;
        if (array_key_exists('active', $body)) {
            $active = Wire::bool($body['active']);
            $delisting = !$active && $before['active'];
            $sets[] = 'active = ?';
            $params[] = $active ? 1 : 0;
        }

        global $pdo;
        $pdo->beginTransaction();
        try {
            if ($sets || array_key_exists('colors', $body)) {
                $params[] = Wire::now();
                $params[] = $id;

                $prefix = $sets ? implode(', ', $sets) . ', ' : '';
                db_execute('UPDATE products SET ' . $prefix . 'updated_at = ? WHERE id = ?', $params);
            }

            if (array_key_exists('colors', $body)) {
                $this->writeColors($id, $body['colors']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->saveError($e);
        }

        $after = $this->withColors([$this->find($id)])[0];

        Ctx::audit('product', $id, $delisting ? 'delist' : 'update', $before, $after);

        if (!$delisting) {
            Engine::notifyTeam(
                'product_updated',
                'Product updated',
                $after['brand'] . ' ' . $after['name'] . ' has changed.',
                ['productId' => $id],
            );
        }

        Envelope::ok($after);
    }

    // ------------------------------------------------------------- internals

    private function find(string $id): array
    {
        $where = 'id = ? AND org_id = ?';

        if (!Ctx::isAdmin()) {
            $where .= ' AND active = 1';
        }

        $product = db_fetch_one("SELECT * FROM products WHERE {$where}", [$id, Ctx::orgId()]);

        if (!$product) {
            Envelope::notFound('PRODUCT_NOT_FOUND', 'No such product');
        }

        return $product;
    }

    /** Loads colours and their per-colour galleries for a page of products. */
    private function withColors(array $products): array
    {
        if (!$products) {
            return [];
        }

        $ids = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $colors = db_fetch_all(
            "SELECT * FROM product_colors WHERE product_id IN ({$placeholders}) ORDER BY position, name",
            $ids,
        );

        $images = [];
        if ($colors) {
            $colorIds = array_column($colors, 'id');
            $cph = implode(',', array_fill(0, count($colorIds), '?'));

            foreach (db_fetch_all(
                "SELECT * FROM product_color_images WHERE color_id IN ({$cph}) ORDER BY position",
                $colorIds,
            ) as $image) {
                $images[$image['color_id']][] = $image['url'];
            }
        }

        $byProduct = [];
        foreach ($colors as $color) {
            $byProduct[$color['product_id']][] = Present::productColor($color, $images[$color['id']] ?? []);
        }

        return array_map(
            static fn(array $p) => Present::product($p, $byProduct[$p['id']] ?? []),
            $products,
        );
    }

    private function writeColors(string $productId, mixed $colors): void
    {
        if (!is_array($colors)) {
            return;
        }

        $existing = array_column(db_fetch_all('SELECT id FROM product_colors WHERE product_id = ?', [$productId]), 'id');
        $kept = [];

        foreach (array_values($colors) as $position => $color) {
            if (!is_array($color) || empty($color['name'])) {
                continue;
            }

            $colorId = !empty($color['id']) && is_string($color['id']) ? $color['id'] : Uuid::v4();
            $pos = isset($color['position']) ? Wire::int($color['position']) : $position;
            $kept[] = $colorId;
            $values = [(string) $color['name'], Wire::int($color['argb'] ?? 0),
                Wire::bool($color['inStock'] ?? $color['in_stock'] ?? true) ? 1 : 0, $pos];
            if (in_array($colorId, $existing, true)) {
                db_execute('UPDATE product_colors SET name = ?, argb = ?, in_stock = ?, position = ? WHERE id = ? AND product_id = ?', [...$values, $colorId, $productId]);
            } else {
                db_execute('INSERT INTO product_colors (name, argb, in_stock, position, id, product_id) VALUES (?, ?, ?, ?, ?, ?)', [...$values, $colorId, $productId]);
            }
            $oldImages = db_fetch_all('SELECT * FROM product_color_images WHERE color_id = ? ORDER BY position', [$colorId]);
            $imageIds = [];

            $rawImages = $color['imageUrls'] ?? $color['images'] ?? [];
            foreach (array_values((array) $rawImages) as $i => $item) {
                $url = is_array($item) ? ($item['url'] ?? '') : (string) $item;
                if ($url === '') continue;
                $match = null;
                foreach ($oldImages as $image) {
                    if ($image['url'] === $url && !in_array($image['id'], $imageIds, true)) { $match = $image; break; }
                }
                $imageId = $match['id'] ?? Uuid::v4();
                $imageIds[] = $imageId;
                if ($match) db_execute('UPDATE product_color_images SET position = ? WHERE id = ?', [$i, $imageId]);
                else db_execute('INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, ?)', [$imageId, $colorId, $url, $i]);
            }
            foreach ($oldImages as $image) if (!in_array($image['id'], $imageIds, true)) db_execute('DELETE FROM product_color_images WHERE id = ?', [$image['id']]);
        }
        foreach ($existing as $colorId) if (!in_array($colorId, $kept, true)) db_execute('DELETE FROM product_colors WHERE id = ? AND product_id = ?', [$colorId, $productId]);
    }

    private function normalizeDraft(array $body): array
    {
        foreach (self::WRITABLE as $wire => $column) {
            if (!array_key_exists($wire, $body) && array_key_exists($column, $body)) $body[$wire] = $body[$column];
        }
        foreach (['pageContent' => 'page_content', 'featureCards' => 'feature_cards', 'defaultColorId' => 'default_color_id'] as $wire => $column) {
            if (!array_key_exists($column, $body) && array_key_exists($wire, $body)) $body[$column] = $body[$wire];
        }
        return $body;
    }

    private function validateContent(array $body, array $before = []): array
    {
        $colors = $body['colors'] ?? $before['colors'] ?? [];
        if (!is_array($colors) || !array_is_list($colors) || count($colors) > 40) Envelope::invalid('Colors must be a list of up to 40 colors', 'colors');
        $ids = [];
        $names = [];
        foreach ($colors as &$color) {
            if (!is_array($color) || !is_string($color['name'] ?? null) || trim($color['name']) === '') Envelope::invalid('Each color needs a name', 'colors');
            $color['name'] = trim($color['name']);
            if (strlen($color['name']) > 250) Envelope::invalid('Color name is too long', 'colors');
            $id = $color['id'] ?? Uuid::v4();
            if (!is_string($id) || strlen($id) > 64 || $id === '' || in_array($id, $ids, true)) Envelope::invalid('Color IDs must be unique', 'colors');
            $owner = db_fetch_one('SELECT product_id FROM product_colors WHERE id = ?', [$id]);
            if ($owner && ($owner['product_id'] !== ($before['id'] ?? null))) Envelope::invalid('Color belongs to another product', 'colors');
            $nameKey = strtolower($color['name']);
            if (in_array($nameKey, $names, true)) Envelope::invalid('Color names must be unique within this product', 'colors');
            $names[] = $nameKey;
            $ids[] = $id;
            $color['id'] = $id;
            $images = $color['imageUrls'] ?? $color['images'] ?? [];
            if (!is_array($images) || count($images) > 40) Envelope::invalid('Each color supports up to 40 images', 'colors');
            foreach ($images as $image) {
                $url = is_array($image) ? ($image['url'] ?? '') : $image;
                if (!is_string($url) || strlen($url) > 2048 || !ProductContent::safeUrl($url)) Envelope::invalid('Invalid color image URL', 'colors');
            }
        }
        unset($color);
        if (array_key_exists('colors', $body)) $body['colors'] = $colors;
        $default = $body['default_color_id'] ?? $before['defaultColorId'] ?? ($ids[0] ?? null);
        if (!$default || !in_array($default, $ids, true)) {
            if (!empty($body['default_color_id'])) Envelope::invalid('Choose a default color from this product', 'default_color_id');
            $default = $ids[0] ?? null;
        }
        if (array_key_exists('colors', $body) || array_key_exists('default_color_id', $body) || !$before) $body['default_color_id'] = $default;
        try {
            if (array_key_exists('page_content', $body)) $body['page_content'] = array_replace(
                $before['pageContent'] ?? [], ProductContent::validatePage($body['page_content'])
            );
            $cards = $body['feature_cards'] ?? $before['featureCards'] ?? null;
            if ($cards === null) {
                $imageUrls = [];
                foreach ($colors as $color) foreach ($color['imageUrls'] ?? $color['images'] ?? [] as $image) $imageUrls[] = is_array($image) ? $image['url'] : $image;
                $cards = ProductContent::legacyFeatures($body, $imageUrls);
                $body['feature_cards'] = $cards;
            }
            ProductContent::validateFeatures($cards, $ids);
            if (array_key_exists('feature_cards', $body)) $body['feature_cards'] = ProductContent::validateFeatures($cards, $ids);
        } catch (InvalidArgumentException $e) {
            Envelope::invalid($e->getMessage(), 'page_content');
        }
        foreach (['hero_image', 'heroImage'] as $key) if (isset($body[$key]) && !ProductContent::safeUrl((string) $body[$key])) Envelope::invalid('Invalid hero image URL', $key);
        if (isset($body['slug']) && $body['slug'] !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $body['slug'])) Envelope::invalid('Use lowercase letters, digits and hyphens for the slug', 'slug');
        if (array_key_exists('page_content', $body)) {
            foreach ($body['page_content']['related_ids'] ?? [] as $id) {
                if (($before['id'] ?? null) === $id || !db_fetch_one('SELECT id FROM products WHERE id = ? AND org_id = ?', [$id, Ctx::orgId()])) Envelope::invalid('Choose related models from this catalogue', 'page_content');
            }
        }
        return $body;
    }

    private function saveError(Throwable $e): never
    {
        if ($e instanceof PDOException && (str_contains($e->getMessage(), 'model_code') || str_contains($e->getMessage(), 'slug'))) {
            Envelope::conflict('PRODUCT_IDENTIFIER_TAKEN', 'That model code or slug already exists');
        }
        error_log('Product save failed: ' . $e->getMessage());
        Envelope::fail('PRODUCT_SAVE_FAILED', 'Could not save the product. Your previous data has been preserved.', 500);
    }

    private function validateDraft(array $body, bool $creating): void
    {
        foreach (['category', 'brand', 'name', 'modelCode'] as $field) {
            if (($creating || array_key_exists($field, $body)) && trim((string) ($body[$field] ?? '')) === '') {
                Envelope::invalid("{$field} is required", $field);
            }
        }
        foreach (['rangeKm', 'topSpeedKmph', 'warrantyYears', 'loadCapacityKg', 'featuredOrder', 'featured_order'] as $field) {
            if (isset($body[$field]) && (!is_numeric($body[$field]) || (float) $body[$field] < 0 || floor((float) $body[$field]) != (float) $body[$field])) Envelope::invalid('Use a non-negative whole number', $field);
        }
        if (isset($body['highlights']) && (!is_array($body['highlights']) || count($body['highlights']) > 100 || array_filter($body['highlights'], static fn($item) => !is_string($item) || strlen($item) > 500))) Envelope::invalid('Highlights must be a list of short text items', 'highlights');

        if (isset($body['category'])
            && Wire::enumIn((string) $body['category'], ['scooty', 'bike', 'bicycle', 'others']) === null) {
            Envelope::invalid('category must be one of scooty, bike, bicycle, others', 'category');
        }

        if (isset($body['rating']) && (!is_numeric($body['rating']) || (float) $body['rating'] < 0 || (float) $body['rating'] > 5)) {
            Envelope::invalid('rating must be between 0 and 5', 'rating');
        }

        if ($creating && (!isset($body['colors']) || !is_array($body['colors']) || !$body['colors'])) {
            Envelope::invalid('At least one colour is required', 'colors');
        }
    }

    private function columnValue(string $wire, mixed $value): mixed
    {
        return match ($wire) {
            'category'       => Wire::enumIn((string) $value, ['scooty', 'bike', 'bicycle', 'others']) ?? 'others',
            'rating'         => Wire::float($value),
            'warrantyYears', 'rangeKm', 'topSpeedKmph', 'loadCapacityKg', 'featuredOrder', 'featured_order' => Wire::int($value),
            'isFeatured', 'is_featured' => Wire::bool($value) ? 1 : 0,
            default          => (string) ($value ?? ''),
        };
    }
}
