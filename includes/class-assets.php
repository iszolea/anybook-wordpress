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
 * Loads Anybook's widget script, only on pages that show the widget.
 *
 * The script is the documented service (readme, External services), loaded from the booking site's plain
 * address with no version, so a widget release reaches every site at once. It is the only remote code the plugin
 * loads. It runs `defer`, in the head when the page is known to hold the widget (so a guest's #bw= email link is
 * read before any tag manager can tidy the address), otherwise in the footer. Speed plugins are asked, through
 * their own filters and attributes, to leave it alone: combined, delayed or moved, it can miss the guest link.
 */
class Assets {

	const HANDLE = 'anybook-widget';

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
		return Settings::site() . '/widget/booking-widget.js';
	}

	/**
	 * Registers the script on every front-end view of a connected site, and enqueues it in the head when the
	 * viewed post holds the widget. Other views enqueue it when the widget renders (in the footer).
	 */
	public static function register() {
		if ( ! Settings::is_connected() ) {
			return;
		}
		$in_head = self::view_has_widget();
		wp_register_script(
			self::HANDLE,
			self::script_url(),
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- no ?ver=: Anybook serves the plain address fresh, so releases arrive at once.
			array(
				'strategy'  => 'defer',
				'in_footer' => ! $in_head,
			)
		);
		if ( $in_head ) {
			wp_enqueue_script( self::HANDLE );
		}
	}

	/**
	 * Called by the renderer: make sure the script is on this page. Block themes render the page before the head
	 * is printed, so then it still goes in the head; after the head (classic themes, widgets) it goes in the footer.
	 */
	public static function enqueue() {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script(
				self::HANDLE,
				self::script_url(),
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- as in register().
				array(
					'strategy'  => 'defer',
					'in_footer' => did_action( 'wp_head' ) > 0,
				)
			);
		}
		wp_enqueue_script( self::HANDLE );
	}

	/** Whether the queried post or page holds the block or the shortcode. */
	public static function view_has_widget() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof \WP_Post && Booking_Page::has_widget( $post->post_content );
	}

	/**
	 * Attributes on the widget's script tag that ask Cloudflare Rocket Loader, LiteSpeed, WP Rocket, Jetpack
	 * Boost and others to leave it as it is.
	 *
	 * @param array $attributes Script tag attributes.
	 */
	public static function script_attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['id'] ) || self::HANDLE . '-js' !== $attributes['id'] ) {
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
	 * Adds the script's path to a speed plugin's exclusion list.
	 *
	 * @param mixed $items Patterns.
	 */
	public static function add_script_path( $items ) {
		$items   = is_array( $items ) ? $items : array();
		$items[] = '/widget/booking-widget.js';
		return $items;
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
	 * Adds the script handle to a list of handles.
	 *
	 * @param mixed $items Handles.
	 */
	public static function add_handle( $items ) {
		$items   = is_array( $items ) ? $items : array();
		$items[] = self::HANDLE;
		return $items;
	}

	/**
	 * Adds the script to a comma-separated exclusion list.
	 *
	 * @param mixed $items Comma-separated patterns.
	 */
	public static function add_to_list( $items ) {
		$items = is_string( $items ) ? trim( $items ) : '';
		return ( '' === $items ? '' : $items . ', ' ) . 'widget/booking-widget.js';
	}

	/**
	 * W3 Total Cache: do not minify the widget's tag.
	 *
	 * @param bool   $do_minify  Whether W3TC would minify this tag.
	 * @param string $script_tag The tag.
	 */
	public static function w3tc_skip( $do_minify, $script_tag ) {
		return ( is_string( $script_tag ) && false !== strpos( $script_tag, '/widget/booking-widget.js' ) ) ? false : $do_minify;
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
