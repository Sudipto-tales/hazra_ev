# Product & Forms Architecture — archived from the redesign plan

**Origin:** `html/EV_Website_Redesign_Next_Day_Plan.md` (deleted 2026-09-01).

This half is **shared**: the markup and styling are `design-agent` (`html/`), but the
catalogue data, product phase/status, media storage and the two lead forms need
`web-agent` (`website/api/`). Kept here because neither side owns it alone.

**Status:** product pages exist as static prototypes in `html/` (7 models). Filtering,
phase/status and form submission are unbuilt.

---

# 7. Product Architecture

This is a critical structural requirement.

The website may contain multiple scooter brands/models/categories.

Do NOT assume the Products page represents one scooter.

The architecture must support:

``` text
Products
 ├── Brand A
 │    ├── Scooter A1
 │    └── Scooter A2
 │
 ├── Brand B
 │    ├── Scooter B1
 │    └── Scooter B2
 │
 └── Upcoming
      └── Scooter X
```

If the business later adds another brand, the existing architecture
should support it without rewriting the Products page.

------------------------------------------------------------------------

# 8. Multi-Brand Product Page

The Products/Scooters catalogue should allow users to understand
different brands.

Recommended structure:

## Product/Brand Header

-   Brand name
-   Brand description
-   Brand logo
-   Optional brand visual

## Products

Display all scooters belonging to that brand.

Each product:

-   Image
-   Name
-   Variant
-   Price
-   Range
-   Speed
-   Battery
-   Status
-   Explore
-   Test Ride

If multiple brands exist, provide:

-   Brand tabs
-   Brand filters
-   Category filters

Do not make the interface confusing.

------------------------------------------------------------------------

# 9. Individual Product Page

Every scooter/model must have its own page.

Example:

``` text
/products
/products/brand-a
/products/brand-a/model-a
/products/brand-b/model-b
```

Each product page should be a complete product experience.

Sections:

1.  Product Hero
2.  Key specifications
3.  Product overview
4.  Design
5.  Performance
6.  Battery
7.  Charging
8.  Smart features
9.  Safety
10. Comfort
11. Dimensions
12. Variants
13. Colours
14. Media gallery
15. Video
16. Optional 360° viewer
17. Full specifications
18. Comparison/related products
19. Test Ride CTA
20. Booking/Enquiry CTA

------------------------------------------------------------------------

# 10. Product Phase / Status

Every product must support a lifecycle/status.

Examples:

-   Upcoming
-   Pre-launch
-   Booking Open
-   Available
-   Limited Availability
-   Discontinued

Example:

``` text
status: upcoming
```

Display:

**Coming Soon**

Example:

``` text
status: booking_open
```

Display:

**Bookings Open**

Example:

``` text
status: available
```

Display:

**Available Now**

The product page should change the CTA based on status.

Do not create separate hard-coded page templates for every status.

------------------------------------------------------------------------

# 11. Product Media Architecture

Every product must support multiple media types.

Supported media:

## Images

-   Hero
-   Front
-   Rear
-   Side
-   3/4
-   Dashboard
-   Seat
-   Storage
-   Battery
-   Charger
-   Motor
-   Detail
-   Colour variants
-   Lifestyle

## Video

-   Product video
-   Feature video
-   Riding video
-   Technology video
-   Charging video

## 360°

Where available:

-   360 viewer
-   frame sequence
-   drag interaction
-   zoom

## Gallery

A reusable product gallery should support:

-   Image
-   Video
-   360
-   Full-screen view
-   Thumbnails

Do not require every product to have every media type.

The architecture must simply be capable of supporting them.

------------------------------------------------------------------------

# 12. Test Ride Form

Product page:

**Test Ride**

opens a form.

Fields:

-   Name
-   Phone
-   Email
-   Product
-   Preferred location
-   Preferred date
-   Preferred time
-   Message

The product must be automatically selected when the form is opened from
a product page.

Flow:

``` text
Product
→ Test Ride
→ Form
→ Validation
→ API
→ Confirmation
```

------------------------------------------------------------------------

# 13. Booking / Enquiry Form

Support product-specific enquiry/booking.

Fields:

-   Name
-   Phone
-   Email
-   Product
-   Brand
-   Variant
-   Colour
-   Location
-   Dealer if applicable
-   Message

Keep forms short.

Do not ask unnecessary personal information.

------------------------------------------------------------------------

# 14. Other Public Pages

The website should eventually contain:

## Products

Multi-brand catalogue.

## Product Detail

One page per model.

## Technology

Battery, motor, charging, safety and smart technology.

## Story/About

Company narrative + timeline + editorial gallery.

## Dealer Locator

Find dealer by location.

## Become a Dealer

Dealer application.

## Service/Support

Service, warranty, FAQs and support.

## Contact

Sales/service/general enquiries.

## Blog

Dynamic articles.

## Careers

Dynamic job listings.

## Accessories

If applicable.

## Savings Calculator

If applicable.

------------------------------------------------------------------------


---

# Development sequence (product steps)

## STEP 9 --- Product Catalogue

Implement:

-   Multiple brands
-   Categories
-   Product cards
-   Product filtering

------------------------------------------------------------------------

## STEP 10 --- Product Detail Template

Create one reusable product-page architecture.

Then populate each actual product.

------------------------------------------------------------------------

## STEP 11 --- Product Lifecycle

Add product phase/status.

------------------------------------------------------------------------

## STEP 12 --- Product Media

Add support for:

-   Images
-   Gallery
-   Video
-   360°

------------------------------------------------------------------------

## STEP 13 --- Test Ride / Booking

Connect forms to the existing backend API.

------------------------------------------------------------------------


---

# Definition of Done — Products

-   [ ] Multiple brands supported
-   [ ] Multiple scooters supported
-   [ ] Product cards are complete
-   [ ] Every product has its own page
-   [ ] Product phase/status supported
-   [ ] Product media architecture supports image/video/360
-   [ ] Test Ride works
-   [ ] Booking/enquiry works
