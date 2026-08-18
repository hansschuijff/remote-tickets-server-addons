<?php
/**
 * REST API Extension for The Events Calendar.
 *
 * Manages the hooks, filters, and actions required to extend The Events Calendar
 * REST API responses with custom event statuses and ticket metrics.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestApiExtension
 *
 * Extends The Events Calendar REST API by providing custom ticket metrics,
 * real-time availability calculations, and event archiving filters.
 */
class RestApiExtension {

	/**
	 * Private constructor to prevent instantiation.
	 *
	 * Enforces the usage of this class as a purely static utility class.
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {}

	/**
	 * Initializes the REST API extensions.
	 *
	 * Hooks the class methods into WordPress and The Events Calendar
	 * REST API filters and actions to modify event data and register
	 * custom fields.
	 *
	 * @return void
	 */
	public static function init(): void {
		\add_filter( 'tribe_rest_events_archive_data', [ __CLASS__, 'filter_tribe_rest_events_archive_data' ], 10, 2 );
		\add_filter( 'tribe_rest_single_event_data', [ __CLASS__, 'add_custom_fields_to_event' ] );
		\add_action( 'rest_api_init', [ __CLASS__, 'register_custom_fields' ] );
	}

	/**
	 * Registers the custom ticket and status fields for the Events Calendar post type.
	 *
	 * Extends the default 'tribe_events' post type schema by registering custom fields.
	 * Each field utilizes get_event_custom_fields() to resolve its value dynamically.
	 *
	 * @return void
	 */
	public static function register_custom_fields(): void {
		$fields = [ 'event_status', 'ticket_min_price', 'ticket_currency', 'tickets_remaining' ];

		foreach ( $fields as $field ) {
			\register_rest_field( 'tribe_events', $field, [
				'get_callback' => function( array $object ) use ( $field ): mixed {
					$event_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
					$data     = self::get_event_custom_fields( $event_id );

					return ( $data && isset( $data[ $field ] ) ) ? $data[ $field ] : null;
				},
				'schema' => [
					'type' => ( 'tickets_remaining' === $field ) ? 'integer' : ( ( 'ticket_min_price' === $field ) ? 'number' : 'string' )
				]
			] );
		}
	}

	/**
	 * Filters the event archive data by enriching events and removing unbookable ones if requested.
	 *
	 * Iterates through the Events Calendar REST API archive events, appends custom fields,
	 * and filters out entries based on the 'hide_unbookable' request parameter.
	 *
	 * @param array $api_data The raw REST API response data containing a list of events.
	 * @param mixed $request  The REST request object holding the query parameters.
	 * @return array The filtered and enriched REST API response data.
	 */
	public static function filter_tribe_rest_events_archive_data( array $api_data, mixed $request ): array {
		// Return early if no events array is present.
		if ( ! isset( $api_data['events'] ) || ! \is_array( $api_data['events'] ) ) {
			return $api_data;
		}

		$filtered_events = [];

		foreach ( $api_data['events'] as $event ) {
			// Ensure $event is an array before processing
			if ( \is_array( $event ) ) {
				// Enrich the event with custom fields (validates ID internally).
				$event = self::add_custom_fields_to_event( $event );

				// Negative selection: skip the event if it meets the hiding criteria.
				if ( self::should_hide_event( $event, $request ) ) {
					continue;
				}
			}

			$filtered_events[] = $event;
		}

		$api_data['events'] = $filtered_events;

		return $api_data;
	}

	/**
	 * Determines whether a specific event should be hidden from the archive response.
	 *
	 * @param array $event   The enriched event data.
	 * @param mixed $request The REST request object holding query parameters.
	 * @return bool True if the event should be hidden, false otherwise.
	 */
	private static function should_hide_event( array $event, mixed $request ): bool {
		if ( ! self::should_hide_unbookable_events( $request ) ) {
			return false;
		}

		if ( empty( $event['event_status'] ) || ! \is_string( $event['event_status'] ) ) {
			return false;
		}

		return self::is_unbookable( $event['event_status'] );
	}

