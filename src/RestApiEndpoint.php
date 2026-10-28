<?php
/**
 * Unified REST API Plural Endpoints and Service Layer Engine for Remote Ticket Blocks.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 * @since   2.0.0
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestApiEndpoint
 *
 * Orchestrates the data-layer middleware service layer for synchronizing external events and tickets.
 * Exposes a strict REST-compliant plural workspace path routing strategy exclusively under the
 * '/events' resource collection namespace, matching industry standards like WordPress Core.
 *
 * ARCHITECTURAL DESIGN INTENT:
 * This component resolves the critical "Ticket-Data Gap" found in native calendar APIs. Instead of splitting
 * data lookups into multiple slow HTTP requests, this middleware acts as an aggregation engine. It executes
 * high-performance lookups, maps native calendar objects directly with backend WooCommerce ticket stock arrays,
 * overrides known native translation/pricing engine glitches, and delivers a consolidated JSON payload.
 *
 * DATA ACCESSIBILITY LOGIC (Convention over Configuration):
 * By default, all pipeline processing loops enforce a pristine, bookable-only filter framework. Events that are
 * closed, past, hidden, or devoid of active 'for-sale' tickets are dynamically intercepted and dropped.
 * To preserve clean interface readability for frontend developers and Gutenberg integrations, a positive-action
 * query variable (?show_unbookable=1) is exposed externally, which this class automatically maps internally
 * to its corresponding inverse negative boolean verification flag ($hide_unbookable).
 *
 * ROUTING MAP SCHEMAS:
 * 1. Plural Collection: /wp-json/dewitteprins/remote-tickets/v1/events
 *    - Strict Discovery Mode: Evaluated when 'exact_date_match=1' is supplied along with strict date criteria.
 *      Performs an exact meta-query and precise title match to localize and verify a unique shadow-event.
 *      Returns an array containing exactly 1 item, or up to 2 items in case of a 409 database conflict.
 *    - Listings Query Mode: Evaluated as a horizontal timeline fallback. Filters upcoming future allocations
 *      by category slug criteria, optional partial keywords title lookup ('s'), and maximum threshold limits.
 * 2. Singular Entity Resource: /wp-json/dewitteprins/remote-tickets/v1/events/{id}
 *    - Performs direct, rapid lookups targeting explicit database post IDs, maximizing WordPress Object Cache structures.
 * 3. Event Categories Directory: /wp-json/dewitteprins/remote-tickets/v1/event-categories
 *    - Delivers an ultralight dictionary payload containing visual names and sanitized slugs of 'tribe_events_cat'
 *      taxonomies, eliminating payload bloating inside React SelectControl dropdown elements.
 *
 * @since 2.0.0
 */
class RestApiEndpoint {

	/**
	 * Private constructor to explicitly prevent manual instantiation.
	 *
	 * Enforces a strict, decoupled static utility and routing service architecture.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {}

	/**
	 * Hooks the route registration mechanics cleanly into the WordPress core REST framework lifecycle.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init(): void {
		\add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Registers the unified plural API patterns within the active WordPress REST manager.
	 *
	 * Defines validation constraints, parameters type-safety blocks, and hooks endpoints into the ecosystem.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function register_routes(): void {
		// Route 1: Plural Events Collection Endpoint (Handles edit.js parameter discovery AND horizontal list aggregation).
		\register_rest_route( 'dewitteprins/remote-tickets/v1', '/events', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_events_collection_request' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'limit' => [
					'validate_callback' => function( $param ) {
						return empty( $param ) || \is_numeric( $param );
					}
				],
			],
		] );

		// Route 2: Singular Event Entity Resource Endpoint (Handles rapid direct unique ID lookups from the client frontend).
		\register_rest_route( 'dewitteprins/remote-tickets/v1', '/events/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_event_by_id_callback' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'validate_callback' => function( $param ) {
						return \is_numeric( $param );
					}
				],
			],
		] );

		// Route 3: Proprietary Event Categories Downloader Endpoint (Exclusively feeds the Gutenberg UI dropdown component).
		\register_rest_route( 'dewitteprins/remote-tickets/v1', '/event-categories', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_remote_event_categories_callback' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * REST API Callback for Route 3: Extracts a clean directory of active Event Categories.
	 *
	 * Decouples 'tribe_events_cat' taxonomies from overhead fields, delivering optimized name-slug pairs.
	 *
	 * @since 2.0.0
	 * @return \WP_REST_Response Standard REST response containing the taxonomy payload.
	 */
	public static function get_remote_event_categories_callback(): \WP_REST_Response {
		$terms = \get_terms( [
			'taxonomy'   => 'tribe_events_cat', // Strictly isolate proprietary TEC taxonomy.
			'hide_empty' => false,              // Keep items visible to allow selections within empty editor environments.
		] );

		if ( \is_wp_error( $terms ) || empty( $terms ) ) {
			return new \WP_REST_Response( [], 200 );
		}

		$payload = [];
		foreach ( $terms as $term ) {
			$payload[] = [
				'name' => \html_entity_decode( $term->name ), // Preserves pristine accent characters across environments.
				'slug' => \sanitize_title( $term->slug ),     // Guaranteed query key format matching backend loops.
			];
		}

		return new \WP_REST_Response( $payload, 200 );
	}

