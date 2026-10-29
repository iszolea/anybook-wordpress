<?php
/**
 * The one place the booking widget's HTML is made.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns clean options into the `<booking-widget>` element (contracts.md, "Block and shortcode"), or the
 * `<booking-overview>` element for the availability overview (PBI 30581).
 *
 * The block and the shortcode both use it, so the element is written in one place only and
 * every value is checked and escaped here, whatever way it came in.
 */
class Renderer {

	/** What a block or shortcode can show. */
	const SHOW = array( 'all', 'unit', 'appointments', 'service', 'overview' );

	/** The overview's weeks: the fewest, the most, and the default (left out of the element). */
	const WEEKS_MIN     = 2;
	const WEEKS_MAX     = 26;
	const WEEKS_DEFAULT = 12;

	/** The wording a block or shortcode can change, to the widget's text names (DEFAULT_TEXTS in the widget). */
	const TEXT_KEYS = array(
		'button_text'     => 'toPayment',
		'details_heading' => 'yourDetails',
		'dates_text'      => 'chooseDates',
	);

	/**
	 * The widget element, and asks for the widget script on this page.
	 *
	 * @param array $options {
	 *     Optional. What to show.
	 *
	 *     @type string $unit        Unit code: start on that unit.
	 *     @type string $service     Service code: start on that service (read by the appointments screens).
	 *     @type bool   $services    Show the appointment steps.
	 *     @type string $accent      Main colour, #rrggbb.
	 *     @type string $text        Text colour, #rrggbb.
	 *     @type bool   $guest_links This widget opens guests' email links (#bw=) when a page has several.
	 *     @type array  $texts       Wording overrides, key to words (for example toPayment).
	 * }
	 * @return string HTML.
	 */
	public static function render( array $options = array() ) {
		if ( ! Settings::is_connected() ) {
			// Visitors see nothing; people who can edit the page are told what to do.
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="anybook-not-connected">' . esc_html__( 'The Anybook booking widget will show here once this site is connected to Anybook (Anybook in the WordPress admin menu).', 'anybook' ) . '</p>';
			}
			return '';
		}

