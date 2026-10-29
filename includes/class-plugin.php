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
		// Step 4 adds the Anybook booking block (blocks/booking) and its catalogue REST route here.
		if ( is_admin() ) {
			Admin::init();
		}
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	/**
	 * Suggested words for the site's privacy policy (Settings, Privacy), as WordPress asks plugins to offer
	 * (ADR-15).
	 */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'Our booking pages use the Anybook booking widget. When you book, what you type in the booking form (such as your name, email address, phone number and booking details) is sent from your browser to Anybook (anybookapp.com), which takes the booking for us. Card payments are made through Stripe; card details go to Stripe, not to this website or to Anybook.', 'anybook' ) . '</p>'
			. '<p>' . esc_html__( 'This website itself stores no booking or payment details.', 'anybook' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Anybook Online Booking', 'anybook' ), wp_kses_post( $text ) );
	}
}
