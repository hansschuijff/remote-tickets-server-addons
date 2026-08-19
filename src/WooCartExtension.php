<?php
/**
 * WooCommerce Cart Extension for handling multi-product additions.
 *
 * Expands the default WooCommerce add-to-cart functionality by allowing users
 * to add multiple products with specific quantities simultaneously via the URL.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCartExtension
 *
 * Manages WooCommerce cart mechanics and custom batch additions via URL request variables.
 */
class WooCartExtension {

	/**
	 * Private constructor to prevent instantiation.
	 *
	 * Enforces the usage of this class as a purely static utility class.
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {}

	/**
	 * Initializes the cart extensions.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Renamed from process_multi_add_to_cart to a cleaner action name
		\add_action( 'wp_loaded', [ __CLASS__, 'process_batch_add_to_cart' ], 15 );
	}

	/**
	 * Intercepts the add-to-cart parameter to process comma-separated products and custom quantities.
	 *
	 * Supports the reliable and structured syntax: ?add-to-cart=101:1,102:3
	 * Bypasses the native single-item WooCommerce restriction when this syntax is detected.
	 *
	 * @return void
	 */
	public static function process_batch_add_to_cart(): void {
		if ( empty( $_REQUEST['add-to-cart'] ) || ! \is_string( $_REQUEST['add-to-cart'] ) ) {
			return;
		}

		$request_value = $_REQUEST['add-to-cart'];

		// Activates if there is a comma OR a custom quantity colon.
		$has_comma = \str_contains( $request_value, ',' );
		$has_colon = \str_contains( $request_value, ':' );

		if ( ! $has_comma && ! $has_colon ) {
			return;
		}

		if ( ! \class_exists( 'WC_Form_Handler' ) || ! \function_exists( 'WC' ) ) {
			return;
		}

		// Bypass the native standard single add-to-cart handler of WooCommerce.
		\remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'add_to_cart_action' ], 20 );

		$items     = \explode( ',', $request_value );
		$was_added = false;

		foreach ( $items as $item ) {
			$item = \trim( $item );

			if ( '' === $item ) {
				continue;
			}

			$product_id = 0;
			$quantity   = 1;

			// Correctly separate the product ID and quantity based on the colon separator.
			if ( \str_contains( $item, ':' ) ) {
				$parts      = \explode( ':', $item );
				$product_id = isset( $parts[0] ) ? (int) $parts[0] : 0;
				$quantity   = isset( $parts[1] ) ? (int) $parts[1] : 1;
			} else {
				$product_id = (int) $item;
			}

			if ( $product_id > 0 && $quantity > 0 ) {
				// Safely execute the addition via the global WooCommerce cart handler.
				if ( false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) {
					$was_added = true;
				}
			}
		}

		// Safely redirect to the cart page if items were successfully processed.
		if ( $was_added ) {
			\wp_safe_redirect( \wc_get_cart_url() );
			exit;
		}
	}
}
