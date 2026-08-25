<?php

/**
 * Reference data: the companies visits are detected against, and the EV
 * catalogue a seller logs units from.
 *
 * There is deliberately no price column on `products` (data doc §5.1, schema
 * doc §9) — the field surface logs units and the payment actually collected.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class CatalogueCustomerTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS companies (
                id         {uuid} PRIMARY KEY,
                org_id     {uuid} NOT NULL,
                name       {str} NOT NULL,
                category   {str:64} NOT NULL DEFAULT '',   -- Distributor / Retail / Corporate
                updated_at {ts} NOT NULL,
                FOREIGN KEY (org_id) REFERENCES organizations(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_companies_org ON companies (org_id, name);
            CREATE INDEX IF NOT EXISTS idx_companies_updated ON companies (updated_at);

            CREATE TABLE IF NOT EXISTS branches (
                id         {uuid} PRIMARY KEY,
                company_id {uuid} NOT NULL,
                name       {str} NOT NULL,
                address    {str:512} NOT NULL DEFAULT '',
                lat        {float} NOT NULL,
                lng        {float} NOT NULL,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_branches_company ON branches (company_id);
            -- No PostGIS here. 'Which branch is this stop at' is a bounding-box
            -- prefilter on lat/lng followed by a haversine check in PHP.
            CREATE INDEX IF NOT EXISTS idx_branches_latlng ON branches (lat, lng);

            CREATE TABLE IF NOT EXISTS products (
                id               {uuid} PRIMARY KEY,
                org_id           {uuid} NOT NULL,
                category         {str:16} NOT NULL CHECK (category IN ('scooty','bike','bicycle','others')),
                brand            {str} NOT NULL,
                name             {str} NOT NULL,
                model_code       {str:64} NOT NULL,
                rating           {float} NOT NULL DEFAULT 0 CHECK (rating >= 0 AND rating <= 5),
                warranty_years   {int} NOT NULL DEFAULT 0,
                warranty_note    {str} NOT NULL DEFAULT '',
                range_km         {int} NOT NULL DEFAULT 0,
                top_speed_kmph   {int} NOT NULL DEFAULT 0,
                charging_time    {str:64} NOT NULL DEFAULT '',
                battery_capacity {str:64} NOT NULL DEFAULT '',
                motor_power      {str:64} NOT NULL DEFAULT '',
                load_capacity_kg {int} NOT NULL DEFAULT 0,
                highlights       {text} NOT NULL {default '[]'},   -- JSON array of strings
                active           {bool} NOT NULL DEFAULT 1,   -- delisted = 0, never deleted
                listed_at        {ts},                        -- null for the seed catalogue
                created_by       {uuid},
                updated_at       {ts} NOT NULL,
                UNIQUE (org_id, model_code),
                FOREIGN KEY (org_id) REFERENCES organizations(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_products_category ON products (org_id, category, active);
            CREATE INDEX IF NOT EXISTS idx_products_updated ON products (updated_at);

            CREATE TABLE IF NOT EXISTS product_colors (
                id         {uuid} PRIMARY KEY,
                product_id {uuid} NOT NULL,
                name       {str} NOT NULL,
                argb       {int} NOT NULL,             -- 0xAARRGGBB
                in_stock   {bool} NOT NULL DEFAULT 1,
                position   {int} NOT NULL DEFAULT 0,
                UNIQUE (product_id, name),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) {opts};

            -- The gallery is PER COLOUR, which is how the data arrives.
            CREATE TABLE IF NOT EXISTS product_color_images (
                id       {uuid} PRIMARY KEY,
                color_id {uuid} NOT NULL,
                url      {str:512} NOT NULL,
                position {int} NOT NULL DEFAULT 0,
                FOREIGN KEY (color_id) REFERENCES product_colors(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_color_images ON product_color_images (color_id, position);
        ");
    }

    public function down()
    {
        $this->drop([
            'product_color_images', 'product_colors', 'products', 'branches', 'companies',
        ]);
    }
}
