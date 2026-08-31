# EV Website Redesign + Admin Panel --- Next-Day Development Plan

## 0. Project Objective

This document is the implementation plan for the next development phase
of the existing EV website.

The website is already in progress and the pages/routes already exist.
The goal is **not** to rebuild the application from scratch.

The next phase should improve:

-   Visual design
-   Content structure
-   Product architecture
-   Home-page storytelling
-   Story/About page
-   Forms and conversion flows
-   Responsive behavior
-   Light/dark mode
-   Media handling
-   Admin-managed content
-   CSS/font/library architecture
-   Overall consistency

Use the existing project as the base.

Use the previously researched GOEEN website as an
**information-architecture and content-depth reference**, but do not
copy its branding, source code, exact visual design, or copyrighted
assets.

------------------------------------------------------------------------

# 1. Important Design References From the Attached Images

Two visual references were supplied for this phase.

## Reference A --- Home Page Timeline / Project Steps

The first reference is a vertical timeline/infographic.

Visual characteristics:

-   A continuous curved line/path moves vertically through the page.
-   Large circular visual areas alternate from left to right.
-   Content blocks alternate around the path.
-   Numbered steps create a clear chronological story.
-   The layout has substantial whitespace.
-   The path visually connects the complete story.
-   Images/content can sit inside or around the circular areas.
-   The design feels editorial rather than like ordinary cards.

### Use this concept on the Home page

Create a section such as:

**Our Journey**

or

**The Road We Took**

The timeline should communicate the company's history/progress.

Conceptual structure:

``` text
                OUR JOURNEY

01 ── ● ──────────────── Story
       │
       └───────────────────────
                              ● ── 02
                              Story
       ┌───────────────────────
       │
03 ── ● ───────────────────── Story
       │
       └───────────────────────
                              ● ── 04
                              Story
```

Do not reproduce the reference pixel-for-pixel.

Use the same **visual principle**:

-   continuous path
-   alternating content
-   numbered milestones
-   large visual areas
-   editorial storytelling

### Timeline content

The timeline must be driven by real company information.

Possible milestone structure:

1.  Beginning / Foundation
2.  First Product / First Major Step
3.  Technology / Expansion
4.  Current Stage
5.  Future / Vision

Only use milestones that are supported by actual company data.

Do not invent dates, numbers, achievements or claims.

### Responsive behavior

Desktop: - Alternating left/right timeline. - Large visual areas. -
Continuous connecting path.

Tablet: - Reduced visual size. - Preserve alternating structure where
space permits.

Mobile: - Convert to a single vertical timeline. - Path remains
visible. - Content and image become one-column. - Do not force large
circles that make the page unnecessarily tall. - Keep milestone numbers
prominent.

------------------------------------------------------------------------

# 2. Home Page --- Final Section Architecture

The Home page should tell a complete story rather than only selling a
product.

Recommended order:

## 2.1 Hero

Purpose:

Immediately communicate:

-   Brand
-   EV category
-   Main product/value proposition
-   Primary action

Include:

-   Product/vehicle visual
-   Strong headline
-   Supporting text
-   Primary CTA
-   Secondary CTA
-   Product indicator where appropriate

Fix the previously identified navbar/hero conflict.

The navbar must never overlap the important part of the hero image.

------------------------------------------------------------------------

## 2.2 Product Showcase

Show multiple scooters/products.

Each product card should contain:

-   Product image
-   Product name
-   Short positioning statement
-   Key specification
-   Price if available
-   Available phase/status
-   Explore CTA
-   Test Ride CTA where applicable

The product card should feel like an automotive product presentation,
not a generic ecommerce card.

------------------------------------------------------------------------

## 2.3 Why Electric / Why Us

Use 4--6 strong benefit points.

Possible categories:

-   Running cost
-   Range
-   Battery
-   Safety
-   Technology
-   Comfort
-   Service

Only show verified claims.

------------------------------------------------------------------------

## 2.4 Featured Product Story

Use a large visual section for the flagship/current product.

Structure:

-   Large image/video
-   Product name
-   Strong statement
-   Short description
-   Key numbers
-   Explore Product CTA
-   Test Ride CTA

------------------------------------------------------------------------

## 2.5 Technology

Introduce the technology behind the products.

Possible content:

-   Battery
-   Motor
-   Charging
-   Smart features
-   Safety
-   Connectivity

Use visual storytelling.

