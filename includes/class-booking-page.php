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
 * Connect makes it as a draft; it is published only when the Owner has agreed on Anybook (review Q1), so a
 * cancelled, expired or abandoned connect leaves nothing public.
 */
class Booking_Page {

	/** What the page holds on a block theme: the Anybook booking block, used for guests' email links. */
	const BLOCK_CONTENT = '<!-- wp:anybook/booking {"guestLinks":true} /-->';

	/**
	 * What the page holds on a classic theme: the shortcode in a shortcode block, which the classic editor and
	 * page builders also show as plain `[anybook]`. has_widget() accepts either.
	 */
	const SHORTCODE_CONTENT = "<!-- wp:shortcode -->\n[anybook guest_links=\"yes\"]\n<!-- /wp:shortcode -->";

	/** The page's address (slug); WordPress makes it book-2 and so on if "book" is taken. */
	const SLUG = 'book';

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

	/** Whether the remembered page is published. */
	public static function is_published() {
		$id = self::current_id();
		return $id > 0 && 'publish' === get_post_status( $id );
	}

	/**
	 * Makes the page as a draft, or keeps the one already made as it is (a trashed one is replaced).
	 *
	 * @return int|\WP_Error The page id.
	 */
	public static function ensure_draft() {
		$id = self::current_id();
		if ( $id && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'Book online', 'anybook' ),
				'post_name'    => self::SLUG,
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
	 * Publishes the page: the Owner has agreed on Anybook.
	 *
	 * @param int $id Page id.
	 */
	public static function publish( $id ) {
		$id = (int) $id;
		if ( $id > 0 && 'publish' !== get_post_status( $id ) && 'trash' !== get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * The address the page has once published, for Anybook's guest page while it is still a draft (a draft's own
	 * permalink is ?page_id=, which would change on publishing).
	 *
	 * @param int $id Page id.
	 */
	public static function future_permalink( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		if ( 'publish' === $post->post_status ) {
			return (string) get_permalink( $post );
		}
		// What get_sample_permalink() does: the post as it will be once published, with the slug it will get.
		$future              = clone $post;
		$future->post_status = 'publish';
		$name                = '' !== (string) $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		$future->post_name   = wp_unique_post_slug( $name, $post->ID, 'publish', $post->post_type, (int) $post->post_parent );
		$future->filter      = 'sample';
		return (string) get_permalink( $future );
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
		return self::post_has_widget( $post ) ? '' : 'no-widget';
	}

	/**
	 * Whether some content holds the Anybook widget (the block or the shortcode).
	 *
	 * @param string $content Post content.
	 */
	public static function has_widget( $content ) {
		return has_block( 'anybook/booking', $content ) || has_shortcode( $content, 'anybook' );
	}

	/**
	 * Whether a post shows the widget: its content, or what a page builder keeps beside it (review Q9).
	 *
	 * Divi keeps its layout as shortcodes in the content, so has_widget() finds `[anybook]` there. Elementor keeps
	 * it in the `_elementor_data` meta as JSON, where a Shortcode or Text widget holds `[anybook ...]`. Other
	 * builders can answer through the `anybook_page_has_widget` filter.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function post_has_widget( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$found = self::has_widget( $post->post_content );
		if ( ! $found ) {
			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			$found     = is_string( $elementor ) && ( false !== strpos( $elementor, '[anybook' ) || false !== strpos( $elementor, 'anybook\/booking' ) );
		}
		/**
		 * Whether a post shows the Anybook widget, for page builders that keep their content outside post_content.
		 * True puts the widget script in the page's head and tells the settings page the booking page is fine.
		 *
		 * @param bool     $found Whether the plugin found the block or the shortcode.
		 * @param \WP_Post $post  The post.
		 */
		return (bool) apply_filters( 'anybook_page_has_widget', $found, $post );
	}
}
