<?php
/**
 * Class RestApiExtension
 * Beheert uitsluitend de filters en acties voor het uitbreiden van de REST API responses
 * van The Events Calendar met de event-status en wat ticket informatie.
 */
namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestApiExtension
 * Beheert uitsluitend de filters en acties voor het uitbreiden van de REST API responses.
 */
class RestApiExtension {

	/**
	 * Door de constructor private te maken, kan niemand 'new RestApiExtension()' aanroepen.
	 * Dit dwingt het puur statische gebruik af.
	 */
	private function __construct() {}

	/**
	 * Start de REST API uitbreidingen.
	 */
	public static function init() {
		\add_filter( 'tribe_rest_events_archive_data', [ __CLASS__, 'process_archive_data' ], 10, 2 );
		\add_filter( 'tribe_rest_single_event_data', [ __CLASS__, 'add_custom_data_to_single' ] );
		\add_action( 'rest_api_init', [ __CLASS__, 'register_standard_rest_fields' ] );
	}

	/**
	 * De specifieke isset-vervanger die strikt op null controleert voor magische objecten.
	 *
	 * @param mixed $value De live opgehaalde waarde van de property.
	 * @return bool True als de property bestaat (niet null is), anders false.
	 */
	private static function isset( $value ) {
		return null !== $value;
	}

	/**
	 * Centrale methode die alle status- en ticketberekeningen per event ID doet.
	 */
	public static function get_comprehensive_event_data( $event_id ) {
		if ( ! $event_id || ! \get_post_status( $event_id ) ) {
			return false;
		}

		$cache_key     = 'comprehensive_data_' . $event_id;
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
					$status = 'sales_closed';
				} elseif ( ! $has_stock ) {
					$status = 'sold_out';
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
			'ticket_price'      => ( '' !== $min_price ) ? (float) $min_price : null,
			'ticket_currency'   => \sanitize_text_field( $currency ),
			'tickets_remaining' => $final_remaining,
		];

		\wp_cache_set( $cache_key, $result, $cache_group );

		return $result;
	}

	/**
	 * Verwerkt de volledige archief-data response array en filtert indien nodig.
	 */
	public static function process_archive_data( array $api_data, $request ) {
		if ( ! isset( $api_data['events'] ) || ! \is_array( $api_data['events'] ) ) {
			return $api_data;
		}

		$filtered_events = [];
		$hide_sold_out   = ( isset( $request['hide_sold_out'] ) && '1' === $request['hide_sold_out'] );

		foreach ( $api_data['events'] as $event_data ) {
			$event_id = isset( $event_data['id'] ) ? (int) $event_data['id'] : 0;

			if ( $event_id > 0 ) {
				$custom_data = self::get_comprehensive_event_data( $event_id );

				if ( $custom_data ) {
					$event_data = \array_merge( $event_data, $custom_data );

					if ( $hide_sold_out && \in_array( $event_data['event_status'], [ 'sold_out', 'sales_closed', 'hidden' ], true ) ) {
						continue;
					}
				}
			}

			$filtered_events[] = $event_data;
		}

		$api_data['events'] = $filtered_events;

		return $api_data;
	}

	/**
	 * Voegt de data toe aan het single event endpoint.
	 */
	public static function add_custom_data_to_single( array $event_data ) {
		if ( \is_array( $event_data ) && isset( $event_data['id'] ) ) {
			$custom_data = self::get_comprehensive_event_data( $event_data['id'] );
			if ( $custom_data ) {
				$event_data = \array_merge( $event_data, $custom_data );
			}
		}
		return $event_data;
	}

	/**
	 * Registreert de nieuwe velden voor de standaard WordPress REST API endpoints.
	 */
	public static function register_standard_rest_fields() {
		$fields = [ 'event_status', 'ticket_price', 'ticket_currency', 'tickets_remaining' ];

		foreach ( $fields as $field ) {
			\register_rest_field( 'tribe_events', $field, [
				'get_callback' => function( $object ) use ( $field ) {
					$event_id = isset( $object['id'] ) ? $object['id'] : 0;
					$data     = RestApiExtension::get_comprehensive_event_data( $event_id );
					return ( $data && isset( $data[ $field ] ) ) ? $data[ $field ] : null;
				},
				'schema' => [
					'type' => \in_array( $field, [ 'ticket_price', 'tickets_remaining' ], true ) ? 'number' : 'string'
				]
			] );
		}
	}
}
