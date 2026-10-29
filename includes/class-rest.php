<?php
/**
 * The plugin's REST route: the block's chooser (contracts.md, `GET /wp-json/anybook/v1/catalog`).
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives the editor the business's units and services, fetched by this server from Anybook's public API, so the
 * editor never calls Anybook itself. Only for people who can edit posts; WordPress checks the REST nonce.
 */
class Rest {

	const NAMESPACE_V1 = 'anybook/v1';

	/** Hooks the route in. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/** Registers the route. */
	public static function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/catalog',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'catalog' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/** Only people who can edit posts may ask (the answer is public data, but the call costs Anybook a request). */
	public static function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * The catalogue, or an error the editor can show.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function catalog( $request ) {
		$refresh = $request instanceof \WP_REST_Request && rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		$data    = Client::catalog( ! $refresh );
		if ( is_wp_error( $data ) ) {
			$status = 'anybook_not_connected' === $data->get_error_code() ? 409 : 502;
			return new \WP_Error( $data->get_error_code(), $data->get_error_message(), array( 'status' => $status ) );
		}
		return $data;
	}
}
