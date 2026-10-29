<?php
/**
 * The plugin's one stored option.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the option `anybook_settings` (ADR-4).
 *
 * It holds public facts only: the booking site address, the business code and name, when it connected, the
 * booking page the plugin made and default colours. Never a key, password or token: there is nothing here
 * worth stealing, so every administrator, plugin and backup that can read wp_options may read it.
 */
class Settings {

	const OPTION = 'anybook_settings';

	/** The booking site for real businesses; test installs change it under Advanced (Assumption 2). */
	const DEFAULT_SITE = 'https://app.anybookapp.com';

	/** Transients the plugin keeps (10 minutes each); uninstall and disconnect delete them. */
	const TRANSIENTS = array( 'anybook_status', 'anybook_catalog' );

	/**
	 * The stored settings with every key present.
	 *
	 * @return array{site:string,slug:string,name:string,connected_at:string,page_id:int,accent:string,text:string}
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key A key from defaults().
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Changes some settings and keeps the rest. Unknown keys are dropped so nothing else creeps into the option.
	 *
	 * @param array $changes Key to new value.
	 */
	public static function update( array $changes ) {
		$all = array_merge( self::all(), array_intersect_key( $changes, self::defaults() ) );
		// Not autoloaded: only pages with the widget and the settings page need it (ADR-4).
		update_option( self::OPTION, $all, false );
	}

	/** Whether a business is connected (by Connect or by its code). */
	public static function is_connected() {
		return '' !== self::get( 'slug' );
	}

	/** The booking site address without a trailing slash. */
	public static function site() {
		$site = (string) self::get( 'site' );
		return '' === $site ? self::DEFAULT_SITE : untrailingslashit( $site );
	}

	/** Forgets the cached answers from Anybook, for example after connecting or changing the site. */
	public static function clear_cache() {
		foreach ( self::TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}
	}

	/** Every key the option may hold, with its empty value. */
	public static function defaults() {
		return array(
			'site'         => self::DEFAULT_SITE,
			'slug'         => '',
			'name'         => '',
			'connected_at' => '',
			'page_id'      => 0,
			'accent'       => '',
			'text'         => '',
		);
	}

	/**
	 * A business code as Anybook writes them, or '' if it is not one. Letters, digits, '-' and '_' only, so it is
	 * safe in a URL path and in an HTML attribute.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function clean_code( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $value ) ? $value : '';
	}

	/**
	 * A colour as #rgb or #rrggbb, or '' if it is not one (the widget reads it into a CSS custom property).
	 *
	 * @param mixed $value Raw input.
	 */
	public static function clean_colour( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $value ) ? strtolower( $value ) : '';
	}

	/**
	 * A booking site address the plugin will talk to, or '' if it is not acceptable.
	 *
	 * Https only, with no user name, password, path, query or fragment. Plain http is allowed only for a local
	 * test install (localhost, 127.0.0.1, *.localhost, *.test), as Anybook allows local guest pages.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function clean_site( $value ) {
		$value = is_string( $value ) ? untrailingslashit( trim( $value ) ) : '';
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		if ( isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== $parts['path'] ) {
			return '';
		}
		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( $parts['host'] );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && self::is_local_host( $host ) ) ) {
			return '';
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}

	/**
	 * Whether a host name is a developer's own machine.
	 *
	 * @param string $host Lower-case host.
	 */
	public static function is_local_host( $host ) {
		return in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true )
			|| (bool) preg_match( '/\.(localhost|test)$/', $host );
	}

	/**
	 * The origin (scheme://host[:port]) of this WordPress site's front end: the website Anybook allows.
	 */
	public static function site_origin() {
		$parts = wp_parse_url( home_url() );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . strtolower( $parts['host'] ) . $port;
	}
}
