# Hazra EV — Implementation Tracker

**Master prompt:** Merged PHP conversion + design unification + admin/leads/settings  
**Last updated:** YYYY-MM-DD

---

## Phase 0 — Foundations

- [ ] Shared design tokens CSS in website/assets/css
- [ ] typography.css / global.css / utilities.css
- [ ] components/header.css + footer.css + buttons.css
- [ ] app/components/head.php
- [ ] app/components/header.php (full nav)
- [ ] app/components/footer.php (rich footer)
- [ ] Theme JS + nav JS working with shared header

## Phase 1 — Chrome unification (no home/story redesign)

- [ ] Home page uses shared footer (keep hero design)
- [ ] Our Story page uses shared header + footer (keep timeline/values design)
- [ ] All other pages switched to shared header + footer
- [ ] Active nav states correct per page
- [ ] Mobile burger + dropdowns work on all pages

## Phase 2 — PHP conversion of prototypes

- [ ] home.php from index.html (design preserved)
- [ ] our-story.php (design preserved)
- [ ] Single product detail template (slug-driven)
- [ ] Catalogue / products index page
- [ ] chalo-1000-v2 content on product template
- [ ] chalo-smart-pro / plus / eco / neo / nja-7 on product template
- [ ] become-a-dealer.php
- [ ] dealer-locator.php
- [ ] warranty-free.php + warranty-paid.php
- [ ] battery-use.php
- [ ] ev-future.php
- [ ] career.php
- [ ] blog.php (structure)
- [ ] contest.php
- [ ] contact page (if missing as dedicated page)

## Phase 3 — Content & section upgrades

- [ ] Product hero: name, tagline, key metrics
- [ ] Product color variants from product_colors
- [ ] Product feature blocks (Battery, Motor, Safety, etc.)
- [ ] Full specs table on product pages
- [ ] Consistent CTAs: Test Ride, Find Dealer, Contact
- [ ] Warranty note on product pages
- [ ] Related models teaser
- [ ] Dealer page benefits + stats
- [ ] Contact page channels + form
- [ ] FAQ content expanded
- [ ] Catalogue cards show range / speed / category

## Phase 4 — Website settings + leads (backend)

- [ ] Migration: website_settings
- [ ] Migration: website_leads
- [ ] WebsiteSettingsController (GET/PUT)
- [ ] WebsiteLeadsController (POST public, GET/PATCH admin)
- [ ] Append only new routes in gateway.php
- [ ] Mailer refactored to settings-driven SMTP
- [ ] Lead create sends email to mail_admin_to
- [ ] Public forms post to /api/v1/website/leads

## Phase 5 — Admin panel

- [ ] Admin auth guard (existing login + admin role)
- [ ] Admin layout (sidebar / topbar)
- [ ] Dashboard counts
- [ ] Products list / create / edit / active toggle (existing products API only)
- [ ] Leads inbox + filters + mark read/archived
- [ ] Website Settings form (contact + SMTP + brand)

## Phase 6 — Wire-up & QA

- [ ] Public product pages read from products table/API
- [ ] Test-ride / contact / dealer forms end-to-end
- [ ] Dark mode on all converted pages
- [ ] Mobile responsive check
- [ ] Broken links / image paths fixed
- [ ] No existing mobile API routes changed
- [ ] Tracker updated after every completed task

---

## Notes / blockers

| Date | Note |
|------|------|
| | |