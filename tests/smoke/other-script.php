<?php
/**
 * Plugin Name: Another script (smoke test only)
 * Description: Puts one ordinary script on every page, for the speed plugins to combine and defer. Never shipped.
 *
 * @package Anybook
 */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script( 'comment-reply' ); // Not on Autoptimize's default exclusion list, as jQuery is.
	}
);
