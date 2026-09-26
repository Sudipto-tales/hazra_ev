<?php

/**
 * Seed 004: Comprehensive Blog Posts & News Press Releases.
 *
 * Populates verified, rich content for the Hazra EV Journal & Newsroom.
 * Includes battery care guides, local Bengal commute stories, model comparisons,
 * dealership updates, and regional network announcements.
 *
 * Safe & idempotent: checks slug before inserting or updates existing rows.
 */

return function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/Uuid.php';
    require_once __DIR__ . '/../../api/support/Wire.php';

    $now = Wire::now();

    $posts = [
        // =================================================================
        // BLOGS
        // =================================================================
        [
            'type' => 'blog',
            'title' => 'Battery Care 101: Maximizing Graphene & Lithium Life in Indian Weather',
            'slug' => 'battery-care-101',
            'category' => 'ownership',
            'tags' => '#BatteryCare,#ElectricMobility,#HazraEV,#OwnershipTips',
            'location' => null,
            'read_minutes' => 6,
            'is_featured' => 1,
            'cover_image' => 'assets/scutie_light.webp',
            'author' => 'Hazra Technical Desk',
            'excerpt' => 'Heat, monsoon humidity, and everyday charging habits determine whether your EV battery lasts three years or six. Here is how to treat it right.',
            'content' => '<h2>Understanding What Happens Inside Your Battery Pack</h2>
<p>Modern electric two-wheelers in India run predominantly on two battery chemistries: advanced Graphene Lead-Acid and high-density Lithium-Ion (NMC or LFP). While both are engineered for rigorous daily commuting, our climate introduces thermal stresses that riders in temperate zones never face.</p>
<p>When ambient temperatures cross 38°C in April and May, chemical reactions inside the cells accelerate. Conversely, torrential monsoon downpours test the integrity of wire harnesses and connector seals. Maintaining optimal pack health does not require an engineering degree—just four consistent habits.</p>

<div class="callout">
<strong>The 20% to 80% Golden Zone:</strong> For daily commutes, keeping your state-of-charge between 20% and 80% can double the total lifecycle charge cycles of lithium cells compared to constant 0% to 100% depletion cycles.
</div>

<h2>Four Rules for High-Temperature Riding</h2>
<ul>
<li><strong>Let the Pack Rest After Rides:</strong> Never plug your scooter into the charger immediately after an intense high-speed run or steep climb. Give the cells 15 to 20 minutes to cool down to ambient temperature.</li>
<li><strong>Avoid Direct Afternoon Sun While Charging:</strong> Always park your scooter under a porch, shade net, or covered parking when connected to the wall charger. Direct solar heating combined with charging heat spikes cell temperatures.</li>
<li><strong>Store at 50% If Going on Vacation:</strong> If you are traveling for more than a week, do not leave your vehicle at 0% or 100%. A 50% charge maintains chemical equilibrium and minimizes passive degradation.</li>
<li><strong>Use Genuine Hazra Chargers Only:</strong> Third-party fast chargers often lack thermal cutoffs tuned to our BMS (Battery Management System), leading to over-voltage and shortened life.</li>
</ul>

<h2>Monsoon Care and Connector Seals</h2>
<p>While Hazra and Dynamo battery enclosures carry IP67 water and dust resistance, water logging above floorboard level should always be navigated cautiously. After riding through heavy rain, inspect the charging flap for moisture and wipe it clean with a dry microfiber cloth before connecting the charger pin.</p>
<blockquote>“A well-tended battery is not just about avoiding replacement cost; it delivers crisp, uniform throttle response from year one to year five.” — Hazra Service Engineering Team</blockquote>',
            'meta_title' => 'Battery Care 101: Maximizing Battery Life in India | Hazra EV',
            'meta_description' => 'Practical tips on charging cycles, heat management, and monsoon precautions to maximize the life of your electric scooter battery pack.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'status' => 'published'
        ],
        [
            'type' => 'blog',
            'title' => 'City Riding Secrets: Conquering Stop-and-Go Traffic on Two Wheels',
            'slug' => 'city-riding-secrets',
            'category' => 'ev-trends',
            'tags' => '#CityRiding,#UrbanCommute,#EcoFriendly,#TwoWheelerLife',
            'location' => null,
            'read_minutes' => 5,
            'is_featured' => 1,
            'cover_image' => 'assets/dark_scutie.webp',
            'author' => 'Priya Sharma',
            'excerpt' => 'Instant torque and silent acceleration change how riders navigate dense market streets, flyover bottlenecks, and unexpected signals.',
            'content' => '<h2>Why Stop-and-Go Traffic Exhausts Petrol Riders</h2>
<p>Urban traffic in cities like Kolkata, Howrah, and Asansol is characterized by sudden braking, endless clutch slipping, and engine heat radiating between your legs on congested ring roads. For decades, riders accepted clutch fatigue and vibrational numbness as the price of urban mobility.</p>
<p>Electric scooters replace internal combustion friction with instant electromagnetic torque. From the second the signal turns green, peak pulling power is delivered linearly without hesitation, gear shifts, or clutch pull.</p>

<h2>Navigating Dense Market Corridors</h2>
<p>Low-speed stability in crowded streets depends heavily on center-of-gravity placement. By housing the battery beneath the floorboard or centrally within the underbone chassis, vehicles like the CHALO 1000 V2 and Dynamo X1 place bulk low to the asphalt. This makes balancing at walking pace (3–5 km/h) effortless.</p>

<div class="callout">
<strong>Regenerative Braking Tip:</strong> Anticipating traffic slowdowns and easing off the throttle gently feeds kinetic energy back into your battery pack while reducing brake pad wear by over 30%.
</div>

<h2>The Psychology of the Silent Commute</h2>
<p>One seldom-discussed benefit of switching to electric is mental clarity. Without mechanical roar and exhaust fumes constantly assaulting your senses, daily transit shifts from an endurance test to a quiet interlude. Riders report arriving at work with noticeably lower stress levels.</p>',
            'meta_title' => 'City Riding Secrets: How Electric Scooters Beat Traffic | Hazra EV',
            'meta_description' => 'Discover how instant torque, low center of gravity, and regenerative braking revolutionize urban commuting in busy Indian cities.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'status' => 'published'
        ],
        [
            'type' => 'blog',
            'title' => 'From Petrol to Electric: Real Monthly Savings in Bengal Towns',
            'slug' => 'petrol-to-electric-savings',
            'category' => 'ownership',
            'tags' => '#CostSavings,#ElectricScooter,#BengalEV,#TCO',
            'location' => null,
            'read_minutes' => 7,
            'is_featured' => 0,
            'cover_image' => 'assets/storm.webp',
            'author' => 'Sudipto Hazra',
            'excerpt' => 'A transparent breakdown comparing monthly fuel and maintenance bills of a 110cc petrol scooter against a modern Hazra electric scooter over 30 km daily riding.',
            'content' => '<h2>The Pure Economics of Every Kilometer</h2>
<p>With petrol hovering consistently between ₹104 and ₹108 per liter across West Bengal, every round trip to the office, grocery market, or children’s coaching class carries a tangible financial penalty. For a typical commuter riding 30 km each day (900 km a month), let us examine the hard numbers.</p>

<h2>Detailed Cost Comparison Table</h2>
<p>Assuming an average petrol scooter economy of 45 km/l versus an electric consumption of 2.2 units (kWh) of electricity per 100 km at standard domestic slab rates (₹7.50 per unit):</p>

<ul>
<li><strong>Monthly Petrol Expense:</strong> 20 Liters @ ₹106 = <strong>₹2,120 / month</strong></li>
<li><strong>Monthly Electric Expense:</strong> 19.8 Units @ ₹7.50 = <strong>₹148.50 / month</strong></li>
<li><strong>Monthly Direct Fuel Savings:</strong> <strong>₹1,971.50 every month</strong></li>
</ul>

<div class="callout">
<strong>Over Three Years:</strong> Fuel savings alone exceed ₹71,000—virtually recovering the entire acquisition cost of your electric vehicle before even factoring in oil changes and engine overhauls.
</div>

<h2>Maintenance and Wearable Parts</h2>
<p>A conventional scooter contains over 200 moving engine and transmission components: piston rings, spark plugs, drive belts, engine oil filters, carburettor jets, and catalytic converters. Electric powertrains feature exactly one moving part inside the BLDC hub motor.</p>
<p>Zero engine oil changes, zero spark plug replacements, and zero belt snaps mean your scheduled maintenance is limited to brake shoes, tire air pressure, and suspension lubrication.</p>',
            'meta_title' => 'Petrol vs Electric Scooter Savings in Bengal | Hazra EV Journal',
            'meta_description' => 'Real-world numbers comparing 30 km daily commutes. See how switching to Hazra EV saves over ₹24,000 every single year in fuel and service.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'status' => 'published'
        ],
        [
            'type' => 'blog',
            'title' => 'Safer Riding After Dusk: Headlights, Braking & Road Visibility',
            'slug' => 'safer-riding-after-dusk',
            'category' => 'safety',
            'tags' => '#RiderSafety,#NightRiding,#DiscBrakes,#Lighting',
            'location' => null,
            'read_minutes' => 4,
            'is_featured' => 0,
            'cover_image' => 'assets/scutie_light.png',
            'author' => 'Hazra Safety Engineering',
            'excerpt' => 'Navigating unlit state highways and suburban streets demands purposeful lighting and predictable stopping power.',
            'content' => '<h2>The Challenge of Suburban Night Rides</h2>
<p>Riding after dark outside metropolitan core zones presents unique hazards: unlit speed breakers, stray animals, uneven shoulder gravel, and oncoming commercial trucks failing to dim high beams. Active visibility and progressive braking are your primary defensive tools.</p>

<h2>Projector LED vs Traditional Halogen</h2>
<p>Older vehicles with 35W halogen bulbs emit a yellowish, diffused glow that dims when engine RPMs drop at idle. Modern Hazra and Dynamo electric scooters utilize DC-to-DC stabilized LED projector assemblies. The beam remains at consistent 100% lumen output whether you are stationary at a crossing or cruising at top speed.</p>
<p>Wide-throw DRL (Daytime Running Light) ribbons outline the vehicle profile, making you instantly recognizable to approaching traffic from side alleys and blind junctions.</p>

<h2>Braking with Confidence: Dual Disc Advantages</h2>
<p>Drum brakes are susceptible to heat fade during rapid repeated stops and can lock up unpredictably on wet asphalt. Models equipped with dual hydraulic disc brakes and ventilated rotors provide progressive bite: fingertip pressure translates directly into controllable deceleration without unsettling the vehicle geometry.</p>',
            'meta_title' => 'Night Riding Safety & LED Lighting for Electric Scooters | Hazra EV',
            'meta_description' => 'Key tips for safe night commuting: understanding projector LED cutoffs, braking distance on wet roads, and defensive riding habits.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-12 days')),
            'status' => 'published'
        ],
        [
            'type' => 'blog',
            'title' => 'The Bardhaman Story: How Local Dealerships are Powering Suburban Commutes',
            'slug' => 'the-bardhaman-story',
            'category' => 'company',
            'tags' => '#Bardhaman,#DealershipStory,#BengalCommute,#LocalEV',
            'location' => null,
            'read_minutes' => 5,
            'is_featured' => 0,
            'cover_image' => 'assets/ind-map.png',
            'author' => 'Sudipto Hazra',
            'excerpt' => 'How a regional town in West Bengal became the vibrant hub for affordable, locally backed electric two-wheeler mobility.',
            'content' => '<h2>Rooted in Bengal’s Industrial Belt</h2>
<p>Electric mobility conversations often revolve around capital cities and high-tech corridors. But the genuine revolution in clean transportation is taking place in district hubs like Bardhaman, Memari, Katwa, and Kalna, where daily commuters travel between agricultural centers, small manufacturing units, and family businesses.</p>
<p>When Hazra EV opened its doors, the mission was straightforward: deliver scooters that can handle Bengal road conditions without requiring riders to wait weeks for spare parts shipped from distant states.</p>

<h2>Building Trust Through Authorised Service</h2>
<p>The primary barrier for first-time EV buyers in regional India has never been enthusiasm—it has been service confidence. What happens if a controller malfunctions after two years? Where can one get a battery cell balance checked? By establishing fully stocked regional spare banks and trained technicians within 20 kilometers of our riders, Hazra eliminated the fear factor.</p>
<blockquote>“People in Bardhaman don’t just buy a vehicle from us; they drop by for tea, share road feedback, and recommend their cousins. That community relationship is our greatest asset.”</blockquote>',
            'meta_title' => 'The Bardhaman Story: Regional EV Growth in West Bengal | Hazra EV',
            'meta_description' => 'Read how Hazra Electrical Bike established trust, robust service infrastructure, and localized mobility solutions in Bardhaman district.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-16 days')),
            'status' => 'published'
        ],
        [
            'type' => 'blog',
            'title' => 'Inside the Dynamo Lineup: Comparing X1, Dual, and Infinity Plus',
            'slug' => 'inside-the-dynamo-lineup',
            'category' => 'product',
            'tags' => '#DynamoEV,#ModelComparison,#Performance,#ProductGuide',
            'location' => null,
            'read_minutes' => 6,
            'is_featured' => 0,
            'cover_image' => 'assets/dark_scutie.png',
            'author' => 'Hazra Product Desk',
            'excerpt' => 'Which Dynamo model fits your lifestyle? We compare chassis strength, top speed, battery configurations, and practical range.',
            'content' => '<h2>Choosing the Right Tool for the Job</h2>
<p>The Dynamo electric series represents durable urban engineering paired with low operating costs. With multiple models available, choosing the exact variant depends on your daily distance, passenger payload, and aesthetic preference.</p>

<h2>1. Dynamo X1: The Nimble Everyday Hero</h2>
<p>Designed for compact agility, the X1 features an upright seating posture, high ground clearance, and an ultra-responsive 1000W BLDC hub motor. With an 80 km real-world range and a top speed of 50 km/h, it is the ideal first scooter for college students and local retail business owners.</p>

<h2>2. Dynamo Dual: Twin Headlamps & Heavy Cargo Balance</h2>
<p>For riders carrying parcels, supplies, or regular pillion passengers, the Dual introduces reinforced twin-cradle steel tubing and dual front headlamps for wide illumination. Its extended floorboard accommodates gas cylinders or grocery crates comfortably.</p>

<h2>3. Dynamo Infinity Plus: Premium Styling & Extended Range</h2>
<p>The flagship of the series features aerodynamic body sculpting, an integrated Infinity DRL signature, and a 60V 32Ah battery providing up to 95 km per charge. For riders commuting between neighboring towns on highways, the Infinity Plus delivers smooth, stable high-speed tracking.</p>',
            'meta_title' => 'Dynamo EV Comparison: X1 vs Dual vs Infinity Plus | Hazra EV',
            'meta_description' => 'Compare specifications, range, payload, and features of the Dynamo electric scooter lineup available at Hazra EV dealerships.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'status' => 'published'
        ],

        // =================================================================
        // NEWS / PRESS RELEASES
        // =================================================================
        [
            'type' => 'news',
            'title' => 'Hazra Opens 50 New Dealership & Service Points Across East India',
            'slug' => 'hazra-opens-50-new-dealership-points',
            'category' => 'company',
            'tags' => '#Expansion,#DealershipNetwork,#EastIndia,#Milestone',
            'location' => 'Kolkata',
            'read_minutes' => 3,
            'is_featured' => 1,
            'cover_image' => 'assets/dark_scutie.webp',
            'author' => 'Corporate Communications',
            'excerpt' => 'Strategic expansion strengthens service coverage and brings electric two-wheelers closer to riders in West Bengal, Odisha, Bihar, and Jharkhand.',
            'content' => '<p><strong>KOLKATA, WEST BENGAL</strong> — Hazra Electrical Bike today announced the inauguration of 50 new authorized dealership and service touchpoints across key urban centers and district towns in Eastern India.</p>
<p>The network expansion brings certified EV maintenance, authentic OEM spare parts, and on-site test rides within 15 minutes of travel time for hundreds of thousands of commuters. Every location features trained diagnostic technicians and battery health assessment tools.</p>
<p>“Electric mobility only achieves true adoption when the product, service, and spare parts are within arm’s reach,” stated Sudipto Hazra during the flag-off ceremony. “These 50 points are an important step toward our vision of zero-emission transit across East India.”</p>',
            'meta_title' => 'Hazra EV Opens 50 New Dealership Points Across East India',
            'meta_description' => 'Official press release: Hazra Electrical Bike expands dealership and service network across West Bengal, Odisha, Bihar, and the North East.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'status' => 'published'
        ],
        [
            'type' => 'news',
            'title' => 'Free Seasonal Battery Health Checkup Camps Launched in Bengal',
            'slug' => 'free-seasonal-battery-health-camps',
            'category' => 'company',
            'tags' => '#ServiceCamp,#BatteryCheckup,#CustomerCare,#HazraService',
            'location' => 'Bardhaman',
            'read_minutes' => 3,
            'is_featured' => 0,
            'cover_image' => 'assets/scutie_light.webp',
            'author' => 'Customer Care Division',
            'excerpt' => 'Two-week service camps offer free computerized cell testing, terminal cleaning, and charger calibration for all Hazra and Dynamo EV riders.',
            'content' => '<p><strong>BARDHAMAN, WEST BENGAL</strong> — Starting this Monday, Hazra EV authorized service centers across West Bengal are running a dedicated 14-day Battery Care & Diagnostics Camp.</p>
<p>Riders can bring in their electric scooters for complimentary computer-guided state-of-health (SoH) scans, cell impedance balancing, connector inspection, and brake fluid top-ups. Riders will also receive a personalized battery health certificate and guidance on optimal charging habits for the upcoming season.</p>
<p>Bookings can be made directly via our dealer locator page or by walking in to any participating showroom between 10:00 AM and 6:00 PM.</p>',
            'meta_title' => 'Free EV Battery Health Checkup Camp Across West Bengal | Hazra EV',
            'meta_description' => 'Complimentary 14-day battery health camp for all electric scooter owners in West Bengal. Get computer diagnostics and cell balancing free.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'status' => 'published'
        ],
        [
            'type' => 'news',
            'title' => 'Hazra Rolls Out Smart TFT Display Firmware Update for CHALO Series',
            'slug' => 'chalo-series-smart-tft-update',
            'category' => 'product',
            'tags' => '#ProductUpdate,#ChaloSeries,#SmartEV,#Firmware',
            'location' => 'Asansol',
            'read_minutes' => 4,
            'is_featured' => 0,
            'cover_image' => 'assets/storm.webp',
            'author' => 'R&D Engineering',
            'excerpt' => 'The over-the-air firmware update introduces precision trip meters, battery temperature readouts, and improved dynamic range estimation.',
            'content' => '<p><strong>ASANSOL, WEST BENGAL</strong> — Hazra EV has released Firmware Version 2.4 for the CHALO Smart Pro and CHALO 1000 V2 digital TFT instrument clusters.</p>
<p>The update enhances algorithmic range estimation by factoring in real-time motor temperature and ambient humidity. It also adds an anti-glare high-contrast night mode and instant service reminder alerts when scheduled maintenance intervals approach.</p>
<p>Owners can have their display units flashed free of charge at any authorized service hub during routine maintenance, requiring less than ten minutes of diagnostic bay time.</p>',
            'meta_title' => 'Smart TFT Display Firmware Update for CHALO EV Series | Hazra EV',
            'meta_description' => 'Hazra EV rolls out v2.4 firmware update for CHALO series digital instrument clusters with improved range calculations and night mode.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-11 days')),
            'status' => 'published'
        ],
        [
            'type' => 'news',
            'title' => 'Hazra Partners with Regional Fast-Charging Networks in West Bengal',
            'slug' => 'fast-charging-partner-network',
            'category' => 'ev-trends',
            'tags' => '#ChargingPartner,#FastCharging,#CleanMobility,#Infrastructure',
            'location' => 'Durgapur',
            'read_minutes' => 4,
            'is_featured' => 0,
            'cover_image' => 'assets/hazraevLogo.jpg',
            'author' => 'Strategic Alliances',
            'excerpt' => 'New partnership enables Hazra EV riders to access over 120 fast-charging bays along major state highways and commercial transit hubs.',
            'content' => '<p><strong>DURGAPUR, WEST BENGAL</strong> — Hazra Electrical Bike has inked a strategic Memorandum of Understanding with regional green infrastructure providers to integrate fast-charging compatibility across the industrial belt.</p>
<p>Under the alliance, more than 120 multi-standard charging bays situated along National Highway 19, Durgapur Expressway, and Grand Trunk Road corridors will offer dedicated parking and priority charging slots for Hazra EV vehicles.</p>
<p>“Range confidence expands exponentially when charging is as accessible as roadside tea stalls,” said the Head of Strategic Alliances. Full station locations and real-time bay availability will be synchronized into our digital dealer locator map shortly.</p>',
            'meta_title' => 'Hazra Partners with Highway Fast-Charging Network in Bengal',
            'meta_description' => 'Hazra EV joins forces with regional charge point operators to grant riders priority access to 120+ charging bays across West Bengal highways.',
            'published_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'status' => 'published'
        ],
    ];

    $inserted = 0;
    $updated = 0;

    foreach ($posts as $p) {
        $existing = db_fetch_one("SELECT id FROM posts WHERE slug = ?", [$p['slug']]);

        if ($existing) {
            $postId = $existing['id'];
            db_execute(
                "UPDATE posts SET
                    type = ?, title = ?, category = ?, tags = ?, location = ?,
                    read_minutes = ?, is_featured = ?, cover_image = ?, author = ?,
                    excerpt = ?, content = ?, meta_title = ?, meta_description = ?,
                    published_at = ?, status = ?, updated_at = ?
                 WHERE id = ?",
                [
                    $p['type'], $p['title'], $p['category'], $p['tags'], $p['location'],
                    $p['read_minutes'], $p['is_featured'], $p['cover_image'], $p['author'],
                    $p['excerpt'], $p['content'], $p['meta_title'], $p['meta_description'],
                    $p['published_at'], $p['status'], $now, $postId
                ]
            );
            $updated++;
        } else {
            $postId = Uuid::v4();
            db_execute(
                "INSERT INTO posts (
                    id, type, title, slug, category, tags, location,
                    read_minutes, is_featured, cover_image, author,
                    excerpt, content, meta_title, meta_description,
                    published_at, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $postId, $p['type'], $p['title'], $p['slug'], $p['category'], $p['tags'], $p['location'],
                    $p['read_minutes'], $p['is_featured'], $p['cover_image'], $p['author'],
                    $p['excerpt'], $p['content'], $p['meta_title'], $p['meta_description'],
                    $p['published_at'], $p['status'], $now, $now
                ]
            );
            $inserted++;
        }
    }

    echo "  Seeded posts: {$inserted} inserted, {$updated} updated." . PHP_EOL;
};
