=== Remote Tickets Server Add-ons ===
Contributors: hansschuijff
Tags: the-events-calendar, woocommerce, rest-api, event-tickets, autoload
Requires at least: 7.0
Tested up to: 7.0.4
Requires PHP: 8.4
Stable tag: 2.0.0
License: GPL-2.0
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offers custom REST API endpoints for the Remote Tickets Blocks plugin, in order to retrieve basic event and ticket data from The Events Calendar and Event Tickets (Plus) plugin and enable remote event ticket lists and tickets blocks.

It also enhances WooCommerce with URL-based batch add-to-cart functionality, enabling adding more than one product in the cart via a single url.

== Description ==

Remote Tickets Server Add-ons is a powerful, lightweight, and object-oriented developed extension tailored for hybrid ticket setups. It seamlessly bridge gaps in the default REST API payloads of The Events Calendar, by offering custom endpoints, and eliminates the native single-item restriction of the WooCommerce cart handler.

The main reason for this plugin is the need for a remote tickets block that is filled with data from a remote events calendar and tickets. It adds some base Rest API data and woocommerce functionality for the proper functioning of the remote tickets for The events calendar client plugin.

Although at this point only used for a single event remote tickets block, the custom endpoint supports the need for list building and future block development.

== Features ==
* **Atomic Event & Ticket Endpoint:** Registers a dedicated, high-performance route (`dewitteprins/remote-tickets/v1/events`) that delivers combined event data and ticket metrics in a single network request, drastically reducing client loading times.
* **Deterministic Event Statuses:** Dynamically resolves the `event_status` field if no custom database status is filled in by the content creator:
    * `hidden`: Triggered when an event is manually hidden from upcoming overviews.
    * `past`: Triggered when the event end date and time have passed.
    * `started`: Triggered when the event start date and time have passed.
    * `sales-closed`: Triggered when Event Tickets (Plus) is active and tickets exist, but the end-date of sales has passed for all tickets.
    * `sold-out`: Triggered when Event Tickets (Plus) is active and tickets are still for sale, but there is no remaining stock for any ticket.
    * `scheduled`: The default fallback state if no other state is active or filled in.
* **Smart Filtering & Scoping:** The endpoints automatically filter out non-bookable event states and dynamically purges unavailable tickets (`past-sales`, `before-sales`, `sold-out`) from the payload based strictly on their commercial availability. This filtering can be stopped by adding the custom query parameter `&show_unbookable=1` across endpoints.
* **WooCommerce Batch Add-to-Cart:** Overrides standard handlers to support complex, reliable multi-product queries via URL. Allows structured syntax like `?add-to-cart=101:2,102:5` (one or more comma-separated pairs of product-id:quantity) for instant batch additions.
* **Modern Architecture:** Built entirely according to PSR-4 autoloading standards, fully type-hinted, and strictly optimized for PHP 8.4+ and WordPress 7.0+ performance with core Object Cache mapping.

== Installation ==

1. Upload the `remote-tickets-server-addons` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API clients or theme URLs to use the new endpoints and query variables.

== Frequently Asked Questions ==

= How do I hide unbookable events and unavailable tickets from my API requests? =
As of version 2.0.0 the hide_unbookable=1 filter is automatically and implicitely applied, automatically purging expired (`past-sales`), pending (`before-sales`), or depleted (`sold-out`) tickets from the event(s) payload.

To reverse that behaviour and also get unbookable events and -tickets in the RestAPI response, simply add the query parameter `show_unbookable=1` to the native endpoint:
`/wp-json/tribe/events/v1/events?show_unbookable=1`

= What is the exact syntax for the batch add-to-cart URL? =
You can combine multiple custom product IDs and specific quantities separated by commas using the following structure:
`https://yourdomain.com/cart/?add-to-cart=123:2,456,789:1`

In this exact example, 2 items of product 123, 1 item of product 456, and 1 item of product 789 will be placed in the WooCommerce cart simultaneously before instantly redirecting the visitor to the checkout cart page.

Each pair of product-id and order amount is separated by commas and the product-id and optional order amount (default=1) are separated by a colon.

= Does this plugin hit the database heavily during API requests? =
No. All custom calculations per Event ID are safely cached using the native WordPress Object Cache framework under the transience-backed `dwp_event_sync` cache group. This ensures minimal database overhead and blazing fast loading times even during intensive remote batch operations.

== API Reference ==

This server addon registers a highly optimized, RESTful plural framework tailored to feed real-time transactional data to remote Gutenberg blocks, bypassing all native framework bottlenecks and calendar pricing bugs.

Base URI: https://{serverdomain}/wp-json/dewitteprins/remote-tickets/v1

=========================
= 1. ROUTE ARCHITECTURE =
=========================

