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
