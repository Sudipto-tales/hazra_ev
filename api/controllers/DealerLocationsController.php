<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/DealerLocation.php';
require_once __DIR__ . '/../../core/Csrf.php';

final class DealerLocationsController extends V1Controller
{
    public function index(): never
    {
        // Public route always excludes drafts, even with an admin cookie or query overrides.
        Envelope::ok(db_fetch_all("SELECT id, name, state, district, city, address, pincode, phone, type, lat, lng, hours FROM dealer_locations WHERE status = 'published' ORDER BY name"));
    }
    public function adminIndex(): never
    {
        $this->requireAdmin();
        Envelope::ok(db_fetch_all('SELECT * FROM dealer_locations ORDER BY updated_at DESC'));
    }
    public function store(): never
    {
        $this->requireAdmin();
        if (!Csrf::validate()) Envelope::forbidden('Invalid request token. Reload the page and try again.');
        $data = $this->validated(ApiRequest::body());
        $data['id'] = Uuid::v4();
        $data['created_at'] = $data['updated_at'] = Wire::now();
        $cols = array_keys($data);
        db_execute('INSERT INTO dealer_locations (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($data));
        Envelope::created($data);
    }
    public function update(): never
    {
        $this->requireAdmin();
        if (!Csrf::validate()) Envelope::forbidden('Invalid request token. Reload the page and try again.');
        $id = (string)$this->param('id');
        $existing = db_fetch_one('SELECT * FROM dealer_locations WHERE id = ?', [$id]);
        if (!$existing) Envelope::notFound();
        $data = $this->validated(ApiRequest::body(), $existing);
        $data['updated_at'] = Wire::now();
        $sets = array_map(static fn($col) => "{$col} = ?", array_keys($data));
        db_execute('UPDATE dealer_locations SET ' . implode(', ', $sets) . ' WHERE id = ?', [...array_values($data), $id]);
        Envelope::ok(array_merge($existing, $data));
    }
    private function validated(array $body, array $existing = []): array
    {
        try { return DealerLocation::validate($body, $existing); }
        catch (InvalidArgumentException $e) { Envelope::invalid($e->getMessage()); }
    }
}
