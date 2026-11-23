<?php
/**
 * Wires the plugin into WordPress.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts each part. Nothing here contacts Anybook: that happens only when an administrator acts (guideline 7).
 */
class Plugin {

	/** Hooks every part of the plugin. */
	public static function init() {
		Assets::init();
		Shortcode::init();
		Block::init();
		Rest::init();
		if ( is_admin() ) {
			Admin::init(); // Also hooks privacy_policy_content() on admin_init.
		}
	}

	/**
	 * Suggested words for the site's privacy policy (Settings, Privacy), as WordPress asks plugins to offer
	 * (ADR-15). They cover page views as well as bookings (review S-L4): opening a page with the widget already
	 * sends the visitor's IP address and browser details to Anybook. The widget keeps nothing in the browser.
	 */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$paragraphs = array(
			__( 'Our booking pages use the Anybook booking widget, which Anybook (anybookapp.com) provides. Pages with the widget load it from Anybook, so when you open them your browser sends Anybook your IP address, your browser\'s details and the address of the page, even if you do not book.', 'anybook' ),
			__( 'When you book, what you type in the booking form (such as your name, email address, phone number and booking details) is sent from your browser to Anybook, which takes the booking for us. The widget stores nothing in your browser.', 'anybook' ),
			__( 'Anybook keeps the booking details on its server in France, in the European Union. Anybook\'s privacy policy: https://anybookapp.com/privacy', 'anybook' ),
			__( 'Card payments are made through Stripe. At the payment step the widget loads Stripe, which receives your card details (not this website or Anybook) and may set its own cookies to prevent fraud.', 'anybook' ),
			__( 'This website itself stores no booking or payment details.', 'anybook' ),
		);
		$text       = '';
		foreach ( $paragraphs as $paragraph ) {
			$text .= '<p>' . esc_html( $paragraph ) . '</p>';
		}
		wp_add_privacy_policy_content( __( 'Anybook Online Booking', 'anybook' ), $text );
	}
}
