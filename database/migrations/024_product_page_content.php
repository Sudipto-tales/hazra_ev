<?php

require_once __DIR__ . '/../../core/ProductContent.php';

class ProductPageContent extends Migration
{
    public function up()
    {
        $this->addColumn('products', 'page_content', '{text}');
        $this->addColumn('products', 'feature_cards', '{text}');
        $this->addColumn('products', 'default_color_id', '{str:64}');
        $this->addColumn('website_leads', 'product_id', '{str:64}');
        $this->addColumn('website_leads', 'color_id', '{str:64}');
        $this->addColumn('website_leads', 'source', '{str:64}');
        $this->addColumn('website_leads', 'submission_key', '{str:64}');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_leads_product ON website_leads (product_id, type, created_at)');
        $this->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_leads_submission ON website_leads (submission_key)');
        $colors = $this->pdo->prepare('SELECT id FROM product_colors WHERE product_id = ? ORDER BY position, name');
        $images = $this->pdo->prepare('SELECT i.url FROM product_color_images i JOIN product_colors c ON c.id = i.color_id WHERE c.product_id = ? ORDER BY c.position, c.name, i.position');
        $update = $this->pdo->prepare('UPDATE products SET page_content = ?, feature_cards = ?, default_color_id = ? WHERE id = ?');
        foreach ($this->pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $colors->execute([$product['id']]);
            $firstColor = $colors->fetchColumn() ?: null;
            $images->execute([$product['id']]);
            $existingImages = $images->fetchAll(PDO::FETCH_COLUMN);
            $page = $product['page_content'] ?: json_encode(ProductContent::DEFAULTS);
            $features = $product['feature_cards'] ?? null;
            if ($features === null) $features = json_encode(ProductContent::legacyFeatures($product, $existingImages));
            $update->execute([$page, $features, $product['default_color_id'] ?: $firstColor, $product['id']]);
        }
        // Keep historic lead snapshots; add lookup columns without changing their details.
        $leadUpdate = $this->pdo->prepare('UPDATE website_leads SET product_id = ?, color_id = ?, source = ? WHERE id = ?');
        foreach ($this->pdo->query("SELECT * FROM website_leads WHERE product_id IS NULL AND type IN ('test_drive', 'test_ride', 'test-drive')")->fetchAll(PDO::FETCH_ASSOC) as $lead) {
            $details = ProductContent::decode($lead['details_json']);
            $leadUpdate->execute([$details['product_id'] ?? null, $details['color_id'] ?? null, $details['source'] ?? null, $lead['id']]);
        }
    }
}
