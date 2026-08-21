# Hazra EV — Website Design Brief

Prototype set in `html/`. Reference-analysed: **goeen.in** (GO WITH GREEN E-VEHICLES).
Rebuilt for Hazra EV with our own palette, copy, and hand-written vanilla animation.

---

## 1. What the reference does (goeen.in analysis)

**Stack observed:** Bootstrap 5.2.3, jQuery, Owl Carousel 2.3.4, AOS 2.3.1,
animate.css 4.1.1, particles.js 2.0.0, `counter.min.js` + `appear.js`,
Font Awesome 6, Google Fonts `Poppins / Montserrat / Inter`, Material Icons Outlined.
Local font files: Poppins, Italianno, Racing Sans One.

**Palette lifted from source:** `#8cc739` (lime primary), `#98c41e`, `#86cf25`,
`#18c75d`/`#12c85e` (emerald), tints `#f6fff2`, `#f2ffe8`, `#E5F5D0`, ink `#111111`/`#181818`.
Signature gradient `linear-gradient(135deg, #8cc739, #18c75d)`.
Radii 6/8/22px + `50%` pills. Shadows soft — `0 5px 20px rgba(0,0,0,.1)`,
glow `0 0 10px rgba(114,185,16,.7)`. Transitions `.15s–.3s ease`, one `6s` parallax.

**Home page section order:**
1. 360° product viewer (drag to rotate, scroll to zoom, `Frame: n / 72`)
2. Hero slider — 10 slides, headline "Book Your New Eco-Friendly Ride"
3. Icon feature strip — 4 icons (100% Electric / Long Range / Eco Neutral / Optimal Speed)
4. Stat bar — 4 H. Charge · 13+ Scooters · 99.99% Satisfaction · 24/7 Support
5. Product collection — "The Chalo Collection", 6 cards: warranty badge, PDF link,
   photo, rating, colour swatches, range, speed class, dual pricing (Graphene / Li-Ion),
   Explore + Test Ride buttons
6. Conversion band — "Ride Smarter with GOEEN…" + Book Your Test Ride
7. "Why Choose GOEEN?" — 6 benefit blocks
8. Offer carousel — 8 artboards, View Details / Test Ride / Book Now
9. "Our Journey in Numbers" — count-up counters on scroll + family photo
10. Modals — 360° viewer, warranty policy
11. Footer — 4 link columns (About / Scooter / Commercial / Policy) + socials
12. Sticky mobile bar (Call / Book Now / Test Ride) + floating WhatsApp

**Where animation lives (the part worth copying):**
| Where | Effect |
| --- | --- |
| Above fold | 72-frame drag-to-rotate 360° spinner, scroll-to-zoom |
| Hero | auto-advancing image slider, chevron arrows |
| Whole page | AOS scroll-reveal (fade/zoom on enter) |
| Journey section | count-up numbers triggered by `appear.js` on scroll |
| Offer strip | Owl carousel loop |
| Nav | dropdown mega-menus with thumbnails, hover transition |
| Hero bg | particles.js field |
| Cards | `transform + box-shadow` lift on hover, `.2s ease` |
| Misc | animate.css `zoomInUp`, `zoomInDown`, `fadeInLeft` one-shots |

**Sub-pages seen:** `about.php` (timeline: The Beginning / New Horizons / Innovation
Continues, then Vision, Mission, Career, Quality, Sustainability, Eco + founder),
`product.php`, `contact.php`, `faq.php` (accordion), `dealer-locator.php`
(search by area + dealer profile cards), `career.php` (4-step hiring flow + job list).

---

## 2. What we build for Hazra EV

**Brand colour source — `mobile_app/lib/core/theme/app_colors.dart`.**
No logo image ships in the repo, so the app's declared brand tokens are the source of truth.

| Token | Hex | From |
| --- | --- | --- |
| `--brand` | `#2F6BFF` | `AppColors.primary` |
| `--brand-dark` | `#1E4FD8` | `AppColors.primaryDark` |
| `--brand-soft` | `#EAF0FF` | `AppColors.primarySoft` |
| `--eco` | `#16A34A` | `AppColors.success` |
| `--eco-soft` | `#E6F6EC` | `AppColors.successSoft` |
| `--amber` | `#F59E0B` | `AppColors.warning` |
| `--ink` | `#0F172A` | `AppColors.textPrimary` |
| `--muted` | `#64748B` | `AppColors.textSecondary` |
| `--night` | `#0B1120` | `AppColors.backgroundDark` |
| `--surface-2` | `#141C2E` | `AppColors.surfaceDark` |
| `--line` | `#E6EAF2` | `AppColors.border` |
| `--bg` | `#F4F6FB` | `AppColors.background` |

Signature gradient: `linear-gradient(135deg, #2F6BFF, #16A34A)` — electric blue into eco green.
So the site reads *electric*, not *lawn*: blue leads, green accents. Same emotional slot
as goeen's lime→emerald ramp, different hue family, matched to the app.

**Type:** Poppins 600/700/800 headings (same family as reference),
Inter 400/500/600 body. Material Symbols Outlined for icons.

**Rules:** no Bootstrap, no jQuery, no AOS, no Owl. Every animation hand-written
in vanilla JS + CSS so the prototype ports cleanly into `website/assets/`.
Shared header/footer injected by `assets/js/site.js` — works over `file://`.

**Pages**
| File | Mirrors | Notes |
| --- | --- | --- |
| `index.html` | home | spinner, hero slider, stats, collection, why-us, offers, journey |
| `products.html` | product.php | filter chips + full lineup grid + compare table |
| `product.html` | detail | gallery, colour switcher, spec tabs, range calculator |
| `about.html` | about.php | timeline, vision/mission, values, founder, plant |
| `dealers.html` | dealer-locator.php | live filter, dealer cards, become-a-dealer form |
| `contact.html` | contact.php | form, cards, map embed, showroom hours |
| `faq.html` | faq.php | accordion, category tabs |
| `careers.html` | career.php | 4-step hiring flow, job board with filters |

**Animation inventory (ours)**
1. Preloader wipe + logo draw
2. Scroll progress bar
3. Sticky header shrink/blur + mega-dropdown reveal
4. Canvas particle field in hero (particles.js replacement)
5. Hero slider — autoplay, Ken Burns zoom, arrows, dots, progress ring
6. Drag-to-rotate 360° viewer (pointer + wheel zoom, frame counter)
7. `IntersectionObserver` scroll-reveal (`data-reveal="up|left|right|zoom"` + `data-delay`)
8. Count-up statistics on enter
9. Card hover lift, image scale, sheen sweep
10. Colour-swatch product image switch
11. Logo/benefit marquee ticker
12. Offer carousel with drag + snap
13. Spec accordion + tabs
14. Range calculator with animated gauge
15. Parallax background layers
16. Counter-driven progress bars
17. Mobile sticky action bar + floating WhatsApp pulse
18. Back-to-top orbit button
19. Form focus/float labels, submit success animation
20. `prefers-reduced-motion` kill-switch for all of the above

**Images:** hot-linked Unsplash (`images.unsplash.com/photo-…`), verified 200.
Placeholder photography only — swap for real Hazra product renders before launch.
