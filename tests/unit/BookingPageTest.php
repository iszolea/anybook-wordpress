<?php
namespace Anybook\Tests;

use Anybook\Booking_Page;
use Anybook\Settings;

class BookingPageTest extends TestCase {

	public function test_makes_and_remembers_a_published_page() {
		$id = Booking_Page::ensure_published();
		$this->assertSame( $id, Settings::get( 'page_id' ) );
		$this->assertSame( 'publish', $this->posts[ $id ]->post_status );
		$this->assertSame( 'book', $this->posts[ $id ]->post_name );
		$this->assertTrue( Booking_Page::has_widget( $this->posts[ $id ]->post_content ) );
		$this->assertSame( '', Booking_Page::problem() );
		$this->assertSame( $id, Booking_Page::ensure_published(), 'no second page' );
		$this->assertCount( 1, $this->posts );
	}

	public function test_republishes_a_draft() {
		$id = Booking_Page::ensure_published();
		Booking_Page::unpublish( $id );
		$this->assertSame( 'unpublished', Booking_Page::problem() );
		Booking_Page::ensure_published();
		$this->assertSame( 'publish', $this->posts[ $id ]->post_status );
	}

	public function test_problems() {
		$this->assertSame( '', Booking_Page::problem(), 'none made' );
		$id                               = Booking_Page::ensure_published();
		$this->posts[ $id ]->post_content = 'Just words';
		$this->assertSame( 'no-widget', Booking_Page::problem() );
		$this->posts[ $id ]->post_status = 'trash';
		$this->assertSame( 'missing', Booking_Page::problem() );
		unset( $this->posts[ $id ] );
		$this->assertSame( 'missing', Booking_Page::problem() );
	}
}
