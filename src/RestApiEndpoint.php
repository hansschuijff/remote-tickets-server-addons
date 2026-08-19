<?php
/**
 * The RestApiEndpoint class creates a new dedicated endpoint to be used
 * by the Remote tickets for The Events Calendar client Gutenberg block plugin.
 *
 * example:
 * /wp-json/dewitteprins/remote-tickets/v1/event/?title={event.title}&start_date={event.start_date}&end_date={event.end_date}
 *
 * The new endpoint offers event and tickets data combined.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestApiEndpoint
 * Registers a dedicated custom REST API route optimized for the Gutenberg client.
 */
class RestApiEndpoint {

	/**
	 * Private constructor to enforce static orchestration and prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Hooks the route registration logic into the WordPress REST framework.
	 */
	public static function init() {
		\add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Registers the vendor-namespaced route pattern.
	 */
	public static function register_routes() {
		\register_rest_route( 'dewitteprins/remote-tickets/v1', '/event', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_event_tickets_data' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Processes the incoming request for an unique event, and builds
	 * summarized data that can be used to offer tickets on remote websites.
	 *
	 * @param \WP_REST_Request $request The current incoming REST request object.
	 * @return \WP_REST_Response The fully compiled and filtered REST response container.
	 */
	public static function get_event_tickets_data( \WP_REST_Request $request ) {
		$title           = $request->get_param( 'title' );
		$start_date      = $request->get_param( 'start_date' );
		$end_date        = $request->get_param( 'end_date' );
		$hide_unbookable = ( isset( $request['hide_unbookable'] ) && '1' === $request['hide_unbookable'] );

		if ( empty( $title ) || empty( $start_date ) || empty( $end_date ) ) {
			return new \WP_REST_Response( [ 'message' => 'Incomplete parameter framework.' ], 400 );
		}

		// Query for the singular active publish post matching the required criteria.
		$args = [
			'post_type'   => 'tribe_events',
			'post_status' => 'publish',
			'title'       => $title,
			'meta_query'  => [
				'relation' => 'AND',
				[
					'key'     => '_EventStartDate',
					'value'   => $start_date,
					'compare' => '=',
				],
				[
					'key'     => '_EventEndDate',
					'value'   => $end_date,
					'compare' => '=',
				],
			],
		];

		$query  = new \WP_Query( $args );
		$events = $query->posts;

		if ( empty( $events ) ) {
			return new \WP_REST_Response( [ 'message' => 'Target event not found.' ], 404 );
		}

		if ( \count( $events ) > 1 ) {
			return new \WP_REST_Response( [ 'message' => 'Duplicate matching events discovered.' ], 409 );
		}

		$event_obj = $events[0];
		$event_id  = $event_obj->ID;

		// Extract cache-backed core metadata values using the decoupled expansion module.
		$custom_fields = RestApiExtension::get_event_custom_fields( $event_id );

		$compiled_tickets = [];
		$prices            = [];
		$total_remaining   = 0;
		$has_unlimited     = false;
		$has_stock         = false;
		$event_status      = $custom_fields['event_status'] ?? 'scheduled';
		$currency          = $custom_fields['ticket_currency'] ?? '€';

		// Intercept and loop through tickets only if the global status permits sales.
		if ( ! \in_array( $event_status, [ 'canceled', 'postponed' ], true ) && \class_exists( 'Tribe__Tickets__Tickets' ) ) {
			$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );

			if ( ! empty( $tickets ) ) {
				foreach ( $tickets as $ticket ) {
					$ticket_id = (int) $ticket->ID;

					// Isolate pure price from cost_details to systematically bypass native bugs.
					$price = 0.00;
					if ( isset( $ticket->cost_details['values'] ) && ! empty( $ticket->cost_details['values'] ) ) {
						$price = (float) $ticket->cost_details['values'];
					} elseif ( isset( $ticket->price ) && '' !== $ticket->price ) {
						$price = (float) $ticket->price;
					}

					// Fetch stock dynamically using the official native method ->stock()
					$raw_stock       = \method_exists( $ticket, 'stock' ) ? $ticket->stock() : '';
					$is_unlimited    = ( '' === $raw_stock || -1 === (int) $raw_stock );
					$ticket_remaining = $is_unlimited ? -1 : (int) $raw_stock;

					// If the global event status is canceled/postponed, force the stock array to 0.
					if ( \in_array( $event_status, [ 'canceled', 'postponed' ], true ) ) {
						$ticket_remaining = 0;
					}

					// Evaluate ticket sales window using the reliable core function
					$is_on_sale = true;
					if ( \function_exists( 'tribe_events_ticket_is_on_sale' ) ) {
						$is_on_sale = \tribe_events_ticket_is_on_sale( $ticket );
					}

					// Map the high-precision English status strings.
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

					// Compile international stock aggregates from verified sales channels.
					if ( $ticket_status === 'for-sale' ) {
						if ( -1 === $ticket_remaining ) {
							$has_unlimited = true;
							$has_stock     = true;
						} elseif ( $ticket_remaining > 0 && ! $has_unlimited ) {
							$has_stock        = true;
							$total_remaining += $ticket_remaining;
						}

						// Only collect the price for the global minimum calculation if the ticket is genuinely 'for-sale'
						$prices[] = $price;
					}

					// OPTION: If requested by the client, purge unavailable tickets from the array.
					if ( $hide_unbookable && $ticket_status !== 'for-sale' ) {
						continue;
					}

					$compiled_tickets[] = [
						'product_id'  => $ticket_id,
						'name'        => $ticket->name,
						'description' => $ticket->description,
						'price'       => $price,
						'remaining'   => $ticket_remaining,
						'status'      => $ticket_status,
					];
				}

				// Fallback overrides for global event metrics.
				if ( 'scheduled' === $event_status ) {
					if ( empty( $compiled_tickets ) ) {
						$event_status = 'sales-closed';
					} elseif ( ! $has_stock && ! $has_unlimited ) {
						$event_status = 'sold-out';
					}
				}
			}
		}

		// Fallback to manual pricing structures if zero native tickets are deployed or active.
		if ( empty( $prices ) && \function_exists( 'tribe_get_cost' ) ) {
			$manual_cost = \get_post_meta( $event_id, '_EventCost', true );
			if ( '' !== $manual_cost ) {
				$prices[] = (float) $manual_cost;
			}
		}

		// Calculate final atomic metrics.
		$min_price       = ! empty( $prices ) ? \min( $prices ) : null;
		$final_remaining = $has_unlimited ? -1 : $total_remaining;
		if ( \in_array( $event_status, [ 'canceled', 'postponed' ], true ) ) {
			$final_remaining = 0;
		}

		// Assemble the complete atomic response payload.
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