	/**
	 * Checks if the request requires unbookable events to be hidden.
	 *
	 * Evaluates the REST request parameters to determine if the 'hide_unbookable'
	 * flag is explicitly enabled.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return bool True if unbookable events should be hidden, false otherwise.
	 */
	private static function should_hide_unbookable_events( \WP_REST_Request $request ): bool {
		if ( \function_exists( '\ddd' ) ) {
			if ( \function_exists( '\DeWittePrins\CoreFunctionality\log' ) ) {
				\DeWittePrins\CoreFunctionality\log(
					array(
						'filter' => \current_filter(),
						'method' => __METHOD__ . ':' . __LINE__,
						'isset( $request[\'hide_unbookable\'] )' => $request['hide_unbookable'],
						' \'1\' === $request[\'hide_unbookable\'' => ('1' === $request['hide_unbookable']),
						'hide_unbookable' => (isset( $request['hide_unbookable'] ) && '1' === $request['hide_unbookable']),
					)
				);
			}
		}
		return isset( $request['hide_unbookable'] ) && '1' === $request['hide_unbookable'];
	}

	/**
	 * Checks if an event status is classified as unbookable.
	 *
	 * @param string $event_status The status string to evaluate.
	 * @return bool True if the status represents an unbookable state, false otherwise.
	 */
	private static function is_unbookable( string $event_status ): bool {
		$unbookable_statuses = [ 'sold-out', 'sales-closed', 'hidden', 'past', 'canceled', 'postponed' ];
		if ( \function_exists( '\DeWittePrins\CoreFunctionality\log' ) ) {
			\DeWittePrins\CoreFunctionality\log(
				array(
					'filter' => \current_filter(),
					'method' => __METHOD__ . ':' . __LINE__,
					// 'msg'    => 'message',
					'$event_status' => $event_status,
					'$unbookable_statuses' => $unbookable_statuses,
					'is_unbookabel' => \in_array( $event_status, $unbookable_statuses, true ),
				)
			);
		}
		return \in_array( $event_status, $unbookable_statuses, true );
	}

	/**
	 * Filters an event data array to inject custom ticket and status fields.
	 *
	 * Intercepts the prepared REST response data for an event, fetches the calculated
	 * metrics via get_event_custom_fields(), and merges them into the payload.
	 *
	 * @param array $event_data The prepared REST API data for an event.
	 * @return array The enriched REST API response data containing the custom fields.
	 */
	public static function add_custom_fields_to_event( array $event_data ): array {
		if ( isset( $event_data['id'] ) ) {
			$custom_data = self::get_event_custom_fields( (int) $event_data['id'] );

			if ( $custom_data ) {
				$event_data = \array_merge( $event_data, $custom_data );
			}
		}
		return $event_data;
	}

