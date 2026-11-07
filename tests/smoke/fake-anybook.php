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
		if ( 0 !== strpos( $url, 'https://site.anybookapp.com/' ) ) {
			return $pre;
		}
		update_option( 'fake_anybook_calls', (int) get_option( 'fake_anybook_calls', 0 ) + 1 );
		$lists = array(
			'https://site.anybookapp.com/api/public/v1/bribie'          => array(
				'name'  => 'Bribie Houseboat Holidays',
				'units' => array(
					array(
						'id'   => 'u1',
						'code' => 'belle',
						'name' => 'The Belle',
					),
				),
			),
			'https://site.anybookapp.com/api/public/v1/bribie/services' => array(
				array(
					'id'   => 's1',
					'code' => 'cut',
					'name' => 'Haircut',
				),
			),
		);
		if ( isset( $lists[ $url ] ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $lists[ $url ] ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		}
		if ( 0 === strpos( $url, 'https://site.anybookapp.com/api/public/v1/bribie/site?origin=' ) ) {
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
						'sources'   => array( 'https://site.anybookapp.com', 'https://js.stripe.com' ),
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
