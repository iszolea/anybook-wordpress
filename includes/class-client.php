<?php
/**
 * Calls from this WordPress server to Anybook's public API.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's only server-side contact with Anybook.
 *
 * Every call is made because an administrator acted (Connect, entering a business code, opening the settings
 * page once connected, or choosing a unit in the editor), never on a schedule or on a visitor's page view
 * (guideline 7). It sends the business code and this site's address, nothing else, and needs no key.
 */
class Client {

	/** Seconds to wait for Anybook before giving up: the settings page must not hang. */
	const TIMEOUT = 5;

	/** How long an answer is kept, in seconds. */
	const CACHE_TTL = 600;

	/**
	 * The site-status answer for a business and this site (ADR-5).
	 *
	 * @param string $slug      Business code.
	 * @param bool   $use_cache False to ask Anybook again.
	 * @param string $site      Booking site; defaults to the stored one.
	 * @return array|\WP_Error The normalised status, or why it could not be had ('anybook_unknown_business' for 404).
	 */
	public static function site_status( $slug, $use_cache = true, $site = '' ) {
		$slug   = Settings::clean_code( $slug );
		$site   = '' === $site ? Settings::site() : $site;
		$origin = Settings::site_origin();
		if ( '' === $slug ) {
			return new \WP_Error( 'anybook_unknown_business', __( 'That is not a business code.', 'anybook' ) );
		}

		// The cache remembers which business, site and origin it answers for, so a change never shows stale ticks.
		$key = $site . '|' . $slug . '|' . $origin;
		if ( $use_cache ) {
			$cached = get_transient( 'anybook_status' );
			if ( is_array( $cached ) && isset( $cached['key'], $cached['data'] ) && $cached['key'] === $key ) {
				return $cached['data'];
			}
		}

		$url  = $site . '/api/public/v1/' . rawurlencode( $slug ) . '/site?origin=' . rawurlencode( $origin );
		$body = self::get_json( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$data = self::normalise_status( $body );
		set_transient(
			'anybook_status',
			array(
				'key'  => $key,
				'data' => $data,
			),
			self::CACHE_TTL
		);
		return $data;
	}

	/**
	 * Keeps only the fields the plugin uses, with the types it expects: the answer is outside data.
	 *
	 * @param array $body Decoded JSON.
	 */
	public static function normalise_status( array $body ) {
		$guest = isset( $body['guestPage'] ) ? (string) $body['guestPage'] : 'none';
		$csp   = array();
		if ( isset( $body['cspSources'] ) && is_array( $body['cspSources'] ) ) {
			foreach ( $body['cspSources'] as $line ) {
				if ( ! is_array( $line ) || empty( $line['directive'] ) ) {
					continue;
				}
				$sources = isset( $line['sources'] ) ? (array) $line['sources'] : array();
				$csp[]   = array(
					'directive' => sanitize_text_field( (string) $line['directive'] ),
					'sources'   => array_values( array_map( 'sanitize_text_field', array_map( 'strval', $sources ) ) ),
				);
			}
		}
		return array(
			'name'          => isset( $body['name'] ) ? sanitize_text_field( (string) $body['name'] ) : '',
			'onlineBooking' => ! empty( $body['onlineBooking'] ),
			'approved'      => ! empty( $body['approved'] ),
			'originAllowed' => ! empty( $body['originAllowed'] ),
			'guestPage'     => in_array( $guest, array( 'this-site', 'elsewhere', 'none' ), true ) ? $guest : 'none',
			'hasUnits'      => ! empty( $body['hasUnits'] ),
			'hasServices'   => ! empty( $body['hasServices'] ),
			'cspSources'    => $csp,
		);
	}

	/**
	 * GET a JSON object from Anybook.
	 *
	 * @param string $url Full https address on the booking site.
	 * @return array|\WP_Error
	 */
	public static function get_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'anybook_unreachable', __( 'Anybook could not be reached. Please try again in a minute.', 'anybook' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) {
			// Anybook answers 404 alike for an unknown code and a business not approved yet: it never says which (ADR-19).
			return new \WP_Error( 'anybook_unknown_business', __( 'Anybook does not show this business yet: it is waiting for Anybook\'s approval, or the business code is wrong.', 'anybook' ) );
		}
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			return new \WP_Error( 'anybook_unreachable', sprintf( __( 'Anybook answered with an error (%d). Please try again in a minute.', 'anybook' ), $code ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'anybook_unreachable', __( 'Anybook sent an answer the plugin could not read.', 'anybook' ) );
		}
		return $body;
	}
}
