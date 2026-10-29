<?php
namespace Anybook\Tests;

use Anybook\Settings;

class SettingsTest extends TestCase {

	public function test_defaults_when_nothing_stored() {
		$this->assertSame( 'https://app.anybookapp.com', Settings::site() );
		$this->assertFalse( Settings::is_connected() );
		$this->assertSame( '', Settings::get( 'slug' ) );
	}

	public function test_update_keeps_known_keys_only_and_is_not_autoloaded() {
		Settings::update( array( 'slug' => 'bribie', 'api_key' => 'nope' ) );
		$this->assertTrue( Settings::is_connected() );
		$this->assertArrayNotHasKey( 'api_key', $this->options['anybook_settings'] );
		$this->assertFalse( $this->autoload['anybook_settings'] );
	}

	public function test_business_codes() {
		$this->assertSame( 'bribie', Settings::clean_code( ' bribie ' ) );
		$this->assertSame( 'the_belle-2', Settings::clean_code( 'the_belle-2' ) );
		$this->assertSame( '', Settings::clean_code( 'bri bie' ) );
		$this->assertSame( '', Settings::clean_code( '"><script>' ) );
		$this->assertSame( '', Settings::clean_code( '../etc' ) );
		$this->assertSame( '', Settings::clean_code( str_repeat( 'a', 65 ) ) );
		$this->assertSame( '', Settings::clean_code( array( 'x' ) ) );
	}

	public function test_colours() {
		$this->assertSame( '#0b6e4f', Settings::clean_colour( '#0B6E4F' ) );
		$this->assertSame( '#abc', Settings::clean_colour( '#abc' ) );
		$this->assertSame( '', Settings::clean_colour( 'red' ) );
		$this->assertSame( '', Settings::clean_colour( '#12345' ) );
		$this->assertSame( '', Settings::clean_colour( '#123;background:url(x)' ) );
	}

	/**
	 * @dataProvider sites
	 */
	public function test_booking_site_addresses( $input, $expected ) {
		$this->assertSame( $expected, Settings::clean_site( $input ) );
	}

	public function sites() {
		return array(
			'https'           => array( 'https://app.anybookapp.com/', 'https://app.anybookapp.com' ),
			'https with port' => array( 'https://test.anybookapp.com:8443', 'https://test.anybookapp.com:8443' ),
			'upper case'      => array( 'HTTPS://App.AnybookApp.com', 'https://app.anybookapp.com' ),
			'http local'      => array( 'http://localhost:5000', 'http://localhost:5000' ),
			'http .test'      => array( 'http://anybook.test', 'http://anybook.test' ),
			'http public'     => array( 'http://app.anybookapp.com', '' ),
			'path'            => array( 'https://app.anybookapp.com/widget', '' ),
			'user info'       => array( 'https://me@app.anybookapp.com', '' ),
			'query'           => array( 'https://app.anybookapp.com?x=1', '' ),
			'javascript'      => array( 'javascript:alert(1)', '' ),
			'not a url'       => array( 'app.anybookapp.com', '' ),
		);
	}

	public function test_site_origin() {
		$this->home = 'https://Example.com/blog';
		$this->assertSame( 'https://example.com', Settings::site_origin() );
		$this->home = 'http://localhost:8080';
		$this->assertSame( 'http://localhost:8080', Settings::site_origin() );
	}
}