=== A. Events Resource Collection ===
* **Route:** `/events`
* **Method:** `GET`
* **Permission:** Public (`__return_true`)

	This endpoint operates in two highly specialized logical modes based on the supplied query parameters:

*   **Mode 1: Exact Date Match (Strict Discovery / edit.js)**
	Triggered automatically when 'exact_date_match=1' is supplied. Used by the client editor to locate, identify, and verify a unique shadow-event mapping boundary.
	Required Parameters:
	* `exact_date_match` (integer): Must be strictly set to '1'.
	* `title` (string): The exact title of the event matching the source database.
	* `start_date` (string): Highly flexible datetime string (e.g., '2026-08-15 11:00' or '2026-08-15 11:00:00').
	* `end_date` (string): Highly flexible datetime string (e.g., '2026-08-15 17:00' or '2026-08-15 17:00:00').

*   **Mode 2: Dynamic Listing Aggregator (Landingspages & Overviews)**
	Triggered as a fallback when 'exact_date_match' is omitted. Returns a chronologically sorted timeline collection of upcoming events.
	* `category` (string): The exact taxonomy slug of the Event Category (e.g., 'familieopstellingen').
	* `limit` (integer): Maximum entries to return. Defaults strictly to '5'.
	* `title` (string): Partial keyword fuzzy search within the event title.
	* `start_date` (string): Custom date range start filter boundary. Defaults to NOW.
	* `end_date` (string): Custom date range end filter boundary (enables strict BETWEEN period queries).

*   **Global Visibility Modifier (Applies to both modes):**
	* `show_unbookable=1` (integer, optional): Pass '1' to disable the default filter layer and include closed, sold-out, past, or hidden entries.
	* *Default Filter Behavior:* If omitted, the engine automatically forces strict e-commerce validation. It purges any event that does not possess at least one active ticket marked as 'for-sale'.


=== B. Singular Event Resource ===
* **Route:** `/events/{id}`
* **Method:** `GET`
* **Permission:** Public (`__return_true`)

Targeted direct data lookup bypassing heavy query loops. Evaluates a single specific database post ID.
* **Optional Parameter:** Supports `?show_unbookable=1` to modify internal ticket visibility.
* Returns a single, non-nested event object `{}`.


=== C. Event Categories Directory ===
* **Route:** `/event-categories`
* **Method:** `GET`
* **Permission:** Public (`__return_true`)

Deploys a lightweight data array mapping proprietary 'tribe_events_cat' properties directly into React <SelectControl> dropdown elements inside edit.js. Returns a lightweight list containing paired "name" and "slug" string structures.


=========================
= 2. UNIVERSAL SCHEMAS  =
=========================

### Expected JSON Object Layout per Event
This 11-field properties schema is identical across all endpoints. Note: The collection endpoints (/events) return these objects nested inside an index array `[...]`, while the singular resource (/events/{id}) returns this object directly `{}`.

{
	"event_id": 1024,
	"event_title": "workshop",
	"event_url": "https://{server-domain}/event/workshop-august/",
	"event_start_date": "2026-08-15 11:00:00",
	"event_end_date": "2026-08-15 17:00:00",
	"event_categories": [
		{ "term_id": 12, "name": "workshops", "slug": "workshops" }
	],
	"event_status": "scheduled",
	"ticket_min_price": 75.00,
	"ticket_currency": "€",
	"tickets_remaining": 20,
	"tickets": [
		{
			"product_id": 2048,
			"name": "Participant",
			"description": "Participate and have fun in this workshop.",
			"price": 45.00,
			"remaining": 20,
			"status": "for-sale"
		}
	]
}

*   **Ticket Status Matrix values (`tickets[].status`):**
	`for-sale` (open for checkout), `before-sales` (pending), `past-sales` (time closed), `sold-out` (depleted).


### Universal HTTP Error States
*   `400 Bad Request`: Returned if 'exact_date_match=1' is supplied but 'title', 'start_date', or 'end_date' is missing.
*   `404 Not Found`: Returned if no active published event matches the criteria (applies to both a missing unique ID route or a failed exact discovery search match).
*   `409 Conflict`: Returned strictly in Exact Match Mode if identical duplicate entries are found. The response includes the data payload of the conflicting entities within `error.data.conflicts`, enabling the client application to react instantly.

== Changelog ==

= 2.0.0 =
* Feature: Change and rebuild the REST API endpoint (`dewitteprins/remote-tickets/v1/events`) to support event-list building and directly inquiry using event_id.
* Feature: New lightweight REST API endpoint `dewitteprins/remote-tickets/v1/event-categories` that retrieves all categories and -slugs to populate React <SelectControl> dropdown elements inside edit.js
* Removed: RestApiExtension since The Events Calendars Rest Api is no longer used for the Remote Tickets functionality.

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
