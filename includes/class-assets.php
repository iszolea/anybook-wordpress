<?php
/**
 * Loading the widget script (ADR-8).
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads Anybook's widget script, only on pages that show the widget, and the availability overview's script
 * (booking-overview.js) on pages that show the overview (PBI 30581). A page with both gets both.
 *
 * The scripts are the documented service (readme, External services), loaded from the booking site's plain
 * address with no version, so a widget release reaches every site at once. They are the only remote code the
 * plugin loads. Each runs `defer`, in the head when the page is known to hold it (so a customer's #bw= email link
 * is read before any tag manager can tidy the address), otherwise in the footer. Speed plugins are asked, through
 * their own filters and attributes, to leave both alone: combined, delayed or moved, the widget can miss the
 * guest link.
 */
class Assets {

	const HANDLE = 'anybook-widget';

	/** The availability overview's script. */
	const OVERVIEW_HANDLE = 'anybook-overview';

	/** Each script's path on the booking site, by handle. */
	const PATHS = array(
		self::HANDLE          => '/widget/booking-widget.js',
		self::OVERVIEW_HANDLE => '/widget/booking-overview.js',
	);

	/** Hooks the loader and the speed-plugin exclusions. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_filter( 'wp_script_attributes', array( __CLASS__, 'script_attributes' ) );

		// WP Rocket: not delayed, combined, deferred again or minified.
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'add_script_path' ) );
		add_filter( 'rocket_exclude_js', array( __CLASS__, 'add_script_path' ) );
		add_filter( 'rocket_exclude_defer_js', array( __CLASS__, 'add_script_path' ) );
		add_filter( 'rocket_minify_excluded_external_js', array( __CLASS__, 'add_site_host' ) );
		// LiteSpeed Cache: not combined, minified or delayed.
		add_filter( 'litespeed_optimize_js_excludes', array( __CLASS__, 'add_script_path' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( __CLASS__, 'add_script_path' ) );
		// SiteGround Optimizer works by script handle.
		add_filter( 'sgo_js_minify_exclude', array( __CLASS__, 'add_handle' ) );
		add_filter( 'sgo_javascript_combine_exclude', array( __CLASS__, 'add_handle' ) );
		add_filter( 'sgo_js_async_exclude', array( __CLASS__, 'add_handle' ) );
		// Autoptimize keeps a comma-separated list.
		add_filter( 'autoptimize_filter_js_exclude', array( __CLASS__, 'add_to_list' ) );
		// W3 Total Cache asks tag by tag.
		add_filter( 'w3tc_minify_js_do_tag_minification', array( __CLASS__, 'w3tc_skip' ), 10, 2 );
	}

	/** The widget script's address on the booking site. */
	public static function script_url() {
		return Settings::site() . self::PATHS[ self::HANDLE ];
	}

	/** The overview script's address on the booking site. */
	public static function overview_script_url() {
		return Settings::site() . self::PATHS[ self::OVERVIEW_HANDLE ];
	}

	/**
	 * On a front-end view of a connected site whose post holds the widget or the overview, enqueues its script in
	 * the head (both when it holds both). Other views get them from enqueue() and enqueue_overview() when they
	 * render. A site that is not connected reads only the small autoloaded flag here (review S-I5), and a view
	 * without either reads nothing more.
	 */
	public static function register() {
		if ( ! Settings::maybe_connected() || ! self::view_has_widget() || ! Settings::is_connected() ) {
			return;
		}
		$shows = self::view_shows();
		if ( $shows['overview'] ) {
			self::register_script( self::OVERVIEW_HANDLE, false );
			wp_enqueue_script( self::OVERVIEW_HANDLE );
		}
		// A page builder that only said "yes" through the filter gets the widget script, as before.
		if ( $shows['widget'] || ! $shows['overview'] ) {
			self::register_script( self::HANDLE, false );
			wp_enqueue_script( self::HANDLE );
		}
	}

	/**
	 * Called by the renderer: make sure the script is on this page. Block themes render the page before the head
	 * is printed, so then it still goes in the head; after the head (classic themes, widgets) it goes in the footer.
	 */
	public static function enqueue() {
		self::enqueue_handle( self::HANDLE );
	}

	/** Called by the renderer for the overview: make sure its script is on this page, as enqueue() does. */
	public static function enqueue_overview() {
		self::enqueue_handle( self::OVERVIEW_HANDLE );
	}

