<?php
/**
 * The "Book online" page the plugin can make (ADR-7).
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the business's guest page and keeps an eye on it.
 *
 * The page is the business's own content once made: the plugin never deletes it, not even on uninstall (ADR-10).
 */
class Booking_Page {

	/** What the page holds on a block theme: the Anybook booking block, used for guests' email links. */
	const BLOCK_CONTENT = '<!-- wp:anybook/booking {"guestLinks":true} /-->';

	/**
	 * What the page holds on a classic theme: the shortcode in a shortcode block, which the classic editor and
	 * page builders also show as plain `[anybook]`. has_widget() accepts either.
	 */
	const SHORTCODE_CONTENT = "<!-- wp:shortcode -->\n[anybook guest_links=\"yes\"]\n<!-- /wp:shortcode -->";

	/** The new page's content for the active theme. */
	public static function content() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? self::BLOCK_CONTENT : self::SHORTCODE_CONTENT;
	}

	/**
	 * The page id the plugin remembers, if that page still exists.
	 */
	public static function current_id() {
		$id = (int) Settings::get( 'page_id' );
		return ( $id > 0 && get_post( $id ) && 'page' === get_post_type( $id ) ) ? $id : 0;
	}

	/**
	 * Makes and publishes the page, or publishes the one already made.
	 *
	 * @return int|\WP_Error The page id.
	 */
	public static function ensure_published() {
		$id = self::current_id();
		if ( $id && 'trash' !== get_post_status( $id ) ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Book online', 'anybook' ),
				// WordPress makes it book-2 and so on if "book" is taken.
				'post_name'    => 'book',
				'post_content' => self::content(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		Settings::update( array( 'page_id' => (int) $id ) );
		return (int) $id;
	}

	/**
	 * Moves the page back to draft, for example when the Owner cancelled on Anybook, so a page Anybook does not
	 * know about is not left public.
	 *
	 * @param int $id Page id.
	 */
	public static function unpublish( $id ) {
		$id = (int) $id;
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * What is wrong with the remembered page, for the settings page: '' (fine or none made), 'missing',
	 * 'unpublished' or 'no-widget'.
	 */
	public static function problem() {
		$id = (int) Settings::get( 'page_id' );
		if ( $id <= 0 ) {
			return '';
		}
		$post = get_post( $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return 'missing';
		}
		if ( 'publish' !== $post->post_status ) {
			return 'unpublished';
		}
		return self::has_widget( $post->post_content ) ? '' : 'no-widget';
	}

	/**
	 * Whether some content holds the Anybook widget (the block or the shortcode).
	 *
	 * @param string $content Post content.
	 */
	public static function has_widget( $content ) {
		return has_block( 'anybook/booking', $content ) || has_shortcode( $content, 'anybook' );
	}
}
