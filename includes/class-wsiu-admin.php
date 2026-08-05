<?php
/**
 * Admin: Einstellungsseite, Assets und AJAX für Schulferien.
 *
 * @package WirSindImUrlaub
 */

namespace WSIU;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	const PAGE = 'wir-sind-im-urlaub';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_wsiu_school_holidays', array( $this, 'ajax_school_holidays' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WSIU_FILE ), array( $this, 'action_links' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Wir sind im Urlaub', 'wir-sind-im-urlaub' ),
			__( 'Urlaubsmodus', 'wir-sind-im-urlaub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-palmtree',
			58
		);
	}

	public function register_settings(): void {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ), esc_html__( 'Einstellungen', 'wir-sind-im-urlaub' ) )
		);
		return $links;
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		// Frontend-Styles auch im Admin laden, damit die Live-Vorschau
		// pixelgenau dem echten Design entspricht.
		wp_enqueue_style( 'wsiu-frontend', WSIU_URL . 'assets/css/frontend.css', array(), WSIU_VERSION );
		wp_enqueue_style( 'wsiu-admin', WSIU_URL . 'assets/css/admin.css', array( 'wsiu-frontend' ), WSIU_VERSION );
		wp_enqueue_script( 'wsiu-admin', WSIU_URL . 'assets/js/admin.js', array(), WSIU_VERSION, true );

		wp_localize_script(
			'wsiu-admin',
			'wsiuAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wsiu_admin' ),
				'i18n'    => array(
					'loading'   => __( 'Ferien werden geladen …', 'wir-sind-im-urlaub' ),
					'error'     => __( 'Ferien konnten nicht geladen werden.', 'wir-sind-im-urlaub' ),
					'applied'   => __( 'Zeitraum übernommen – bitte speichern.', 'wir-sind-im-urlaub' ),
					'noResults' => __( 'Keine kommenden Ferien gefunden.', 'wir-sind-im-urlaub' ),
					'days'      => __( 'Tage', 'wir-sind-im-urlaub' ),
					'day'       => __( 'Tag', 'wir-sind-im-urlaub' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * AJAX: Schulferien laden
	 * ------------------------------------------------------------------ */

	public function ajax_school_holidays(): void {
		check_ajax_referer( 'wsiu_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'wir-sind-im-urlaub' ) ), 403 );
		}

		$land    = isset( $_POST['land'] ) ? sanitize_text_field( wp_unslash( $_POST['land'] ) ) : '';
		$refresh = ! empty( $_POST['refresh'] );

		$result = SchoolHolidays::get( $land, $refresh );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'holidays' => $result ) );
	}

	/* ---------------------------------------------------------------------
	 * Einstellungsseite
	 * ------------------------------------------------------------------ */

	public function render_page(): void {
		$s      = Settings::get();
		$status = Settings::status( $s );
		$themes = array(
			'ozean'   => __( 'Ozean', 'wir-sind-im-urlaub' ),
			'sonne'   => __( 'Sonnenuntergang', 'wir-sind-im-urlaub' ),
			'wald'    => __( 'Wald', 'wir-sind-im-urlaub' ),
			'nacht'   => __( 'Mitternacht', 'wir-sind-im-urlaub' ),
			'elegant' => __( 'Elegant', 'wir-sind-im-urlaub' ),
			'custom'  => __( 'Eigene Farben', 'wir-sind-im-urlaub' ),
		);
		?>
		<div class="wrap wsiu-admin">
			<form method="post" action="options.php" id="wsiu-form">
				<?php settings_fields( Settings::GROUP ); ?>

				<header class="wsiu-hero">
					<div class="wsiu-hero-title">
						<span class="wsiu-hero-emoji" aria-hidden="true">🌴</span>
						<div>
							<h1><?php esc_html_e( 'Wir sind im Urlaub', 'wir-sind-im-urlaub' ); ?></h1>
							<p class="wsiu-status wsiu-status--<?php echo esc_attr( $status['code'] ); ?>"><?php echo esc_html( $status['label'] ); ?></p>
						</div>
					</div>
					<label class="wsiu-master-toggle">
						<span><?php esc_html_e( 'Urlaubsmodus', 'wir-sind-im-urlaub' ); ?></span>
						<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="0">
						<input type="checkbox" id="wsiu-enabled" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>>
						<span class="wsiu-switch" aria-hidden="true"></span>
					</label>
				</header>

				<div class="wsiu-layout">
					<div class="wsiu-col-settings">

						<!-- Zeitraum -->
						<section class="wsiu-panel">
							<h2><span aria-hidden="true">📅</span> <?php esc_html_e( 'Zeitraum', 'wir-sind-im-urlaub' ); ?></h2>
							<div class="wsiu-grid-2">
								<div class="wsiu-field">
									<label for="wsiu-start-date"><?php esc_html_e( 'Erster Urlaubstag', 'wir-sind-im-urlaub' ); ?></label>
									<div class="wsiu-datetime">
										<input type="date" id="wsiu-start-date" name="<?php echo esc_attr( Settings::OPTION ); ?>[start_date]" value="<?php echo esc_attr( $s['start_date'] ); ?>">
										<input type="time" id="wsiu-start-time" name="<?php echo esc_attr( Settings::OPTION ); ?>[start_time]" value="<?php echo esc_attr( $s['start_time'] ); ?>" title="<?php esc_attr_e( 'Uhrzeit, ab der der Hinweis erscheint', 'wir-sind-im-urlaub' ); ?>">
									</div>
								</div>
								<div class="wsiu-field">
									<label for="wsiu-end-date"><?php esc_html_e( 'Letzter Urlaubstag', 'wir-sind-im-urlaub' ); ?></label>
									<div class="wsiu-datetime">
										<input type="date" id="wsiu-end-date" name="<?php echo esc_attr( Settings::OPTION ); ?>[end_date]" value="<?php echo esc_attr( $s['end_date'] ); ?>">
										<input type="time" id="wsiu-end-time" name="<?php echo esc_attr( Settings::OPTION ); ?>[end_time]" value="<?php echo esc_attr( $s['end_time'] ); ?>" title="<?php esc_attr_e( 'Uhrzeit, bis zu der der Hinweis sichtbar bleibt', 'wir-sind-im-urlaub' ); ?>">
									</div>
								</div>
							</div>
							<div class="wsiu-field wsiu-field--inline">
								<label for="wsiu-announce"><?php esc_html_e( 'Vorankündigung', 'wir-sind-im-urlaub' ); ?></label>
								<input type="number" id="wsiu-announce" min="0" max="365" name="<?php echo esc_attr( Settings::OPTION ); ?>[announce_days]" value="<?php echo esc_attr( $s['announce_days'] ); ?>">
								<span class="wsiu-hint"><?php esc_html_e( 'Tage vor Urlaubsbeginn bereits anzeigen (0 = erst ab dem ersten Urlaubstag).', 'wir-sind-im-urlaub' ); ?></span>
							</div>
							<p class="wsiu-note">
								<?php esc_html_e( 'Nach dem letzten Urlaubstag (bzw. der eingestellten End-Uhrzeit) verschwindet der Hinweis automatisch – ganz ohne weiteres Zutun.', 'wir-sind-im-urlaub' ); ?>
							</p>
						</section>

						<!-- Schulferien -->
						<section class="wsiu-panel">
							<h2><span aria-hidden="true">🎒</span> <?php esc_html_e( 'Schulferien übernehmen', 'wir-sind-im-urlaub' ); ?></h2>
							<p class="wsiu-hint"><?php esc_html_e( 'Ein Klick auf einen Vorschlag trägt Start- und Enddatum automatisch ein.', 'wir-sind-im-urlaub' ); ?></p>
							<div class="wsiu-ferien-controls">
								<select id="wsiu-bundesland" name="<?php echo esc_attr( Settings::OPTION ); ?>[bundesland]">
									<?php foreach ( Settings::bundeslaender() as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['bundesland'], $code ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button" id="wsiu-ferien-reload" title="<?php esc_attr_e( 'Neu laden', 'wir-sind-im-urlaub' ); ?>">&#8635;</button>
							</div>
							<div id="wsiu-ferien-list" class="wsiu-ferien-list" aria-live="polite"></div>
						</section>

						<!-- Darstellung -->
						<section class="wsiu-panel">
							<h2><span aria-hidden="true">🎨</span> <?php esc_html_e( 'Darstellung', 'wir-sind-im-urlaub' ); ?></h2>

							<div class="wsiu-field">
								<span class="wsiu-label"><?php esc_html_e( 'Anzeigeform', 'wir-sind-im-urlaub' ); ?></span>
								<div class="wsiu-segments" role="radiogroup">
									<?php
									$modes = array(
										'banner' => array( '▬', __( 'Balken', 'wir-sind-im-urlaub' ), __( 'Schmaler Hinweis am Seitenrand', 'wir-sind-im-urlaub' ) ),
										'popup'  => array( '❐', __( 'Popup', 'wir-sind-im-urlaub' ), __( 'Zentrales Fenster mit Overlay', 'wir-sind-im-urlaub' ) ),
										'card'   => array( '▢', __( 'Karte', 'wir-sind-im-urlaub' ), __( 'Schwebende Karte in der Ecke', 'wir-sind-im-urlaub' ) ),
									);
									foreach ( $modes as $value => $meta ) :
										?>
										<label class="wsiu-segment">
											<input type="radio" name="<?php echo esc_attr( Settings::OPTION ); ?>[display_mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['display_mode'], $value ); ?>>
											<span class="wsiu-segment-box">
												<span class="wsiu-segment-icon" aria-hidden="true"><?php echo esc_html( $meta[0] ); ?></span>
												<strong><?php echo esc_html( $meta[1] ); ?></strong>
												<small><?php echo esc_html( $meta[2] ); ?></small>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="wsiu-grid-2">
								<div class="wsiu-field" data-wsiu-show-for="banner">
									<label for="wsiu-banner-pos"><?php esc_html_e( 'Position des Balkens', 'wir-sind-im-urlaub' ); ?></label>
									<select id="wsiu-banner-pos" name="<?php echo esc_attr( Settings::OPTION ); ?>[banner_position]">
										<option value="top" <?php selected( $s['banner_position'], 'top' ); ?>><?php esc_html_e( 'Oben', 'wir-sind-im-urlaub' ); ?></option>
										<option value="bottom" <?php selected( $s['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'Unten', 'wir-sind-im-urlaub' ); ?></option>
									</select>
								</div>
								<div class="wsiu-field" data-wsiu-show-for="card">
									<label for="wsiu-card-pos"><?php esc_html_e( 'Position der Karte', 'wir-sind-im-urlaub' ); ?></label>
									<select id="wsiu-card-pos" name="<?php echo esc_attr( Settings::OPTION ); ?>[card_position]">
										<option value="bottom-right" <?php selected( $s['card_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Unten rechts', 'wir-sind-im-urlaub' ); ?></option>
										<option value="bottom-left" <?php selected( $s['card_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Unten links', 'wir-sind-im-urlaub' ); ?></option>
									</select>
								</div>
								<div class="wsiu-field" data-wsiu-show-for="popup">
									<label for="wsiu-popup-freq"><?php esc_html_e( 'Popup anzeigen', 'wir-sind-im-urlaub' ); ?></label>
									<select id="wsiu-popup-freq" name="<?php echo esc_attr( Settings::OPTION ); ?>[popup_frequency]">
										<option value="always" <?php selected( $s['popup_frequency'], 'always' ); ?>><?php esc_html_e( 'Bei jedem Seitenaufruf', 'wir-sind-im-urlaub' ); ?></option>
										<option value="session" <?php selected( $s['popup_frequency'], 'session' ); ?>><?php esc_html_e( 'Einmal pro Besuch', 'wir-sind-im-urlaub' ); ?></option>
										<option value="day" <?php selected( $s['popup_frequency'], 'day' ); ?>><?php esc_html_e( 'Einmal pro Tag', 'wir-sind-im-urlaub' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wsiu-field">
								<span class="wsiu-label"><?php esc_html_e( 'Farbwelt', 'wir-sind-im-urlaub' ); ?></span>
								<div class="wsiu-themes" role="radiogroup">
									<?php foreach ( $themes as $value => $label ) : ?>
										<label class="wsiu-theme-pick">
											<input type="radio" name="<?php echo esc_attr( Settings::OPTION ); ?>[theme]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['theme'], $value ); ?>>
											<span class="wsiu-swatch wsiu-swatch--<?php echo esc_attr( $value ); ?>" aria-hidden="true"></span>
											<span class="wsiu-theme-name"><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="wsiu-grid-3 wsiu-custom-colors" id="wsiu-custom-colors" <?php echo 'custom' === $s['theme'] ? '' : 'hidden'; ?>>
								<div class="wsiu-field">
									<label for="wsiu-color-start"><?php esc_html_e( 'Farbe 1', 'wir-sind-im-urlaub' ); ?></label>
									<input type="color" id="wsiu-color-start" name="<?php echo esc_attr( Settings::OPTION ); ?>[color_start]" value="<?php echo esc_attr( $s['color_start'] ); ?>">
								</div>
								<div class="wsiu-field">
									<label for="wsiu-color-end"><?php esc_html_e( 'Farbe 2', 'wir-sind-im-urlaub' ); ?></label>
									<input type="color" id="wsiu-color-end" name="<?php echo esc_attr( Settings::OPTION ); ?>[color_end]" value="<?php echo esc_attr( $s['color_end'] ); ?>">
								</div>
								<div class="wsiu-field">
									<label for="wsiu-color-text"><?php esc_html_e( 'Textfarbe', 'wir-sind-im-urlaub' ); ?></label>
									<input type="color" id="wsiu-color-text" name="<?php echo esc_attr( Settings::OPTION ); ?>[color_text]" value="<?php echo esc_attr( $s['color_text'] ); ?>">
								</div>
							</div>

							<div class="wsiu-field">
								<span class="wsiu-label"><?php esc_html_e( 'Symbol', 'wir-sind-im-urlaub' ); ?></span>
								<div class="wsiu-icons" role="radiogroup">
									<?php foreach ( Settings::icons() as $icon ) : ?>
										<label class="wsiu-icon-pick">
											<input type="radio" name="<?php echo esc_attr( Settings::OPTION ); ?>[icon]" value="<?php echo esc_attr( $icon ); ?>" <?php checked( $s['icon'], $icon ); ?>>
											<span class="wsiu-icon-box"><?php echo '' === $icon ? esc_html__( 'ohne', 'wir-sind-im-urlaub' ) : esc_html( $icon ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="wsiu-toggles">
								<label class="wsiu-toggle">
									<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[show_countdown]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[show_countdown]" value="1" <?php checked( $s['show_countdown'] ); ?>>
									<span class="wsiu-switch wsiu-switch--small" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Countdown anzeigen („Wieder da in X Tagen“)', 'wir-sind-im-urlaub' ); ?></span>
								</label>
								<label class="wsiu-toggle" data-wsiu-show-for="banner card">
									<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[dismissible]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[dismissible]" value="1" <?php checked( $s['dismissible'] ); ?>>
									<span class="wsiu-switch wsiu-switch--small" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Besucher dürfen den Hinweis schließen', 'wir-sind-im-urlaub' ); ?></span>
								</label>
							</div>
						</section>

						<!-- Texte -->
						<section class="wsiu-panel">
							<h2><span aria-hidden="true">✏️</span> <?php esc_html_e( 'Texte', 'wir-sind-im-urlaub' ); ?></h2>
							<div class="wsiu-field">
								<label for="wsiu-headline"><?php esc_html_e( 'Überschrift (während des Urlaubs)', 'wir-sind-im-urlaub' ); ?></label>
								<input type="text" id="wsiu-headline" name="<?php echo esc_attr( Settings::OPTION ); ?>[headline]" value="<?php echo esc_attr( $s['headline'] ); ?>">
							</div>
							<div class="wsiu-field">
								<label for="wsiu-message"><?php esc_html_e( 'Nachricht (während des Urlaubs)', 'wir-sind-im-urlaub' ); ?></label>
								<textarea id="wsiu-message" rows="3" name="<?php echo esc_attr( Settings::OPTION ); ?>[message]"><?php echo esc_textarea( $s['message'] ); ?></textarea>
							</div>
							<div class="wsiu-grid-2">
								<div class="wsiu-field">
									<label for="wsiu-headline-before"><?php esc_html_e( 'Überschrift (Vorankündigung)', 'wir-sind-im-urlaub' ); ?></label>
									<input type="text" id="wsiu-headline-before" name="<?php echo esc_attr( Settings::OPTION ); ?>[headline_before]" value="<?php echo esc_attr( $s['headline_before'] ); ?>">
								</div>
								<div class="wsiu-field">
									<label for="wsiu-button-text"><?php esc_html_e( 'Button-Text (Popup)', 'wir-sind-im-urlaub' ); ?></label>
									<input type="text" id="wsiu-button-text" name="<?php echo esc_attr( Settings::OPTION ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>">
								</div>
							</div>
							<div class="wsiu-field">
								<label for="wsiu-message-before"><?php esc_html_e( 'Nachricht (Vorankündigung)', 'wir-sind-im-urlaub' ); ?></label>
								<textarea id="wsiu-message-before" rows="2" name="<?php echo esc_attr( Settings::OPTION ); ?>[message_before]"><?php echo esc_textarea( $s['message_before'] ); ?></textarea>
							</div>
							<p class="wsiu-note">
								<?php esc_html_e( 'Platzhalter: {start} = erster Urlaubstag, {ende} = letzter Urlaubstag, {wieder_da} = Tag der Rückkehr.', 'wir-sind-im-urlaub' ); ?>
							</p>
						</section>

						<div class="wsiu-savebar">
							<button type="submit" class="wsiu-save"><?php esc_html_e( 'Einstellungen speichern', 'wir-sind-im-urlaub' ); ?></button>
							<a class="wsiu-preview-link" href="<?php echo esc_url( add_query_arg( 'wsiu_preview', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">
								<?php esc_html_e( 'Live auf der Webseite testen ↗', 'wir-sind-im-urlaub' ); ?>
							</a>
						</div>

						<p class="wsiu-footnote">
							<?php esc_html_e( 'Tipp: Mit dem Shortcode [wir_sind_im_urlaub] lässt sich der Hinweis zusätzlich in jede Seite einbetten.', 'wir-sind-im-urlaub' ); ?>
						</p>
					</div>

					<!-- Live-Vorschau -->
					<aside class="wsiu-col-preview">
						<div class="wsiu-preview-card">
							<h2><?php esc_html_e( 'Live-Vorschau', 'wir-sind-im-urlaub' ); ?></h2>
							<div class="wsiu-browser">
								<div class="wsiu-browser-bar" aria-hidden="true"><span></span><span></span><span></span></div>
								<div class="wsiu-preview-stage" id="wsiu-preview-stage"></div>
							</div>
							<p class="wsiu-hint"><?php esc_html_e( 'Die Vorschau aktualisiert sich sofort bei jeder Änderung.', 'wir-sind-im-urlaub' ); ?></p>
						</div>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}
}
