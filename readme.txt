=== Remote Tickets Server Add-ons ===
Contributors: hansschuijff
Tags: the-events-calendar, woocommerce, rest-api, event-tickets, autoload
Requires at least: 7.0
Tested up to: 7.0.4
Requires PHP: 8.4
Stable tag: 1.0.0
License: GPL-2.0
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends The Events Calendar REST API with event status, custom ticket metrics and enhances WooCommerce with URL-based batch add-to-cart functionality.

== Description ==

Remote Tickets Server Add-ons is a powerful, lightweight, and object-oriented developer extension tailored for hybrid ticket setups. It seamlessly bridge gaps in the default REST API payloads of The Events Calendar and eliminates the native single-item restriction of the WooCommerce cart handler.

The main reason for this plugin is the need for a remote tickets block that is filled with data from a remote events calendar and tickets. It adds some base Rest API data and woocommerce functionality for the proper functioning of the remote tickets for The events calendar client plugin.

= Features: =
* **REST API Enhancements:** Appends structured, real-time calculated object metrics directly to the default `tribe_events` API event schemas (both archive list and single endpoints). The API response will offer these additional fields for each event:
	* `event_status`: The default `_tribe_events_status` meta field content, combined with calculated fallbacks (see below) that help make decisions about showing tickets more efficiently and exact (since they are determined with the usage of The Events Calendar's own core functionality).
    * `ticket_min_price`: The price of the lowest-priced available ticket, allowing clients to present it as a "starting from" price without having to retrieve all individual tickets.
    * `ticket_currency`: The active currency symbol used for the event tickets.
    * `tickets_remaining`: Total number of available tickets (the combined sum of stock across all active tickets).
* **Deterministic Event Statuses:** Dynamically resolves the `event_status` field if no custom database status is filled in by the content creator:
    * `hidden`: Triggered when an event is manually hidden from upcoming overviews (primarily acts as a safeguard for single event requests, as archive lists typically omit these automatically).
    * `past`: Triggered when the event end date and time have passed.
    * `sales-closed`: Triggered when Event Tickets (Plus) is active and tickets exist, but the end-date of sales has passed for all tickets.
    * `sold-out`: Triggered when Event Tickets (Plus) is active and tickets are still for sale, but there is no remaining stock for any ticket.
    * `scheduled`: The default fallback state if no other state is active or filled in.
* **Smart Archive Filtering:** Introduces a custom query parameter `&hide_unbookable=1` to filter out non-bookable events ('sold-out', 'sales-closed', 'hidden', 'past', 'canceled', and 'postponed' event states) dynamically during the request loop.
* **WooCommerce Batch Add-to-Cart:** Overrides standard handlers to support complex, reliable multi-product queries via URL. Allows structured syntax like `?add-to-cart=101:2,102:5` (one or more comma-separated pairs of product-id:quantity) for instant batch additions.
* **Modern Architecture:** Built entirely according to PSR-4 autoloading standards, fully type-hinted, and strictly optimized for PHP 8.4+ and WordPress 7.0+ performance with core Object Cache mapping.

* **Smart Archive Filtering:** Introduces a custom query parameter `&hide_unbookable=1` to filter out non-bookable events ('sold-out', 'sales-closed', 'hidden', 'past', 'canceled', and 'postponed' event states) dynamically during the request loop.
* **WooCommerce Batch Add-to-Cart:** Overrides standard handlers to support complex, reliable multi-product queries via URL. Allows structured syntax like `?add-to-cart=101:2,102:5` (one or more comma separated pairs of product-id:quantity ) for instant batch additions.
* **Modern Architecture:** Built entirely according to PSR-4 autoloading standards, fully type-hinted, and strictly optimized for PHP 8.4 and WordPress 7.0+ performance with core Object Cache mapping.

== Installation ==

1. Upload the `remote-tickets-server-addons` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API clients or theme URLs to use the new endpoints and query variables.

== Frequently Asked Questions ==

= How do I hide unbookable events from my API requests? =
Simply append the parameter to your archive API request URL, like this:
`/wp-json/tribe/events/v1/events?hide_unbookable=1`

= What is the exact syntax for the batch add-to-cart URL? =
You can combine single products and specific quantities separated by commas:
`https://yourdomain.com`
In this example, 2 items of product 123, 1 item of product 456, and 1 default item of product 789 will be placed in the cart simultaneously before redirecting to the cart page.

= Does this plugin hit the database heavily during API requests? =
No. Every calculations per Event ID is safely cached using the native WordPress Object Cache system under the `dwp_event_sync` cache group. This ensures zero redundant database load during batch list operations.

== Changelog ==

= 1.0.0 =
* Initial modular release optimized for PHP 8.4+.
* Implemented clean namespaces, strict type-hinting, and PSR-4 compliant autoloader.
* Added `ticket_min_price` array mapping for flexible tiered ticket rates.
* Refactored query filtering logic to use the `hide_unbookable=1` parameter covering the 'past', 'canceled', and 'postponed' event status types.
* Upgraded batch cart system to handle clean array structures and single item custom quantities.
