<?php
namespace Anybook\Tests;

use Anybook\Plugin;
use Brain\Monkey\Functions;

/** The privacy policy text (review S-L4) and the release tree (review Q17). */
class PluginTest extends TestCase {

	public function test_privacy_text_covers_page_views_storage_and_stripe() {
		$added = array();
		Functions\when( 'wp_add_privacy_policy_content' )->alias(
			function ( $name, $text ) use ( &$added ) {
				$added[] = array( $name, $text );
			}
		);
		Plugin::privacy_policy_content();
		$this->assertCount( 1, $added );
		$text = $added[0][1];
		$this->assertStringContainsString( 'IP address', $text );
		$this->assertStringContainsString( 'even if you do not book', $text );
		$this->assertStringContainsString( 'stores nothing in your browser', $text );
		$this->assertStringContainsString( 'Stripe', $text );
		$this->assertStringStartsWith( '<p>', $text );
	}

	/**
	 * WPP-04 (AB#288): the words the plugin suggests for a site's privacy policy and the readme's External services section say
	 * the same about where Anybook keeps the data, and use "customers", not "guests". Change one, and this fails until the other follows.
	 */
	public function test_the_privacy_text_and_the_readme_external_services_say_the_same() {
		$added = array();
		Functions\when( 'wp_add_privacy_policy_content' )->alias(
			function ( $name, $text ) use ( &$added ) {
				$added[] = $text;
			}
		);
		Plugin::privacy_policy_content();
		$text   = html_entity_decode( strip_tags( $added[0] ), ENT_QUOTES );
		$readme = (string) file_get_contents( ANYBOOK_DIR . 'readme.txt' );
		$this->assertSame( 1, preg_match( '/== External services ==(.*?)\n== /s', $readme, $m ), 'the readme has an External services section' );
		$section = $m[1];
		foreach ( array(
			'Anybook stores booking details on its server in France (EU).',
			"privacy policy is at https://anybookapp.com/privacy.",
		) as $fact ) {
			$this->assertStringContainsString( $fact, $text, 'plugin privacy text' );
			$this->assertStringContainsString( $fact, $section, 'readme External services' );
		}
		foreach ( array( 'IP address', 'Stripe' ) as $fact ) {
			$this->assertStringContainsString( $fact, $text, 'plugin privacy text' );
			$this->assertStringContainsString( $fact, $section, 'readme External services' );
		}
		$this->assertDoesNotMatchRegularExpression( '/\bguests?\b/i', $text, 'the privacy text says customers' );
		$this->assertStringContainsString( 'no booking, customer or payment details', $section, 'the readme closing line says customers' );
	}

	public function test_the_release_tree_holds_the_built_block() {
		// Block::register() returns quietly without it, so a broken package would show raw block comments.
		foreach ( array( 'block.json', 'index.js', 'index.asset.php', 'index.css' ) as $file ) {
			$this->assertFileExists( ANYBOOK_DIR . 'build/booking/' . $file );
		}
		$attributes = (string) file_get_contents( ANYBOOK_DIR . '.gitattributes' );
		$this->assertDoesNotMatchRegularExpression( '#^/?build\S*\s+export-ignore#m', $attributes, 'build/ ships' );
		$this->assertDoesNotMatchRegularExpression( '#^/package\.json\s+export-ignore#m', $attributes, 'package.json ships with the block source (Q8)' );
		$block = json_decode( (string) file_get_contents( ANYBOOK_DIR . 'build/booking/block.json' ), true );
		$this->assertSame( 'anybook/booking', $block['name'] );
	}
}
