<?php
/**
 * Unified REST API Plural Endpoints and Service Layer Engine for Remote Ticket Blocks.
 *
 * Instead of using and extending the events calendar, it builds
 * entirely new endpoints specifically for the remote server needs
 * of the Remote Tickets Blocks plugin.
 *
 * It replaced the RestApiExtension use as of version 2.0.0 of that client plugin.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 * @since   3.0.0
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
 * '/events' resource collection namespace, matching industry standards like WordPress Core and WooCommerce.
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
 *    - Strict Discovery Mode: Evaluated when 'title', 'start_date', and 'end_date' parameters are present.
 *      Performs a strict, exact meta-query evaluation to localize and verify exactly 1 unique shadow-event.
 *    - Listings Query Mode: Evaluated as a horizontal timeline fallback. Filters upcoming future allocations
 *      by category slug criteria, optional partial keywords title lookup ('s'), and maximum threshold limits.
 * 2. Singular Entity Resource: /wp-json/dewitteprins/remote-tickets/v1/events/{id}
 *    - Performs direct, rapid lookups targeting explicit database post IDs, maximizing WordPress Object Cache structures.
 * 3. Event Categories Directory: /wp-json/dewitteprins/remote-tickets/v1/event-categories
 *    - Delivers an ultralight dictionary payload containing visual names and sanitized slugs of 'tribe_events_cat'
 *      taxonomies, eliminating payload bloating inside React SelectControl dropdown elements.
 *
 * @since 3.0.0
 */
class RestApiEndpoint {

	/**
	 * Private constructor to explicitly prevent manual instantiation.
	 *
	 * Enforces a strict, decoupled static utility and routing service architecture.
	 *
	 * @since 3.0.0
	 */
	private function __construct() {}

	/**
	 * Hooks the route registration mechanics cleanly into the WordPress core REST framework lifecycle.
	 *
	 * @since 3.0.0
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
	 * @since 3.0.0
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
	 * @since 3.0.0
	 * @return \WP_REST_Response Standard normalized REST response package containing the taxonomy payload.
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
	 * REST API Collection Orchestrator: Branches incoming requests safely to the corresponding core engine.
	 *
	 * Now securely bridges structural timeline parameters downstream into the query core pipeline.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Current incoming HTTP REST request container object.
	 * @return \WP_REST_Response Fully compiled REST response package processed by downstream services.
	 */
	public static function handle_events_collection_request( \WP_REST_Request $request ): \WP_REST_Response {
		$title      = $request->get_param( 'title' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );
		$exact_match = ( '1' === $request->get_param( 'exact_date_match' ) );

		$hide_unbookable = true;
		if ( '1' === $request->get_param( 'show_unbookable' ) ) {
			$hide_unbookable = false;
		}

		// STRICT DISCOVERY SWITCH OPERATION: Executed ONLY when exact_date_match=1 is explicitly passed.
		if ( $exact_match && ! empty( $title ) && ! empty( $start_date ) && ! empty( $end_date ) ) {
			return self::execute_event_discovery_search( $request, $hide_unbookable );
		}

		// DYNAMIC LISTINGS RESOLVER: Captures category, title, limits AND vertical date window boundaries.
		$filters = [
			'category'        => $request->get_param( 'category' ),
			'title'           => $title,
			'start_date'      => $start_date, // CRITICAL FIX: Bridge variables downstream into the core filter query matrix.
			'end_date'        => $end_date,   // CRITICAL FIX: Enables period range filtering across timelines.
			'limit'           => $request->get_param( 'limit' ),
			'hide_unbookable' => $hide_unbookable,
		];

		$data = self::get_internal_events_list( $filters );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Internal Core Engine: Runs a precise metadata evaluation query matching explicit title and timestamps.
	 *
	 * Returns all discovered matches inside a unified array collection layout. If zero entries match
	 * the strict criteria framework, it returns a semantic 404 response to notify the client instantly.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request         Current incoming HTTP REST request parameters block.
	 * @param bool             $hide_unbookable Resolved boolean flag controlling unbookable data extraction.
	 * @return \WP_REST_Response Plural collection formatted JSON response wrapper containing all matched entities.
	 */
	private static function execute_event_discovery_search( \WP_REST_Request $request, bool $hide_unbookable ): \WP_REST_Response {
		$title      = $request->get_param( 'title' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$args = [
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'title'          => \sanitize_text_field( $title ),
			'posts_per_page' => -1, // Fetch all matches to let the client accurately detect duplicates.
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_EventStartDate', 'value' => \sanitize_text_field( $start_date ), 'compare' => '=' ],
				[ 'key' => '_EventEndDate',   'value' => \sanitize_text_field( $end_date ),   'compare' => '=' ],
			],
		];

		$query  = new \WP_Query( $args );
		$events = $query->posts;

		// CRITICAL FIX: If no exact date match target exists in the database, return a structured 404 response framework.
		if ( empty( $events ) ) {
			return new \WP_REST_Response( [
				'code'    => 'remote_event_not_found',
				'message' => 'Target shadow event not found on this server matching the exact title and timestamps.',
				'data'    => [ 'status' => 404 ]
			], 404 );
		}

		$response_payload = [];

		// Compile the payload matrix for every found match (even duplicates).
		foreach ( $events as $event_post ) {
			$ticket_response = self::compile_event_tickets_response( (int) $event_post->ID, $hide_unbookable );
			$response_payload[] = $ticket_response->get_data();
		}

		return new \WP_REST_Response( $response_payload, 200 );
	}

	/**
	 * REST API Callback for Route 2: Processes rapid specific data delivery using direct unique URL index paths.
	 *
	 * Bypasses zillions of meta parsing constraints by tracking core object index cache structures.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Current incoming HTTP REST request containing verified numerical ID path.
	 * @return \WP_REST_Response Normalized data payload container matching the targeted post ID block.
	 */
	public static function get_event_by_id_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$event_id = (int) $request['id'];

		$event_post = \get_post( $event_id );
		if ( ! $event_post || 'tribe_events' !== $event_post->post_type || 'publish' !== $event_post->post_status ) {
			return new \WP_REST_Response( [ 'message' => 'Target event not found.' ], 404 );
		}

		// Maintain unified interface parameter mapping strategies for singular lookups as well.
		$hide_unbookable = true;
		if ( '1' === $request->get_param( 'show_unbookable' ) ) {
			$hide_unbookable = false;
		}

		return self::compile_event_tickets_response( $event_id, $hide_unbookable );
	}

