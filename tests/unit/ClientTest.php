<?php
namespace Anybook\Tests;

use Anybook\Client;

class ClientTest extends TestCase {

	private function answer( $code, $body ) {
		$this->http = function () use ( $code, $body ) {
			return self::json( $code, $body );
		};
	}

	public function test_asks_the_site_route_with_this_origin_and_caches() {
		$this->answer( 200, self::status_body() );
		$status = Client::site_status( 'bribie' );
		$this->assertTrue( $status['originAllowed'] );
		$this->assertSame( 'this-site', $status['guestPage'] );
		$this->assertSame( 'https://app.anybookapp.com/api/public/v1/bribie/site?origin=https%3A%2F%2Fexample.com', $this->requests[0][0] );
		$this->assertSame( 5, $this->requests[0][1]['timeout'] );

		Client::site_status( 'bribie' );
		$this->assertCount( 1, $this->requests, 'second call is served from the cache' );
		Client::site_status( 'bribie', false );
		$this->assertCount( 2, $this->requests, 'no cache when asked' );
	}

	public function test_cache_is_for_one_business_only() {
		$this->answer( 200, self::status_body() );
		Client::site_status( 'bribie' );
		Client::site_status( 'other' );
		$this->assertCount( 2, $this->requests );
	}

	public function test_unknown_business_and_failures() {
		$this->answer( 404, '' );
		$this->assertSame( 'anybook_unknown_business', Client::site_status( 'nobody' )->get_error_code() );
		$this->answer( 500, 'oops' );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie' )->get_error_code() );
		$this->answer( 200, 'not json' );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie' )->get_error_code() );
		$this->http = function () {
			return new \WP_Error( 'http_request_failed', 'timeout' );
		};
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie' )->get_error_code() );
	}

	public function test_bad_code_makes_no_request() {
		$this->assertSame( 'anybook_unknown_business', Client::site_status( '../x' )->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_answer_is_normalised() {
		$status = Client::normalise_status(
			array(
				'name'       => '<b>Bribie</b>',
				'guestPage'  => 'somewhere',
				'cspSources' => array(
					'junk',
					array(
						'directive' => 'frame-src',
						'sources'   => array( 'https://js.stripe.com' ),
					),
				),
				'extra'      => 'dropped',
			)
		);
		$this->assertSame( 'Bribie', $status['name'] );
		$this->assertSame( 'none', $status['guestPage'] );
		$this->assertFalse( $status['originAllowed'] );
		$this->assertCount( 1, $status['cspSources'] );
		$this->assertArrayNotHasKey( 'extra', $status );
	}
}