	/**
	 * REST API Collection Orchestrator: Branches incoming requests to the corresponding search or listing engine.
	 *
	 * Intercepts query variables and maps positive user-facing parameters safely to negative core filters.
	 *
	 * @since 2.0.0
	 * @param \WP_REST_Request $request Current incoming HTTP REST request container object.
	 * @return \WP_REST_Response|\WP_Error Fully compiled REST response package, or an error with an HTTP status.
	 */
	public static function handle_events_collection_request( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$title       = $request->get_param( 'title' );
		$start_date  = $request->get_param( 'start_date' );
		$end_date    = $request->get_param( 'end_date' );
		$exact_match = ( '1' === $request->get_param( 'exact_date_match' ) );

		// Global show/hide modifier mapping
		$hide_unbookable = true;
		if ( '1' === $request->get_param( 'show_unbookable' ) ) {
			$hide_unbookable = false;
		}

		// STRICT DISCOVERY SWITCH: Trigger ONLY when exact_date_match=1 is explicitly passed.
		if ( $exact_match ) {
			// HARD GUARD: If the contract parameters are missing for this mode, return a 400 Bad Request immediately.
			if ( empty( $title ) || empty( $start_date ) || empty( $end_date ) ) {
				return new \WP_Error(
					'remote_event_invalid_parameters',
					'Missing required framework parameters (title, start_date, end_date) for exact date matching mode.',
					[ 'status' => 400 ]
				);
			}

			return self::execute_event_discovery_search( $request, $hide_unbookable );
		}

		// DYNAMIC LISTINGS FALLBACK (Mode B): Capture range limits and pass to horizontal listing core.
		$filters = [
			'category'        => $request->get_param( 'category' ),
			'title'           => $title,
			'start_date'      => $start_date,
			'end_date'        => $end_date,
			'limit'           => $request->get_param( 'limit' ),
			'hide_unbookable' => $hide_unbookable,
		];

		$data = self::get_internal_events_list( $filters );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Internal Core Engine: Runs strict exact evaluation queries for unique discovery matching (Mode A).
	 *
	 * Bounds query execution strictly to 2 posts to allow client duplicate inspection as documented.
	 *
	 * @since 2.0.0
	 * @param \WP_REST_Request $request         Current incoming HTTP REST request containing query criteria.
	 * @param bool             $hide_unbookable Evaluated boolean flag controlling unbookable data extraction.
	 * @return \WP_REST_Response|\WP_Error Normalized REST response containing the matched event, or a 404/409 error.
	 */
	private static function execute_event_discovery_search( \WP_REST_Request $request, bool $hide_unbookable ): \WP_REST_Response|\WP_Error {
		$title      = $request->get_param( 'title' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$args = [
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => -1, // Few events share exact timestamps; the title match below narrows them down.
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_EventStartDate', 'value' => \sanitize_text_field( $start_date ), 'compare' => '=' ],
				[ 'key' => '_EventEndDate',   'value' => \sanitize_text_field( $end_date ),   'compare' => '=' ],
			],
		];

		$query = new \WP_Query( $args );

		// Enforce an exact title match for Mode A. Compared in PHP on normalized titles, because clients send
		// either the raw title (block editor) or the rendered get_the_title() output (texturized, entity-encoded).
		$wanted_title = self::normalize_title( (string) $title );
		$events       = \array_values( \array_filter( $query->posts, function( $event_post ) use ( $wanted_title ) {
			return self::normalize_title( $event_post->post_title ) === $wanted_title;
		} ) );

		// Return up to 2 items to let the client inspect duplicates without overhead.
		$events = \array_slice( $events, 0, 2 );

		if ( empty( $events ) ) {
			return new \WP_Error(
				'remote_event_not_found',
				'Target shadow event not found on this server matching the exact title and timestamps.',
				[ 'status' => 404 ]
			);
		}

		$response_payload = [];
		foreach ( $events as $event_post ) {
			$ticket_response = self::compile_event_tickets_response( (int) $event_post->ID, $hide_unbookable );
			$response_payload[] = $ticket_response->get_data();
		}

		// If duplicate conflicts exist, return a HTTP 409 Conflict with the data payload mapped inside error data.
		if ( \count( $events ) > 1 ) {
			return new \WP_Error(
				'remote_event_duplicate_conflict',
				'Database integrity conflict: Multiple identical remote events discovered matching these timestamps.',
				[
					'status'    => 409,
					'conflicts' => $response_payload,
				]
			);
		}

		return new \WP_REST_Response( $response_payload, 200 );
	}

	/**
	 * Normalizes an event title for exact comparison.
	 *
	 * Decodes HTML entities and applies the same typography as get_the_title() (wptexturize), so a raw
	 * stored title and its rendered counterpart (e.g. '-' versus '&#8211;') compare as equal.
	 *
	 * @since 2.0.2
	 * @param string $title Raw or rendered event title.
	 * @return string Normalized title.
	 */
	private static function normalize_title( string $title ): string {
		$title = \html_entity_decode( \wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = \html_entity_decode( \wptexturize( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return \trim( $title );
	}

	/**
	 * REST API Callback for Route 2: Direct lookup via explicit post ID path segment.
	 *
	 * @since 2.0.0
	 * @param \WP_REST_Request $request Current incoming HTTP REST request container with validated ID segment.
	 * @return \WP_REST_Response|\WP_Error Fully compiled REST payload for the event, or a 404 error.
	 */
	public static function get_event_by_id_callback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$event_id   = (int) $request['id'];
		$event_post = \get_post( $event_id );

		if ( ! $event_post || 'tribe_events' !== $event_post->post_type || 'publish' !== $event_post->post_status ) {
			return new \WP_Error( 'remote_event_not_found', 'Target event not found.', [ 'status' => 404 ] );
		}

		$hide_unbookable = true;
		if ( '1' === $request->get_param( 'show_unbookable' ) ) {
			$hide_unbookable = false;
		}

		return self::compile_event_tickets_response( $event_id, $hide_unbookable );
	}

	/**
	 * THE INTERNAL PHP CORE MOTOR: Unpacks and filters horizontal timelines arrays (Mode B / Local PHP).
	 *
	 * @since 2.0.0
	 * @param array $filters Matrix array grid configuring runtime variables (category, title, limit, hide_unbookable).
	 * @return array Multi-dimensional layout data tree compiled from database entities.
	 */
	public static function get_internal_events_list( array $filters = [] ): array {
		$category_slug   = $filters['category'] ?? '';
		$search_title    = $filters['title'] ?? '';
		$limit           = isset( $filters['limit'] ) ? (int) $filters['limit'] : 5;
		$hide_unbookable = isset( $filters['hide_unbookable'] ) ? (bool) $filters['hide_unbookable'] : true;

		$range_start = ! empty( $filters['start_date'] ) ? \sanitize_text_field( $filters['start_date'] ) : \current_time( 'mysql' );
		$range_end   = ! empty( $filters['end_date'] ) ? \sanitize_text_field( $filters['end_date'] ) : '';

		$meta_query = [ 'relation' => 'AND' ];

		if ( ! empty( $range_end ) ) {
			$meta_query[] = [ 'key' => '_EventStartDate', 'value' => [ $range_start, $range_end ], 'compare' => 'BETWEEN', 'type' => 'DATETIME' ];
		} else {
			$meta_query[] = [ 'key' => '_EventStartDate', 'value' => $range_start, 'compare' => '>=', 'type' => 'DATETIME' ];
		}

		$args = [
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => $hide_unbookable ? -1 : $limit,
			'meta_key'       => '_EventStartDate',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		];

		if ( ! empty( $category_slug ) ) {
			$args['tax_query'] = [
				[ 'taxonomy' => 'tribe_events_cat', 'field' => 'slug', 'terms' => \sanitize_text_field( $category_slug ) ],
			];
		}

		if ( ! empty( $search_title ) ) {
			$args['s'] = \sanitize_text_field( $search_title );
		}

		$query  = new \WP_Query( $args );
		$events = $query->posts;

		if ( empty( $events ) ) {
			return [];
		}

		$response_payload = [];

		foreach ( $events as $event_post ) {
			if ( $hide_unbookable && \count( $response_payload ) >= $limit ) {
				break;
			}

			$event_id    = (int) $event_post->ID;
			$ticket_data = self::compile_event_tickets_response( $event_id, $hide_unbookable )->get_data();
			if ( $hide_unbookable ) {
				if ( empty( $ticket_data['tickets'] ) ) {
					continue;
				}
				if ( \in_array( $ticket_data['event_status'], [ 'canceled', 'postponed' ], true ) ) {
					continue;
				}

				$has_active_sales = false;
				foreach ( $ticket_data['tickets'] as $compiled_ticket ) {
					if ( 'for-sale' === $compiled_ticket['status'] ) {
						$has_active_sales = true;
						break;
					}
				}

				if ( ! $has_active_sales ) {
					continue;
				}
			}

			$response_payload[] = $ticket_data;
		}

		return $response_payload;
	}

	/**
	 * CENTRAL COMPILATION MIDDLEWARE ENGINE: Merges database schemas and stock levels into a unique JSON container.
	 *
	 * Intercepts core framework matrices, prioritizes active ticket sales over generic calendar timeline
	 * statuses, resolves native framework pricing engine glitches, and appends associated categories.
	 * Guarantees a strict, identical properties schema structure across all calling endpoints.
	 *
	 * @since 2.0.0
	 * @param int  $event_id        The target core database structural entity identifier.
	 * @param bool $hide_unbookable Evaluation directive controlling whether unbookable lines are discarded.
	 * @return \WP_REST_Response Standard normalized data payload package wrapper.
	 */
	private static function compile_event_tickets_response( int $event_id, bool $hide_unbookable ): \WP_REST_Response {
		// Extract cache-backed core metadata values using the decoupled expansion module.
		$custom_fields = RestApiExtension::get_event_custom_fields( $event_id );

		$compiled_tickets = [];
		$prices            = [];
		$total_remaining   = 0;
		$has_unlimited     = false;
		$has_stock         = false;

		// Map the raw event status from custom fields (e.g. 'scheduled', 'past', 'canceled', 'postponed').
		$event_status      = $custom_fields['event_status'] ?? 'scheduled';
		$currency          = $custom_fields['ticket_currency'] ?? '€';

		// Parse stock values and process children metrics if status permits trading.
		if ( ! \in_array( $event_status, [ 'canceled', 'postponed' ], true ) && \class_exists( 'Tribe__Tickets__Tickets' ) ) {
			$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );

			if ( ! empty( $tickets ) ) {
				foreach ( $tickets as $ticket ) {
					$ticket_id = (int) $ticket->ID;

					// Enforce high-precision float casting to completely sidestep native meta price glitches.
					$price = 0.00;
					if ( isset( $ticket->cost_details['values'] ) && ! empty( $ticket->cost_details['values'] ) ) {
						$price = (float) $ticket->cost_details['values'];
					} elseif ( isset( $ticket->price ) && '' !== $ticket->price ) {
						$price = (float) $ticket->price;
					}

					// Verify inventory allocations via official native method execution.
					$raw_stock       = \method_exists( $ticket, 'stock' ) ? $ticket->stock() : '';
					$is_unlimited    = ( '' === $raw_stock || -1 === (int) $raw_stock );
					$ticket_remaining = $is_unlimited ? -1 : (int) $raw_stock;

					// Map scheduling parameters via native timeline checks.
					$is_on_sale = true;
					if ( \function_exists( 'tribe_events_ticket_is_on_sale' ) ) {
						$is_on_sale = \tribe_events_ticket_is_on_sale( $ticket );
					}

					// Define localized operational ticket status keywords.
					$ticket_status = 'for-sale';
					if ( ! $is_on_sale ) {
						$start_sale = \get_post_meta( $ticket_id, '_ticket_start_date', true );
						if ( $start_sale && \strtotime( $start_sale ) > \current_time( 'timestamp' ) ) {
							$ticket_status = 'before-sales';
						} else {
							$ticket_status = 'past-sales';
						}
					} elseif ( 0 === $ticket_remaining ) {
						$ticket_status = 'sold-out';
					}

					// HARDCODED FILTER CHECKPOINT: Drop unbookable lines immediately if restrictions apply.
					if ( $hide_unbookable && 'for-sale' !== $ticket_status ) {
						continue;
					}

					if ( 'for-sale' === $ticket_status ) {
						if ( -1 === $ticket_remaining ) {
							$has_unlimited = true;
							$has_stock     = true;
						} elseif ( $ticket_remaining > 0 && ! $has_unlimited ) {
							$has_stock        = true;
							$total_remaining += $ticket_remaining;
						}
						$prices[] = $price;
					}

					$compiled_tickets[] = [
						'product_id'  => $ticket_id,
						'name'        => \sanitize_text_field( $ticket->name ),
						'description' => \wp_kses_post( $ticket->description ), // Strips malicious runtime injection patterns while keeping formatting intact.
						'price'       => $price,
						'remaining'   => $ticket_remaining,
						'status'      => $ticket_status,
					];
				}

				// COMMERCE OVERRIDE: If the event status was chronologically resolved as 'past' or 'sales-closed',
				// but active 'for-sale' tickets survived inside the pool, force the status to reflex active trading.
				if ( ! empty( $compiled_tickets ) && $has_stock ) {
					if ( \in_array( $event_status, [ 'past', 'sales-closed' ], true ) ) {
						$event_status = 'for-sale';
					}
				}

				// Fallback structural modifier adjustments based on remaining active matrix context.
				if ( 'scheduled' === $event_status && empty( $compiled_tickets ) ) {
					$event_status = 'sales-closed';
				} elseif ( 'scheduled' === $event_status && ! $has_stock && ! $has_unlimited ) {
					$event_status = 'sold-out';
				}
			}
		}

		// Implement meta fallback pricing algorithms if native system tickets are unassigned.
		if ( empty( $prices ) && \function_exists( 'tribe_get_cost' ) ) {
			$manual_cost = \get_post_meta( $event_id, '_EventCost', true );
			if ( '' !== $manual_cost ) {
				$prices[] = (float) $manual_cost;
			}
		}

		$min_price       = ! empty( $prices ) ? \min( $prices ) : null;
		$final_remaining = $has_unlimited ? -1 : $total_remaining;
		if ( \in_array( $event_status, [ 'canceled', 'postponed' ], true ) ) {
			$final_remaining = 0;
		}

		// UNIFIED METADATA: Gather core event taxonomies.
		$categories = [];
		$terms      = \get_the_terms( $event_id, 'tribe_events_cat' );
		if ( ! empty( $terms ) && ! \is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = [
					'term_id' => $term->term_id,
					'name'    => \html_entity_decode( $term->name ),
					'slug'    => $term->slug,
				];
			}
		}

		// ASSEMBLE UNIVERSAL OBJECT FIELDS GRID:
		$response_data = [
			'event_id'          => $event_id,
			'event_title'       => \get_the_title( $event_id ),
			'event_url'         => \get_permalink( $event_id ),
			'event_start_date'  => \get_post_meta( $event_id, '_EventStartDate', true ),
			'event_end_date'    => \get_post_meta( $event_id, '_EventEndDate', true ),
			'event_categories'  => $categories,
			'event_status'      => $event_status,
			'ticket_min_price'  => $min_price,
			'ticket_currency'   => \sanitize_text_field( $currency ),
			'tickets_remaining' => $final_remaining,
			'tickets'           => $compiled_tickets,
		];

		return new \WP_REST_Response( $response_data, 200 );
	}
}
