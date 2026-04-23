=== Bootflow – Product XML & CSV Importer ===
Contributors: bootflowio
Tags: woocommerce, import, xml, csv, products
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.9.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import WooCommerce products from XML and CSV files using a clear, manual field mapping workflow. No limits on product count.

== Description ==

**Bootflow – Product XML & CSV Importer** is a reliable product import tool for WooCommerce.

Upload an XML or CSV file, map its fields to WooCommerce product fields, preview the result, and run the import. That is it — no complexity, no surprises.

= Features =

* **XML and CSV import** — upload product feeds directly from your computer
* **Manual field mapping** — full control over which source fields map to which product fields
* **Simple and variable products** — supports both product types including attributes and variations
* **Image import** — import product images from URLs in your feed
* **Categories and tags** — map or create product categories and tags during import
* **SKU matching** — match incoming products to existing ones by SKU
* **Import preview** — review mapped data before running the import
* **Unlimited products** — no artificial product count limits
* **No tracking or telemetry** — no data is sent to external servers, no analytics, no account required

= Who is this for? =

Store owners, developers, and agencies who need a straightforward way to import product data into WooCommerce from supplier feeds or internal exports.

= Pro Version =

A Pro version is available at [bootflow.io](https://bootflow.io) with additional features for stores that need automation and advanced processing:

* Import from remote URLs (XML/CSV)
* Scheduled and recurring imports via WP-Cron
* Auto field mapping — automatic detection and matching of source fields to WooCommerce product fields
* Per-field selective update control (choose which individual fields to update on re-import)
* PHP / hybrid data transformations during import (modify values on the fly with custom rules)
* Reusable import templates
* AI-assisted field mapping, data transformation, on-the-fly translation of product titles, descriptions and attributes, and AI generation of any product field (e.g. SEO titles, descriptions, short descriptions, tags) from existing data
* Detailed import logs

AI features are optional and require user-provided API keys.

== Installation ==

1. Upload the plugin files to the wp-content/plugins/bootflow-product-importer/ directory, or install through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Navigate to WooCommerce, then Product Importer to start importing.

== Frequently Asked Questions ==

= What file formats are supported? =
XML and CSV files are supported.

= Is there a product limit? =
No. There is no limit on the number of products you can import.

= Does this plugin support variable products? =
Yes. Variable products with attributes and variations are fully supported.

= Can I update existing products? =
Yes. Enable "Update existing products" in the import settings to update existing products matched by SKU. You can also enable "Skip products if data unchanged" to avoid unnecessary writes, and choose what to do with products that are no longer in the feed (move to draft, mark out of stock, allow backorder, move to trash, or permanently delete). Per-field selective update — choosing which individual fields are updated on re-import — is available in the Pro version.

= Does the plugin send data to external servers? =
No. The plugin does not collect analytics, track usage, or phone home. When your import file contains image URLs, the plugin downloads those images to your server using the WordPress HTTP API. No store data is sent externally.

= Does this plugin work with WooCommerce HPOS? =
Yes. The plugin is compatible with WooCommerce High-Performance Order Storage.

== Requirements ==

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher (tested up to WooCommerce 9.0)
* PHP 7.4 or higher

== Changelog ==

= 0.9.8 =
* Removed per-field "Clear Mapping" (X) button from the mapping wizard and import editor; mapping fields can be cleared by editing the source field directly

= 0.9.7 =
* Removed per-field "Update on re-import" toggle from the mapping wizard and import editor; all mapped fields are now always updated when re-importing existing products
* Updated readme to accurately describe which features are included in the free version
* Removed decorative styling from the upgrade menu link

== Upgrade Notice ==

= 0.9.8 =
UI cleanup. Recommended update.
