<?php
namespace Anybook\Tests;

use Anybook\Renderer;
use Anybook\Shortcode;
use Brain\Monkey\Functions;

class RendererTest extends TestCase {

	const BARE = '<booking-widget business="bribie" api="https://site.anybookapp.com"></booking-widget>';

	public function test_nothing_for_visitors_when_not_connected() {
		$this->caps = array();
		$this->assertSame( '', Renderer::render() );
		$this->assertEmpty( $this->scripts['enqueued'] );
	}

	public function test_editors_are_told_to_connect() {
		$this->assertStringContainsString( 'anybook-not-connected', Renderer::render() );
	}

	public function test_minimal_element_and_script() {
		$this->connect();
		$this->assertSame( self::BARE, Renderer::render() );
		$this->assertArrayHasKey( 'anybook-widget', $this->scripts['enqueued'] );
		$registered = $this->scripts['registered']['anybook-widget'];
		$this->assertSame( 'https://site.anybookapp.com/widget/booking-widget.js', $registered['src'] );
		$this->assertNull( $registered['ver'] );
		$this->assertSame( 'defer', $registered['args']['strategy'] );
	}

	public function test_all_options() {
		$this->connect();
		$html = Renderer::render(
			array(
				'unit'        => 'belle',
				'service'     => 'cut',
				'accent'      => '#0B6E4F',
				'text'        => '#111',
				'guest_links' => true,
				'texts'       => array( 'toPayment' => 'Book & pay "now"' ),
			)
		);
		$this->assertStringContainsString( ' unit="belle"', $html );
		$this->assertStringContainsString( ' service="cut" services', $html );
		$this->assertStringContainsString( ' guest-links', $html );
		$this->assertStringContainsString( ' style="--bw-accent:#0b6e4f;--bw-text:#111;"', $html );
		// The JSON is attribute-escaped: no raw quote can end the attribute.
		$this->assertStringContainsString( ' texts="{&quot;toPayment&quot;:&quot;Book &amp; pay \&quot;now\&quot;&quot;}"', $html );
	}

	public function test_bad_values_are_dropped() {
		$this->connect();
		$html = Renderer::render(
			array(
				'unit'   => '"><script>alert(1)</script>',
				'accent' => 'red;background:url(x)',
				'texts'  => array(
					'bad key!' => 'x',
					'ok'       => array( 'nested' ),
				),
			)
		);
		$this->assertSame( self::BARE, $html );
	}

	public function test_default_colours_from_settings() {
		$this->connect( array( 'accent' => '#123456' ) );
		$this->assertStringContainsString( 'style="--bw-accent:#123456;"', Renderer::render() );
		$this->assertStringContainsString( 'style="--bw-accent:#654321;"', Renderer::render( array( 'accent' => '#654321' ) ) );
	}

	public function test_shortcode_maps_its_attributes() {
		$this->connect();
		Functions\when( 'shortcode_atts' )->alias(
			function ( $defaults, $atts ) {
				return array_merge( $defaults, array_intersect_key( $atts, $defaults ) );
			}
		);
		$html = Shortcode::render(
			array(
				'unit'        => 'belle',
				'guest_links' => 'Yes',
				'services'    => 'no',
				'button_text' => 'Go',
			)
		);
		$this->assertSame(
			'<booking-widget business="bribie" api="https://site.anybookapp.com" unit="belle" guest-links texts="{&quot;toPayment&quot;:&quot;Go&quot;}"></booking-widget>',
			$html
		);
		$this->assertSame( self::BARE, Shortcode::render( '' ) );
	}
}
