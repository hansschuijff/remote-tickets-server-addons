<?php
/**
 * Plugin Name: Remote Tickets Server Add-ons
 * Description: Voegt wat extra gegevens toe aan de response van de TEC Rest API en maakt woocommerce geschikt om meerdere tickets via een url in de winkelmand te plaatsen.
 * Version:     0.3.0
 * Author:      De Witte Prins
 * License:     GPL2
 * Text Domain: remote-tickets-server-dwp
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
