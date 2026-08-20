<?php

require_once __DIR__ . '/../support/V1Controller.php';

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

        $body = ApiRequest::body();
        $this->validateDraft($body, true);

        $id = Uuid::v4();
        $now = Wire::now();

        $columns = ['id', 'org_id', 'listed_at', 'created_by', 'updated_at'];
        $values  = [$id, Ctx::orgId(), $now, Ctx::id(), $now];

        foreach (self::WRITABLE as $wire => $column) {
            $columns[] = $column;
            $values[] = $this->columnValue($wire, $body[$wire] ?? null);
        }

        $columns[] = 'highlights';
        $values[] = json_encode(array_values((array) ($body['highlights'] ?? [])));

        try {
            db_execute(
                'INSERT INTO products (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $values,
            );
        } catch (PDOException) {
            Envelope::conflict('MODEL_CODE_TAKEN', 'That model code already exists in this organisation');
        }

        $this->writeColors($id, $body['colors'] ?? []);

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
        $body = ApiRequest::body();

        $sets = [];
        $params = [];

        foreach (self::WRITABLE as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }

            $sets[] = "{$column} = ?";
            $params[] = $this->columnValue($wire, $body[$wire]);
        }

        if (array_key_exists('highlights', $body)) {
            $sets[] = 'highlights = ?';
            $params[] = json_encode(array_values((array) $body['highlights']));
        }

        // The listed/delisted switch is this, not a second route.
        $delisting = false;
        if (array_key_exists('active', $body)) {
            $active = Wire::bool($body['active']);
            $delisting = !$active && $before['active'];
            $sets[] = 'active = ?';
            $params[] = $active ? 1 : 0;
        }

        if ($sets) {
            $params[] = Wire::now();
            $params[] = $id;

            try {
                db_execute('UPDATE products SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            } catch (PDOException) {
                Envelope::conflict('MODEL_CODE_TAKEN', 'That model code already exists in this organisation');
            }
        }

        if (array_key_exists('colors', $body)) {
            $this->writeColors($id, $body['colors']);
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

        // Replaced wholesale: the draft carries the full colour set, and a
        // partial merge would leave a removed colourway behind.
        db_execute("DELETE FROM product_colors WHERE product_id = ?", [$productId]);

        foreach (array_values($colors) as $position => $color) {
            if (!is_array($color) || empty($color['name'])) {
                continue;
            }

            $colorId = Uuid::v4();

            db_execute(
                "INSERT INTO product_colors (id, product_id, name, argb, in_stock, position)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $colorId, $productId, (string) $color['name'], Wire::int($color['argb'] ?? 0),
                    Wire::bool($color['inStock'] ?? true) ? 1 : 0, $position,
                ],
            );

            foreach (array_values((array) ($color['imageUrls'] ?? [])) as $i => $url) {
                db_execute(
                    "INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, ?)",
                    [Uuid::v4(), $colorId, (string) $url, $i],
                );
            }
        }
    }

    private function validateDraft(array $body, bool $creating): void
    {
        foreach (['category', 'brand', 'name', 'modelCode'] as $field) {
            if ($creating && empty($body[$field])) {
                Envelope::invalid("{$field} is required", $field);
            }
        }

        if (isset($body['category'])
            && Wire::enumIn((string) $body['category'], ['scooty', 'bike', 'bicycle', 'others']) === null) {
            Envelope::invalid('category must be one of scooty, bike, bicycle, others', 'category');
        }

        if (isset($body['rating']) && ((float) $body['rating'] < 0 || (float) $body['rating'] > 5)) {
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
            'warrantyYears', 'rangeKm', 'topSpeedKmph', 'loadCapacityKg' => Wire::int($value),
            default          => (string) ($value ?? ''),
        };
    }
}
