<?php
namespace Anybook\Tests;

use Anybook\Admin;
use Anybook\Settings;
use Brain\Monkey\Functions;

/** The page stopped: wp_die() or a redirect (the real ones end the request). */
class Stopped extends \RuntimeException {
	/** @var string Where a redirect went. */
	public $url = '';
	/** @var int HTTP status of a wp_die(). */
	public $status = 0;
}

/**
 * The settings page and its actions (review Q6): capability and nonce first, the notices, saving settings, the
 * booking site rules (S-M1), Connect's checks (S-I1, Q1) and the status lines.
 */
class AdminTest extends TestCase {

	/** @var bool Whether the form's nonce is good. */
	private $nonce_ok = true;

	protected function setUp(): void {
		parent::setUp();
		$t = $this;
		Functions\when( 'check_admin_referer' )->alias(
			function () use ( $t ) {
				if ( ! $t->nonce_ok ) {
					$e         = new Stopped( 'nonce' );
					$e->status = 403;
					throw $e;
				}
				return 1;
			}
		);
		Functions\when( 'wp_die' )->alias(
			function ( $message, $title = '', $args = array() ) {
				$e         = new Stopped( (string) $message );
				$e->status = isset( $args['response'] ) ? $args['response'] : 500;
				throw $e;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( $url ) {
				$e      = new Stopped( 'redirect' );
				$e->url = $url;
				throw $e;
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			function ( $key, $value, $url ) {
				return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
			}
		);
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);
		Functions\when( 'submit_button' )->alias(
			function ( $text ) {
				echo '<button>' . $text . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test stand-in.
			}
		);
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'get_the_title' )->justReturn( 'Book online' );
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.com/wp-admin/post.php?post=1&action=edit' );
		$_GET  = array();
		$_POST = array();
	}

	protected function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Runs a handler and returns how it stopped.
	 *
	 * @param string $method Admin method.
	 */
	private function stop( $method ) {
		try {
			Admin::$method();
		} catch ( Stopped $e ) {
			return $e;
		}
		$this->fail( "$method did not stop" );
	}