		if ( ! empty( $options['overview'] ) ) {
			Assets::enqueue_overview();
			return '<booking-overview' . self::overview_attributes( $options ) . '></booking-overview>';
		}
		Assets::enqueue();
		return '<booking-widget' . self::attributes( $options ) . '></booking-widget>';
	}

	/**
	 * What to show, as the block and the shortcode both describe it, turned into the renderer's options. Both go
	 * through here, so the same choices always give the same element.
	 *
	 * @param array $choice {
	 *     Optional.
	 *
	 *     @type string $show            all, unit, appointments, service or overview ('' to work it out from the rest).
	 *     @type string $unit            Unit code, for show=unit.
	 *     @type string $service         Service code, for show=service; for the overview with rows=people, its id.
	 *     @type mixed  $weeks           Overview: weeks shown, 2 to 26.
	 *     @type bool   $prices          Overview: show prices.
	 *     @type string $units           Overview: unit codes, comma-separated ('' for all units).
	 *     @type string $rows            Overview: services (one row per service) or people (one per team member).
	 *     @type string $accent          Main colour.
	 *     @type string $text            Text colour.
	 *     @type bool   $guest_links     Use this widget for guests' email links.
	 *     @type string $button_text     Words on the "Book and go to payment" button.
	 *     @type string $details_heading The "Your details" heading.
	 *     @type string $dates_text      The "Choose your arrival date..." line.
	 * }
	 * @return array Options for render().
	 */
	public static function options_for( array $choice ) {
		$get     = function ( $key ) use ( $choice ) {
			return isset( $choice[ $key ] ) && is_scalar( $choice[ $key ] ) ? trim( (string) $choice[ $key ] ) : '';
		};
		$show    = $get( 'show' );
		$unit    = Settings::clean_code( $get( 'unit' ) );
		$service = Settings::clean_code( $get( 'service' ) );
		if ( ! in_array( $show, self::SHOW, true ) ) {
			// The shortcode may give just unit= or service=: the code says what to show.
			$show = '' !== $service ? 'service' : ( '' !== $unit ? 'unit' : 'all' );
		}
		if ( 'overview' === $show ) {
			// The overview has its own choices; the widget's colours and wording are not for it.
			return array(
				'overview' => true,
				'weeks'    => isset( $choice['weeks'] ) ? $choice['weeks'] : '',
				'prices'   => isset( $choice['prices'] ) && true === $choice['prices'],
				'units'    => $get( 'units' ),
				'rows'     => $get( 'rows' ),
				'service'  => $get( 'service' ),
			);
		}

		$options = array(
			'accent'      => $get( 'accent' ),
			'text'        => $get( 'text' ),
			'guest_links' => ! empty( $choice['guest_links'] ),
			'texts'       => array(),
		);
		if ( 'unit' === $show && '' !== $unit ) {
			$options['unit'] = $unit;
		} elseif ( 'service' === $show && '' !== $service ) {
			$options['service'] = $service;
		} elseif ( 'appointments' === $show || 'service' === $show ) {
			// A service not chosen yet still shows the appointments.
			$options['services'] = true;
		}
		foreach ( self::TEXT_KEYS as $key => $widget_key ) {
			if ( '' !== $get( $key ) ) {
				$options['texts'][ $widget_key ] = $get( $key );
			}
		}
		return $options;
	}

	/**
	 * The element's attributes as an escaped HTML string, starting with a space.
	 *
	 * @param array $options See render().
	 */
	public static function attributes( array $options ) {
		$attrs = array(
			'business' => Settings::get( 'slug' ),
			// Always given, so a bundler that moves the script cannot make the widget call the wrong site (ADR-8).
			'api'      => Settings::site(),
		);

		$unit    = Settings::clean_code( isset( $options['unit'] ) ? $options['unit'] : '' );
		$service = Settings::clean_code( isset( $options['service'] ) ? $options['service'] : '' );
		if ( '' !== $unit ) {
			$attrs['unit'] = $unit;
		}
		if ( '' !== $service ) {
			$attrs['service'] = $service;
		}
		if ( ! empty( $options['services'] ) || '' !== $service ) {
			$attrs['services'] = true;
		}
		if ( ! empty( $options['guest_links'] ) ) {
			$attrs['guest-links'] = true;
		}

		$texts = self::clean_texts( isset( $options['texts'] ) ? $options['texts'] : array() );
		if ( $texts ) {
			$attrs['texts'] = wp_json_encode( $texts );
		}

		$style  = '';
		$accent = Settings::clean_colour( isset( $options['accent'] ) ? $options['accent'] : '' );
		$text   = Settings::clean_colour( isset( $options['text'] ) ? $options['text'] : '' );
		$accent = '' !== $accent ? $accent : Settings::clean_colour( Settings::get( 'accent' ) );
		$text   = '' !== $text ? $text : Settings::clean_colour( Settings::get( 'text' ) );
		if ( '' !== $accent ) {
			$style .= '--bw-accent:' . $accent . ';';
		}
		if ( '' !== $text ) {
			$style .= '--bw-text:' . $text . ';';
		}
		if ( '' !== $style ) {
			$attrs['style'] = $style;
		}

		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return $html;
	}

	/**
	 * The overview element's attributes as an escaped HTML string, starting with a space. Choices that are the
	 * overview's own defaults (12 weeks, no prices, all units, one row per service) are left out.
	 *
	 * @param array $options See options_for().
	 */
	public static function overview_attributes( array $options ) {
		$attrs = array(
			'business' => Settings::get( 'slug' ),
			// Always given, as for the widget, so a bundler that moves the script cannot point it at the wrong site.
			'api'      => Settings::site(),
		);

		$weeks = self::clean_weeks( isset( $options['weeks'] ) ? $options['weeks'] : '' );
		if ( self::WEEKS_DEFAULT !== $weeks ) {
			$attrs['weeks'] = (string) $weeks;
		}
		if ( ! empty( $options['prices'] ) ) {
			$attrs['prices'] = true;
		}
		$units = self::clean_units( isset( $options['units'] ) ? $options['units'] : '' );
		if ( '' !== $units ) {
			$attrs['units'] = $units;
		}
		// One row per team member is for one service only: without a good one, the rows stay one per service.
		$rows    = isset( $options['rows'] ) && is_string( $options['rows'] ) ? strtolower( trim( $options['rows'] ) ) : '';
		$service = self::clean_service( isset( $options['service'] ) ? $options['service'] : '' );
		if ( 'people' === $rows && '' !== $service ) {
			$attrs['rows']    = 'people';
			$attrs['service'] = $service;
		}

		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return $html;
	}

	/**
	 * Weeks shown as a whole number from 2 to 26; anything that is not a whole number gives the default, 12.
	 *
	 * @param mixed $value Raw input (a number from the block, words from the shortcode).
	 */
	public static function clean_weeks( $value ) {
		if ( is_string( $value ) && preg_match( '/^\s*\d{1,6}\s*$/', $value ) ) {
			$value = (int) trim( $value );
		}
		if ( ! is_int( $value ) ) {
			return self::WEEKS_DEFAULT;
		}
		return max( self::WEEKS_MIN, min( self::WEEKS_MAX, $value ) );
	}

	/**
	 * Unit codes, comma-separated, each lower-case letters, digits and - (up to 40); others and repeats dropped.
	 *
	 * @param mixed $value Raw input.
	 * @return string Clean codes joined by commas, or '' for all units.
	 */
	public static function clean_units( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$codes = array();
		foreach ( explode( ',', $value ) as $code ) {
			$code = trim( $code );
			if ( preg_match( '/^[a-z0-9-]{1,40}$/', $code ) && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
		}
		return implode( ',', $codes );
	}

	/**
	 * A service for the overview's team-member rows: its id (a GUID, kept in lower case) or its code (lower-case
	 * letters, digits and -), or ''.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function clean_service( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value ) ) {
			return strtolower( $value );
		}
		return preg_match( '/^[a-z0-9-]{1,64}$/', $value ) ? $value : '';
	}

	/**
	 * Wording overrides: simple keys only, plain text values, a sensible length.
	 *
	 * @param mixed $texts Key to words.
	 */
	public static function clean_texts( $texts ) {
		$clean = array();
		if ( ! is_array( $texts ) ) {
			return $clean;
		}
		foreach ( $texts as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z]{1,40}$/', $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $value );
			if ( '' !== $value ) {
				$clean[ $key ] = mb_substr( $value, 0, 200 );
			}
		}
		return $clean;
	}
}
