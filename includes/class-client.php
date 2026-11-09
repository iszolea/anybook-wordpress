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
 * Every call is made because an administrator or editor acted (Connect, entering a business code, opening the
 * settings page once connected, or choosing a unit in the editor), never on a schedule or on a visitor's page
 * view (guideline 7). It sends the business code and this site's address, nothing else, and needs no key.
 */
class Client {

	/** Seconds to wait for Anybook when the admin has just acted (Connect, a business code). */
	const TIMEOUT = 5;

	/** Seconds to wait when a page only shows the status or the editor's lists: a page load must not hang. */
	const TIMEOUT_QUICK = 3;

	/** How long an answer is kept, in seconds. */
	const CACHE_TTL = 600;

	/**
	 * How long a failure is kept, in seconds (review Q2): while Anybook is unreachable, the settings page and the
	 * editor ask once a minute, not on every load.
	 */
	const FAIL_TTL = 60;

	/** The largest answer the plugin reads (review S-L3); Anybook's answers are a few kilobytes. */
	const MAX_BYTES = 524288;

	/** How often one person may skip the cache, in seconds (reviews S-L2, Q15). */
	const REFRESH_TTL = 30;

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
			$cached = self::cached( 'anybook_status', $key );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$url  = $site . '/api/public/v1/' . rawurlencode( $slug ) . '/site?origin=' . rawurlencode( $origin );
		$body = self::get_json( $url, $use_cache ? self::TIMEOUT_QUICK : self::TIMEOUT );
		$data = is_wp_error( $body ) ? $body : self::normalise_status( $body );
		self::remember( 'anybook_status', $key, $data );
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
	 * The units and services the block's chooser offers (ADR-6), from the public business and services lists.
	 *
	 * Asked only from the editor, by someone who can edit posts, and kept for ten minutes.
	 *
	 * @param bool $use_cache False to ask Anybook again.
	 * @return array|\WP_Error `{ units: [{id, code, name}], services: [{id, code, name}] }`.
	 */
	public static function catalog( $use_cache = true ) {
		$slug = Settings::clean_code( Settings::get( 'slug' ) );
		$site = Settings::site();
		if ( '' === $slug ) {
			return new \WP_Error( 'anybook_not_connected', __( 'This site is not connected to Anybook yet.', 'anybook' ) );
		}

		$key = $site . '|' . $slug;
		if ( $use_cache ) {
			$cached = self::cached( 'anybook_catalog', $key );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$data = self::fetch_catalog( $site . '/api/public/v1/' . rawurlencode( $slug ) );
		self::remember( 'anybook_catalog', $key, $data );
		return $data;
	}

	/**
	 * Whether this person may skip the cache now: administrators only, once every 30 seconds (reviews S-L2, Q15).
	 * Everyone else, and a second ask within 30 seconds, gets the cached answer.
	 *
	 * @param int $user_id WordPress user.
	 */
	public static function may_refresh( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$key = self::refresh_key( $user_id );
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, self::REFRESH_TTL );
		return true;
	}

	/**
	 * The transient that throttles a person's refreshes (uninstall deletes them by this prefix).
	 *
	 * @param int $user_id WordPress user.
	 */
	public static function refresh_key( $user_id ) {
		return 'anybook_refresh_' . (int) $user_id;
	}

	/**
	 * Units or services as `{id, code, name}`, keeping only ones with a usable code (a service without a code is
	 * chosen by its id, which the widget also accepts).
	 *
	 * @param array $items Decoded JSON list.
	 */
	public static function normalise_choices( array $items ) {
		$clean = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id   = isset( $item['id'] ) ? Settings::clean_code( $item['id'] ) : '';
			$code = isset( $item['code'] ) ? Settings::clean_code( $item['code'] ) : '';
			$code = '' !== $code ? $code : $id;
			if ( '' === $code ) {
				continue;
			}
			$name    = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '';
			$clean[] = array(
				'id'   => $id,
				'code' => $code,
				'name' => '' !== $name ? $name : $code,
			);
		}
		return $clean;
	}

	/**
	 * GET a JSON object from Anybook.
	 *
	 * Through wp_safe_remote_get (review S-M1), which refuses private addresses and unusual ports, unless the
	 * booking site is a developer's own machine (localhost or *.localhost) and WordPress's environment type is
	 * 'local' or 'development'. At most 512 KB is read (review S-L3).
	 *
	 * @param string $url     Full address on the booking site.
	 * @param int    $timeout Seconds to wait.
	 * @return array|\WP_Error
	 */
	public static function get_json( $url, $timeout = self::TIMEOUT ) {
		$args     = array(
			'timeout'             => $timeout,
			'redirection'         => 0,
			'limit_response_size' => self::MAX_BYTES,
			'headers'             => array( 'Accept' => 'application/json' ),
		);
		$response = Settings::local_sites_allowed() && Settings::is_local_host( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) )
			? wp_remote_get( $url, $args )
			: wp_safe_remote_get( $url, $args );
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
		$raw = (string) wp_remote_retrieve_body( $response );
		// An answer cut off at the limit is not read at all.
		$body = strlen( $raw ) >= self::MAX_BYTES ? null : json_decode( $raw, true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'anybook_unreachable', __( 'Anybook sent an answer the plugin could not read.', 'anybook' ) );
		}
		return $body;
	}

	/**
	 * Asks Anybook for the units and services.
	 *
	 * @param string $base The business's public API address.
	 * @return array|\WP_Error
	 */
	private static function fetch_catalog( $base ) {
		$business = self::get_json( $base, self::TIMEOUT_QUICK );
		if ( is_wp_error( $business ) ) {
			return $business;
		}
		$units = isset( $business['units'] ) && is_array( $business['units'] ) ? $business['units'] : array();

		// A business without appointments (or with online booking off) may answer 404 here: no services then.
		$services = self::get_json( $base . '/services', self::TIMEOUT_QUICK );
		if ( is_wp_error( $services ) ) {
			if ( 'anybook_unknown_business' !== $services->get_error_code() ) {
				return $services;
			}
			$services = array();
		}

		return array(
			'units'    => self::normalise_choices( $units ),
			'services' => self::normalise_choices( $services ),
		);
	}

	/**
	 * A cached answer for this key: the data, a WP_Error for a recent failure, or null.
	 *
	 * @param string $transient Transient name.
	 * @param string $key       What the answer is for.
	 * @return mixed
	 */
	private static function cached( $transient, $key ) {
		$cached = get_transient( $transient );
		if ( ! is_array( $cached ) || ! isset( $cached['key'] ) || $cached['key'] !== $key ) {
			return null;
		}
		if ( isset( $cached['error'] ) && is_array( $cached['error'] ) && 2 === count( $cached['error'] ) ) {
			return new \WP_Error( (string) $cached['error'][0], (string) $cached['error'][1] );
		}
		return isset( $cached['data'] ) ? $cached['data'] : null;
	}

	/**
	 * Keeps an answer for ten minutes, or a failure for one.
	 *
	 * @param string          $transient Transient name.
	 * @param string          $key       What the answer is for.
	 * @param array|\WP_Error $data      The answer.
	 */
	private static function remember( $transient, $key, $data ) {
		if ( is_wp_error( $data ) ) {
			$record = array(
				'key'   => $key,
				'error' => array( $data->get_error_code(), $data->get_error_message() ),
			);
			set_transient( $transient, $record, self::FAIL_TTL );
			return;
		}
		set_transient(
			$transient,
			array(
				'key'  => $key,
				'data' => $data,
			),
			self::CACHE_TTL
		);
	}
}
