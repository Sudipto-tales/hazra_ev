<?php

/** Shared defaults and validation for the editable product landing page. */
final class ProductContent
{
    public const DEFAULTS = [
        'hero_kicker' => '{brand} / ELECTRIC MOBILITY',
        'hero_title' => "Move\nbeyond ordinary.",
        'hero_description' => 'Engineered for performance and comfort. Experience effortless urban mobility with cutting-edge EV technology.',
        'primary_cta' => 'Book a test ride', 'secondary_cta' => 'Explore the details',
        'hero_image_label' => 'THE NEXT CHAPTER OF YOUR EVERYDAY',
        'showcase_kicker' => 'MEET YOUR NEXT RIDE',
        'showcase_title' => "{name}.\nEvery angle. Every detail.",
        'showcase_description' => 'Take a closer look. Choose your color and explore the full view.',
        'range_kicker' => 'GO THE DISTANCE / RANGE',
        'range_title' => "More possibilities.\nOn a single charge.",
        'range_note' => 'Range is measured per full charge under standard conditions. Actual range varies with riding speed, rider weight, terrain, weather and battery condition.',
        'cinematic_kicker' => 'MADE FOR YOUR EVERYDAY',
        'cinematic_title' => "Designed for\nmodern mobility.",
        'cinematic_image' => '', 'cinematic_link' => '#features', 'cinematic_cta' => 'Discover the details',
        'features_kicker' => 'THOUGHTFULLY ENGINEERED',
        'features_title' => "The details make\nthe difference.",
        'specs_kicker' => 'PERFORMANCE, AT A GLANCE',
        'specs_title' => "Everything you\nneed to know.",
        'specs_description' => 'The complete specifications for {brand} {name}.',
        'ownership_kicker' => 'RIDE WITH CONFIDENCE', 'ownership_title' => "Support for the\nroad ahead.",
        'ownership_description' => '', 'dealer_cta' => 'Find your nearest dealer',
        'related_kicker' => 'FIND YOUR FIT', 'related_title' => 'More ways to move.',
        'related_cta' => 'View all models', 'related_ids' => [],
        'booking_kicker' => 'YOUR NEXT CHAPTER STARTS HERE', 'booking_title' => 'Experience {name}.',
        'booking_description' => 'Leave your details. Our team will help arrange your test ride.',
        'show_showcase' => true, 'show_range' => true, 'show_cinematic' => true,
        'show_features' => true, 'show_ownership' => true, 'show_related' => true,
    ];

    public static function decode(mixed $value, array $fallback = []): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) ($value ?? ''), true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function page(mixed $value): array
    {
        return array_replace(self::DEFAULTS, self::decode($value));
    }

    public static function text(array $page, string $key, array $product): string
    {
        return strtr((string) ($page[$key] ?? ''), [
            '{name}' => (string) $product['name'], '{brand}' => (string) $product['brand'],
            '{model}' => (string) ($product['model_code'] ?? $product['modelCode'] ?? ''),
        ]);
    }

    public static function safeUrl(string $url, bool $allowAnchor = false): bool
    {
        if ($url === '') return true;
        if (preg_match('/[\x00-\x20\\\\]/', $url)) return false;
        if ($allowAnchor && preg_match('/^#[A-Za-z][\w-]*$/', $url)) return true;
        if (str_starts_with($url, '//')) return false;
        if (preg_match('~^https?://~i', $url)) return filter_var($url, FILTER_VALIDATE_URL) !== false;
        return !str_contains($url, ':') && !str_starts_with($url, '#');
    }

    /** Seeds feature cards using the product's actual highlights and photographs. */
    public static function legacyFeatures(array $product, array $images): array
    {
        $labels = self::decode($product['highlights'] ?? []);
        if (!$labels) {
            foreach (['range_km' => 'Riding range', 'top_speed_kmph' => 'Top speed',
                'battery_capacity' => 'Battery capacity', 'motor_power' => 'Motor power',
                'charging_time' => 'Charging time', 'load_capacity_kg' => 'Payload capacity'] as $key => $label) {
                if (!empty($product[$key])) $labels[] = $label;
            }
        }
        $images = array_values(array_filter($images));
        if (!$images) $images = [$product['hero_image'] ?? 'assets/scutie_light.webp'];
        return array_map(static fn($title, $i) => [
            'id' => 'legacy-' . $i, 'title' => (string) $title, 'description' => '',
            'image' => $images[$i % count($images)], 'alt' => $product['name'] . ' - ' . $title,
            'color_id' => '', 'visible' => true, 'legacy_crop' => true,
        ], $labels, array_keys($labels));
    }

    /** Throw before any database changes. The controller maps this to a field error. */
    public static function validatePage(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value) && $value !== []) throw new InvalidArgumentException('Page content must be an object');
        $out = [];
        foreach ($value as $key => $item) {
            if (!array_key_exists($key, self::DEFAULTS)) continue;
            if (str_starts_with($key, 'show_')) {
                $out[$key] = filter_var($item, FILTER_VALIDATE_BOOLEAN);
            } elseif ($key === 'related_ids') {
                if (!is_array($item) || count($item) > 12) throw new InvalidArgumentException('Choose up to 12 related models');
                foreach ($item as $id) if (!is_string($id) || strlen($id) > 64) throw new InvalidArgumentException('Invalid related product identifier');
                $out[$key] = array_values(array_unique($item));
            } else {
                if (!is_string($item) || strlen($item) > 5000) throw new InvalidArgumentException('Page text must be at most 5000 bytes');
                if (in_array($key, ['cinematic_image', 'cinematic_link'], true) && !self::safeUrl($item, $key === 'cinematic_link')) {
                    throw new InvalidArgumentException('Use a valid image path, website URL or section link');
                }
                $out[$key] = $item;
            }
        }
        return $out;
    }

    public static function validateFeatures(mixed $cards, array $colorIds): array
    {
        if (!is_array($cards) || !array_is_list($cards) || count($cards) > 40) throw new InvalidArgumentException('Feature cards must be a list of up to 40 cards');
        $out = [];
        foreach ($cards as $i => $card) {
            if (!is_array($card)) throw new InvalidArgumentException('Invalid feature card');
            foreach (['id', 'title', 'image', 'description', 'alt', 'color_id'] as $key) {
                if (isset($card[$key]) && !is_string($card[$key])) throw new InvalidArgumentException('Feature fields must contain text');
            }
            $title = trim((string) ($card['title'] ?? ''));
            if ($title === '' || strlen($title) > 250) throw new InvalidArgumentException('Each feature needs a title of up to 250 bytes');
            $image = trim((string) ($card['image'] ?? ''));
            if (strlen($image) > 2048 || !self::safeUrl($image)) throw new InvalidArgumentException('Invalid feature image URL');
            $colorId = (string) ($card['color_id'] ?? '');
            if ($colorId !== '' && !in_array($colorId, $colorIds, true)) throw new InvalidArgumentException('A feature references a removed or unknown color');
            $description = (string) ($card['description'] ?? '');
            $alt = (string) ($card['alt'] ?? '');
            if (strlen($description) > 3000 || strlen($alt) > 500) throw new InvalidArgumentException('Feature description or alt text is too long');
            $out[] = ['id' => substr((string) ($card['id'] ?? 'feature-' . $i), 0, 64),
                'title' => $title, 'image' => $image, 'description' => $description, 'alt' => $alt,
                'color_id' => $colorId, 'visible' => filter_var($card['visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'legacy_crop' => filter_var($card['legacy_crop'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }
        return $out;
    }
}
