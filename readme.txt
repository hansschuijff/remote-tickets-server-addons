=== Remote Tickets Server Add-ons ===
Contributors: hansschuijff
Tags: the-events-calendar, woocommerce, rest-api, event-tickets, autoload
Requires at least: 7.0
Tested up to: 7.0.4
Requires PHP: 8.4
Stable tag: 1.2.0
License: GPL-2.0
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends The Events Calendar REST API with event status, custom ticket metrics and enhances WooCommerce with URL-based batch add-to-cart functionality.

== Description ==

Remote Tickets Server Add-ons is a powerful, lightweight, and object-oriented developer extension tailored for hybrid ticket setups. It seamlessly bridge gaps in the default REST API payloads of The Events Calendar and eliminates the native single-item restriction of the WooCommerce cart handler.

The main reason for this plugin is the need for a remote tickets block that is filled with data from a remote events calendar and tickets. It adds some base Rest API data and woocommerce functionality for the proper functioning of the remote tickets for The events calendar client plugin.

== Features ==
* **Atomic Event & Ticket Endpoint:** Registers a dedicated, high-performance route (`dewitteprins/remote-tickets/v1/event`) that delivers combined event data and ticket metrics in a single network request, drastically reducing client loading times.
* **Deterministic Event Statuses:** Dynamically resolves the `event_status` field if no custom database status is filled in by the content creator:
    * `hidden`: Triggered when an event is manually hidden from upcoming overviews (primarily acts as a safeguard for single event requests, as archive lists typically omit these automatically).
    * `past`: Triggered when the event end date and time have passed.
    * `sales-closed`: Triggered when Event Tickets (Plus) is active and tickets exist, but the end-date of sales has passed for all tickets.
    * `sold-out`: Triggered when Event Tickets (Plus) is active and tickets are still for sale, but there is no remaining stock for any ticket.
    * `scheduled`: The default fallback state if no other state is active or filled in.
* **Smart Filtering & Scoping:** Extends the custom query parameter `&hide_unbookable=1` across endpoints. It automatically filters out non-bookable event states and dynamically purges unavailable tickets (`past-sales`, `before-sales`, `sold-out`) from the payload based strictly on their commercial availability.
* **WooCommerce Batch Add-to-Cart:** Overrides standard handlers to support complex, reliable multi-product queries via URL. Allows structured syntax like `?add-to-cart=101:2,102:5` (one or more comma-separated pairs of product-id:quantity) for instant batch additions.
* **Modern Architecture:** Built entirely according to PSR-4 autoloading standards, fully type-hinted, and strictly optimized for PHP 8.4+ and WordPress 7.0+ performance with core Object Cache mapping.

== Installation ==

1. Upload the `remote-tickets-server-addons` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API clients or theme URLs to use the new endpoints and query variables.

== Frequently Asked Questions ==

= How do I hide unbookable events and unavailable tickets from my API requests? =
For filtering raw event archives, append the query parameter to the native endpoint:
`/wp-json/tribe/events/v1/events?hide_unbookable=1`

To automatically purge expired (`past-sales`), pending (`before-sales`), or depleted (`sold-out`) tickets from a single event payload, append it to our custom atomic endpoint:
`/wp-json/dewitteprins/remote-tickets/v1/event?title=Event+Title&start_date=2026-09-13+11:00:00&end_date=2026-09-13+17:00:00&hide_unbookable=1`

= What is the exact syntax for the batch add-to-cart URL? =
You can combine multiple custom product IDs and specific quantities separated by commas using the following structure:
`https://yourdomain.com/cart/?add-to-cart=123:2,456,789:1`

In this exact example, 2 items of product 123, 1 item of product 456, and 1 item of product 789 will be placed in the WooCommerce cart simultaneously before instantly redirecting the visitor to the checkout cart page.

Each pair of product-id and order amount is separated by commas and the product-id and optional order amount (default=1) are separated by a colon.

