<?php
namespace Anybook\Tests;

use Anybook\Assets;
use Anybook\Block;
use Anybook\Renderer;
use Anybook\Shortcode;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * The availability overview (PBI 30581): `show="overview"` in the block and the shortcode gives the
 * `<booking-overview>` element and loads booking-overview.js instead of the widget script.
 */
class OverviewTest extends TestCase {

	const BARE = '<booking-overview business="bribie" api="https://app.anybookapp.com"></booking-overview>';

	const GUID = '0f8fad5b-d9cb-469f-a165-70867728950e';

	public function test_defaults_give_the_bare_element_and_the_overview_script() {
		$this->connect();
		$this->assertSame( self::BARE, Shortcode::render( array( 'show' => 'overview' ) ) );
		$this->assertArrayHasKey( 'anybook-overview', $this->scripts['enqueued'] );
		$this->assertArrayNotHasKey( 'anybook-widget', $this->scripts['enqueued'] );
		$registered = $this->scripts['registered']['anybook-overview'];
		$this->assertSame( 'https://app.anybookapp.com/widget/booking-overview.js', $registered['src'] );
		$this->assertNull( $registered['ver'] );
		$this->assertSame( 'defer', $registered['args']['strategy'] );
	}

	/**
	 * Review Q-13: the colours chosen on the plugin's settings page are the overview's too, as for the widget.
	 */
	public function test_the_settings_colours_are_the_overviews_too() {
		$this->connect(
			array(
				'accent' => '#123456',
				'text'   => '#222222',
			)
		);
		$this->assertStringContainsString( ' style="--bw-accent:#123456;--bw-text:#222222;"></booking-overview>', Shortcode::render( array( 'show' => 'overview' ) ) );
	}

	public function test_defaults_written_out_are_left_out() {
		$this->connect();
		$html = Shortcode::render(
			array(
				'show'   => 'overview',
				'weeks'  => '12',
				'prices' => 'no',
				'units'  => '',
				'rows'   => 'services',
			)
		);
		$this->assertSame( self::BARE, $html );
	}

	public function test_every_choice() {
		$this->connect();
		$html = Shortcode::render(
			array(
				'show'    => 'Overview',
				'weeks'   => '8',
				'prices'  => 'yes',
				'units'   => 'belle, kayak',
				'rows'    => 'people',
				'service' => self::GUID,
			)
		);
		$this->assertSame(
			'<booking-overview business="bribie" api="https://app.anybookapp.com" weeks="8" prices units="belle,kayak" rows="people" service="' . self::GUID . '"></booking-overview>',
			$html
		);
	}

	/** Weeks as typed and what the element gets (12, the default, is left out). */
	public function weeks() {
		return array(
			'too many'   => array( '99', ' weeks="26"' ),
			'too few'    => array( '1', ' weeks="2"' ),
			'not a word' => array( 'x', '' ),
			'negative'   => array( '-5', '' ),
			'a fraction' => array( '3.5', '' ),
			'spaces'     => array( ' 4 ', ' weeks="4"' ),
			'lowest'     => array( '2', ' weeks="2"' ),
			'highest'    => array( '26', ' weeks="26"' ),
		);
	}

	/**
	 * @dataProvider weeks
	 */
	public function test_weeks_are_a_whole_number_from_2_to_26( $typed, $attribute ) {
		$this->connect();
		$html = Shortcode::render(
			array(
				'show'  => 'overview',
				'weeks' => $typed,
			)
		);
		$this->assertSame( '<booking-overview business="bribie" api="https://app.anybookapp.com"' . $attribute . '></booking-overview>', $html );
	}

	public function test_only_yes_shows_prices() {
		$this->connect();
		foreach ( array( 'true', '1', 'on', 'yes please', '' ) as $typed ) {
			$this->assertSame(
				self::BARE,
				Shortcode::render(
					array(
						'show'   => 'overview',
						'prices' => $typed,
					)
				),
				$typed
			);
		}
		$this->assertStringContainsString( ' prices>', Shortcode::render( array( 'show' => 'overview', 'prices' => 'Yes' ) ) );
	}

