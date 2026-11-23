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
	 * WPP-04 (AB#288): the readme's External services section and the suggested privacy text say the same things, so they cannot
	 * drift apart: what a page view sends, what a booking sends, where Anybook keeps it (France, in the EU, as anybookapp.com/privacy
	 * says), Anybook's privacy policy, Stripe, and that the widget stores nothing in the browser. Neither says "guest".
	 */
	public function test_the_readme_and_the_privacy_text_say_the_same() {
		$added = array();
		Functions\when( 'wp_add_privacy_policy_content' )->alias(
			function ( $name, $text ) use ( &$added ) {
				$added[] = $text;
			}
		);
		Plugin::privacy_policy_content();
		$privacy = html_entity_decode( self::text_of( $added[0] ), ENT_QUOTES );
		$readme  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/readme.txt' );
		$this->assertSame( 1, preg_match( '/== External services ==(.*?)\n== /s', $readme, $m ), 'the readme has an External services section' );
		$services = $m[1];
		$shared   = array(
			'IP address',
			'the address of the page',
			'even if',
			'email address, phone number and booking details',
			'France, in the European Union',
			'https://anybookapp.com/privacy',
			'Stripe',
			'stores nothing in',
		);
		foreach ( $shared as $fact ) {
			$this->assertStringContainsString( $fact, $privacy, "the privacy text says: $fact" );
			$this->assertStringContainsString( $fact, $services, "the readme's External services says: $fact" );
		}
		$this->assertStringNotContainsStringIgnoringCase( 'guest', $privacy );
		$this->assertStringNotContainsStringIgnoringCase( 'guest', $services );
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

	/** The text of HTML without its tags (WordPress's wp_strip_all_tags() is not loaded in the unit tests). */
	private static function text_of( $html ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( str_replace( '</p>', '</p> ', $html ) ) ) );
	}
}
