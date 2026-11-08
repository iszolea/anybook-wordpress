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

	/** The booking site for real businesses (Assumption 2). */
	const DEFAULT_SITE = 'https://site.anybookapp.com';

	/**
	 * The Dev install's host, the default of early builds, which saved it. It is never used from the settings:
	 * a site that saved it moves to DEFAULT_SITE, and only ANYBOOK_SITE in wp-config.php can reach it.
	 */
	const DEV_HOST = 'dev.anybookapp.com';

	/** Anybook's own domain: the settings page accepts a booking site on it or its subdomains only (review S-M1). */
	const ANYBOOK_DOMAIN = 'anybookapp.com';

	/**
	 * The wp-config.php constant that sets any other booking site (a test install, for example). Only whoever can
	 * edit wp-config.php can point the plugin, and the script it loads on every widget page, at another host.
	 */
	const SITE_CONSTANT = 'ANYBOOK_SITE';

	/**
	 * A small autoloaded option, '1' while a business is connected, so a page view on a site that is not connected
	 * needs no extra query (review S-I5). The settings themselves stay out of autoload (ADR-4).
	 */
	const CONNECTED_FLAG = 'anybook_connected';

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
		update_option( self::CONNECTED_FLAG, '' !== $all['slug'] ? '1' : '0', true );
	}

	/**
	 * Whether a business may be connected, from the autoloaded flag alone; the front end asks this first. A site
	 * that has no flag yet (settings saved by an earlier build) reads the settings.
	 */
	public static function maybe_connected() {
		$flag = get_option( self::CONNECTED_FLAG, null );
		return null === $flag || false === $flag ? self::is_connected() : '1' === $flag;
	}

	/** Whether a business is connected (by Connect or by its code). */
	public static function is_connected() {
		return '' !== self::get( 'slug' );
	}

	/**
	 * The booking site address without a trailing slash: ANYBOOK_SITE from wp-config.php when it is set and valid,
	 * otherwise the saved one if it is still an address the settings page accepts, otherwise site.anybookapp.com.
	 */
	public static function site() {
		$constant = self::site_from_constant();
		if ( '' !== $constant ) {
			return $constant;
		}
		$saved = self::form_site( (string) self::get( 'site' ) );
		return '' === $saved ? self::DEFAULT_SITE : $saved;
	}

	/** The booking site set by ANYBOOK_SITE in wp-config.php, or '' if it is not set or not a valid address. */
	public static function site_from_constant() {
		return defined( self::SITE_CONSTANT ) ? self::clean_site( constant( self::SITE_CONSTANT ) ) : '';
	}

	/**
	 * A booking site the settings page may save, or ''. Anybook's own hosts (https only), or a developer's own
	 * machine; any other host must come from ANYBOOK_SITE (review S-M1), so a site admin cannot load a script from
	 * a host of their choosing and a lookalike address is refused.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function form_site( $value ) {
		$clean = self::clean_site( $value );
		if ( '' === $clean ) {
			return '';
		}
		$host = (string) wp_parse_url( $clean, PHP_URL_HOST );
		if ( self::DEV_HOST === $host ) {
			return '';
		}
		if ( self::is_local_host( $host ) ) {
			return $clean;
		}
		return ( 0 === strpos( $clean, 'https://' ) && self::is_anybook_host( $host ) ) ? $clean : '';
	}

	/**
	 * Whether a host is anybookapp.com or one of its subdomains.
	 *
	 * @param string $host Lower-case host.
	 */
	public static function is_anybook_host( $host ) {
		$host   = strtolower( (string) $host );
		$suffix = '.' . self::ANYBOOK_DOMAIN;
		return self::ANYBOOK_DOMAIN === $host || ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix );
	}

	/** Whether the booking site is a developer's own machine (then the server may call it on any port). */
	public static function site_is_local() {
		return self::is_local_host( (string) wp_parse_url( self::site(), PHP_URL_HOST ) );
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
	 * test install (localhost, 127.0.0.1, *.localhost), the same list as Anybook's local guest pages.
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
	 * Whether a host name is a developer's own machine. The same list as Anybook's (review S-I2): *.test is not on it.
	 *
	 * @param string $host Lower-case host.
	 */
	public static function is_local_host( $host ) {
		return in_array( $host, array( 'localhost', '127.0.0.1' ), true )
			|| (bool) preg_match( '/\.localhost$/', $host );
	}

	/**
	 * The origin (scheme://host[:port]) of this WordPress site's front end: the website Anybook allows.
	 */
	public static function site_origin() {
		return self::origin_of( home_url() );
	}

	/** The origin of the WordPress admin, where Anybook sends the browser back to (review S-I1). */
	public static function admin_origin() {
		return self::origin_of( admin_url() );
	}

	/**
	 * Whether other sites share this site's address, so whatever a business allows for this website it allows for
	 * them as well (review S-L1): a subdirectory network, or WordPress in a folder of a bigger site.
	 */
	public static function shares_origin() {
		if ( is_multisite() && ! is_subdomain_install() ) {
			return true;
		}
		$path = wp_parse_url( home_url(), PHP_URL_PATH );
		return is_string( $path ) && '' !== trim( $path, '/' );
	}

	/**
	 * The origin (scheme://host[:port]) of an address.
	 *
	 * @param string $url Address.
	 */
	private static function origin_of( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . strtolower( $parts['host'] ) . $port;
	}
}