= Does this plugin hit the database heavily during API requests? =
No. All custom calculations per Event ID are safely cached using the native WordPress Object Cache framework under the transience-backed `dwp_event_sync` cache group. This ensures minimal database overhead and blazing fast loading times even during intensive remote batch operations.

== API Reference ==

= Custom Event & Tickets Endpoint =
This plugin registers a highly optimized, high-precision endpoint tailored to feed real-time transactional data to remote Gutenberg blocks. It strictly queries published events and bypasses draft/concept clutter.

* **Route:** `/wp-json/dewitteprins/remote-tickets/v1/event`
* **Method:** `GET`
* **Permission:** Public (`__return_true`)

### Query Parameters
* `title` (string, required): The exact title of the event matching the source database.
* `start_date` (string, required): The exact event start datetime string (`YYYY-MM-DD HH:MM:SS`).
* `end_date` (string, required): The exact event end datetime string (`YYYY-MM-DD HH:MM:SS`).
* `hide_unbookable` (integer, optional): Pass `1` to systematically purge expired (`past-sales`), pending (`before-sales`), or depleted (`sold-out`) tickets from the payload. Ideal for live production frontends.

### Example Request
`GET https://yourdomain.com`

### Expected JSON Response Structures

**Success (200 OK) - Event found with tickets:**
```json
{
  "event_id": 1024,
  "event_status": "scheduled",
  "ticket_min_price": 45.00,
  "ticket_currency": "€",
  "tickets_remaining": 108,
  "tickets": [
    {
      "product_id": 2048,
      "name": "Deelnemer / Representant",
      "description": "Deelname aan de familieopstelling zonder eigen vraag.",
      "price": 45.00,
      "remaining": 99,
      "status": "for-sale"
    },
    {
      "product_id": 2049,
      "name": "Vraagsteller",
      "description": "Inbreng van een eigen persoonlijke casus.",
      "price": 105.00,
      "remaining": 9,
      "status": "for-sale"
    }
  ]
}
```

**Ticket Status Matrix values (`tickets[].status`):**
* `for-sale`: Open for checkout. Quantities align with `remaining` (or `-1` for unlimited).
* `before-sales`: Sales window has not opened yet.
* `past-sales`: Sales window has closed based on time rules.
* `sold-out`: Ticket inventory is strictly depleted (`0`).

**Error States:**
* `400 Bad Request`: Returned if `title`, `start_date`, or `end_date` is missing.
* `404 Not Found`: Returned if no published event matches the criteria.
* `409 Conflict`: Returned if duplicate identical events are found on the server.

== Changelog ==

= 1.2.0 =
* Feature: Introduced a dedicated atomic REST API endpoint (`dewitteprins/remote-tickets/v1/event`) specifically optimized for the Remote Tickets Gutenberg block client.
* Optimization: Combined event status, ticket stock, and WooCommerce pricing rules into a single, high-performance database request.
* Bugfix: Resolved native Event Tickets Plus dollar-currency bug by safely extracting clean prices from WooCommerce cost details.
* Bugfix: Patched individual ticket inventory mapping to natively support unlimited capacities (-1) and manual event cancellations.

= 1.1.0 =
* Adds query filtering (an `exact_date_match=1` parameter) that forces tribes events Rest api endpoint to use start_date and end_date as exact matches, hiding all events that don't exactly match those start and end timestamp.
* Renames CartExtension class to WooCartExtension to explicitely link it to the woocommerce shopcart.

= 1.0.0 =
* Initial production release, optimized for PHP 8.4+.
* Implemented clean namespaces, strict type-hinting, and PSR-4 compliant autoloader.
* Added `ticket_min_price` array mapping for flexible tiered ticket rates.
* Refactored query filtering logic to use the `hide_unbookable=1` parameter covering the 'past', 'canceled', and 'postponed' event status types.
* Upgraded batch cart system to handle clean array structures and single item custom quantities.
