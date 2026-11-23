<?php
namespace Anybook\Tests;

use Anybook\Booking_Page;
use Anybook\Settings;

class UninstallTest extends TestCase {

	private function uninstall() {
		global $wpdb;
		$t = $this;
		\Brain\Monkey\Functions\when( 'delete_option' )->alias(
			function ( $name ) use ( $t ) {
				unset( $t->options[ $name ] );
				return true;
			}
		);
		$wpdb = new class() {
			public $options = 'wp_options';
			public function prepare( $query ) {
				return $query;
			}
			public function esc_like( $text ) {
				return $text;
			}
			public function query( $query ) {
				return 0;
			}
		};
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'anybook/anybook.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	public function test_an_untouched_book_online_page_becomes_a_draft() {
		foreach ( array( Booking_Page::BLOCK_CONTENT, Booking_Page::SHORTCODE_CONTENT ) as $content ) {
			$this->posts = array();
			$id          = Booking_Page::ensure_draft();
			Booking_Page::publish( $id );
			$this->posts[ $id ]->post_content = $content;
			$this->assertSame( 'publish', $this->posts[ $id ]->post_status );
			$this->uninstall();
			$this->assertSame( 'draft', $this->posts[ $id ]->post_status );
			$this->assertSame( array(), $this->options, 'settings are gone' );
			Settings::update( array( 'page_id' => 0 ) );
		}
	}

	public function test_an_edited_page_stays_published() {
		$id = Booking_Page::ensure_draft();
		Booking_Page::publish( $id );
		$this->posts[ $id ]->post_content .= "\n<p>Our boats are the best.</p>";
		$this->uninstall();
		$this->assertSame( 'publish', $this->posts[ $id ]->post_status );
		$this->assertSame( array(), $this->options, 'settings are gone' );
	}

	public function test_no_page_is_no_problem() {
		$this->uninstall();
		$this->assertSame( array(), $this->options );
	}
}