------------------------------------------------------------------------

# 3. Home Page --- NEW Timeline Section

Place the timeline after the main brand/product introduction and before
the final conversion sections.

Recommended title:

**Our Journey**

Supporting copy:

A short statement explaining how the company moved from its beginning
toward its current EV vision.

Then render the alternating timeline.

Each timeline item should support:

-   Number
-   Year/date if real
-   Short title
-   Short description
-   Image/media
-   Optional CTA

Example data model:

``` text
timelineItem:
  id
  year
  title
  description
  image
  order
  active
```

For now, timeline data can remain static if the requirement is only to
make Career, Blog and Website Settings dynamic.

Do NOT add a timeline management module to the admin panel unless there
is a real business requirement.

------------------------------------------------------------------------

# 4. Home Page --- Lifestyle / Storytelling Gallery

Use a visual editorial section inspired by the second attached image.

The reference demonstrates:

-   Large typography
-   Black/white editorial imagery
-   Asymmetric image arrangement
-   Different image sizes
-   Rounded image corners
-   Short supporting statements
-   Strong visual rhythm
-   Minimal text
-   Small visual controls/icons

Do not directly copy the exact composition.

Create an original EV-focused editorial gallery.

Possible title:

**More Than a Ride.**

or

**Built for Everyday Movement.**

The section can combine:

-   City riding
-   People
-   Product details
-   Roads
-   Charging
-   Technology
-   Lifestyle
-   Brand moments

### Layout

Desktop: - Asymmetric masonry/grid. - Multiple image sizes. - Large
statement typography. - Small supporting text.

Tablet: - Simplified grid.

Mobile: - 1--2 column editorial layout. - Avoid tiny unreadable text. -
Images remain visually strong.

------------------------------------------------------------------------

# 5. Story / About Page --- NEW Editorial Gallery

The second attached image should also influence the Story/About page.

The Story page should not be a plain:

`About Us + paragraphs`

page.

It should feel like a visual company story.

Recommended structure:

## Hero

Strong company statement.

------------------------------------------------------------------------

## Our Story

Short narrative explaining:

-   Why the company exists
-   How it started
-   What problem it wants to solve
-   How it developed

------------------------------------------------------------------------

## Timeline / Journey

Reuse the visual language from the Home timeline where appropriate, but
do not duplicate the entire section unnecessarily.

------------------------------------------------------------------------

## Editorial Story Gallery

Add a dedicated visual gallery based on the second reference.

Possible sections:

### The Beginning

Image + short story.

### The People

Team/company/lifestyle imagery.

### The Product

Product development imagery.

### The Technology

Engineering/battery/motor imagery.

### The Road Ahead

Future/vision imagery.

Use varied image sizes and editorial composition.

------------------------------------------------------------------------

# 6. Story Page --- Content Architecture

Recommended full structure:

1.  Hero
2.  Short company introduction
3.  Our Story
4.  Timeline/Journey
5.  Editorial image gallery
6.  Mission
7.  Vision
8.  Values
9.  Technology/Innovation
10. People/Team if real content exists
11. Sustainability if supported by real company information
12. Future vision
13. Final CTA

The Story page should feel like a narrative.

The visitor should understand:

**Where we came from → What we believe → What we build → Where we are
going.**

------------------------------------------------------------------------

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

# 15. Admin Panel --- Scope

The admin panel should remain intentionally small.

Do NOT build a huge CMS.

The admin panel is primarily for content that changes regularly.

Admin-managed modules:

1.  Careers
2.  Blogs
3.  Website Settings

Everything else should remain controlled by the application's existing
data/code architecture unless there is a future requirement.

------------------------------------------------------------------------

# 16. Admin Authentication

The Admin panel must use the **same backend API and authorization
system** as the existing application.

Do NOT create a second independent authentication system.

Architecture:

``` text
Public Website
       |
       v
Existing Backend API
       |
       +---- Public endpoints
       |
       +---- Auth endpoints
       |
       +---- Admin endpoints
                    |
                    v
             Authorization
                    |
                    v
                Admin role
```

The admin login should authenticate through the same existing auth API.

After authentication:

-   Validate token/session
-   Validate user role/permission
-   Allow admin routes only for authorized users

Do not rely only on hiding the admin URL.

Authorization must be enforced on the backend.

------------------------------------------------------------------------

# 17. Admin Route Protection

Example conceptual flow:

