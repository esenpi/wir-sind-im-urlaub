<?php
/**
 * Einstellungen: Defaults, Validierung und Zeitraum-Logik.
 *
 * @package WirSindImUrlaub
 */

namespace WSIU;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	const OPTION = 'wsiu_settings';
	const GROUP  = 'wsiu';

	/**
	 * Alle 16 Bundesländer (ISO-3166-2-Codes, wie sie die OpenHolidays-API erwartet).
	 */
	public static function bundeslaender(): array {
		return array(
			'DE-BW' => 'Baden-Württemberg',
			'DE-BY' => 'Bayern',
			'DE-BE' => 'Berlin',
			'DE-BB' => 'Brandenburg',
			'DE-HB' => 'Bremen',
			'DE-HH' => 'Hamburg',
			'DE-HE' => 'Hessen',
			'DE-MV' => 'Mecklenburg-Vorpommern',
			'DE-NI' => 'Niedersachsen',
			'DE-NW' => 'Nordrhein-Westfalen',
			'DE-RP' => 'Rheinland-Pfalz',
			'DE-SL' => 'Saarland',
			'DE-SN' => 'Sachsen',
			'DE-ST' => 'Sachsen-Anhalt',
			'DE-SH' => 'Schleswig-Holstein',
			'DE-TH' => 'Thüringen',
		);
	}

	public static function icons(): array {
		return array( '🌴', '🏖️', '✈️', '☀️', '⛱️', '🌊', '😎', '🧳', '' );
	}

	public static function defaults(): array {
		return array(
			'enabled'         => 0,
			'start_date'      => '',
			'start_time'      => '00:00',
			'end_date'        => '',
			'end_time'        => '23:59',
			'announce_days'   => 7,
			'bundesland'      => 'DE-NW',
			'display_mode'    => 'banner', // banner | popup | card
			'banner_position' => 'top',    // top | bottom
			'card_position'   => 'bottom-right', // bottom-right | bottom-left
			'popup_frequency' => 'session', // always | session | day
			'theme'           => 'ozean',  // ozean | sonne | wald | nacht | elegant | custom
			'color_start'     => '#0284c7',
			'color_end'       => '#4f46e5',
			'color_text'      => '#ffffff',
			'icon'            => '🌴',
			'headline'        => __( 'Wir sind im Urlaub!', 'wir-sind-im-urlaub' ),
			'message'         => __( "Vom {start} bis einschließlich {ende} bleibt unser Betrieb geschlossen.\nAb dem {wieder_da} sind wir wieder wie gewohnt für Sie da.", 'wir-sind-im-urlaub' ),
			'headline_before' => __( 'Betriebsurlaub steht an', 'wir-sind-im-urlaub' ),
			'message_before'  => __( "Vom {start} bis {ende} machen wir Betriebsferien.\nBis dahin sind wir wie gewohnt für Sie erreichbar.", 'wir-sind-im-urlaub' ),
			'button_text'     => __( 'Alles klar', 'wir-sind-im-urlaub' ),
			'show_countdown'  => 1,
			'dismissible'     => 1,
		);
	}

	/**
	 * Gespeicherte Einstellungen, gegen die Defaults gemergt.
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Sanitize-Callback für register_setting().
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 */
	public static function sanitize( $input ): array {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		$out['enabled']        = empty( $in['enabled'] ) ? 0 : 1;
		$out['show_countdown'] = empty( $in['show_countdown'] ) ? 0 : 1;
		$out['dismissible']    = empty( $in['dismissible'] ) ? 0 : 1;

		$out['start_date'] = self::valid_date( $in['start_date'] ?? '' );
		$out['end_date']   = self::valid_date( $in['end_date'] ?? '' );
		$out['start_time'] = self::valid_time( $in['start_time'] ?? '', '00:00' );
		$out['end_time']   = self::valid_time( $in['end_time'] ?? '', '23:59' );

		$out['announce_days'] = min( 365, absint( $in['announce_days'] ?? $d['announce_days'] ) );

		$out['bundesland'] = array_key_exists( $in['bundesland'] ?? '', self::bundeslaender() )
			? $in['bundesland']
			: $d['bundesland'];

		$out['display_mode']    = self::one_of( $in['display_mode'] ?? '', array( 'banner', 'popup', 'card' ), $d['display_mode'] );
		$out['banner_position'] = self::one_of( $in['banner_position'] ?? '', array( 'top', 'bottom' ), $d['banner_position'] );
		$out['card_position']   = self::one_of( $in['card_position'] ?? '', array( 'bottom-right', 'bottom-left' ), $d['card_position'] );
		$out['popup_frequency'] = self::one_of( $in['popup_frequency'] ?? '', array( 'always', 'session', 'day' ), $d['popup_frequency'] );
		$out['theme']           = self::one_of( $in['theme'] ?? '', array( 'ozean', 'sonne', 'wald', 'nacht', 'elegant', 'custom' ), $d['theme'] );

		$out['color_start'] = self::hex_or( $in['color_start'] ?? '', $d['color_start'] );
		$out['color_end']   = self::hex_or( $in['color_end'] ?? '', $d['color_end'] );
		$out['color_text']  = self::hex_or( $in['color_text'] ?? '', $d['color_text'] );

		$out['icon'] = in_array( $in['icon'] ?? '', self::icons(), true ) ? $in['icon'] : $d['icon'];

		$out['headline']        = sanitize_text_field( $in['headline'] ?? $d['headline'] );
		$out['headline_before'] = sanitize_text_field( $in['headline_before'] ?? $d['headline_before'] );
		$out['button_text']     = sanitize_text_field( $in['button_text'] ?? $d['button_text'] );
		$out['message']         = self::sanitize_message( $in['message'] ?? $d['message'] );
		$out['message_before']  = self::sanitize_message( $in['message_before'] ?? $d['message_before'] );

		// Plausibilität: Ende darf nicht vor dem Start liegen.
		if ( $out['start_date'] && $out['end_date'] && $out['end_date'] < $out['start_date'] ) {
			$tmp               = $out['end_date'];
			$out['end_date']   = $out['start_date'];
			$out['start_date'] = $tmp;
		}

		return $out;
	}

	private static function sanitize_message( string $value ): string {
		return wp_kses(
			$value,
			array(
				'strong' => array(),
				'em'     => array(),
				'br'     => array(),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		);
	}

	private static function hex_or( string $value, string $fallback ): string {
		$hex = sanitize_hex_color( $value );
		return $hex ? $hex : $fallback;
	}

	private static function valid_date( string $value ): string {
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
		return ( $dt && $dt->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	private static function valid_time( string $value, string $fallback ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}

	private static function one_of( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/* ---------------------------------------------------------------------
	 * Zeitraum-Logik
	 * ------------------------------------------------------------------ */

	/**
	 * Beginn des Urlaubs als DateTimeImmutable (WP-Zeitzone) oder null.
	 */
	public static function start( array $s ): ?\DateTimeImmutable {
		return self::combine( $s['start_date'], $s['start_time'] ? $s['start_time'] : '00:00' );
	}

	/**
	 * Ende des Urlaubs (inklusive, d. h. letzter angezeigter Moment) oder null.
	 */
	public static function end( array $s ): ?\DateTimeImmutable {
		$end = self::combine( $s['end_date'], $s['end_time'] ? $s['end_time'] : '23:59' );
		return $end ? $end->setTime( (int) $end->format( 'H' ), (int) $end->format( 'i' ), 59 ) : null;
	}

	private static function combine( string $date, string $time ): ?\DateTimeImmutable {
		if ( '' === $date ) {
			return null;
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return false === $dt ? null : $dt;
	}

	/**
	 * Aktuelle Phase des Hinweises:
	 *  - 'during'  → Urlaub läuft, Hinweis anzeigen
	 *  - 'before'  → Vorankündigung (announce_days vor dem Start)
	 *  - null      → nichts anzeigen (kein Zeitraum, noch zu früh oder vorbei)
	 */
	public static function phase( array $s ): ?string {
		$start = self::start( $s );
		$end   = self::end( $s );
		if ( ! $start || ! $end || $end < $start ) {
			return null;
		}

		$now = current_datetime();

		if ( $now > $end ) {
			return null; // Letzter Tag vorbei → automatisch ausblenden.
		}
		if ( $now >= $start ) {
			return 'during';
		}
		if ( $s['announce_days'] > 0 ) {
			$announce_from = $start->modify( '-' . (int) $s['announce_days'] . ' days' )->setTime( 0, 0, 0 );
			if ( $now >= $announce_from ) {
				return 'before';
			}
		}
		return null;
	}

	/**
	 * Datum, ab dem der Betrieb wieder erreichbar ist (Tag nach dem Urlaubsende).
	 */
	public static function back_date( array $s ): ?\DateTimeImmutable {
		$end = self::end( $s );
		return $end ? $end->modify( '+1 day' )->setTime( 0, 0, 0 ) : null;
	}

	/**
	 * Status für die Admin-Oberfläche.
	 *
	 * @return array{code:string,label:string}
	 */
	public static function status( array $s ): array {
		if ( empty( $s['enabled'] ) ) {
			return array(
				'code'  => 'off',
				'label' => __( 'Deaktiviert – es wird nichts angezeigt.', 'wir-sind-im-urlaub' ),
			);
		}

		$start = self::start( $s );
		$end   = self::end( $s );
		if ( ! $start || ! $end ) {
			return array(
				'code'  => 'missing',
				'label' => __( 'Aktiv, aber es fehlt noch ein gültiger Zeitraum.', 'wir-sind-im-urlaub' ),
			);
		}

		$now         = current_datetime();
		$date_format = get_option( 'date_format', 'j. F Y' );

		if ( $now > $end ) {
			return array(
				'code'  => 'expired',
				'label' => sprintf(
					/* translators: %s: Datum */
					__( 'Abgelaufen – der Urlaub endete am %s. Der Hinweis wird nicht mehr angezeigt.', 'wir-sind-im-urlaub' ),
					wp_date( $date_format, $end->getTimestamp() )
				),
			);
		}

		$phase = self::phase( $s );
		if ( 'during' === $phase ) {
			return array(
				'code'  => 'live',
				'label' => sprintf(
					/* translators: %s: Datum */
					__( 'Live – der Hinweis wird angezeigt (bis %s).', 'wir-sind-im-urlaub' ),
					wp_date( $date_format, $end->getTimestamp() )
				),
			);
		}
		if ( 'before' === $phase ) {
			return array(
				'code'  => 'announce',
				'label' => sprintf(
					/* translators: %s: Datum */
					__( 'Vorankündigung läuft – der Urlaub beginnt am %s.', 'wir-sind-im-urlaub' ),
					wp_date( $date_format, $start->getTimestamp() )
				),
			);
		}

		$show_from = $s['announce_days'] > 0
			? $start->modify( '-' . (int) $s['announce_days'] . ' days' )
			: $start;

		return array(
			'code'  => 'scheduled',
			'label' => sprintf(
				/* translators: %s: Datum */
				__( 'Geplant – der Hinweis erscheint automatisch ab dem %s.', 'wir-sind-im-urlaub' ),
				wp_date( $date_format, $show_from->getTimestamp() )
			),
		);
	}
}
