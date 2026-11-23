<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen (ADR-10, ADR-11).
 *
 * The option and transients go, on every site of a network. The booking page stays, as the business's own content,
 * with one exception: a page the plugin made and the business never edited (it still holds exactly what the plugin
 * wrote) becomes a draft, so visitors are not left with a published page showing a raw [anybook] shortcode or
 * nothing. An edited page is left as it is. Anybook is not contacted: the business's account and allowed websites
 * are its own to change.
 *
 * @package Anybook
 */

// Run only by WordPress's uninstall, never by a direct request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the plugin's option and transients on the current site.
 */
$anybook_uninstall_site = function () {
	global $wpdb;
	// The page id is read before the option goes. Classes are not loaded here, so the two contents the plugin writes
	// (Booking_Page::BLOCK_CONTENT and SHORTCODE_CONTENT) are copied.
	$anybook_settings = get_option( 'anybook_settings', array() );
	$anybook_page_id  = is_array( $anybook_settings ) && ! empty( $anybook_settings['page_id'] ) ? (int) $anybook_settings['page_id'] : 0;
	if ( $anybook_page_id > 0 ) {
		$anybook_page = get_post( $anybook_page_id );
		if ( $anybook_page && 'page' === $anybook_page->post_type && 'publish' === $anybook_page->post_status
			&& in_array(
				$anybook_page->post_content,
				array(
					'<!-- wp:anybook/booking {"guestLinks":true} /-->',
					"<!-- wp:shortcode -->\n[anybook guest_links=\"yes\"]\n<!-- /wp:shortcode -->",
				),
				true
			)
		) {
			wp_update_post(
				array(
					'ID'          => $anybook_page_id,
					'post_status' => 'draft',
				)
			);
		}
	}
	delete_option( 'anybook_settings' );
	delete_option( 'anybook_connected' );
	delete_transient( 'anybook_status' );
	delete_transient( 'anybook_catalog' );
	// Per-user records: pending connect states (anybook_state_{user id}) and refresh limits (anybook_refresh_{user id}).
	// With an object cache they expire by themselves.
	foreach ( array( 'anybook_state_', 'anybook_refresh_' ) as $anybook_prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off clean-up on uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $anybook_prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $anybook_prefix ) . '%'
			)
		);
	}
};

if ( is_multisite() ) {
	$anybook_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $anybook_site_ids as $anybook_site_id ) {
		switch_to_blog( $anybook_site_id );
		$anybook_uninstall_site();
		restore_current_blog();
	}
} else {
	$anybook_uninstall_site();
}
