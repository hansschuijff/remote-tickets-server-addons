<?php
/**
 * Remote Tickets Server Add-ons
 *
 * @wordpress-plugin
 * Plugin Name:       Remote Tickets Server Add-ons
 * Plugin URI:        https://github.com/hansschuijff/remote-tickets-server-addons
 * Description:       Voegt wat extra gegevens toe aan de response van de The Events Calendar Rest API en maakt woocommerce geschikt om meerdere tickets via een url in de winkelmand te plaatsen.
 * Version:           0.1.0
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
 * Tested up to:      7.4
 * Requires PHP:      8.4
 *
 * @package           DeWittePrins\RemoteTicketsServerAddons
 * @author            Hans Schuijff (@hansschuijff)
 * @license           GPL-2.0
 * @link              https://dewitteprins.nl
 * @see               https://github.com/hansschuijff/remote-tickets-server-addons
 * @license           GPL-2.0
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

// Registreer de handmatige PSR-4 Autoloader voor de nieuwe namespace
\spl_autoload_register( function ( $class ) {
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

// Lanceer de plugin via de centrale bootstrapper
Plugin::launch();