	/**
	 * Registers a script if it is not yet (head or footer, see enqueue()) and enqueues it.
	 *
	 * @param string $handle HANDLE or OVERVIEW_HANDLE.
	 */
	private static function enqueue_handle( $handle ) {
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			self::register_script( $handle, did_action( 'wp_head' ) > 0 );
		}
		wp_enqueue_script( $handle );
	}

	/**
	 * Registers the widget or the overview script, deferred and with no version.
	 *
	 * @param string $handle    HANDLE or OVERVIEW_HANDLE.
	 * @param bool   $in_footer Whether it goes in the footer.
	 */
	private static function register_script( $handle, $in_footer ) {
		wp_register_script(
			$handle,
			Settings::site() . self::PATHS[ $handle ],
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- no ?ver=: Anybook serves the plain address fresh, so releases arrive at once.
			array(
				'strategy'  => 'defer',
				'in_footer' => (bool) $in_footer,
			)
		);
	}

	/** Whether the queried post or page shows the widget (in its content or a page builder's, see Booking_Page). */
	public static function view_has_widget() {
		if ( ! is_singular() ) {
			return false;
		}
		return Booking_Page::post_has_widget( get_queried_object() );
	}

	/**
	 * What the queried post shows: the widget, the overview or both. It reads the post's content and Elementor's
	 * copy of it (see Booking_Page::post_has_widget()), so call it only once view_has_widget() said yes.
	 *
	 * @return array{widget: bool, overview: bool}
	 */
	public static function view_shows() {
		$post = get_queried_object();
		$text = $post instanceof \WP_Post ? (string) $post->post_content : '';
		if ( $post instanceof \WP_Post ) {
			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			$text     .= is_string( $elementor ) ? "\n" . $elementor : '';
		}
		return self::shows_in( $text );
	}

	/**
	 * Whether some content holds the widget, the overview or both: each `[anybook ...]` shortcode and
	 * `anybook/booking` block counts as the overview when it says show="overview", otherwise as the widget. Elementor
	 * keeps the shortcode JSON-escaped (show=\"overview\"), which is read the same.
	 *
	 * @param string $text Post content.
	 * @return array{widget: bool, overview: bool}
	 */
	public static function shows_in( $text ) {
		$shows = array(
			'widget'   => false,
			'overview' => false,
		);
		$text  = (string) $text;
		if ( preg_match_all( '/\[anybook(?:\s[^\]]*)?\]/', $text, $found ) ) {
			foreach ( $found[0] as $shortcode ) {
				$kind           = preg_match( '/\sshow\s*=\s*\\\\?["\']?overview\b/i', $shortcode ) ? 'overview' : 'widget';
				$shows[ $kind ] = true;
			}
		}
		if ( preg_match_all( '#wp:anybook\\\\?/booking(?:\s+(\{.*?\}))?\s*/?-->#s', $text, $found ) ) {
			foreach ( $found[1] as $settings ) {
				$kind           = preg_match( '/\\\\?"show\\\\?"\s*:\s*\\\\?"overview\\\\?"/', $settings ) ? 'overview' : 'widget';
				$shows[ $kind ] = true;
			}
		}
		return $shows;
	}

	/**
	 * Attributes on the widget's and the overview's script tags that ask Cloudflare Rocket Loader, LiteSpeed, WP Rocket, Jetpack
	 * Boost and others to leave it as it is.
	 *
	 * @param array $attributes Script tag attributes.
	 */
	public static function script_attributes( $attributes ) {
		$ids = array( self::HANDLE . '-js', self::OVERVIEW_HANDLE . '-js' );
		if ( ! is_array( $attributes ) || ! isset( $attributes['id'] ) || ! in_array( $attributes['id'], $ids, true ) ) {
			return $attributes;
		}
		return array_merge(
			$attributes,
			array(
				'data-cfasync'       => 'false',
				'data-no-optimize'   => '1',
				'data-no-defer'      => '1',
				'data-no-minify'     => '1',
				'data-jetpack-boost' => 'ignore',
				'nowprocket'         => true,
			)
		);
	}

	/**
	 * Adds the scripts' paths to a speed plugin's exclusion list.
	 *
	 * @param mixed $items Patterns.
	 */
	public static function add_script_path( $items ) {
		$items = is_array( $items ) ? $items : array();
		return array_merge( $items, array_values( self::PATHS ) );
	}

	/**
	 * Adds the booking site's host to a list of external hosts to leave alone.
	 *
	 * @param mixed $items Hosts.
	 */
	public static function add_site_host( $items ) {
		$items   = is_array( $items ) ? $items : array();
		$host    = wp_parse_url( Settings::site(), PHP_URL_HOST );
		$items[] = is_string( $host ) ? $host : '';
		return array_values( array_filter( $items ) );
	}

	/**
	 * Adds the scripts' handles to a list of handles.
	 *
	 * @param mixed $items Handles.
	 */
	public static function add_handle( $items ) {
		$items = is_array( $items ) ? $items : array();
		return array_merge( $items, array_keys( self::PATHS ) );
	}

	/**
	 * Adds the scripts to a comma-separated exclusion list.
	 *
	 * @param mixed $items Comma-separated patterns.
	 */
	public static function add_to_list( $items ) {
		$items = is_string( $items ) ? trim( $items ) : '';
		$mine  = implode( ', ', array_map( 'ltrim', array_values( self::PATHS ), array( '/', '/' ) ) );
		return ( '' === $items ? '' : $items . ', ' ) . $mine;
	}

	/**
	 * W3 Total Cache: do not minify the widget's or the overview's tag.
	 *
	 * @param bool   $do_minify  Whether W3TC would minify this tag.
	 * @param string $script_tag The tag.
	 */
	public static function w3tc_skip( $do_minify, $script_tag ) {
		if ( ! is_string( $script_tag ) ) {
			return $do_minify;
		}
		foreach ( self::PATHS as $path ) {
			if ( false !== strpos( $script_tag, $path ) ) {
				return false;
			}
		}
		return $do_minify;
	}

	/**
	 * Speed plugins that are active, for the settings page's note.
	 *
	 * @return string[] Display names.
	 */
	public static function active_optimisers() {
		$known = array(
			'WP Rocket'            => defined( 'WP_ROCKET_VERSION' ),
			'LiteSpeed Cache'      => defined( 'LSCWP_V' ),
			'Autoptimize'          => defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ),
			'SiteGround Optimizer' => defined( 'SiteGround_Optimizer\VERSION' ),
			'W3 Total Cache'       => defined( 'W3TC' ),
			'Jetpack Boost'        => defined( 'JETPACK_BOOST_VERSION' ),
		);
		return array_keys( array_filter( $known ) );
	}
}
