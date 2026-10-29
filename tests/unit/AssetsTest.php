<?php
namespace Anybook\Tests;

use Anybook\Assets;
use Brain\Monkey\Functions;

class AssetsTest extends TestCase {

	private function viewing( $content ) {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( new \WP_Post( array( 'post_content' => $content ) ) );
	}

	public function test_not_registered_until_connected() {
		$this->viewing( '[anybook]' );
		Assets::register();
		$this->assertEmpty( $this->scripts['registered'] );
	}

	public function test_head_when_the_viewed_post_holds_the_widget() {
		$this->connect();
		$this->viewing( 'Hello [anybook unit="belle"]' );
		Assets::register();
		$this->assertFalse( $this->scripts['registered']['anybook-widget']['args']['in_footer'] );
		$this->assertArrayHasKey( 'anybook-widget', $this->scripts['enqueued'] );
	}

	public function test_registered_for_the_footer_otherwise() {
		$this->connect();
		$this->viewing( 'No widget here, [anybooking]' );
		Assets::register();
		$this->assertTrue( $this->scripts['registered']['anybook-widget']['args']['in_footer'] );
		$this->assertEmpty( $this->scripts['enqueued'] );
	}

	public function test_rendered_before_the_head_goes_in_the_head() {
		$this->connect();
		Functions\when( 'did_action' )->justReturn( 0 ); // A block theme renders the template before wp_head.
		Assets::enqueue();
		$this->assertFalse( $this->scripts['registered']['anybook-widget']['args']['in_footer'] );
	}

	public function test_rendered_after_the_head_goes_in_the_footer() {
		$this->connect();
		Assets::enqueue();
		$this->assertTrue( $this->scripts['registered']['anybook-widget']['args']['in_footer'] );
	}

	public function test_block_counts_as_the_widget() {
		$this->viewing( '<!-- wp:anybook/booking /-->' );
		$this->assertTrue( Assets::view_has_widget() );
	}

	public function test_attributes_only_on_the_widget_script() {
		$mine = Assets::script_attributes(
			array(
				'id'  => 'anybook-widget-js',
				'src' => 'x',
			)
		);
		$this->assertSame( 'false', $mine['data-cfasync'] );
		$this->assertSame( '1', $mine['data-no-optimize'] );
		$this->assertTrue( $mine['nowprocket'] );
		$other = array( 'id' => 'jquery-js' );
		$this->assertSame( $other, Assets::script_attributes( $other ) );
	}

	public function test_speed_plugin_lists() {
		$this->connect();
		$this->assertSame( array( 'a', '/widget/booking-widget.js' ), Assets::add_script_path( array( 'a' ) ) );
		$this->assertSame( array( '/widget/booking-widget.js' ), Assets::add_script_path( null ) );
		$this->assertSame( array( 'app.anybookapp.com' ), Assets::add_site_host( array() ) );
		$this->assertSame( array( 'anybook-widget' ), Assets::add_handle( array() ) );
		$this->assertSame( 'a.js, widget/booking-widget.js', Assets::add_to_list( 'a.js' ) );
		$this->assertSame( 'widget/booking-widget.js', Assets::add_to_list( '' ) );
		$this->assertFalse( Assets::w3tc_skip( true, '<script src="https://app.anybookapp.com/widget/booking-widget.js">' ) );
		$this->assertTrue( Assets::w3tc_skip( true, '<script src="/other.js">' ) );
	}
}
