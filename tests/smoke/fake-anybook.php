<?php
/**
 * Plugin Name: Fake Anybook (smoke test only)
 * Description: Answers the plugin's calls to Anybook's public API and counts them. Never shipped.
 *
 * @package Anybook
 */

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://app.anybookapp.com/' ) ) {
			return $pre;
		}
		update_option( 'fake_anybook_calls', (int) get_option( 'fake_anybook_calls', 0 ) + 1 );
		if ( 0 === strpos( $url, 'https://app.anybookapp.com/api/public/v1/bribie/site?origin=' ) ) {
			$body = array(
				'name'          => 'Bribie Houseboat Holidays',
				'onlineBooking' => true,
				'approved'      => true,
				'originAllowed' => true,
				'guestPage'     => 'none',
				'hasUnits'      => true,
				'hasServices'   => false,
				'cspSources'    => array(
					array(
						'directive' => 'script-src',
						'sources'   => array( 'https://app.anybookapp.com', 'https://js.stripe.com' ),
					),
				),
			);
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $body ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		}
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'cookies'  => array(),
		);
	},
	10,
	3
);
