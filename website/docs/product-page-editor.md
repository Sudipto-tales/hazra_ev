The product details page is managed in **Admin > Products > Edit Product**.

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
