<?php
/**
 * The [anybook] shortcode (ADR-6), for the classic editor and page builders.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `[anybook show="all|unit|appointments|service" unit="" service="" services="yes" accent="" text=""
 * guest_links="yes" button_text="" details_heading="" dates_text=""]`, the same choices as the block.
 *
 * The availability overview: `[anybook show="overview" weeks="12" prices="yes" units="belle,kayak" rows="people"
 * service="<service id>"]`.
 *
 * It only maps its attributes to the renderer; checking and escaping happen there (Renderer::options_for()).
 */
class Shortcode {

	const TAG = 'anybook';

	/** Registers the shortcode. */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * The widget for the shortcode's attributes.
	 *
	 * @param array|string $atts Shortcode attributes ('' when none).
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'show'            => '',
				'unit'            => '',
				'service'         => '',
				'services'        => '',
				'accent'          => '',
				'text'            => '',
				'guest_links'     => '',
				'button_text'     => '',
				'details_heading' => '',
				'dates_text'      => '',
				'weeks'           => '',
				'prices'          => '',
				'units'           => '',
				'rows'            => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$show = strtolower( trim( (string) $atts['show'] ) );
		if ( '' === $show && self::is_yes( $atts['services'] ) && '' === trim( (string) $atts['service'] ) ) {
			// services="yes" is the older way to say show="appointments".
			$show = 'appointments';
		}
		$atts['show']        = $show;
		$atts['guest_links'] = self::is_yes( $atts['guest_links'] );
		// Only prices="yes" shows the overview's prices.
		$atts['prices'] = 'yes' === strtolower( trim( (string) $atts['prices'] ) );

		return Renderer::render( Renderer::options_for( $atts ) );
	}

	/**
	 * Whether a shortcode value means yes (yes, true, 1 or on).
	 *
	 * @param mixed $value Attribute value.
	 */
	public static function is_yes( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on' ), true );
	}
}
