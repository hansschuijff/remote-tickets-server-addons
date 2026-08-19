<?php
/**
 * Remote Tickets Server Add-ons
 *
 * @wordpress-plugin
 * Plugin Name:       Remote Tickets Server Add-ons
 * Plugin URI:        https://github.com/hansschuijff/remote-tickets-server-addons
 * Description:       Adds custom event statuses and ticket metrics to The Events Calendar REST API and enables WooCommerce batch add-to-cart capabilities via URL.
 * Version:           1.2.0
 * Author:            Hans Schuijff @hansschuijff
 * Author URI:        https://dewitteprins.nl
 * Text Domain:       remote-tickets-server-dwp
 * Domain Path:       /languages
 * License:           GPL-2.0
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/hansschuijff/remote-tickets-server-addons
 * GitHub Plugin URI: https://github.com/hansschuijff/remote-tickets-server-addons
 * GitHub Branch:     master
 * Requires WP:       7.0
 * Tested up to:      7.0.4
 * Requires PHP:      8.4
 *
 * @package           DeWittePrins\RemoteTicketsServerAddons
 * @author            Hans Schuijff (@hansschuijff)
 * @license           GPL-2.0
 * @link              https://dewitteprins.nl
 * @see               https://github.com/hansschuijff/remote-tickets-server-addons
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers an anonymous function as a PSR-4 compliant autoloader for the project.
 *
 * Maps the namespace prefix to the local '/src/' directory, converts namespace
 * separators to directory separators, and requires the target file if it exists.
 *
 * @param string $class The fully qualified class name to load.
 * @return void
 */
\spl_autoload_register( function ( string $class ): void {
	$prefix   = 'DeWittePrins\\RemoteTicketsServerAddons\\';
	$base_dir = __DIR__ . '/src/';

	$len = \strlen( $prefix );
	if ( \strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = \substr( $class, $len );
	$file           = $base_dir . \str_replace( '\\', '/', $relative_class ) . '.php';

	if ( \file_exists( $file ) ) {
		require_once $file;
	}
} );

// Launch the plugin architecture via the central bootstrapper.
Plugin::launch();
