<?php
/**
 * Main plugin bootstrapper.
 *
 * Responsible for initializing and launching all underlying static services
 * and subsystems within the plugin repository.
 *
 * @package DeWittePrins\RemoteTicketsServerAddons
 */

namespace DeWittePrins\RemoteTicketsServerAddons;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * The central bootstrapper class responsible for launching the plugin architecture.
 */
class Plugin {

	/**
	 * Private constructor to prevent instantiation.
	 *
	 * Enforces the usage of this class as a purely static bootstrapper.
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {}

	/**
	 * Launches the plugin by initializing all registered subsystem services.
	 *
	 * @return void
	 */
	public static function launch(): void {
		$services = [
			RestApiExtension::class,
			RestApiEndpoint::class,
			WooCartExtension::class,
		];

		foreach ( $services as $service ) {
			// Safely verify existence and call the initialization trigger on each static class.
			if ( \class_exists( $service ) && \is_callable( [ $service, 'init' ] ) ) {
				$service::init();
			}
		}
	}
}
