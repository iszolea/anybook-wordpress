<?php
namespace Anybook\Tests;

use Anybook\Block;
use Anybook\Booking_Page;
use Anybook\Client;
use Anybook\Renderer;
use Anybook\Rest;
use Anybook\Shortcode;
use Brain\Monkey\Functions;

class BlockTest extends TestCase {

	const API = 'https://app.anybookapp.com/api/public/v1/bribie';

	/** Each block choice and the shortcode that means the same, with the element both must give. */
	public function same_choices() {
		$base = '<booking-widget business="bribie" api="https://app.anybookapp.com"';
		return array(
			'all'                 => array( array(), array(), $base . '></booking-widget>' ),
			'one unit'            => array(
				array(
					'show' => 'unit',
					'unit' => 'belle',
				),
				array( 'unit' => 'belle' ),
				$base . ' unit="belle"></booking-widget>',
			),
			'appointments'        => array( array( 'show' => 'appointments' ), array( 'show' => 'appointments' ), $base . ' services></booking-widget>' ),
			'appointments, older' => array( array( 'show' => 'appointments' ), array( 'services' => 'yes' ), $base . ' services></booking-widget>' ),
			'one service'         => array(
				array(
					'show'    => 'service',
					'service' => 'cut',
				),
				array( 'service' => 'cut' ),
				$base . ' service="cut" services></booking-widget>',
			),
			'service not chosen'  => array( array( 'show' => 'service' ), array( 'show' => 'service' ), $base . ' services></booking-widget>' ),
			'unit not chosen'     => array( array( 'show' => 'unit' ), array( 'show' => 'unit' ), $base . '></booking-widget>' ),
			'unit left from before, showing all' => array(
				array(
					'show' => 'all',
					'unit' => 'belle',
				),
				array(
					'show' => 'all',
					'unit' => 'belle',
				),
				$base . '></booking-widget>',
			),
			'everything'          => array(
				array(
					'show'           => 'unit',
					'unit'           => 'belle',
					'accent'         => '#0B6E4F',
					'text'           => '#111111',
					'guestLinks'     => true,
					'buttonText'     => 'Book now',
					'detailsHeading' => 'About you',
					'datesText'      => 'Pick dates',
				),
				array(
					'show'            => 'unit',
					'unit'            => 'belle',
					'accent'          => '#0B6E4F',
					'text'            => '#111111',
					'guest_links'     => 'yes',
					'button_text'     => 'Book now',
					'details_heading' => 'About you',
					'dates_text'      => 'Pick dates',
				),
				$base . ' unit="belle" guest-links texts="{&quot;toPayment&quot;:&quot;Book now&quot;,&quot;yourDetails&quot;:&quot;About you&quot;,&quot;chooseDates&quot;:&quot;Pick dates&quot;}" style="--bw-accent:#0b6e4f;--bw-text:#111111;"></booking-widget>',
			),
		);
	}

	/**
	 * @dataProvider same_choices
	 */
	public function test_block_and_shortcode_give_the_same_element( $block, $shortcode, $html ) {
		$this->connect();
		$this->assertSame( $html, Block::render( $block ) );
		$this->assertSame( $html, Shortcode::render( $shortcode ) );
		$this->assertArrayHasKey( 'anybook-widget', $this->scripts['enqueued'] );
	}

	public function test_bad_block_values_are_dropped() {
		$this->connect();
		$html = Block::render(
			array(
				'show'       => 'everything',
				'unit'       => '"><script>',
				'service'    => array( 'x' ),
				'accent'     => 'red',
				'guestLinks' => 'no thanks',
				'buttonText' => '<b>Go</b>',
			)
		);
		$this->assertSame(
			'<booking-widget business="bribie" api="https://app.anybookapp.com" texts="{&quot;toPayment&quot;:&quot;Go&quot;}"></booking-widget>',
			$html
		);
		$this->assertSame( Renderer::render(), Block::render( 'not an array' ) );
	}

	public function test_empty_state_when_not_connected() {
		$this->assertStringContainsString( 'anybook-not-connected', Block::render( array() ) );
		$this->caps = array();
		$this->assertSame( '', Block::render( array() ) );
		$this->assertEmpty( $this->scripts['enqueued'] );
	}

	public function test_editor_data() {
		$this->connect( array( 'accent' => '#0B6E4F' ) );
		$this->scripts['registered']['anybook-booking-editor-script'] = array();
		Functions\when( 'generate_block_asset_handle' )->justReturn( 'anybook-booking-editor-script' );
		$inline = array();
		Functions\when( 'wp_add_inline_script' )->alias(
			function ( $handle, $js, $where ) use ( &$inline ) {
				$inline[] = array( $handle, $js, $where );
			}
		);
		Block::editor_data();
		$this->assertSame( 'before', $inline[0][2] );
		$this->assertStringContainsString( '"connected":true', $inline[0][1] );
		$this->assertStringContainsString( '"accent":"#0b6e4f"', $inline[0][1] );
		$this->assertStringContainsString( 'admin.php?page=anybook', $inline[0][1] );

		$this->caps = array( 'edit_posts' );
		Block::editor_data();
		$this->assertStringContainsString( '"settingsUrl":""', $inline[1][1], 'only admins get the connect link' );
	}