``` text
/admin/login
       |
       v
Login API
       |
       v
Token/session
       |
       v
Check authenticated user
       |
       v
Check admin authorization
       |
       +---- Not authorized → reject
       |
       +---- Authorized → /admin
```

The frontend route guard is useful for UX, but backend authorization is
mandatory.

------------------------------------------------------------------------

# 18. Admin Dashboard

Keep the dashboard simple.

Show useful summaries such as:

-   Published blogs
-   Draft blogs
-   Active careers
-   Website status
-   Recent content updates

Do not build unnecessary analytics if they are not required.

------------------------------------------------------------------------

# 19. Admin --- Careers

Career content should be fully manageable.

Actions:

-   Create job
-   Edit job
-   Publish/unpublish
-   Archive
-   Delete

Fields:

-   Job title
-   Department
-   Location
-   Employment type
-   Experience
-   Description
-   Responsibilities
-   Requirements
-   Skills
-   Benefits
-   Application instructions
-   Published status
-   Publish date

Public page:

``` text
/careers
```

Individual job:

``` text
/careers/job-slug
```

------------------------------------------------------------------------

# 20. Admin --- Blogs

Blog management should support:

-   Create
-   Edit
-   Draft
-   Publish
-   Unpublish
-   Archive
-   Delete

Fields:

-   Title
-   Slug
-   Featured image
-   Excerpt
-   Content
-   Category
-   Tags
-   Author
-   Publish date
-   SEO title
-   SEO description
-   Status

Public pages:

``` text
/blog
/blog/article-slug
```

Use a proper rich-text editor or Markdown editor according to the
existing stack.

------------------------------------------------------------------------

# 21. Admin --- Website Settings

Website settings should control only genuinely global content.

Possible settings:

## Brand

-   Logo
-   Favicon
-   Brand name
-   Short description

## Contact

-   Phone
-   Email
-   Address

## Social

-   Instagram
-   Facebook
-   YouTube
-   LinkedIn

## SEO

-   Default title
-   Default description
-   OG image

## Footer

-   Copyright
-   Footer text
-   Important links

## General

-   Maintenance mode if required
-   Default theme preferences if required

Do not put product data into Website Settings.

------------------------------------------------------------------------

# 22. What Should NOT Be Admin-Dynamic Yet

Do not unnecessarily create admin CRUD for:

-   Navbar structure
-   Home layout
-   Product page layout
-   Product specifications
-   Product images
-   Product status
-   Timeline
-   Technology sections
-   Story page structure

Unless the business explicitly requires these to be editable by
non-developers.

Keep the first admin panel small.

This reduces complexity and security risk.

------------------------------------------------------------------------

# 23. Shared Backend Architecture

Use the same API/backend.

Conceptually:

``` text
/api/auth/*
/api/blogs/*
/api/careers/*
/api/settings/*
```

Admin endpoints should enforce authorization.

Example:

``` text
GET    /api/blogs
GET    /api/blogs/:slug

POST   /api/admin/blogs
PUT    /api/admin/blogs/:id
DELETE /api/admin/blogs/:id

GET    /api/careers
POST   /api/admin/careers
PUT    /api/admin/careers/:id
DELETE /api/admin/careers/:id

GET    /api/settings
PUT    /api/admin/settings
```

Exact endpoint names should follow the existing backend conventions.

Do not create duplicate APIs if equivalent endpoints already exist.

------------------------------------------------------------------------

# 24. Admin Authorization Model

At minimum:

``` text
User
 ├── authenticated
 └── role = admin
```

If the existing application already has a role/permission system, reuse
it.

Do not create a second role table unless the current architecture
requires it.

Future roles can later include:

-   Super Admin
-   Content Manager
-   Product Manager

But do not implement them now unless required.

------------------------------------------------------------------------

# 25. Content States

For Careers and Blogs, use clear states:

``` text
draft
published
archived
```

Frontend only displays:

``` text
published
```

Admin can manage all states.

This prevents unfinished content from accidentally appearing publicly.

------------------------------------------------------------------------

# 26. Image Upload Strategy

Admin content should support image uploads for:

-   Blog featured image
-   Blog content images
-   Career/company images if required
-   Website settings logo/social image

Validate:

-   File type
-   File size
-   Image dimensions where appropriate

Prefer optimized:

-   WebP
-   AVIF where supported

Do not store huge original images directly in the page response.

------------------------------------------------------------------------

