=== Bootflow – Product XML & CSV Importer ===
Contributors: bootflowio
Tags: woocommerce, import, xml, csv, products
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.9.5
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
* Update existing products with selective field control
* Reusable import templates
* AI-assisted field mapping and data transformation
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
The free version creates new products and can match by SKU. For selective field updates and advanced update rules, the Pro version is available.

= Does the plugin send data to external servers? =
No. The plugin does not collect analytics, track usage, or phone home. When your import file contains image URLs, the plugin downloads those images to your server using the WordPress HTTP API. No store data is sent externally.

= Does this plugin work with WooCommerce HPOS? =
Yes. The plugin is compatible with WooCommerce High-Performance Order Storage.

== Requirements ==

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher (tested up to WooCommerce 9.0)
* PHP 7.4 or higher

== Screenshots ==

1. Import wizard — file upload
2. Field mapping interface
3. Import preview
4. Import progress and results

== Changelog ==

= 0.9.5 =
* Removed "Get PRO" admin menu upsell and related methods
* Removed unused license management class
* Regenerated translation files without PRO strings

= 0.9.4 =
* Replaced LICENSE file with correct GPL v2 text
* Regenerated translation files — removed leftover PRO strings
* Removed dead PRO CSS classes (pro-badge, pro-feature-disabled)
* Removed unused license/AI stub methods from admin class
* Added file path validation against uploads directory
* Clarified readme FAQ about image downloads from user-provided URLs
* Cleaned PRO references from JS and PHP comments

= 0.9.3 =
* Removed unused PRO scaffolding code (is_pro, pro_badge, License stubs)
* Fixed nonce sanitization to use sanitize_text_field instead of sanitize_key
* Added wp_unslash to all superglobal access points
* Scoped pro menu script to plugin pages only
* Renamed WC-prefixed log messages to BFPI prefix

= 0.9.2 =
* WordPress.org compliance updates
* Replaced curl with WordPress HTTP API
* Fixed text domain consistency
* Improved input sanitization and output escaping

= 0.9.0 =
* Initial release
* XML and CSV import
* Manual field mapping
* Simple and variable products
* Attributes and variations
* SKU matching
* Import preview

== Upgrade Notice ==

= 0.9.2 =
Compliance and security improvements. Recommended update.
