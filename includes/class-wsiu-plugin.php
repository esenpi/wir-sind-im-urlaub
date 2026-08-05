<?php
/**
 * Zentraler Orchestrator: verdrahtet Admin & Frontend.
 *
 * @package WirSindImUrlaub
 */

namespace WSIU;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Admin */
	public $admin;

	/** @var Frontend */
	public $frontend;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'wir-sind-im-urlaub', false, dirname( plugin_basename( WSIU_FILE ) ) . '/languages' );

		$this->frontend = new Frontend();
		$this->frontend->register();

		if ( is_admin() ) {
			$this->admin = new Admin();
			$this->admin->register();
		}
	}

	/**
	 * Bei Aktivierung: Standardwerte anlegen (falls noch nicht vorhanden).
	 */
	public static function activate(): void {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}
}