	public function test_bad_unit_codes_are_dropped() {
		$this->connect();
		$html = Shortcode::render(
			array(
				'show'  => 'overview',
				'units' => 'belle,"><script>alert(1)</script>,kayak_2,' . str_repeat( 'a', 41 ) . ',belle,,Kayak, kayak ',
			)
		);
		$this->assertSame( '<booking-overview business="bribie" api="https://app.anybookapp.com" units="belle,kayak"></booking-overview>', $html );
		$this->assertSame(
			self::BARE,
			Shortcode::render(
				array(
					'show'  => 'overview',
					'units' => '"><script>alert(1)</script>',
				)
			)
		);
	}

	public function test_rows_other_than_people_are_ignored() {
		$this->connect();
		foreach ( array( 'services', 'staff', '"><b>', 'PEOPLES' ) as $rows ) {
			$this->assertSame(
				self::BARE,
				Shortcode::render(
					array(
						'show'    => 'overview',
						'rows'    => $rows,
						'service' => 'cut',
					)
				),
				$rows
			);
		}
	}

	public function test_service_only_with_rows_of_people() {
		$this->connect();
		$this->assertSame(
			self::BARE,
			Shortcode::render(
				array(
					'show'    => 'overview',
					'service' => self::GUID,
				)
			)
		);
		$this->assertStringContainsString(
			' rows="people" service="cut">',
			Shortcode::render(
				array(
					'show'    => 'overview',
					'rows'    => 'People',
					'service' => 'cut',
				)
			)
		);
		// One row per team member needs one service: a bad one or none gives one row per service.
		foreach ( array( '', '"><script>', 'cut_2', 'Cut' ) as $service ) {
			$this->assertSame(
				self::BARE,
				Shortcode::render(
					array(
						'show'    => 'overview',
						'rows'    => 'people',
						'service' => $service,
					)
				),
				$service
			);
		}
	}

	public function test_a_guid_service_is_kept_in_lower_case() {
		$this->connect();
		$this->assertStringContainsString(
			' service="' . self::GUID . '"',
			Shortcode::render(
				array(
					'show'    => 'overview',
					'rows'    => 'people',
					'service' => strtoupper( self::GUID ),
				)
			)
		);
	}

	public function test_widget_choices_do_not_reach_the_overview() {
		$this->connect();
		$html = Shortcode::render(
			array(
				'show'        => 'overview',
				'unit'        => 'belle',
				'guest_links' => 'yes',
				'button_text' => 'Go',
			)
		);
		$this->assertSame( self::BARE, $html );
	}

	public function test_block_and_shortcode_give_the_same_overview() {
		$this->connect();
		$block = Block::render(
			array(
				'show'    => 'overview',
				'weeks'   => 6,
				'prices'  => true,
				'units'   => 'belle,kayak',
				'rows'    => 'people',
				'service' => self::GUID,
			)
		);
		$short = Shortcode::render(
			array(
				'show'    => 'overview',
				'weeks'   => '6',
				'prices'  => 'yes',
				'units'   => 'belle,kayak',
				'rows'    => 'people',
				'service' => self::GUID,
			)
		);
		$this->assertSame( $short, $block );
		$this->assertStringContainsString( ' weeks="6" prices units="belle,kayak" rows="people"', $block );
		$this->assertSame( self::BARE, Block::render( array( 'show' => 'overview' ) ) );
	}

	public function test_bad_block_values_are_dropped() {
		$this->connect();
		$html = Block::render(
			array(
				'show'    => 'overview',
				'weeks'   => '"><script>',
				'prices'  => 'yes',
				'units'   => array( 'belle' ),
				'rows'    => array( 'people' ),
				'service' => self::GUID,
			)
		);
		$this->assertSame( self::BARE, $html );
		$this->assertStringContainsString( ' weeks="26"', Block::render( array( 'show' => 'overview', 'weeks' => 400 ) ) );
	}

	public function test_nothing_for_visitors_when_not_connected() {
		$this->caps = array();
		$this->assertSame( '', Shortcode::render( array( 'show' => 'overview' ) ) );
		$this->assertEmpty( $this->scripts['enqueued'] );
	}

	public function test_a_page_with_both_gets_both_scripts() {
		$this->connect();
		Shortcode::render( array( 'show' => 'overview' ) );
		Shortcode::render( array( 'unit' => 'belle' ) );
		$this->assertArrayHasKey( 'anybook-overview', $this->scripts['enqueued'] );
		$this->assertArrayHasKey( 'anybook-widget', $this->scripts['enqueued'] );
		$this->assertSame( 'defer', $this->scripts['registered']['anybook-widget']['args']['strategy'] );
		$this->assertSame( 'defer', $this->scripts['registered']['anybook-overview']['args']['strategy'] );
	}

