<?php
namespace Anybook\Tests;

use Anybook\Booking_Page;
use Anybook\Settings;

class BookingPageTest extends TestCase {

	public function test_makes_and_remembers_a_draft_then_publishes_it() {
		$id = Booking_Page::ensure_draft();
		$this->assertSame( $id, Settings::get( 'page_id' ) );
		$this->assertSame( 'draft', $this->posts[ $id ]->post_status );
		$this->assertSame( 'book', $this->posts[ $id ]->post_name );
		$this->assertTrue( Booking_Page::has_widget( $this->posts[ $id ]->post_content ) );
		$this->assertSame( 'unpublished', Booking_Page::problem() );
		$this->assertSame( $id, Booking_Page::ensure_draft(), 'no second page' );
		$this->assertCount( 1, $this->posts );
		Booking_Page::publish( $id );
		$this->assertSame( 'publish', $this->posts[ $id ]->post_status );
		$this->assertSame( '', Booking_Page::problem() );
		$this->assertSame( $id, Booking_Page::ensure_draft() );
		$this->assertSame( 'publish', $this->posts[ $id ]->post_status, 'a published page is left as it is' );
	}

	public function test_a_draft_knows_its_address_once_published() {
		$id = Booking_Page::ensure_draft();
		$this->assertSame( 'https://example.com/book/', Booking_Page::future_permalink( $id ) );
		$this->posts[1] = new \WP_Post(
			array(
				'ID'          => 1,
				'post_name'   => 'book',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'https://example.com/book-2/', Booking_Page::future_permalink( $id ), 'the slug WordPress will give it' );
		$this->assertSame( 'draft', $this->posts[ $id ]->post_status, 'working out the address changes nothing' );
	}

	public function test_a_trashed_page_is_replaced() {
		$id                              = Booking_Page::ensure_draft();
		$this->posts[ $id ]->post_status = 'trash';
		$this->assertNotSame( $id, Booking_Page::ensure_draft() );
	}

	public function test_elementor_page_is_not_reported_as_missing_the_widget() {
		$id = Booking_Page::ensure_draft();
		Booking_Page::publish( $id );
		$this->posts[ $id ]->post_content     = '<p>Built with Elementor</p>';
		$this->meta[ $id ]['_elementor_data'] = '[{"settings":{"shortcode":"[anybook guest_links=\\"yes\\"]"}}]';
		$this->assertSame( '', Booking_Page::problem() );
	}

	public function test_problems() {
		$this->assertSame( '', Booking_Page::problem(), 'none made' );
		$id = Booking_Page::ensure_draft();
		Booking_Page::publish( $id );
		$this->posts[ $id ]->post_content = 'Just words';
		$this->assertSame( 'no-widget', Booking_Page::problem() );
		$this->posts[ $id ]->post_status = 'trash';
		$this->assertSame( 'missing', Booking_Page::problem() );
		unset( $this->posts[ $id ] );
		$this->assertSame( 'missing', Booking_Page::problem() );
	}
}
