<?php
/**
 * Schulferien-Service.
 *
 * Primärquelle:  OpenHolidays API (openholidaysapi.org)
 * Fallback:      ferien-api.de
 * Ergebnisse werden 12 Stunden pro Bundesland gecacht.
 *
 * @package WirSindImUrlaub
 */

namespace WSIU;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchoolHolidays {

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;
	const MAX_ITEMS = 12;

	/**
	 * Kommende Schulferien für ein Bundesland.
	 *
	 * @param string $land    ISO-Code, z. B. "DE-NW".
	 * @param bool   $refresh Cache umgehen und neu laden.
	 * @return array|\WP_Error Liste aus { name, start, end } (Y-m-d, Ende inklusive).
	 */
	public static function get( string $land, bool $refresh = false ) {
		if ( ! array_key_exists( $land, Settings::bundeslaender() ) ) {
			return new \WP_Error( 'wsiu_invalid_state', __( 'Unbekanntes Bundesland.', 'wir-sind-im-urlaub' ) );
		}

		$cache_key = 'wsiu_ferien_' . strtolower( str_replace( '-', '_', $land ) );

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$holidays = self::from_openholidays( $land );
		if ( empty( $holidays ) ) {
			$holidays = self::from_ferien_api( $land );
		}

		if ( empty( $holidays ) ) {
			return new \WP_Error(
				'wsiu_api_unreachable',
				__( 'Die Ferien-Dienste sind gerade nicht erreichbar. Bitte später erneut versuchen oder die Daten manuell eintragen.', 'wir-sind-im-urlaub' )
			);
		}

		$holidays = self::finalize( $holidays );
		set_transient( $cache_key, $holidays, self::CACHE_TTL );

		return $holidays;
	}

	/**
	 * OpenHolidays API: SchoolHolidays für die nächsten 18 Monate.
	 */
	private static function from_openholidays( string $land ): array {
		$today = current_datetime();
		$url   = add_query_arg(
			array(
				'countryIsoCode' => 'DE',
				'subdivisionCode' => $land,
				'languageIsoCode' => 'DE',
				'validFrom'      => $today->format( 'Y-m-d' ),
				'validTo'        => $today->modify( '+18 months' )->format( 'Y-m-d' ),
			),
			'https://openholidaysapi.org/SchoolHolidays'
		);

		$data = self::fetch_json( $url );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$out = array();
		foreach ( $data as $item ) {
			if ( empty( $item['startDate'] ) || empty( $item['endDate'] ) ) {
				continue;
			}
			$out[] = array(
				'name'  => self::localized_name( $item['name'] ?? array() ),
				'start' => substr( $item['startDate'], 0, 10 ),
				'end'   => substr( $item['endDate'], 0, 10 ),
			);
		}
		return $out;
	}

	/**
	 * Fallback: ferien-api.de (aktuelles + nächstes Jahr).
	 */
	private static function from_ferien_api( string $land ): array {
		$short = substr( $land, 3 ); // "DE-NW" → "NW"
		$year  = (int) current_datetime()->format( 'Y' );
		$out   = array();

		foreach ( array( $year, $year + 1 ) as $y ) {
			$data = self::fetch_json( sprintf( 'https://ferien-api.de/api/v1/holidays/%s/%d', rawurlencode( $short ), $y ) );
			if ( ! is_array( $data ) ) {
				continue;
			}
			foreach ( $data as $item ) {
				if ( empty( $item['start'] ) || empty( $item['end'] ) ) {
					continue;
				}
				$out[] = array(
					'name'  => self::pretty_name( (string) ( $item['name'] ?? '' ) ),
					'start' => substr( $item['start'], 0, 10 ),
					'end'   => substr( $item['end'], 0, 10 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Filtern (nur kommende bzw. laufende Ferien), sortieren, begrenzen.
	 */
	private static function finalize( array $holidays ): array {
		$today = current_datetime()->format( 'Y-m-d' );

		$holidays = array_filter(
			$holidays,
			static function ( $h ) use ( $today ) {
				return ! empty( $h['name'] ) && $h['end'] >= $today && $h['start'] <= $h['end'];
			}
		);

		usort(
			$holidays,
			static function ( $a, $b ) {
				return strcmp( $a['start'], $b['start'] );
			}
		);

		// Duplikate (gleicher Zeitraum aus beiden Quellen) entfernen.
		$seen = array();
		$out  = array();
		foreach ( $holidays as $h ) {
			$key = $h['start'] . '|' . $h['end'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $h;
		}

		return array_slice( $out, 0, self::MAX_ITEMS );
	}

	/**
	 * HTTP-GET mit JSON-Dekodierung; bei Fehlern null.
	 */
	private static function fetch_json( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * OpenHolidays liefert Namen als Liste { language, text } – deutsche Variante bevorzugen.
	 */
	private static function localized_name( array $names ): string {
		$first = '';
		foreach ( $names as $n ) {
			if ( empty( $n['text'] ) ) {
				continue;
			}
			if ( '' === $first ) {
				$first = $n['text'];
			}
			if ( isset( $n['language'] ) && 'DE' === strtoupper( $n['language'] ) ) {
				return self::pretty_name( $n['text'] );
			}
		}
		return self::pretty_name( $first );
	}

	/**
	 * "sommerferien" → "Sommerferien".
	 */
	private static function pretty_name( string $name ): string {
		$name = trim( $name );
		if ( '' === $name ) {
			return '';
		}
		if ( function_exists( 'mb_convert_case' ) && $name === strtolower( $name ) ) {
			$name = mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
		}
		return $name;
	}

	/**
	 * Alle Ferien-Caches löschen (für Deinstallation).
	 */
	public static function purge_cache(): void {
		foreach ( array_keys( Settings::bundeslaender() ) as $land ) {
			delete_transient( 'wsiu_ferien_' . strtolower( str_replace( '-', '_', $land ) ) );
		}
	}
}
