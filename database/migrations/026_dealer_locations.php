<?php
class DealerLocations extends Migration
{
    public function up()
    {
        $this->exec("CREATE TABLE IF NOT EXISTS dealer_locations (
            id {uuid} PRIMARY KEY,
            name {str:160} NOT NULL,
            state {str:100} NOT NULL DEFAULT '',
            district {str:100} NOT NULL DEFAULT '',
            city {str:100} NOT NULL DEFAULT '',
            address {str:500} NOT NULL DEFAULT '',
            pincode {str:6} NOT NULL DEFAULT '',
            phone {str:32} NOT NULL DEFAULT '',
            type {str:16} NOT NULL DEFAULT 'showroom',
            lat {float}, lng {float},
            hours {str:255} NOT NULL DEFAULT '',
            status {str:16} NOT NULL DEFAULT 'draft',
            created_at {ts} NOT NULL, updated_at {ts} NOT NULL
        ) {opts};
        CREATE INDEX IF NOT EXISTS idx_dealer_locations_status ON dealer_locations (status);");
    }
    public function down() { $this->drop(['dealer_locations']); }
}
