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
 * `[anybook unit="" service="" services="yes" accent="" text="" guest_links="yes" button_text=""]`.
 *
 * It only maps its attributes to the renderer's options; checking and escaping happen in the renderer.
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
				'unit'        => '',
				'service'     => '',
				'services'    => '',
				'accent'      => '',
				'text'        => '',
				'guest_links' => '',
				'button_text' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$texts = array();
		if ( '' !== $atts['button_text'] ) {
			$texts['toPayment'] = $atts['button_text'];
		}

		return Renderer::render(
			array(
				'unit'        => $atts['unit'],
				'service'     => $atts['service'],
				'services'    => self::is_yes( $atts['services'] ),
				'accent'      => $atts['accent'],
				'text'        => $atts['text'],
				'guest_links' => self::is_yes( $atts['guest_links'] ),
				'texts'       => $texts,
			)
		);
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
