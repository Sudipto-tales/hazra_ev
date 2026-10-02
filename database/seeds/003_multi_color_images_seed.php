<?php

/**
 * Seed multiple colors and multiple gallery images per color for flagship scooters.
 */
return static function (): void {
    require_once __DIR__ . '/../../api/support/Uuid.php';
    $models = [
        'chalo-smart-pro' => [
            'name' => 'CHALO SMART PRO',
            'colors' => [
                [
                    'name' => 'Matte Black',
                    'argb' => -14671840, // #202020
                    'in_stock' => 1,
                    'images' => [
                        'assets/dark_scutie.webp',
                        'assets/scooters/hazra_broucher_6_scooter_14.png',
                        'assets/scooters/hazra_broucher_6_scooter_13.png',
                        'assets/scooters/hazra_broucher_6_scooter_15.png',
                    ]
                ],
                [
                    'name' => 'Pearl White',
                    'argb' => -657931, // #F5F5F5
                    'in_stock' => 1,
                    'images' => [
                        'assets/scutie_light.webp',
                        'assets/scooters/hazra_broucher_6_scooter_12.png',
                        'assets/scooters/hazra_broucher_6_scooter_6.png',
                        'assets/scooters/hazra_broucher_6_scooter_16.png',
                    ]
                ],
                [
                    'name' => 'Electric Cyan',
                    'argb' => -15570977, // #12A5E0
                    'in_stock' => 1,
                    'images' => [
                        'assets/storm.webp',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_11.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_12.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_3.png',
                    ]
                ],
                [
                    'name' => 'Crimson Red',
                    'argb' => -2549202, // #D92E2E
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_1.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_2.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_8.png',
                    ]
                ],
            ]
        ],
        'chalo-1000-v2' => [
            'name' => 'CHALO 1000 V2',
            'colors' => [
                [
                    'name' => 'Stealth Grey',
                    'argb' => -11184811, // #555555
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/hazra_broucher_6_scooter_16.png',
                        'assets/scooters/hazra_broucher_6_scooter_14.png',
                        'assets/scooters/hazra_broucher_6_scooter_13.png',
                    ]
                ],
                [
                    'name' => 'Glacier Blue',
                    'argb' => -14299943, // #25A5D9
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_11.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_12.png',
                    ]
                ],
                [
                    'name' => 'Blaze Orange',
                    'argb' => -1027584, // #F0532B
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/oleant_updated_scooter_1.png',
                        'assets/scooters/oleant_updated_scooter_3.png',
                        'assets/scooters/oleant_updated_scooter_4.png',
                    ]
                ]
            ]
        ],
        'chalo-smart-plus' => [
            'name' => 'CHALO SMART PLUS',
            'colors' => [
                [
                    'name' => 'Midnight Blue',
                    'argb' => -15060410, // #1A2646
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/oleant_updated_scooter_5.png',
                        'assets/scooters/oleant_updated_scooter_6.png',
                        'assets/scooters/oleant_updated_scooter_7.png',
                    ]
                ],
                [
                    'name' => 'Pure White',
                    'argb' => -1, // #FFFFFF
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/hazra_broucher_6_scooter_12.png',
                        'assets/scooters/hazra_broucher_6_scooter_6.png',
                    ]
                ]
            ]
        ],
        'chalo-neo' => [
            'name' => 'CHALO NEO',
            'colors' => [
                [
                    'name' => 'Neon Lime',
                    'argb' => -6030541, // #A3F333
                    'in_stock' => 1,
                    'images' => [
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_22.png',
                        'assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_23.png',
                    ]
                ],
                [
                    'name' => 'Dark Obsidian',
                    'argb' => -15658735, // #111111
                    'in_stock' => 1,
                    'images' => [
                        'assets/dark_scutie.webp',
                        'assets/scooters/hazra_broucher_6_scooter_14.png',
                    ]
                ]
            ]
        ]
    ];

    $updated = 0;
    foreach ($models as $slug => $data) {
        $p = db_fetch_one("SELECT id FROM products WHERE slug = ? OR name = ?", [$slug, $data['name']]);
        if (!$p) continue;
        $productId = $p['id'];

        // Remove old colors for this product
        db_execute("DELETE FROM product_colors WHERE product_id = ?", [$productId]);

        foreach ($data['colors'] as $pos => $c) {
            $colorId = Uuid::v4();
            db_execute(
                "INSERT INTO product_colors (id, product_id, name, argb, in_stock, position) VALUES (?, ?, ?, ?, ?, ?)",
                [$colorId, $productId, $c['name'], $c['argb'], $c['in_stock'], $pos]
            );

            foreach ($c['images'] as $imgPos => $imgUrl) {
                db_execute(
                    "INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, ?)",
                    [Uuid::v4(), $colorId, $imgUrl, $imgPos]
                );
            }
        }
        $updated++;
    }

    echo "Seeded multi-color galleries for {$updated} flagship products.\n";
};
