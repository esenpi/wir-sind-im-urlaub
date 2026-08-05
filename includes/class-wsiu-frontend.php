<?php
/**
 * Frontend-Ausgabe: Balken, Popup oder schwebende Karte.
 *
 * Die Anzeige endet serverseitig automatisch nach dem letzten Urlaubstag
 * (Settings::phase() liefert dann null). Für gecachte Seiten prüft
 * frontend.js zusätzlich den Endzeitpunkt im Browser.
 *
 * @package WirSindImUrlaub
 */

namespace WSIU;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
		add_shortcode( 'wir_sind_im_urlaub', array( $this, 'shortcode' ) );
	}

	/**
	 * Vorschau-Modus: ?wsiu_preview=1 (nur für Admins) erzwingt die Anzeige,
	 * damit man das Design live testen kann, bevor der Urlaub beginnt.
	 */
	private function preview_phase(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reine Lese-Vorschau ohne Statusänderung, zusätzlich per Capability-Check abgesichert.
		if ( ! isset( $_GET['wsiu_preview'] ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$value = sanitize_key( wp_unslash( $_GET['wsiu_preview'] ) );
		// phpcs:enable
		return 'before' === $value ? 'before' : 'during';
	}

	private function active_phase( array $s ): ?string {
		$preview = $this->preview_phase();
		if ( $preview ) {
			return $preview;
		}
		if ( empty( $s['enabled'] ) ) {
			return null;
		}
		return Settings::phase( $s );
	}

	public function enqueue(): void {
		$s = Settings::get();
		if ( ! $this->active_phase( $s ) ) {
			return;
		}
		$this->enqueue_assets( $s );
	}

	/**
	 * Styles/Skripte laden; eigene Farben als Inline-CSS-Variablen anhängen.
	 */
	private function enqueue_assets( array $s ): void {
		wp_enqueue_style( 'wsiu-frontend', WSIU_URL . 'assets/css/frontend.css', array(), WSIU_VERSION );
		wp_enqueue_script( 'wsiu-frontend', WSIU_URL . 'assets/js/frontend.js', array(), WSIU_VERSION, true );

		if ( 'custom' === $s['theme'] ) {
			$color_a = sanitize_hex_color( $s['color_start'] );
			$color_b = sanitize_hex_color( $s['color_end'] );
			$color_t = sanitize_hex_color( $s['color_text'] );
			wp_add_inline_style(
				'wsiu-frontend',
				sprintf(
					'.wsiu-root{--wsiu-a:%s;--wsiu-b:%s;--wsiu-text:%s;}',
					$color_a ? $color_a : '#0284c7',
					$color_b ? $color_b : '#4f46e5',
					$color_t ? $color_t : '#ffffff'
				)
			);
		}
	}

	public function render(): void {
		if ( is_feed() || is_embed() ) {
			return;
		}
		$s     = Settings::get();
		$phase = $this->active_phase( $s );
		if ( ! $phase ) {
			return;
		}

		echo wp_kses( $this->markup( $s, $phase, $s['display_mode'], (bool) $this->preview_phase() ), self::allowed_html() );
		// Ohne JavaScript: Balken/Karte/Inline-Box trotzdem zeigen. Das Popup
		// bleibt verborgen, da es sich ohne JS nicht schließen ließe.
		echo '<noscript><style>.wsiu-banner,.wsiu-card,.wsiu-inlinebox{visibility:visible !important;opacity:1 !important}</style></noscript>';
	}

	/**
	 * Shortcode [wir_sind_im_urlaub] – Inline-Karte im Seiteninhalt.
	 */
	public function shortcode(): string {
		$s     = Settings::get();
		$phase = $this->active_phase( $s );
		if ( ! $phase ) {
			return '';
		}
		// Assets sicherstellen, falls der Shortcode die einzige Ausgabe ist.
		$this->enqueue_assets( $s );

		return wp_kses( $this->markup( $s, $phase, 'inline', (bool) $this->preview_phase() ), self::allowed_html() );
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------ */

	/**
	 * Whitelist für wp_kses(): alle Tags/Attribute, die markup() erzeugt.
	 */
	private static function allowed_html(): array {
		return array(
			'div'    => array(
				'class'            => true,
				'role'             => true,
				'aria-label'       => true,
				'aria-modal'       => true,
				'aria-labelledby'  => true,
				'aria-hidden'      => true,
				'data-wsiu-root'   => true,
				'data-mode'        => true,
				'data-phase'       => true,
				'data-hash'        => true,
				'data-start'       => true,
				'data-end'         => true,
				'data-dismissible' => true,
				'data-frequency'   => true,
				'data-countdown'   => true,
				'data-banner-pos'  => true,
				'data-preview'     => true,
			),
			'h2'     => array(
				'class' => true,
				'id'    => true,
			),
			'p'      => array( 'class' => true ),
			'span'   => array(
				'class'               => true,
				'aria-hidden'         => true,
				'data-wsiu-countdown' => true,
			),
			'strong' => array( 'class' => true ),
			'em'     => array(),
			'br'     => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
			),
			'button' => array(
				'type'              => true,
				'class'             => true,
				'aria-label'        => true,
				'data-wsiu-dismiss' => true,
			),
		);
	}

	private function markup( array $s, string $phase, string $mode, bool $is_preview ): string {
		$start = Settings::start( $s );
		$end   = Settings::end( $s );
		$back  = Settings::back_date( $s );

		$date_format = get_option( 'date_format', 'j. F Y' );
		$fmt         = static function ( ?\DateTimeImmutable $dt ) use ( $date_format ) {
			return $dt ? wp_date( $date_format, $dt->getTimestamp() ) : '';
		};

		$replacements = array(
			'{start}'     => $fmt( $start ),
			'{ende}'      => $fmt( $end ),
			'{wieder_da}' => $fmt( $back ),
		);

		$headline = 'before' === $phase ? $s['headline_before'] : $s['headline'];
		$message  = 'before' === $phase ? $s['message_before'] : $s['message'];
		$headline = strtr( $headline, $replacements );
		$message  = strtr( $message, $replacements );

		$classes = array( 'wsiu-root', 'wsiu-theme-' . $s['theme'] );
		switch ( $mode ) {
			case 'popup':
				$classes[] = 'wsiu-overlay';
				break;
			case 'card':
				$classes[] = 'wsiu-card';
				$classes[] = 'wsiu-card--' . $s['card_position'];
				break;
			case 'inline':
				$classes[] = 'wsiu-inlinebox';
				break;
			default:
				$classes[] = 'wsiu-banner';
				$classes[] = 'wsiu-banner--' . $s['banner_position'];
		}

		// Hash identifiziert den konfigurierten Zeitraum: Ändert er sich,
		// gilt eine frühere "Ausblenden"-Entscheidung des Besuchers nicht mehr.
		$hash = substr( md5( $s['start_date'] . '|' . $s['end_date'] . '|' . $mode . '|' . WSIU_VERSION ), 0, 12 );

		$attrs = array(
			'class'            => implode( ' ', $classes ),
			'data-wsiu-root'   => '1',
			'data-mode'        => $mode,
			'data-phase'       => $phase,
			'data-hash'        => $hash,
			'data-start'       => $start ? (string) $start->getTimestamp() : '',
			'data-end'         => $end ? (string) $end->getTimestamp() : '',
			'data-dismissible' => $s['dismissible'] ? '1' : '0',
			'data-frequency'   => $s['popup_frequency'],
			'data-countdown'   => $s['show_countdown'] ? '1' : '0',
			'data-banner-pos'  => $s['banner_position'],
			'data-preview'     => $is_preview ? '1' : '0',
		);

		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
		}

		$inner = $this->inner_content( $s, $mode, $headline, $message, $phase );

		if ( 'popup' === $mode ) {
			return sprintf(
				'<div%1$s><div class="wsiu-popup" role="dialog" aria-modal="true" aria-labelledby="wsiu-headline">%2$s</div></div>',
				$attr_html,
				$inner
			);
		}

		$role = sprintf( ' role="region" aria-label="%s"', esc_attr__( 'Urlaubshinweis', 'wir-sind-im-urlaub' ) );
		return sprintf( '<div%1$s%2$s>%3$s</div>', $attr_html, $role, $inner );
	}

	private function inner_content( array $s, string $mode, string $headline, string $message, string $phase ): string {
		$icon_html = '';
		if ( '' !== $s['icon'] ) {
			$icon_html = sprintf( '<span class="wsiu-icon" aria-hidden="true">%s</span>', esc_html( $s['icon'] ) );
		}

		$countdown_html = '';
		if ( $s['show_countdown'] ) {
			$countdown_html = sprintf(
				'<span class="wsiu-chip" data-wsiu-countdown>%s</span>',
				esc_html( $this->countdown_text( $s, $phase ) )
			);
		}

		$dismiss_html = '';
		if ( 'popup' === $mode || ( $s['dismissible'] && 'inline' !== $mode ) ) {
			$dismiss_html = sprintf(
				'<button type="button" class="wsiu-dismiss" data-wsiu-dismiss aria-label="%s">&#10005;</button>',
				esc_attr__( 'Hinweis schließen', 'wir-sind-im-urlaub' )
			);
		}

		$message_html = nl2br( $message ); // Bereits beim Speichern per wp_kses bereinigt.

		if ( 'popup' === $mode ) {
			$dates_html = $this->date_pills( $s );
			return sprintf(
				'%1$s<div class="wsiu-popup-icon" aria-hidden="true">%2$s</div>' .
				'<h2 class="wsiu-headline" id="wsiu-headline">%3$s</h2>' .
				'<p class="wsiu-message">%4$s</p>%5$s%6$s' .
				'<button type="button" class="wsiu-btn" data-wsiu-dismiss>%7$s</button>',
				$dismiss_html,
				'' !== $s['icon'] ? esc_html( $s['icon'] ) : '🌴',
				esc_html( $headline ),
				$message_html,
				$dates_html,
				$countdown_html,
				esc_html( $s['button_text'] )
			);
		}

		if ( 'card' === $mode || 'inline' === $mode ) {
			return sprintf(
				'%1$s<div class="wsiu-card-head">%2$s<strong class="wsiu-headline">%3$s</strong></div>' .
				'<p class="wsiu-message">%4$s</p>%5$s',
				$dismiss_html,
				$icon_html,
				esc_html( $headline ),
				$message_html,
				$countdown_html
			);
		}

		// Balken.
		return sprintf(
			'<div class="wsiu-banner-inner">%1$s<div class="wsiu-banner-text">' .
			'<strong class="wsiu-headline">%2$s</strong>' .
			'<span class="wsiu-message">%3$s</span></div>%4$s%5$s</div>',
			$icon_html,
			esc_html( $headline ),
			$message_html,
			$countdown_html,
			$dismiss_html
		);
	}

	private function date_pills( array $s ): string {
		$start = Settings::start( $s );
		$end   = Settings::end( $s );
		if ( ! $start || ! $end ) {
			return '';
		}
		return sprintf(
			'<div class="wsiu-dates"><span class="wsiu-date-pill">%1$s</span><span class="wsiu-dates-arrow" aria-hidden="true">&#8594;</span><span class="wsiu-date-pill">%2$s</span></div>',
			esc_html( wp_date( 'j. M Y', $start->getTimestamp() ) ),
			esc_html( wp_date( 'j. M Y', $end->getTimestamp() ) )
		);
	}

	/**
	 * Server-seitig vorberechneter Countdown-Text (frontend.js aktualisiert live).
	 */
	private function countdown_text( array $s, string $phase ): string {
		$now   = current_datetime()->setTime( 0, 0, 0 );
		$start = Settings::start( $s );
		$end   = Settings::end( $s );

		if ( 'before' === $phase && $start ) {
			$days = (int) $now->diff( $start->setTime( 0, 0, 0 ) )->format( '%r%a' );
			if ( $days <= 0 ) {
				return __( 'Der Urlaub beginnt heute', 'wir-sind-im-urlaub' );
			}
			if ( 1 === $days ) {
				return __( 'Der Urlaub beginnt morgen', 'wir-sind-im-urlaub' );
			}
			/* translators: %d: Anzahl Tage */
			return sprintf( __( 'Der Urlaub beginnt in %d Tagen', 'wir-sind-im-urlaub' ), $days );
		}

		if ( $end ) {
			$days = (int) $now->diff( $end->setTime( 0, 0, 0 ) )->format( '%r%a' ) + 1;
			if ( $days <= 1 ) {
				return __( 'Ab morgen wieder da', 'wir-sind-im-urlaub' );
			}
			/* translators: %d: Anzahl Tage */
			return sprintf( __( 'Wieder da in %d Tagen', 'wir-sind-im-urlaub' ), $days );
		}

		return '';
	}
}