	public function test_booking_page_uses_the_block_on_block_themes() {
		$this->assertSame( Booking_Page::SHORTCODE_CONTENT, Booking_Page::content() );
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		$id = Booking_Page::ensure_draft();
		Booking_Page::publish( $id );
		$this->assertSame( '<!-- wp:anybook/booking {"guestLinks":true} /-->', $this->posts[ $id ]->post_content );
		$this->assertSame( '', Booking_Page::problem() );
	}

	/** Answers the business and services lists. */
	private function answer_catalog( $services_code = 200 ) {
		$this->http = function ( $url ) use ( $services_code ) {
			if ( self::API === $url ) {
				return self::json(
					200,
					array(
						'name'  => 'Bribie',
						'units' => array(
							array(
								'id'   => 'a1',
								'code' => 'belle',
								'name' => 'The <b>Belle</b>',
							),
							array(
								'id'   => 'a2',
								'code' => 'bad code!',
								'name' => 'Bad',
							),
							'junk',
						),
					)
				);
			}
			if ( self::API . '/services' === $url ) {
				return self::json(
					$services_code,
					array(
						array(
							'id'   => '0f8fad5b-d9cb-469f-a165-70867728950e',
							'code' => 'cut',
							'name' => 'Haircut',
						),
						array(
							'id'   => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
							'code' => null,
							'name' => 'Colour',
						),
					)
				);
			}
			return self::json( 404, '' );
		};
	}

	public function test_catalog_from_the_public_api_and_cached() {
		$this->connect();
		$this->answer_catalog();
		$want = array(
			'units'    => array(
				array(
					'id'   => 'a1',
					'code' => 'belle',
					'name' => 'The Belle',
				),
				// A code Anybook would never send: chosen by its id instead.
				array(
					'id'   => 'a2',
					'code' => 'a2',
					'name' => 'Bad',
				),
			),
			'services' => array(
				array(
					'id'   => '0f8fad5b-d9cb-469f-a165-70867728950e',
					'code' => 'cut',
					'name' => 'Haircut',
				),
				array(
					'id'   => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
					'code' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
					'name' => 'Colour',
				),
			),
		);
		$this->assertSame( $want, Client::catalog() );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( $want, Client::catalog(), 'cached' );
		$this->assertCount( 2, $this->requests );
		Client::catalog( false );
		$this->assertCount( 4, $this->requests, 'refresh asks again' );

		// Another business does not see this one's answer.
		$this->connect( array( 'slug' => 'other' ) );
		$this->assertTrue( is_wp_error( Client::catalog() ) );
	}

	public function test_catalog_without_services() {
		$this->connect();
		$this->answer_catalog( 404 );
		$data = Client::catalog();
		$this->assertCount( 2, $data['units'] );
		$this->assertSame( array(), $data['services'] );
	}

	public function test_catalog_errors() {
		$this->assertSame( 'anybook_not_connected', Client::catalog()->get_error_code() );
		$this->assertEmpty( $this->requests );
		$this->connect();
		$this->answer_catalog( 500 );
		$this->assertSame( 'anybook_unreachable', Client::catalog()->get_error_code() );
		$asked = count( $this->requests );
		// A failure is kept for a minute (review Q2), so the next editor load does not wait on Anybook again.
		$this->assertSame( 'anybook_unreachable', Client::catalog()->get_error_code() );
		$this->assertCount( $asked, $this->requests );
		$this->assertArrayHasKey( 'error', $this->transients['anybook_catalog'] );
	}

	public function test_rest_route() {
		$routes = array();
		Functions\when( 'register_rest_route' )->alias(
			function ( $ns, $route, $args ) use ( &$routes ) {
				$routes[ $ns . $route ] = $args;
			}
		);
		Rest::register();
		$this->assertSame( 'GET', $routes['anybook/v1/catalog']['methods'] );
		$this->assertTrue( call_user_func( $routes['anybook/v1/catalog']['permission_callback'] ) );
		$this->caps = array();
		$this->assertFalse( Rest::can_edit() );

		$error = Rest::catalog( null );
		$this->assertSame( array( 'status' => 409 ), $error->get_error_data() );
		$this->connect();
		$this->answer_catalog( 500 );
		$this->assertSame( array( 'status' => 502 ), Rest::catalog( null )->get_error_data() );
		$this->transients = array();
		$this->answer_catalog();
		$this->assertSame( 'belle', Rest::catalog( null )['units'][0]['code'] );
	}

	public function test_refresh_is_for_administrators_once_every_30_seconds() {
		$this->connect();
		$this->answer_catalog();
		$refresh = new \WP_REST_Request( array( 'refresh' => true ) );
		Rest::catalog( new \WP_REST_Request() );
		$this->assertCount( 2, $this->requests );

		// An editor or contributor asking to refresh gets the cached answer (review S-L2).
		$this->caps = array( 'edit_posts' );
		Rest::catalog( $refresh );
		Rest::catalog( $refresh );
		$this->assertCount( 2, $this->requests );

		// An administrator may refresh, but not twice within 30 seconds.
		$this->caps = array( 'manage_options', 'edit_posts' );
		Rest::catalog( $refresh );
		$this->assertCount( 4, $this->requests );
		Rest::catalog( $refresh );
		$this->assertCount( 4, $this->requests, 'throttled' );
		unset( $this->transients['anybook_refresh_7'] ); // 30 seconds later.
		Rest::catalog( $refresh );
		$this->assertCount( 6, $this->requests );
	}
}
