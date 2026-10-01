<?php
/** Shared validation for both creates and partial updates. */
final class DealerLocation
{
    public static function validate(array $body, array $existing = []): array
    {
        $data = [];
        foreach (['name'=>160, 'state'=>100, 'district'=>100, 'city'=>100, 'address'=>500,
            'pincode'=>6, 'phone'=>32, 'hours'=>255, 'type'=>16, 'status'=>16] as $field => $max) {
            $value = $body[$field] ?? $existing[$field] ?? match ($field) {
                'type' => 'showroom', 'status' => 'draft', default => ''
            };
            if (!is_string($value) || mb_strlen(trim($value)) > $max) {
                throw new InvalidArgumentException("Invalid {$field} (maximum {$max} characters).");
            }
            $data[$field] = trim($value);
        }
        if ($data['name'] === '') throw new InvalidArgumentException('Dealer name is required.');
        if (!in_array($data['type'], ['showroom', 'service', 'both'], true)) throw new InvalidArgumentException('Invalid dealer type.');
        if (!in_array($data['status'], ['draft', 'published', 'inactive'], true)) throw new InvalidArgumentException('Invalid status.');
        if ($data['pincode'] !== '' && !preg_match('/^[1-9][0-9]{5}$/', $data['pincode'])) throw new InvalidArgumentException('Enter a valid six-digit PIN code.');
        $phoneDigits = preg_replace('/\D/', '', $data['phone']);
        if ($data['phone'] !== '' && (!preg_match('/^\+?[0-9 ()-]{7,32}$/', $data['phone']) || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15)) throw new InvalidArgumentException('Enter a valid phone number.');
        foreach (['lat'=>90, 'lng'=>180] as $field => $limit) {
            $value = array_key_exists($field, $body) ? $body[$field] : ($existing[$field] ?? null);
            if ($value === '' || $value === null) { $data[$field] = null; continue; }
            if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > $limit) {
                throw new InvalidArgumentException("Invalid {$field} coordinate.");
            }
            $data[$field] = (float)$value;
        }
        if (($data['lat'] === null) !== ($data['lng'] === null)) throw new InvalidArgumentException('Enter both latitude and longitude.');
        if ($data['status'] === 'published') {
            foreach (['state', 'district', 'city', 'address', 'pincode', 'phone', 'lat', 'lng'] as $field) {
                if ($data[$field] === '' || $data[$field] === null) throw new InvalidArgumentException("{$field} is required before publishing.");
            }
        }
        return $data;
    }
}