	/** Viewing a post with this content. */
	private function viewing( $content ) {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( new \WP_Post( array( 'post_content' => $content ) ) );
	}

	/** What a view's content puts in the head: overview script, widget script. */
	public function heads() {
		return array(
			'overview shortcode'          => array( '[anybook show="overview" weeks="8"]', true, false ),
			'overview, single quotes'     => array( "[anybook weeks='8' show='overview']", true, false ),
			'overview, bare'              => array( '[anybook show=overview]', true, false ),
			'overview block'              => array( '<!-- wp:anybook/booking {"show":"overview","weeks":8} /-->', true, false ),
			'widget shortcode'            => array( '[anybook unit="belle"]', false, true ),
			'widget block'                => array( '<!-- wp:anybook/booking {"show":"unit","unit":"belle"} /-->', false, true ),
			'plain block'                 => array( '<!-- wp:anybook/booking /-->', false, true ),
			'both shortcodes'             => array( '[anybook show="overview"] and [anybook]', true, true ),
			'both blocks'                 => array( '<!-- wp:anybook/booking {"show":"overview"} /--><!-- wp:anybook/booking {"guestLinks":true} /-->', true, true ),
			'overview block, widget code' => array( '<!-- wp:anybook/booking {"show":"overview"} /-->[anybook]', true, true ),
		);
	}

	/**
	 * @dataProvider heads
	 */
	public function test_the_head_gets_the_script_the_view_shows( $content, $overview, $widget ) {
		$this->connect();
		$this->viewing( $content );
		Assets::register();
		$this->assertSame( $overview, isset( $this->scripts['enqueued']['anybook-overview'] ), 'overview script' );
		$this->assertSame( $widget, isset( $this->scripts['enqueued']['anybook-widget'] ), 'widget script' );
		foreach ( array_keys( $this->scripts['enqueued'] ) as $handle ) {
			$this->assertFalse( $this->scripts['registered'][ $handle ]['args']['in_footer'], $handle );
			$this->assertSame( 'defer', $this->scripts['registered'][ $handle ]['args']['strategy'], $handle );
		}
	}

	public function test_elementor_overview_counts_as_the_overview() {
		$this->connect();
		Functions\when( 'is_singular' )->justReturn( true );
		$this->meta[5]['_elementor_data'] = '[{"elType":"widget","widgetType":"shortcode","settings":{"shortcode":"[anybook show=\\"overview\\"]"}}]';
		Functions\when( 'get_queried_object' )->justReturn(
			new \WP_Post(
				array(
					'ID'           => 5,
					'post_content' => '<p>Built with Elementor</p>',
				)
			)
		);
		Assets::register();
		$this->assertArrayHasKey( 'anybook-overview', $this->scripts['enqueued'] );
		$this->assertArrayNotHasKey( 'anybook-widget', $this->scripts['enqueued'] );
	}

	public function test_another_builder_saying_yes_gets_the_widget_script() {
		$this->connect();
		$this->viewing( '<p>Kept elsewhere</p>' );
		Filters\expectApplied( 'anybook_page_has_widget' )->andReturn( true );
		Assets::register();
		$this->assertArrayHasKey( 'anybook-widget', $this->scripts['enqueued'] );
		$this->assertArrayNotHasKey( 'anybook-overview', $this->scripts['enqueued'] );
	}

	public function test_speed_plugins_leave_the_overview_script_alone() {
		$this->connect();
		$mine = Assets::script_attributes(
			array(
				'id'  => 'anybook-overview-js',
				'src' => 'x',
			)
		);
		$this->assertSame( 'false', $mine['data-cfasync'] );
		$this->assertSame( '1', $mine['data-no-optimize'] );
		$this->assertSame( '1', $mine['data-no-defer'] );
		$this->assertSame( '1', $mine['data-no-minify'] );
		$this->assertSame( 'ignore', $mine['data-jetpack-boost'] );
		$this->assertTrue( $mine['nowprocket'] );

		$this->assertContains( '/widget/booking-overview.js', Assets::add_script_path( array() ) );
		$this->assertContains( 'anybook-overview', Assets::add_handle( array() ) );
		$this->assertStringContainsString( 'widget/booking-overview.js', Assets::add_to_list( 'a.js' ) );
		$this->assertFalse( Assets::w3tc_skip( true, '<script src="https://app.anybookapp.com/widget/booking-overview.js">' ) );
	}
}
