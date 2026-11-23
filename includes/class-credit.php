<?php
/**
 * The small "Booking by Anybook" credit under the booking widget.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes the credit line on the server, in the page's own HTML, next to the booking widget (Anybook's Free plan and free days ask for it).
 *
 * Anybook's site-status answer says whether the credit is required for this business and with which `rel`. That answer is only read when an
 * administrator or editor acts (the settings page, the editor), as every call of this plugin is, and kept in an option. A visitor's page view
 * reads that option and never contacts Anybook. The line is always made here from fixed text and a checked `rel`: the HTML in Anybook's answer
 * is outside data and is never printed.
 *
 * The link is followed by default; Anybook can ask for `nofollow` or `sponsored` and the plugin passes that on. Nothing is claimed about search.
 */
class Credit {

	/** The option that keeps what Anybook last said about this business. */
	const OPTION = 'anybook_credit';

	/** The link: the brand, nothing else. */
	const URL = 'https://anybookapp.com';

	/** The `rel` values the plugin will write. "follow" is no rel at all. */
	const RELS = array( 'follow', 'nofollow', 'sponsored' );

	/**
	 * Whether the line has been written on this page already (once is enough).
	 *
	 * @var bool
	 */
	private static $written = false;

	/**
	 * Keeps what the status answer says, for the business it was asked about.
	 *
	 * @param string $slug   Business code the answer is for.
	 * @param array  $credit The normalised credit: `required` (bool) and `rel`.
	 */
	public static function remember( $slug, array $credit ) {
		$record = array(
			'slug'     => (string) $slug,
			'required' => ! empty( $credit['required'] ),
			'rel'      => self::clean_rel( isset( $credit['rel'] ) ? $credit['rel'] : '' ),
		);
		if ( get_option( self::OPTION ) !== $record ) {
			update_option( self::OPTION, $record, true );
		}
	}

	/**
	 * Which `rel` to write: only the three Anybook may ask for; anything else is the followed link.
	 *
	 * @param mixed $rel What the answer said.
	 * @return string follow, nofollow or sponsored.
	 */
	public static function clean_rel( $rel ) {
		$rel = is_string( $rel ) ? strtolower( trim( $rel ) ) : '';
		return in_array( $rel, self::RELS, true ) ? $rel : 'follow';
	}

	/**
	 * The line to put after the widget: the exact paragraph Anybook shows its customers, or '' when the credit is not required, nothing is
	 * known yet, or it was already written on this page.
	 *
	 * @return string HTML.
	 */
	public static function html() {
		if ( self::$written ) {
			return '';
		}
		$record = get_option( self::OPTION );
		$slug   = Settings::clean_code( Settings::get( 'slug' ) );
		if ( ! is_array( $record ) || empty( $record['required'] ) || '' === $slug || ! isset( $record['slug'] ) || $record['slug'] !== $slug ) {
			return '';
		}
		self::$written = true;
		return self::line( isset( $record['rel'] ) ? $record['rel'] : 'follow' );
	}

	/**
	 * The line itself.
	 *
	 * @param string $rel follow, nofollow or sponsored.
	 * @return string HTML.
	 */
	public static function line( $rel ) {
		$rel   = self::clean_rel( $rel );
		$attrs = 'follow' === $rel ? '' : ' rel="' . esc_attr( $rel ) . '"';
		return '<p class="anybook-credit">Booking by <a href="' . esc_url( self::URL ) . '"' . $attrs . '>Anybook</a></p>';
	}

	/**
	 * Forgets what was kept (on disconnect), so a different business never shows the last one's credit.
	 */
	public static function forget() {
		delete_option( self::OPTION );
	}

	/**
	 * Starts a new page: only for the tests, which render several pages in one run.
	 */
	public static function reset_page() {
		self::$written = false;
	}
}
