<?php
/**
 * Räumt bei der Deinstallation alle Plugin-Daten auf.
 *
 * @package WirSindImUrlaub
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wsiu_settings' );

// Ferien-Caches aller Bundesländer entfernen.
$wsiu_states = array( 'BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH' );
foreach ( $wsiu_states as $wsiu_state ) {
	delete_transient( 'wsiu_ferien_de_' . strtolower( $wsiu_state ) );
}
