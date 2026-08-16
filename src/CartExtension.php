<?php
/**
 * Dezeclass is opgezet om de add-to-cart functionaliteit van WooCommerce
 * uit te breiden met de mogelijkheid om meerdere producten tegelijk
 * aan de winkelwagen toe te voegen.
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartExtension
 * Beheert uitsluitend de WooCommerce functionaliteiten voor de winkelwagen via de URL.
 */
class CartExtension {

	/**
	 * Door de constructor private te maken, kan niemand 'new CartExtension()' aanroepen.
	 * Dit dwingt het puur statische gebruik af.
	 */
	private function __construct() {}


	/**
	 * Start de winkelwagen uitbreidingen.
	 */
	public static function init() {
		\add_action( 'wp_loaded', [ __CLASS__, 'process_multi_add_to_cart' ], 15 );
	}

	/**
	 * Vangt de add-to-cart parameter op en staat komma-gescheiden product-ID's en flexibele aantallen toe.
	 * Ondersteunt de betrouwbare syntax: ?add-to-cart=101:1,102:3
	 */
	public static function process_multi_add_to_cart() {
		if ( ! \class_exists( 'WC_Form_Handler' ) || empty( $_REQUEST['add-to-cart'] ) || false === \strpos( $_REQUEST['add-to-cart'], ',' ) ) {
			return;
		}

		// Omzeil de standaard add-to-cart restrictie van WooCommerce
		\remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'add_to_cart_action' ], 20 );

		$items     = \explode( ',', $_REQUEST['add-to-cart'] );
		$was_added = false;

		if ( ! empty( $items ) && \function_exists( 'WC' ) ) {
			foreach ( $items as $item ) {
				$item = \trim( $item );

				$product_id = 0;
				$quantity   = 1;

				// Splits het ID en het aantal correct op basis van de dubbelepunt en de juiste array index
				if ( false !== \strpos( $item, ':' ) ) {
					$parts      = \explode( ':', $item );
					$product_id = isset( $parts[0] ) ? (int) $parts[0] : 0;
					$quantity   = isset( $parts[1] ) ? (int) $parts[1] : 1;
				} else {
					$product_id = (int) $item;
				}

				if ( $product_id > 0 && $quantity > 0 ) {
					if ( false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) {
						$was_added = true;
					}
				}
			}
		}

		if ( $was_added ) {
			\wp_safe_redirect( \wc_get_cart_url() );
			exit;
		}
	}
}