# 27. Image Strategy for the Whole Website

Images are part of the design system.

Use different image types for different purposes.

## Product

-   Studio
-   Lifestyle
-   Detail
-   Technical
-   Colour
-   Dashboard
-   Battery
-   Charger
-   360
-   Video

## Home

-   Hero
-   Product
-   Lifestyle
-   Technology
-   Timeline
-   Brand story

## Story

-   Company
-   People
-   Timeline
-   Manufacturing
-   Product development
-   Lifestyle
-   Future

## Blog

-   Featured
-   Article content
-   Related articles

Never reuse one generic image throughout the entire website if a
suitable asset exists.

------------------------------------------------------------------------

# 28. Typography and Brand Consistency

The logo and logo text must remain visually consistent throughout the
website.

The previously identified problem must be fixed:

-   Do not randomly turn navbar text blue during scroll.
-   Maintain intended grey/black/neutral branding.
-   Use accent color only where appropriate.

Hazra/EV-sector branding should visually remain consistent.

------------------------------------------------------------------------

# 29. Dark Mode

Audit every page.

Check:

-   Logo
-   Logo text
-   Navbar
-   Hero
-   Product cards
-   Product gallery
-   Timeline
-   Story gallery
-   Blog
-   Careers
-   Forms
-   Buttons
-   Footer
-   Icons
-   Borders

Do not simply invert colors.

Create intentional light/dark tokens.

------------------------------------------------------------------------

# 30. Responsive Behavior

Test:

-   1440px+
-   1280px
-   1024px
-   768px
-   480px
-   360px

Especially test:

-   Navbar
-   Hero
-   Timeline
-   Product cards
-   Product gallery
-   Story gallery
-   Forms
-   Comparison
-   Footer

------------------------------------------------------------------------

# 31. CSS Architecture

After the visual redesign is stable, optimize the CSS architecture.

Prefer:

``` text
styles/
 ├── tokens.css
 ├── typography.css
 ├── global.css
 ├── utilities.css
 └── components/
```

Adapt this to the existing framework instead of forcing a new structure.

Use CSS variables for:

-   Brand colors
-   Backgrounds
-   Text
-   Borders
-   Spacing
-   Radius
-   Shadows
-   Typography

Avoid duplicated values across components.

Do not use excessive inline styles.

------------------------------------------------------------------------

# 32. Fonts

Use downloaded/local fonts where practical.

Recommended architecture:

``` text
assets/
  fonts/
    Font-Regular.woff2
    Font-Medium.woff2
    Font-Semibold.woff2
    Font-Bold.woff2
```

Use `@font-face`.

Only load the weights actually required.

Avoid unnecessary external font requests.

------------------------------------------------------------------------

# 33. Libraries

Use libraries only when they provide real value.

Potential categories:

-   Animation
-   Smooth scrolling
-   Image gallery
-   360° viewer
-   Carousel
-   Icons
-   Form validation
-   Map
-   Rich text editor

Before adding a library:

1.  Check whether the project already has an equivalent.
2.  Check bundle size.
3.  Check whether native CSS/JS can solve it.
4.  Add it only if it improves the final experience.

Do not add multiple libraries for the same problem.

------------------------------------------------------------------------

# 34. Performance

Optimize:

-   Images
-   Fonts
-   CSS
-   JavaScript
-   Animations
-   Third-party libraries

Use:

-   Lazy loading
-   Responsive images
-   WebP/AVIF
-   Proper image dimensions
-   Font preloading only when justified
-   Code splitting where supported

Do not load 360° media, videos or large galleries before the user needs
them.

------------------------------------------------------------------------

# 35. SEO

Every public page should have:

-   Unique title
-   Meta description
-   One H1
-   H2/H3 hierarchy
-   Canonical URL
-   Open Graph metadata
-   Descriptive image alt text

Product pages should support appropriate Product structured data.

Blog pages should support Article structured data where appropriate.

Organization information should support Organization structured data
where appropriate.

------------------------------------------------------------------------

# 36. Development Sequence For The Next Work Session

Follow this order.

## STEP 1 --- Audit

Inspect the existing application.

Do not modify code yet.

------------------------------------------------------------------------

## STEP 2 --- Global Design System

Finalize:

-   Colors
-   Typography
-   Spacing
-   Buttons
-   Cards
-   Theme tokens

------------------------------------------------------------------------

## STEP 3 --- Navbar + Footer

