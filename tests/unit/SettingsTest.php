<?php
namespace Anybook\Tests;

use Anybook\Settings;

class SettingsTest extends TestCase {

	public function test_defaults_when_nothing_stored() {
		$this->assertSame( 'https://site.anybookapp.com', Settings::site() );
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
			'https'           => array( 'https://site.anybookapp.com/', 'https://site.anybookapp.com' ),
			'https with port' => array( 'https://test.anybookapp.com:8443', 'https://test.anybookapp.com:8443' ),
			'upper case'      => array( 'HTTPS://Site.AnybookApp.com', 'https://site.anybookapp.com' ),
			'http local'      => array( 'http://localhost:5000', 'http://localhost:5000' ),
			'http .test'      => array( 'http://anybook.test', '' ),
			'http .localhost' => array( 'http://anybook.localhost:8092', 'http://anybook.localhost:8092' ),
			'http public'     => array( 'http://site.anybookapp.com', '' ),
			'path'            => array( 'https://site.anybookapp.com/widget', '' ),
			'user info'       => array( 'https://me@site.anybookapp.com', '' ),
			'query'           => array( 'https://site.anybookapp.com?x=1', '' ),
			'javascript'      => array( 'javascript:alert(1)', '' ),
			'not a url'       => array( 'site.anybookapp.com', '' ),
		);
	}

	public function test_site_origin() {
		$this->home = 'https://Example.com/blog';
		$this->assertSame( 'https://example.com', Settings::site_origin() );
		$this->home = 'http://localhost:8080';
		$this->assertSame( 'http://localhost:8080', Settings::site_origin() );
		$this->assertSame( 'http://localhost:8080', Settings::admin_origin() );
		$this->admin = 'https://admin.example.com';
		$this->assertSame( 'https://admin.example.com', Settings::admin_origin() );
	}

	public function test_anybook_hosts() {
		$this->assertTrue( Settings::is_anybook_host( 'anybookapp.com' ) );
		$this->assertTrue( Settings::is_anybook_host( 'site.anybookapp.com' ) );
		$this->assertTrue( Settings::is_anybook_host( 'test.site.anybookapp.com' ) );
		$this->assertFalse( Settings::is_anybook_host( 'xanybookapp.com' ) );
		$this->assertFalse( Settings::is_anybook_host( 'anybookapp.com.evil.example' ) );
		$this->assertFalse( Settings::is_anybook_host( 'app.anybook-app.com' ) );
		$this->assertFalse( Settings::is_anybook_host( '.anybookapp.com' ) );
	}

	public function test_form_sites() {
		$this->assertSame( 'https://test.anybookapp.com', Settings::form_site( 'https://test.anybookapp.com/' ) );
		$this->assertSame( 'http://localhost:5000', Settings::form_site( 'http://localhost:5000' ) );
		$this->assertSame( 'https://anybook.localhost', Settings::form_site( 'https://anybook.localhost' ) );
		$this->assertSame( '', Settings::form_site( 'https://evil.example' ) );
		$this->assertSame( '', Settings::form_site( 'http://site.anybookapp.com' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_config_constant_sets_any_site() {
		define( 'ANYBOOK_SITE', 'https://bookings.example.org/' );
		$this->assertSame( 'https://bookings.example.org', Settings::site() );
		$this->connect( array( 'site' => 'https://test.anybookapp.com' ) );
		$this->assertSame( 'https://bookings.example.org', Settings::site(), 'the constant wins over the saved address' );
		$this->caps = array( 'manage_options', 'unfiltered_html' );
		$this->assertFalse( \Anybook\Admin::may_change_site(), 'the form cannot change it' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_bad_constant_is_ignored() {
		define( 'ANYBOOK_SITE', 'javascript:alert(1)' );
		$this->assertSame( 'https://site.anybookapp.com', Settings::site() );
	}

	public function test_the_dev_address_is_not_used_from_the_settings() {
		$this->assertSame( '', Settings::form_site( Settings::DEV_SITE ) );
		$this->connect( array( 'site' => Settings::DEV_SITE ) );
		$this->assertSame( Settings::DEFAULT_SITE, Settings::site() );
	}

	public function test_shared_origins() {
		$this->assertFalse( Settings::shares_origin() );
		$this->multisite = true;
		$this->assertFalse( Settings::shares_origin(), 'a subdomain network gives each site its own origin' );
		$this->subdomains = false;
		$this->assertTrue( Settings::shares_origin(), 'a subdirectory network shares one' );
		$this->multisite = false;
		$this->home      = 'https://example.com/wordpress/';
		$this->assertTrue( Settings::shares_origin(), 'WordPress in a folder shares its origin' );
	}

	public function test_connected_flag_is_autoloaded_and_follows_the_business() {
		$this->assertFalse( Settings::maybe_connected() );
		Settings::update( array( 'slug' => 'bribie' ) );
		$this->assertSame( '1', $this->options['anybook_connected'] );
		$this->assertTrue( $this->autoload['anybook_connected'] );
		$this->assertTrue( Settings::maybe_connected() );
		Settings::update( array( 'slug' => '' ) );
		$this->assertSame( '0', $this->options['anybook_connected'] );
		$this->assertFalse( Settings::maybe_connected() );
	}
}
