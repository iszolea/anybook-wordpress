<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen (ADR-10, ADR-11).
 *
 * The option and transients go, on every site of a network. The booking page stays: it is the business's own
 * content. Anybook is not contacted: the business's account and allowed websites are its own to change.
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
function anybook_uninstall_site() {
	global $wpdb;
	delete_option( 'anybook_settings' );
	delete_transient( 'anybook_status' );
	delete_transient( 'anybook_catalog' );
	// Pending connect states, one per user (anybook_state_{user id}). With an object cache they expire by themselves.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off clean-up on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_anybook_state_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_anybook_state_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$anybook_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $anybook_site_ids as $anybook_site_id ) {
		switch_to_blog( $anybook_site_id );
		anybook_uninstall_site();
		restore_current_blog();
	}
} else {
	anybook_uninstall_site();
}