	/**
	 * Computes custom fields data (status, price, stock) for an event.
	 *
	 * Processes raw event and ticket data to generate a specific set of fields
	 * including sales status, lowest ticket price, currency, and stock capacity.
	 * Results are cached in the WordPress Object Cache to optimize performance.
	 *
	 * @param int $event_id The ID of the event post to process.
	 * @return array|false {
	 *     Array of custom fields data, or false if the event is invalid.
	 *
	 *     @type string     $event_status      The current sales/event status (e.g., 'scheduled', 'postponed', 'moved-online', 'canceled' ,'hidden', 'past', 'sales-closed', 'sold-out').
	 *     @type float|null $ticket_min_price  The lowest available ticket price (starting price), or null if unavailable.
	 *     @type string     $ticket_currency   The currency symbol used for the event tickets.
	 *     @type int        $tickets_remaining Total remaining stock across all tickets, or -1 for unlimited.
	 * }
	 *
	 * @see \Tribe__Tickets__Tickets::get_all_event_tickets()
	 * @see \wp_cache_get()
	 */
	public static function get_event_custom_fields( int $event_id ): array|false {
		if ( ! $event_id || ! \get_post_status( $event_id ) ) {
			return false;
		}

		$cache_key     = 'event_custom_fields_data_' . $event_id;
		$cache_group   = 'dwp_event_sync';
		$cached_data   = \wp_cache_get( $cache_key, $cache_group );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$status            = 'scheduled';
		$min_price         = '';
		$currency          = \function_exists( 'tribe_get_coordinate_currency_symbol' ) ? \tribe_get_coordinate_currency_symbol( $event_id ) : '€';
		$total_remaining   = 0;
		$has_unlimited     = false;
		$has_ticket_system = false;

		if ( 'yes' === \get_post_meta( $event_id, '_EventHideFromUpcoming', true ) ) {
			$status = 'hidden';
		}
		elseif ( \function_exists( 'tribe_is_past_event' ) && \tribe_is_past_event( $event_id ) ) {
			$status = 'past';
		}
		elseif ( \class_exists( 'Tribe__Tickets__Tickets' ) ) {
			$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );

			if ( ! empty( $tickets ) ) {
				$has_ticket_system = true;
				$has_stock         = false;
				$sales_open        = false;
				$now               = \current_time( 'timestamp' );
				$prices            = [];

				foreach ( $tickets as $ticket ) {
					if ( self::isset( $ticket->price ) && '' !== $ticket->price ) {
						$prices[] = (float) $ticket->price;
					}

					$ticket_id = self::isset( $ticket->ID ) ? (int) $ticket->ID : 0;

					if ( $ticket_id > 0 ) {
						$start_sale = \get_post_meta( $ticket_id, '_ticket_start_date', true );
						$end_sale   = \get_post_meta( $ticket_id, '_ticket_end_date', true );

						$start_timestamp = $start_sale ? \strtotime( $start_sale ) : 0;
						$end_timestamp   = $end_sale ? \strtotime( $end_sale ) : \PHP_INT_MAX;

						if ( $now >= $start_timestamp && $now <= $end_timestamp ) {
							$sales_open = true;

							if ( self::isset( $ticket->stock ) && '' !== $ticket->stock ) {
								$stock_value = (int) $ticket->stock;

								if ( -1 === $stock_value ) {
									$has_unlimited = true;
									$has_stock     = true;
								} elseif ( $stock_value > 0 && ! $has_unlimited ) {
									$has_stock        = true;
									$total_remaining += $stock_value;
								}
							}
						}
					}
				}

				if ( ! empty( $prices ) ) {
					$min_price = \min( $prices );
				}

				if ( ! $sales_open ) {
					$status = 'sales-closed';
				} elseif ( ! $has_stock ) {
					$status = 'sold-out';
				}
			}
		}

		if ( ! $has_ticket_system && \function_exists( 'tribe_get_cost' ) ) {
			$min_price = \get_post_meta( $event_id, '_EventCost', true );
		}

		if ( 'scheduled' === $status ) {
			$meta_status = \get_post_meta( $event_id, '_tribe_events_status', true );
			if ( ! empty( $meta_status ) && false !== $meta_status ) {
				$status = \sanitize_text_field( $meta_status );
			}
		}

		$final_remaining = $has_unlimited ? -1 : $total_remaining;

		$result = [
			'event_status'      => $status,
			'ticket_min_price'  => ( '' !== $min_price ) ? (float) $min_price : null,
			'ticket_currency'   => \sanitize_text_field( $currency ),
			'tickets_remaining' => $final_remaining,
		];

		\wp_cache_set( $cache_key, $result, $cache_group );

		return $result;
	}

	/**
	 * Custom isset replacement to safely validate dynamic properties from magic objects.
	 *
	 * Native PHP isset() can fail on virtual properties generated via __get() if the
	 * external class lacks a proper __isset() implementation. This helper bypasses
	 * that limitation by accepting the pre-retrieved property value and performing
	 * a strict null check.
	 *
	 * @param mixed $value The live retrieved value of the dynamic property.
	 * @return bool True if the property value is explicitly set and not null, false otherwise.
	 */
	private static function isset( mixed $value ): bool {
		return null !== $value;
	}
}
