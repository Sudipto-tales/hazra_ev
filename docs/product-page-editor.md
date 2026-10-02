The product details page is managed in **Admin > Products > Edit Product**.

The mobile admin **Products > Add/Edit product** form also manages product information, publication status, homepage placement, default color, ordered color galleries, feature cards, page content, related models, and specifications/warranty. Both clients use the same product API. Published/Hidden maps to `active: true/false`; it is separate from per-color stock availability. An out-of-stock product can still be published.

Mobile keeps existing color IDs and feature-card IDs on edit. Removing a color asks for confirmation and converts its associated feature cards to shared cards; removing the default color selects the first remaining color. The first photograph in each color gallery is its primary image. Product saves are disabled during image uploads, and failed saves retain the form for correction/retry. Test-ride lead management remains in the web admin.

The mobile field definitions are in `mobile_app/lib/features/admin/products/product_form_page.dart`, with separate color and feature-card editors. Website text defaults/groups are in `mobile_app/lib/data/models/product_page_content.dart` and must remain aligned with `website/core/ProductContent.php` and `website/assets/admin/js/pages/product-editor.js`. Read/write mappings live in `mobile_app/lib/data/api/wire.dart`.

Run mobile regression checks from `mobile_app` with `flutter test test/product_form_parity_test.dart test/admin_repository_test.dart test/report_sale_test.dart`. These checks use mock repositories/HTTP responses and do not modify the live catalogue. Camera/gallery permissions and real uploads should also be checked on a device against the deployed API.

- **Product information:** identifiers, highlights (one per line), fallback image, publication status and homepage placement.
- **Colors & galleries:** upload photographs for each color; use the first photograph as that color's primary image, reorder photographs, choose the default color, and mark stock availability.
- **Feature cards:** upload independent detail photographs, add titles/descriptions/alt text, reorder cards and hide individual cards. Shared cards remain consistent when a customer changes color. Associate a card with a color to show it only for that color.
- **Page content:** edit section headings, descriptions, buttons, banner image and visibility. Use `{name}`, `{brand}`, and `{model}` placeholders and line breaks in headings. Select related products, or leave the selection empty for automatic recommendations.
- **Specifications & warranty:** edit range, speed, battery, motor, charging time, payload, rating and warranty information.
- **Test-ride requests:** review requests for this product and open the Test Drive Leads screen to contact, approve, schedule or reject them.

Save Changes commits the product, page content, feature cards, colors and gallery relations together. Removing an image from a product does not delete the physical file. Existing color and image IDs are retained when editing/reordering them.

Deploy the schema with `php vayu migrate` (without `--fresh`). Migration `024_product_page_content` preserves the catalogue and backfills feature cards from existing highlights/specifications and color-gallery images. It uses the existing fallback image when a product has no gallery. It also populates default-color selections and links historic product test-ride leads when their stored details contain a product ID. Rerunning this migration preserves custom content and intentionally empty feature lists.

The public test-ride form saves the customer, city, product and selected-color snapshot in `website_leads`. Product/color identifiers are verified on the server. A unique submission key prevents duplicate records when a customer retries a timed-out request. Notification delivery happens after saving and does not invalidate a saved request. The admin lead list supports filtering by product.

Run the isolated regression checks with `php tests/product-content.php`. The tests use an in-memory SQLite database and do not modify the application's database.
