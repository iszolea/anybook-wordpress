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
		$this->assertSame( 'https://site.anybookapp.com/api/public/v1/bribie/site?origin=https%3A%2F%2Fexample.com', $this->requests[0][0] );
		$this->assertSame( 3, $this->requests[0][1]['timeout'], 'a page load waits 3 seconds at most' );
		$this->assertSame( 524288, $this->requests[0][1]['limit_response_size'] );
		$this->assertSame( 'wp_safe_remote_get', $this->requests[0][2] );

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
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie', false )->get_error_code() );
		$this->answer( 200, 'not json' );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie', false )->get_error_code() );
		$this->http = function () {
			return new \WP_Error( 'http_request_failed', 'timeout' );
		};
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie', false )->get_error_code() );
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

	public function test_a_failure_is_kept_for_a_minute_only() {
		$this->answer( 500, 'oops' );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie' )->get_error_code() );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie' )->get_error_code() );
		$this->assertCount( 1, $this->requests, 'the second page load does not wait on Anybook again' );
		$this->assertArrayHasKey( 'error', $this->transients['anybook_status'] );

		// A fresh ask (Connect, Check again) does not use the failure, and a success replaces it.
		$this->answer( 200, self::status_body() );
		$this->assertTrue( Client::site_status( 'bribie', false )['approved'] );
		$this->assertArrayHasKey( 'data', $this->transients['anybook_status'] );
		$this->assertSame( 5, $this->requests[1][1]['timeout'], 'the admin has just acted: 5 seconds' );
	}

	public function test_a_local_booking_site_on_a_live_server_is_called_with_wp_safe_remote_get() {
		$this->env = 'production';
		$this->connect( array( 'site' => 'http://anybook.localhost:8092' ) );
		$this->answer( 200, self::status_body() );
		Client::site_status( 'bribie' );
		$this->assertSame( 'wp_safe_remote_get', $this->requests[0][2] );
	}

	public function test_a_local_booking_site_is_called_with_wp_remote_get() {
		$this->connect( array( 'site' => 'http://anybook.localhost:8092' ) );
		$this->answer( 200, self::status_body() );
		Client::site_status( 'bribie' );
		$this->assertSame( 'http://anybook.localhost:8092/api/public/v1/bribie/site?origin=https%3A%2F%2Fexample.com', $this->requests[0][0] );
		$this->assertSame( 'wp_remote_get', $this->requests[0][2] );
	}

	public function test_an_answer_cut_off_at_the_size_limit_is_not_read() {
		$big = '{"name":"' . str_repeat( 'a', Client::MAX_BYTES ) . '"}';
		$this->answer( 200, substr( $big, 0, Client::MAX_BYTES ) );
		$this->assertSame( 'anybook_unreachable', Client::site_status( 'bribie', false )->get_error_code() );
	}

	public function test_only_administrators_may_refresh_and_not_too_often() {
		$this->caps = array( 'edit_posts' );
		$this->assertFalse( Client::may_refresh( 3 ) );
		$this->caps = array( 'manage_options' );
		$this->assertTrue( Client::may_refresh( 3 ) );
		$this->assertFalse( Client::may_refresh( 3 ) );
		$this->assertTrue( Client::may_refresh( 4 ), 'per person' );
	}

	public function test_the_shared_fixture_matches_what_the_plugin_reads() {
		// tests/fixtures/site-status.json is Anybook's real answer, captured by tests/e2e (review Q6).
		$fixture = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/site-status.json' ), true );
		$this->assertIsArray( $fixture );
		foreach ( array( 'name', 'onlineBooking', 'approved', 'originAllowed', 'guestPage', 'hasUnits', 'hasServices', 'cspSources' ) as $key ) {
			$this->assertArrayHasKey( $key, $fixture, $key );
		}
		$status = Client::normalise_status( $fixture );
		$this->assertContains( $status['guestPage'], array( 'this-site', 'elsewhere', 'none' ) );
		$this->assertNotEmpty( $status['cspSources'] );
		$this->assertNotSame( '', $status['cspSources'][0]['directive'] );
	}
}
