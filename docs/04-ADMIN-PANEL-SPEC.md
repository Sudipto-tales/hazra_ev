# Admin Panel & Backend Spec — archived from the redesign plan

**Origin:** `html/EV_Website_Redesign_Next_Day_Plan.md` (deleted 2026-09-01 after the
design half was executed). This file preserves the half that was *not* built: the admin
panel, its authorization model, and the shared backend architecture.

**Owner:** `web-agent` (`website/`). Nothing here is design-agent work.

**Status:** unbuilt. `docs/02-API-PLAN.md` remains authoritative for endpoint shapes;
this document describes the admin surface those endpoints must serve.

---

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


---

# Development sequence (backend steps only)

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


---

# Definition of Done — Admin

-   [ ] Same auth API
-   [ ] Backend admin authorization
-   [ ] Admin route protection
-   [ ] Careers CRUD
-   [ ] Blog CRUD
-   [ ] Website Settings
-   [ ] Draft/published/archived states
-   [ ] Image upload validation

---

# Guiding principle

The admin journey stays intentionally simple:

``` text
Admin Login
    ↓
Dashboard
    ├── Careers
    ├── Blogs
    └── Website Settings
```

Do not turn the admin panel into a complete website builder. Keep the content system
focused, secure and maintainable.
