<?php
namespace Anybook\Tests;

use Anybook\Connection;
use Anybook\Settings;

class ConnectionTest extends TestCase {

	/** Starts a connect and returns the address and the query Anybook would receive. */
	private function start_connect( $guest_page = '', $page_made = false ) {
		$url = Connection::start( 7, $guest_page, $page_made );
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		return array( $url, $query );
	}

	private function anybook_knows_bribie() {
		$this->http = function () {
			return self::json( 200, self::status_body() );
		};
	}

	public function test_connect_url_carries_return_state_and_site_name() {
		list( $url, $query ) = $this->start_connect( 'https://example.com/book/' );
		$this->assertStringStartsWith( 'https://site.anybookapp.com/connect/wordpress?', $url );
		$this->assertSame( 'https://example.com/wp-admin/admin.php?page=anybook', $query['return'] );
		$this->assertSame( 'https://example.com/book/', $query['guest_page'] );
		$this->assertSame( 'Example & Co', $query['site_name'] );
		$this->assertSame( 32, strlen( $query['state'] ) );
		// Only a hash of the state is kept.
		$this->assertNotContains( $query['state'], $this->transients['anybook_state_7'] );
	}

	public function test_return_saves_the_business_once_confirmed() {
		$this->anybook_knows_bribie();
		list( , $query ) = $this->start_connect();
		$notice          = Connection::handle_return(
			array(
				'state'        => $query['state'],
				'anybook_slug' => 'bribie',
				'anybook_name' => 'Ignored name',
			),
			7
		);
		$this->assertSame( 'connected', $notice );
		$this->assertSame( 'bribie', Settings::get( 'slug' ) );
		$this->assertSame( 'Bribie Houseboat Holidays', Settings::get( 'name' ), 'the name comes from Anybook, not the return' );
		$this->assertNotSame( '', Settings::get( 'connected_at' ) );
	}

	public function test_state_is_single_use_and_per_user() {
		$this->anybook_knows_bribie();
		list( , $query ) = $this->start_connect();
		$return          = array(
			'state'        => $query['state'],
			'anybook_slug' => 'bribie',
		);
		$this->assertSame( 'bad-state', Connection::handle_return( $return, 8 ), 'another user' );
		$this->assertSame( 'connected', Connection::handle_return( $return, 7 ) );
		$this->assertSame( 'bad-state', Connection::handle_return( $return, 7 ), 'used twice' );
	}