	/** The notice code a handler redirected with. */
	private function notice_of( Stopped $e ) {
		parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $query );
		return isset( $query['anybook_notice'] ) ? $query['anybook_notice'] : '';
	}

	/** The settings page's HTML. */
	private function page() {
		ob_start();
		Admin::render_page();
		return ob_get_clean();
	}

	public function test_every_action_needs_manage_options() {
		$this->caps = array( 'edit_posts' );
		foreach ( Admin::ACTIONS as $method ) {
			$e = $this->stop( $method );
			$this->assertSame( 403, $e->status, $method );
			$this->assertSame( '', $e->url, $method );
		}
	}

	public function test_a_bad_nonce_stops_before_anything_changes() {
		$this->connect();
		$this->nonce_ok        = false;
		$_POST['anybook_code'] = 'other';
		$_POST['anybook_accent'] = '#123456';
		foreach ( Admin::ACTIONS as $method ) {
			$this->assertSame( 'nonce', $this->stop( $method )->getMessage(), $method );
		}
		$this->assertSame( 'bribie', Settings::get( 'slug' ) );
		$this->assertSame( '', Settings::get( 'accent' ) );
	}

	public function test_each_notice_code_has_words_and_a_type() {
		$codes = array( 'connected', 'connected-page', 'connected-pending', 'disconnected', 'saved', 'error-cancelled', 'error-not-owner', 'error-not-approved', 'error-other', 'bad-state', 'bad-return', 'bad-code', 'unknown-business', 'unreachable', 'bad-colour', 'bad-site', 'site-not-allowed', 'origin-mismatch', 'checked-recently', 'page-published', 'page-failed' );
		foreach ( $codes as $code ) {
			$notice = Admin::notice( $code );
			$this->assertIsArray( $notice, $code );
			$this->assertContains( $notice[0], array( 'success', 'warning', 'error', 'info' ), $code );
			$this->assertNotSame( '', $notice[1], $code );
		}
		$this->assertNull( Admin::notice( '<script>' ) );
	}

	public function test_the_page_shows_only_a_known_notice() {
		$_GET['anybook_notice'] = 'saved';
		$this->assertStringContainsString( 'Settings saved.', $this->page() );
		$_GET['anybook_notice'] = '<b>hi</b>';
		$this->assertStringNotContainsString( 'hi</b>', $this->page() );
	}

	public function test_each_connected_case_shows_only_its_own_sentence() {
		$_GET['anybook_notice'] = 'connected';
		$html                   = $this->page();
		$this->assertStringContainsString( 'Connected to Anybook. Next, add the Anybook booking block to a page.', $html );
		$this->assertStringNotContainsString( 'Book online page', $html );
		$this->assertStringNotContainsString( 'View page', $html );

		$id                     = \Anybook\Booking_Page::ensure_draft();
		\Anybook\Booking_Page::publish( $id );
		$_GET['anybook_notice'] = 'connected-page';
		$html                   = $this->page();
		$this->assertStringContainsString( 'Connected to Anybook. Your Book online page is now published.', $html );
		$this->assertStringContainsString( '<a href="https://example.com/book/">View page</a>', $html );
		$this->assertStringNotContainsString( 'add the Anybook booking block', $html );
	}

	public function test_the_code_form_button_says_it_connects() {
		$html = $this->page();
		$this->assertStringContainsString( 'Connect with this code', $html );
		$this->assertStringNotContainsString( 'Save code', $html );
	}

	public function test_saving_keeps_good_colours_and_refuses_bad_ones() {
		$this->connect( array( 'text' => '#111111' ) );
		$_POST = array(
			'anybook_accent' => '#0B6E4F',
			'anybook_text'   => 'red',
		);
		$e     = $this->stop( 'handle_save_settings' );
		$this->assertSame( 'bad-colour', $this->notice_of( $e ) );
		$this->assertSame( '#0b6e4f', Settings::get( 'accent' ) );
		$this->assertSame( '#111111', Settings::get( 'text' ), 'the colour saved before stays' );

		$_POST = array( 'anybook_accent' => '' );
		$this->assertSame( 'saved', $this->notice_of( $this->stop( 'handle_save_settings' ) ) );
		$this->assertSame( '', Settings::get( 'accent' ), 'an empty box clears it' );
	}

	public function test_a_site_admin_without_unfiltered_html_cannot_change_the_booking_site() {
		$this->connect();
		$this->caps = array( 'manage_options', 'edit_posts' ); // A network site admin.
		foreach ( array( 'https://evil.example', 'https://test.anybookapp.com' ) as $site ) {
			$_POST = array( 'anybook_site' => $site );
			$this->assertSame( 'site-not-allowed', $this->notice_of( $this->stop( 'handle_save_settings' ) ), $site );
			$this->assertSame( 'https://site.anybookapp.com', Settings::site() );
		}
		// Sending the address unchanged is fine.
		$_POST = array( 'anybook_site' => 'https://site.anybookapp.com/' );
		$this->assertSame( 'saved', $this->notice_of( $this->stop( 'handle_save_settings' ) ) );
		$this->assertStringNotContainsString( 'name="anybook_site"', $this->page(), 'the field is not shown' );
	}

	public function test_only_anybook_hosts_or_local_ones_from_the_form() {
		$this->connect();
		$this->caps = array( 'manage_options', 'edit_posts', 'unfiltered_html' );
		$refused    = array( 'https://evil.example', 'https://app.anybook-app.com', 'https://anybookapp.com.evil.example', 'https://xanybookapp.com', 'http://site.anybookapp.com', 'http://anybook.test', 'https://dev.anybookapp.com', 'https://demo.anybookapp.com', 'https://anybookapp.com' );
		foreach ( $refused as $site ) {
			$_POST = array( 'anybook_site' => $site );
			$this->assertSame( 'bad-site', $this->notice_of( $this->stop( 'handle_save_settings' ) ), $site );
			$this->assertSame( 'https://site.anybookapp.com', Settings::site(), $site );
		}
		foreach ( array( 'https://site.anybookapp.com', 'http://anybook.localhost:8092' ) as $site ) {
			$_POST = array( 'anybook_site' => $site );
			$this->assertSame( 'saved', $this->notice_of( $this->stop( 'handle_save_settings' ) ), $site );
			$this->assertSame( $site, Settings::site() );
		}
		$this->assertStringContainsString( 'name="anybook_site"', $this->page() );
	}

	public function test_a_saved_site_that_is_no_longer_accepted_is_ignored() {
		$this->connect( array( 'site' => 'https://evil.example' ) );
		$this->assertSame( 'https://site.anybookapp.com', Settings::site() );
	}

	public function test_the_return_is_ignored_without_its_state_and_refused_without_the_capability() {
		$_GET = array( 'anybook_slug' => 'evil' );
		Admin::maybe_handle_return(); // Returns: nothing to handle.
		$this->assertFalse( Settings::is_connected() );

		$_GET       = array(
			'state'        => 'x',
			'anybook_slug' => 'evil',
		);
		$this->caps = array( 'edit_posts' );
		$this->assertSame( 403, $this->stop( 'maybe_handle_return' )->status );

		$this->caps = array( 'manage_options' );
		$this->assertSame( 'bad-state', $this->notice_of( $this->stop( 'maybe_handle_return' ) ) );
		$this->assertFalse( Settings::is_connected() );
	}

	public function test_connect_drafts_the_page_and_sends_its_future_address() {
		$_POST = array( 'anybook_make_page' => '1' );
		$e     = $this->stop( 'handle_connect' );
		$this->assertStringStartsWith( 'https://site.anybookapp.com/connect/wordpress?', $e->url );
		parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $query );
		$this->assertSame( 'https://example.com/book/', $query['guest_page'] );
		$id = Settings::get( 'page_id' );
		$this->assertSame( 'draft', $this->posts[ $id ]->post_status, 'not public before the Owner agrees (Q1)' );
		$this->assertTrue( $this->transients['anybook_state_7']['page_made'] );
	}

	public function test_connect_with_a_published_page_does_not_touch_it() {
		$this->posts[42]                   = new \WP_Post(
			array(
				'ID'        => 42,
				'post_name' => 'book',
			)
		);
		$this->options['anybook_settings'] = array( 'page_id' => 42 );
		$_POST                             = array( 'anybook_make_page' => '1' );
		$this->stop( 'handle_connect' );
		$this->assertFalse( $this->transients['anybook_state_7']['page_made'] );
	}

	public function test_a_private_booking_page_is_neither_published_nor_offered() {
		$this->posts[42]                   = new \WP_Post(
			array(
				'ID'          => 42,
				'post_status' => 'private',
			)
		);
		$this->options['anybook_settings'] = array( 'page_id' => 42 );
		$_POST                             = array( 'anybook_make_page' => '1' );
		$e                                 = $this->stop( 'handle_connect' );
		parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $query );
		$this->assertArrayNotHasKey( 'guest_page', $query );
		$this->assertFalse( $this->transients['anybook_state_7']['page_made'] );
		\Anybook\Booking_Page::publish( 42 );
		$this->assertSame( 'private', $this->posts[42]->post_status, 'publish() touches drafts only' );
	}

	public function test_a_draft_page_anybook_already_uses_can_be_published_from_the_status() {
		$this->connect( array( 'page_id' => 42 ) );
		$this->posts[42] = new \WP_Post(
			array(
				'ID'           => 42,
				'post_status'  => 'draft',
				'post_content' => '[anybook]',
			)
		);
		$this->http      = function () {
			return self::json( 200, self::status_body() );
		};
		$this->assertStringContainsString( 'value="anybook_publish_page"', $this->page() );
		$this->assertSame( 'page-published', $this->notice_of( $this->stop( 'handle_publish_page' ) ) );
		$this->assertSame( 'publish', $this->posts[42]->post_status );
		$this->assertStringNotContainsString( 'value="anybook_publish_page"', $this->page() );
	}

	public function test_connect_is_refused_when_admin_and_website_addresses_differ() {
		$this->admin = 'https://admin.example.com';
		$_POST       = array( 'anybook_make_page' => '1' );
		$this->assertSame( 'origin-mismatch', $this->notice_of( $this->stop( 'handle_connect' ) ) );
		$this->assertArrayNotHasKey( 'anybook_state_7', $this->transients );
		$this->assertEmpty( $this->posts );
		$html = $this->page();
		$this->assertStringNotContainsString( 'value="anybook_connect"', $html );
		$this->assertStringContainsString( 'value="anybook_save_code"', $html, 'the business-code fallback is offered' );
	}

	public function test_check_again_is_throttled() {
		$this->connect();
		$this->transients['anybook_status'] = array( 'key' => 'x' );
		$this->assertSame( '', $this->notice_of( $this->stop( 'handle_refresh' ) ) );
		$this->assertArrayNotHasKey( 'anybook_status', $this->transients );
		$this->transients['anybook_status'] = array( 'key' => 'x' );
		$this->assertSame( 'checked-recently', $this->notice_of( $this->stop( 'handle_refresh' ) ) );
		$this->assertArrayHasKey( 'anybook_status', $this->transients );
	}

	public function test_status_lines() {
		$this->connect();
		$this->http = function () {
			return self::json(
				200,
				self::status_body(
					array(
						'approved'  => false,
						'guestPage' => 'none',
					)
				)
			);
		};
		$html       = $this->page();
		$this->assertStringContainsString( 'sign-up is not finished yet', $html );
		$this->assertStringContainsString( 'You have no guest page yet', $html );
		$this->assertStringContainsString( 'Set your guest page', $html );
		$this->assertStringContainsString( 'script-src https://site.anybookapp.com https://js.stripe.com', $html );
		$this->assertStringContainsString( 'data-anybook-confirm="', $html, 'Disconnect asks first' );
		$this->assertStringContainsString( 'Widget colors', $html );
	}

	public function test_anybook_unreachable_is_said_once_a_minute() {
		$this->connect();
		$html = $this->page();
		$this->assertStringContainsString( 'Anybook could not be reached', $html );
		$this->assertStringContainsString( 'does not depend on this check', $html );
		$this->page();
		$this->assertCount( 1, $this->requests, 'the second load makes no request (Q2)' );
	}

	public function test_before_connecting_only_connect_shows() {
		$html = $this->page();
		$this->assertStringContainsString( 'value="anybook_connect"', $html );
		$this->assertStringNotContainsString( 'Widget colors', $html, 'colours come after connecting (Q10d)' );
		$this->assertStringContainsString( 'draft', $html );
	}

	public function test_shared_addresses_are_warned_about() {
		$this->assertStringNotContainsString( 'Other websites share', $this->page() );
		$this->multisite  = true;
		$this->subdomains = false;
		$this->assertStringContainsString( 'address (https://example.com), for example the other sites of a network', $this->page() );
		$this->multisite = false;
		$this->home      = 'https://example.com/shop';
		$this->assertStringContainsString( 'Other websites share', $this->page() );
	}

	public function test_the_page_style_and_disconnect_check_load_on_this_page_only() {
		$added = array();
		$keep  = function ( $handle, $code ) use ( &$added ) {
			$added[] = $code;
			return true;
		};
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_add_inline_style' )->alias( $keep );
		Functions\when( 'wp_add_inline_script' )->alias( $keep );

		Admin::enqueue_page_assets( 'index.php' );
		$this->assertSame( array(), $added );

		Admin::enqueue_page_assets( 'toplevel_page_anybook' );
		$this->assertCount( 2, $added );
		$this->assertStringContainsString( 'data-anybook-confirm', $added[1] );
	}

	public function test_connect_notice_on_the_plugins_screen_until_connected() {
		$screen = (object) array( 'id' => 'plugins' );
		Functions\when( 'get_current_screen' )->alias(
			function () use ( &$screen ) {
				return $screen;
			}
		);
		ob_start();
		Admin::connect_notice();
		$this->assertStringContainsString( 'admin.php?page=anybook', ob_get_clean() );

		foreach ( array( 'edit-post', 'dashboard' ) as $other ) {
			$screen = (object) array( 'id' => $other );
			ob_start();
			Admin::connect_notice();
			$this->assertSame( '', ob_get_clean(), 'not on other screens' );
		}

		$screen = (object) array( 'id' => 'plugins' );
		$this->connect();
		ob_start();
		Admin::connect_notice();
		$this->assertSame( '', ob_get_clean(), 'gone once connected' );
	}
}
