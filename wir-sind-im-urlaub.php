<?php
/**
 * Plugin Name:       Wir sind im Urlaub
 * Plugin URI:        https://amarenasoftware.com
 * Description:       Betriebsurlaub eintragen und als eleganter Balken, Popup oder schwebende Karte anzeigen – mit Schulferien-Vorschlägen, Countdown und automatischem Ausblenden nach dem letzten Urlaubstag.
 * Version:           0.0.1
 * Author:            Amarena Software
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wir-sind-im-urlaub
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSIU_VERSION', '0.0.1' );
define( 'WSIU_FILE', __FILE__ );
define( 'WSIU_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSIU_URL', plugin_dir_url( __FILE__ ) );

require_once WSIU_DIR . 'includes/class-wsiu-settings.php';
require_once WSIU_DIR . 'includes/class-wsiu-school-holidays.php';
require_once WSIU_DIR . 'includes/class-wsiu-frontend.php';
require_once WSIU_DIR . 'includes/class-wsiu-admin.php';
require_once WSIU_DIR . 'includes/class-wsiu-plugin.php';

register_activation_hook( __FILE__, array( 'WSIU\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WSIU\Plugin', 'instance' ) );