	public function test_wrong_or_missing_state_is_refused() {
		$this->start_connect();
		$this->assertSame( 'bad-state', Connection::handle_return( array( 'state' => 'forged', 'anybook_slug' => 'evil' ), 7 ) );
		$this->assertSame( 'bad-state', Connection::handle_return( array( 'anybook_slug' => 'evil' ), 7 ) );
		$this->assertFalse( Settings::is_connected() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_a_business_waiting_for_approval_connects_as_pending() {
		// The site-status route answers 404 until Anybook approves the business (ADR-19).
		$this->http = function () {
			return self::json( 404, '' );
		};
		list( , $query ) = $this->start_connect();
		$return          = array(
			'state'        => $query['state'],
			'anybook_slug' => 'newbiz',
			'anybook_name' => 'New <b>Biz</b>',
		);
		$this->assertSame( 'connected-pending', Connection::handle_return( $return, 7 ) );
		$this->assertSame( 'newbiz', Settings::get( 'slug' ) );
		$this->assertSame( 'New Biz', Settings::get( 'name' ) );
	}

	public function test_unknown_code_is_not_saved_by_the_fallback() {
		$this->http = function () {
			return self::json( 404, '' );
		};
		$this->assertSame( 'unknown-business', Connection::save_code( 'nobody' ) );
		$this->assertFalse( Settings::is_connected() );
	}

	public function test_unreachable_anybook_saves_nothing() {
		list( , $query ) = $this->start_connect();
		$this->assertSame( 'unreachable', Connection::handle_return( array( 'state' => $query['state'], 'anybook_slug' => 'bribie' ), 7 ) );
		$this->assertFalse( Settings::is_connected() );
	}

	public function test_bad_slug_in_return() {
		list( , $query ) = $this->start_connect();
		$this->assertSame( 'bad-return', Connection::handle_return( array( 'state' => $query['state'], 'anybook_slug' => '<x>' ), 7 ) );
	}

	/** A booking page drafted for this connect, as Admin::handle_connect() leaves it. */
	private function drafted_page() {
		$this->posts[42]                   = new \WP_Post(
			array(
				'ID'          => 42,
				'post_status' => 'draft',
				'post_name'   => 'book',
			)
		);
		$this->options['anybook_settings'] = array( 'page_id' => 42 );
	}

	public function test_a_refused_connect_leaves_the_page_a_draft() {
		$this->drafted_page();
		list( , $query ) = $this->start_connect( 'https://example.com/book/', true );
		$this->assertSame( 'error-not-owner', Connection::handle_return( array( 'state' => $query['state'], 'anybook_error' => 'not-owner' ), 7 ) );
		$this->assertSame( 'draft', $this->posts[42]->post_status );
	}

	public function test_the_page_is_published_only_when_the_owner_agrees() {
		$this->anybook_knows_bribie();
		$this->drafted_page();
		list( , $query ) = $this->start_connect( 'https://example.com/book/', true );
		$this->assertSame( 'draft', $this->posts[42]->post_status, 'nothing public while the Owner is on Anybook' );
		$this->assertSame( 'connected-page', Connection::handle_return( array( 'state' => $query['state'], 'anybook_slug' => 'bribie' ), 7 ) );
		$this->assertSame( 'publish', $this->posts[42]->post_status );
	}

	public function test_an_expired_or_abandoned_connect_leaves_the_page_a_draft() {
		$this->anybook_knows_bribie();
		$this->drafted_page();
		list( , $query ) = $this->start_connect( 'https://example.com/book/', true );
		unset( $this->transients['anybook_state_7'] ); // The 15 minutes are up (or the tab was closed).
		$this->assertSame( 'bad-state', Connection::handle_return( array( 'state' => $query['state'], 'anybook_slug' => 'bribie' ), 7 ) );
		$this->assertSame( 'draft', $this->posts[42]->post_status );
		$this->assertFalse( Settings::is_connected() );
	}

	public function test_a_failed_check_after_the_return_leaves_the_page_a_draft() {
		$this->drafted_page();
		list( , $query ) = $this->start_connect( 'https://example.com/book/', true );
		$this->assertSame( 'unreachable', Connection::handle_return( array( 'state' => $query['state'], 'anybook_slug' => 'bribie' ), 7 ) );
		$this->assertSame( 'draft', $this->posts[42]->post_status );
	}

	public function test_cancel_leaves_a_page_that_was_already_public() {
		$this->posts[42]                   = new \WP_Post( array( 'ID' => 42 ) );
		$this->options['anybook_settings'] = array( 'page_id' => 42 );
		list( , $query )                   = $this->start_connect( 'https://example.com/book/', false );
		$this->assertSame( 'error-cancelled', Connection::handle_return( array( 'state' => $query['state'], 'anybook_error' => 'cancelled' ), 7 ) );
		$this->assertSame( 'publish', $this->posts[42]->post_status );
	}

	public function test_unexpected_error_reason() {
		list( , $query ) = $this->start_connect();
		$this->assertSame( 'error-other', Connection::handle_return( array( 'state' => $query['state'], 'anybook_error' => '<script>' ), 7 ) );
	}

	public function test_business_code_fallback_and_disconnect() {
		$this->anybook_knows_bribie();
		$this->assertSame( 'bad-code', Connection::save_code( 'not a code' ) );
		$this->assertSame( 'connected', Connection::save_code( 'bribie' ) );
		$this->assertTrue( Settings::is_connected() );
		$this->assertArrayHasKey( 'anybook_status', $this->transients );

		Connection::disconnect();
		$this->assertFalse( Settings::is_connected() );
		$this->assertArrayNotHasKey( 'anybook_status', $this->transients );
	}
}