Fix:

-   Product navigation presentation
-   Logo consistency
-   Scroll behavior
-   Responsive collapse
-   Hero overlap

------------------------------------------------------------------------

## STEP 4 --- Home Hero

Fix the current hero layout.

------------------------------------------------------------------------

## STEP 5 --- Home Product Showcase

Create the multi-product presentation.

------------------------------------------------------------------------

## STEP 6 --- Home Timeline

Implement the attached first-reference concept.

Use the curved alternating timeline.

------------------------------------------------------------------------

## STEP 7 --- Home Editorial Gallery

Implement the attached second-reference concept as an original EV
editorial/lifestyle section.

------------------------------------------------------------------------

## STEP 8 --- Story Page

Add:

-   Story structure
-   Timeline
-   Editorial gallery
-   Mission
-   Vision
-   Future

------------------------------------------------------------------------

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

## STEP 14 --- Admin Authentication

Reuse the existing authentication API.

Add backend authorization for admin access.

------------------------------------------------------------------------

## STEP 15 --- Admin Careers

CRUD + publish states.

------------------------------------------------------------------------

## STEP 16 --- Admin Blogs

CRUD + publish states + SEO fields.

------------------------------------------------------------------------

## STEP 17 --- Admin Website Settings

Global settings only.

------------------------------------------------------------------------

## STEP 18 --- Public Careers / Blog

Connect public pages to the admin-managed API.

------------------------------------------------------------------------

## STEP 19 --- Dark Mode

Audit every page.

------------------------------------------------------------------------

## STEP 20 --- Responsive QA

Test all major breakpoints.

------------------------------------------------------------------------

## STEP 21 --- CSS / Font / Library Optimization

Only after the visual design is stable:

-   Refactor duplicated CSS
-   Download/localize fonts
-   Remove unused styles
-   Remove unnecessary libraries
-   Optimize images
-   Optimize JS
-   Optimize loading

------------------------------------------------------------------------

# 37. Definition of Done

The phase is complete when:

### Home

-   [ ] Hero no longer conflicts with navbar
-   [ ] Product presentation is professional
-   [ ] Timeline is implemented
-   [ ] Editorial gallery is implemented
-   [ ] Brand story is clear
-   [ ] CTAs are clear

### Story

-   [ ] Story has proper narrative
-   [ ] Timeline is visually strong
-   [ ] Attached editorial/gallery concept is incorporated
-   [ ] Images have varied visual composition

### Products

-   [ ] Multiple brands supported
-   [ ] Multiple scooters supported
-   [ ] Product cards are complete
-   [ ] Every product has its own page
-   [ ] Product phase/status supported
-   [ ] Product media architecture supports image/video/360
-   [ ] Test Ride works
-   [ ] Booking/enquiry works

### Admin

-   [ ] Same auth API
-   [ ] Backend admin authorization
-   [ ] Admin route protection
-   [ ] Careers CRUD
-   [ ] Blog CRUD
-   [ ] Website Settings
-   [ ] Draft/published/archived states
-   [ ] Image upload validation

### Design

-   [ ] Logo consistent
-   [ ] Logo text consistent
-   [ ] Navbar colors consistent
-   [ ] Light mode works
-   [ ] Dark mode works
-   [ ] Mobile works
-   [ ] Tablet works
-   [ ] Desktop works

### Optimization

-   [ ] Local fonts
-   [ ] External/reusable CSS architecture
-   [ ] Unused CSS removed
-   [ ] Images optimized
-   [ ] Heavy libraries avoided
-   [ ] Lazy loading used where appropriate
-   [ ] SEO metadata implemented

------------------------------------------------------------------------

# 38. Final Principle

Do not treat this as a simple UI makeover.

Treat it as:

**Brand Website + Product Catalogue + Product Experience + Lead
Conversion System + Small Content CMS**

The visitor journey should be:

``` text
Discover Brand
      ↓
Understand Story
      ↓
Explore Products
      ↓
Choose Brand / Scooter
      ↓
View Product Details
      ↓
Compare / Understand
      ↓
Book Test Ride
      ↓
Find Dealer
      ↓
Enquire / Purchase
```

The admin journey should remain intentionally simple:

``` text
Admin Login
    ↓
Dashboard
    ├── Careers
    ├── Blogs
    └── Website Settings
```

Do not turn the admin panel into a complete website builder.

Keep the content system focused, secure and maintainable.
