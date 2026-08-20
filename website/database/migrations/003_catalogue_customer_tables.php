<?php

/**
 * Reference data: the companies visits are detected against, and the EV
 * catalogue a seller logs units from.
 *
 * There is deliberately no price column on `products` (data doc §5.1, schema
 * doc §9) — the field surface logs units and the payment actually collected.
 */
class CatalogueCustomerTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS companies (
                id         TEXT PRIMARY KEY,
                org_id     TEXT NOT NULL REFERENCES organizations(id),
                name       TEXT NOT NULL,
                category   TEXT NOT NULL DEFAULT '',   -- Distributor / Retail / Corporate
                updated_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_companies_org ON companies (org_id, name);
            CREATE INDEX IF NOT EXISTS idx_companies_updated ON companies (updated_at);

            CREATE TABLE IF NOT EXISTS branches (
                id         TEXT PRIMARY KEY,
                company_id TEXT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
                name       TEXT NOT NULL,
                address    TEXT NOT NULL DEFAULT '',
                lat        REAL NOT NULL,
                lng        REAL NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_branches_company ON branches (company_id);
            -- No PostGIS here. 'Which branch is this stop at' is a bounding-box
            -- prefilter on lat/lng followed by a haversine check in PHP.
            CREATE INDEX IF NOT EXISTS idx_branches_latlng ON branches (lat, lng);

            CREATE TABLE IF NOT EXISTS products (
                id               TEXT PRIMARY KEY,
                org_id           TEXT NOT NULL REFERENCES organizations(id),
                category         TEXT NOT NULL CHECK (category IN ('scooty','bike','bicycle','others')),
                brand            TEXT NOT NULL,
                name             TEXT NOT NULL,
                model_code       TEXT NOT NULL,
                rating           REAL NOT NULL DEFAULT 0 CHECK (rating >= 0 AND rating <= 5),
                warranty_years   INTEGER NOT NULL DEFAULT 0,
                warranty_note    TEXT NOT NULL DEFAULT '',
                range_km         INTEGER NOT NULL DEFAULT 0,
                top_speed_kmph   INTEGER NOT NULL DEFAULT 0,
                charging_time    TEXT NOT NULL DEFAULT '',
                battery_capacity TEXT NOT NULL DEFAULT '',
                motor_power      TEXT NOT NULL DEFAULT '',
                load_capacity_kg INTEGER NOT NULL DEFAULT 0,
                highlights       TEXT NOT NULL DEFAULT '[]',   -- JSON array of strings
                active           INTEGER NOT NULL DEFAULT 1,   -- delisted = 0, never deleted
                listed_at        TEXT,                         -- null for the seed catalogue
                created_by       TEXT REFERENCES users(id),
                updated_at       TEXT NOT NULL,
                UNIQUE (org_id, model_code)
            );
            CREATE INDEX IF NOT EXISTS idx_products_category ON products (org_id, category, active);
            CREATE INDEX IF NOT EXISTS idx_products_updated ON products (updated_at);

            CREATE TABLE IF NOT EXISTS product_colors (
                id         TEXT PRIMARY KEY,
                product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                name       TEXT NOT NULL,
                argb       INTEGER NOT NULL,             -- 0xAARRGGBB
                in_stock   INTEGER NOT NULL DEFAULT 1,
                position   INTEGER NOT NULL DEFAULT 0,
                UNIQUE (product_id, name)
            );

            -- The gallery is PER COLOUR, which is how the data arrives.
            CREATE TABLE IF NOT EXISTS product_color_images (
                id       TEXT PRIMARY KEY,
                color_id TEXT NOT NULL REFERENCES product_colors(id) ON DELETE CASCADE,
                url      TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_color_images ON product_color_images (color_id, position);
        ");
    }

    public function down()
    {
        foreach ([
            'product_color_images', 'product_colors', 'products', 'branches', 'companies',
        ] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
