<?php
/**
 * Deze class bevat de functionaliteit om de plugin te starten.
 */
namespace DeWittePrins\RemoteTicketsServerAddons;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 * De centrale bootstrapper die verantwoordelijk is voor het lanceren van de plugin.
 */
class Plugin {

	/**
	 * Private constructor dwingt af dat deze klasse niet los kan worden aangemaakt.
	 */
	private function __construct() {}

	/**
	 * De centrale startmethode (het lanceerplatform) van de plugin.
	 */
	public static function launch() {
		self::bootstrap();
	}

	/**
	 * Initialiseert alle onderliggende statische subsystemen.
	 */
	private static function bootstrap() {
		$services = [
			RestApiExtension::class,
			CartExtension::class,
		];

		foreach ( $services as $service ) {
			// De subklassen behouden keurig hun vertrouwde init() methode
			if ( \class_exists( $service ) && \is_callable( [ $service, 'init' ] ) ) {
				$service::init();
			}
		}
	}
}
