<?php
/**
 * Connect to Anybook in the browser, the business-code fallback and disconnect (ADR-2, ADR-10).
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The connection between this site and one Anybook business.
 *
 * Connect sends the admin's browser to Anybook with a single-use state; the Owner allows the site there; Anybook
 * sends the browser back with the business code and the same state. The return is only a hint: the plugin saves
 * the business only after the public site-status route confirms it exists. A forged return could at worst save a
 * business code, which is public and harmless (the widget works only where that business allowed the site).
 */
class Connection {

	/** How long a Connect may take before its state expires: 15 minutes. */
	const STATE_TTL = 900;

	/** Anybook's answers to a refused or cancelled connect. */
	const ERRORS = array( 'cancelled', 'not-owner', 'not-approved' );

	/**
	 * Starts a connect for this user and gives the Anybook address to send the browser to.
	 *
	 * @param int    $user_id    The WordPress user connecting.
	 * @param string $guest_page Address of the booking page to offer as the guest page, or ''.
	 * @param bool   $page_made  Whether that page is a draft made for this connect (published once the Owner agrees).
	 */
	public static function start( $user_id, $guest_page = '', $page_made = false ) {
		$state = wp_generate_password( 32, false );
		// Only a hash is kept, tied to this user; a newer Connect replaces an older one.
		set_transient(
			self::state_key( $user_id ),
			array(
				'hash'      => hash( 'sha256', $state ),
				'page_made' => (bool) $page_made,
			),
			self::STATE_TTL
		);
		return self::connect_url( $state, $guest_page );
	}

	/**
	 * The address of Anybook's connect page for a state (contracts.md: GET /connect/wordpress).
	 *
	 * @param string $state      Single-use state.
	 * @param string $guest_page Guest page to offer, or ''.
	 */
	public static function connect_url( $state, $guest_page = '' ) {
		$args = array(
			'return'    => self::return_url(),
			'state'     => $state,
			'site_name' => wp_strip_all_tags( get_bloginfo( 'name' ) ),
		);
		if ( '' !== $guest_page ) {
			$args['guest_page'] = $guest_page;
		}
		return Settings::site() . '/connect/wordpress?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Where Anybook sends the browser back: this plugin's page, which Anybook requires to be
	 * .../wp-admin/admin.php?page=anybook (ADR-3).
	 */
	public static function return_url() {
		return admin_url( 'admin.php?page=' . Admin::PAGE );
	}

	/**
	 * Handles the browser coming back from Anybook.
	 *
	 * @param array $query   The return's query values (unslashed).
	 * @param int   $user_id The WordPress user.
	 * @return string A notice code for the settings page.
	 */
	public static function handle_return( array $query, $user_id ) {
		$record = self::consume_state( $user_id, isset( $query['state'] ) ? $query['state'] : '' );
		if ( null === $record ) {
			return 'bad-state';
		}

		if ( isset( $query['anybook_error'] ) ) {
			// A page drafted for this connect stays a draft: the Owner did not agree (ADR-7, review Q1).
			$reason = (string) $query['anybook_error'];
			return in_array( $reason, self::ERRORS, true ) ? 'error-' . $reason : 'error-other';
		}

		$slug = Settings::clean_code( isset( $query['anybook_slug'] ) ? $query['anybook_slug'] : '' );
		if ( '' === $slug ) {
			return 'bad-return';
		}
		$fallback_name = isset( $query['anybook_name'] ) ? sanitize_text_field( (string) $query['anybook_name'] ) : '';
		// A business waiting for approval may connect, but the site-status route answers 404 until it is approved
		// (ADR-19). The state shows this return closes a connect this user started, and a business code is public,
		// so it is saved and the settings page says the widget waits for approval.
		$notice = self::save_business( $slug, $fallback_name, true );
		// The Owner agreed on Anybook: only now is the page drafted for this connect published (review Q1).
		if ( $record['page_made'] && in_array( $notice, array( 'connected', 'connected-pending' ), true ) ) {
			Booking_Page::publish( Booking_Page::current_id() );
		}
		return $notice;
	}

	/**
	 * The fallback: an admin types the business code (staff who are not the Owner, or a site Anybook cannot
	 * return to). It saves the business only; allowed websites and the guest page stay as set on Anybook.
	 *
	 * @param string $code Typed code.
	 * @return string A notice code.
	 */
	public static function save_code( $code ) {
		$slug = Settings::clean_code( $code );
		return '' === $slug ? 'bad-code' : self::save_business( $slug, '' );
	}

	/**
	 * Forgets the business. The site stays on Anybook's allowed websites until the business removes it there:
	 * the plugin has no credential to do it (ADR-10).
	 */
	public static function disconnect() {
		Settings::update(
			array(
				'slug'         => '',
				'name'         => '',
				'connected_at' => '',
			)
		);
		Settings::clear_cache();
	}

	/**
	 * Confirms a business through the site-status route, then saves it.
	 *
	 * @param string $slug          Clean business code.
	 * @param string $fallback_name Name to use if Anybook sends none.
	 * @param bool   $from_connect  True for a return from Connect, where a 404 means "not approved yet".
	 */
	private static function save_business( $slug, $fallback_name, $from_connect = false ) {
		$status  = Client::site_status( $slug, false );
		$pending = false;
		if ( is_wp_error( $status ) ) {
			if ( 'anybook_unknown_business' !== $status->get_error_code() ) {
				return 'unreachable';
			}
			if ( ! $from_connect ) {
				return 'unknown-business';
			}
			$pending = true;
		}
		$name = ( ! $pending && '' !== $status['name'] ) ? $status['name'] : $fallback_name;
		Settings::update(
			array(
				'slug'         => $slug,
				'name'         => '' !== $name ? $name : $slug,
				'connected_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
		return $pending ? 'connected-pending' : 'connected';
	}

	/**
	 * Takes the user's pending state if it matches, so it can be used once only.
	 *
	 * @param int   $user_id WordPress user.
	 * @param mixed $state   State from the return.
	 * @return array|null The stored record, or null if there is none or it does not match.
	 */
	private static function consume_state( $user_id, $state ) {
		$key    = self::state_key( $user_id );
		$record = get_transient( $key );
		if ( ! is_array( $record ) || empty( $record['hash'] ) || ! is_string( $state ) || '' === $state ) {
			return null;
		}
		if ( ! hash_equals( $record['hash'], hash( 'sha256', $state ) ) ) {
			return null;
		}
		delete_transient( $key );
		return array( 'page_made' => ! empty( $record['page_made'] ) );
	}

	/**
	 * The transient holding a user's pending state.
	 *
	 * @param int $user_id WordPress user.
	 */
	public static function state_key( $user_id ) {
		return 'anybook_state_' . (int) $user_id;
	}
}