	/**
	 * THE INTERNAL PHP CORE MOTOR: Retrieves a filtered timeline array of upcoming events and ticket metrics.
	 *
	 * Prioritizes active ticket sales windows over generic chronological calendar statuses.
	 * Explicitly allows events marked as past or closed to remain in the dataset if active sales exist.
	 *
	 * @since 3.0.0
	 * @param array $filters Matrix array grid configuring runtime variables (category, title, limit, hide_unbookable, start_date, end_date).
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
			$meta_query[] = [
				'key'     => '_EventStartDate',
				'value'   => [ $range_start, $range_end ],
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			];
		} else {
			$meta_query[] = [
				'key'     => '_EventStartDate',
				'value'   => $range_start,
				'compare' => '>=',
				'type'    => 'DATETIME',
			];
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
			\add_filter( 'posts_title', function( $title, $query ) use ( $search_title ) {
				global $wpdb;
				if ( $query->get( 'post_type' ) === 'tribe_events' ) {
					return $wpdb->prepare( "AND {$wpdb->posts}.post_title = %s", \sanitize_text_field( $search_title ) );
				}
				return $title;
			}, 10, 2 );
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

			// STRIKTEST SYSTEM SANITY SCREENING: Enforce bookable criteria based on TICKET availability.
			if ( $hide_unbookable ) {
				// 1. If no tickets exist or survived the filter, drop the event immediately.
				if ( empty( $ticket_data['tickets'] ) ) {
					continue;
				}

				// 2. HARD SECURITY GUARD: Intercept administrative blocks. Block ONLY genuinely dead states.
				// Past, sales-closed, or hidden events are allowed to pass if they contain active tickets.
				if ( \in_array( $ticket_data['event_status'], [ 'canceled', 'postponed' ], true ) ) {
					continue;
				}

				// 3. TRANSACTION LAYER VALIDATION: Verify at least one individual sub-ticket is actively 'for-sale'.
				$has_active_sales = false;
				foreach ( $ticket_data['tickets'] as $compiled_ticket ) {
					if ( 'for-sale' === $compiled_ticket['status'] ) {
						$has_active_sales = true;
						break;
					}
				}

				if ( ! $has_active_sales ) {
					continue; // Discard event item if all stock allocation arrays are locked.
				}
			}

			$categories = [];
			$terms      = \get_the_terms( $event_id, 'tribe_events_cat' );
			if ( ! empty( $terms ) && ! \is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = [ 'term_id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug ];
				}
			}

			$response_payload[] = [
				'event_id'          => $event_id,
				'event_title'       => \get_the_title( $event_id ),
				'event_url'         => \get_permalink( $event_id ),
				'event_start_date'  => \get_post_meta( $event_id, '_EventStartDate', true ),
				'event_end_date'    => \get_post_meta( $event_id, '_EventEndDate', true ),
				'event_categories'  => $categories,
				'event_status'      => $ticket_data['event_status'] ?? 'scheduled',
				'ticket_min_price'  => $ticket_data['ticket_min_price'] ?? null,
				'ticket_currency'   => $ticket_data['ticket_currency'] ?? '€',
				'tickets_remaining' => $ticket_data['tickets_remaining'] ?? 0,
				'tickets'           => $ticket_data['tickets'] ?? [],
			];
		}

		return $response_payload;
	}

	/**
	 * CENTRAL COMPILATION MIDDLEWARE ENGINE: Merges database schemas and stock levels into a unique JSON container.
	 *
	 * Intercepts core framework matrices and resolves native framework pricing engine glitches at the server level.
	 * Preserves strict native chronological event statuses while mapping underlying ticket arrays.
	 *
	 * @since 3.0.0
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

		// Intercept and loop through tickets only if the administrative status permits trading.
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

		// Structure final serialized schema package layout array.
		$response_data = [
			'event_id'          => $event_id,
			'event_status'      => $event_status,
			'ticket_min_price'  => $min_price,
			'ticket_currency'   => \sanitize_text_field( $currency ),
			'tickets_remaining' => $final_remaining,
			'tickets'           => $compiled_tickets,
		];

		return new \WP_REST_Response( $response_data, 200 );
	}
}
