<?php
namespace Anybook\Tests;

use Anybook\Client;
use Anybook\Credit;
use Anybook\Renderer;
use Brain\Monkey\Functions;

/** The "Booking by Anybook" credit: written on the server from Anybook's status answer, never from its HTML, never by contacting Anybook on a page view. */
class CreditTest extends TestCase {

	const FOLLOWED   = '<p class="anybook-credit">Booking by <a href="https://anybookapp.com">Anybook</a></p>';
	const NOFOLLOWED = '<p class="anybook-credit">Booking by <a href="https://anybookapp.com" rel="nofollow">Anybook</a></p>';

	protected function setUp(): void {
		parent::setUp();
		Credit::reset_page();
		$this->connect();
	}

	private function answer( $code, $body ) {
		$this->http = function () use ( $code, $body ) {
			return self::json( $code, $body );
		};
	}

	private function status_says( $credit ) {
		$this->answer( 200, self::status_body( array( 'credit' => $credit ) ) );
		Client::site_status( 'bribie', false );
	}

	public function test_the_status_answers_credit_is_kept_and_the_widget_is_followed_by_the_line() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow', 'html' => '<p>Booking by <a href="https://anybookapp.com">Anybook</a></p>' ) );
		$html = Renderer::render();
		$this->assertSame( CreditTest::widget() . self::FOLLOWED, $html );
	}

	public function test_a_paying_business_has_no_line() {
		$this->status_says( array( 'required' => false, 'rel' => 'follow', 'html' => self::FOLLOWED ) );
		$this->assertSame( CreditTest::widget(), Renderer::render() );
	}

	public function test_an_older_anybook_with_no_credit_in_its_answer_asks_for_nothing() {
		$this->answer( 200, self::status_body() );
		Client::site_status( 'bribie', false );
		$this->assertSame( CreditTest::widget(), Renderer::render() );
	}

	public function test_nofollow_and_sponsored_are_passed_on_and_nothing_else_is() {
		$this->status_says( array( 'required' => true, 'rel' => 'nofollow' ) );
		$this->assertSame( CreditTest::widget() . self::NOFOLLOWED, Renderer::render() );
		Credit::reset_page();
		$this->status_says( array( 'required' => true, 'rel' => 'sponsored' ) );
		$this->assertStringContainsString( ' rel="sponsored">Anybook</a>', Renderer::render() );
		Credit::reset_page();
		$this->status_says( array( 'required' => true, 'rel' => 'noopener" onclick="x' ) );
		$this->assertSame( CreditTest::widget() . self::FOLLOWED, Renderer::render() );
	}

	public function test_the_html_in_the_answer_is_never_printed() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow', 'html' => '<script>alert(1)</script><a href="https://evil.example">x</a>' ) );
		$html = Renderer::render();
		$this->assertStringNotContainsString( 'evil', $html );
		$this->assertStringNotContainsString( 'script', $html );
		$this->assertSame( CreditTest::widget() . self::FOLLOWED, $html );
	}

	public function test_required_must_be_exactly_true() {
		foreach ( array( 'yes', 1, '1', 'true', array( 'x' ) ) as $loose ) {
			Credit::reset_page();
			$this->status_says( array( 'required' => $loose ) );
			$this->assertSame( CreditTest::widget(), Renderer::render(), 'required => ' . json_encode( $loose ) );
		}
	}

	public function test_a_page_view_never_contacts_anybook() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow' ) );
		$before = count( $this->requests );
		Credit::reset_page();
		Renderer::render();
		Credit::reset_page();
		Renderer::render();
		$this->assertCount( $before, $this->requests );
	}

	public function test_the_line_is_written_once_a_page_even_with_several_widgets() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow' ) );
		$first  = Renderer::render();
		$second = Renderer::render();
		$this->assertStringContainsString( 'anybook-credit', $first );
		$this->assertStringNotContainsString( 'anybook-credit', $second );
	}

	public function test_the_overview_gets_the_line_too() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow' ) );
		$this->assertStringEndsWith( '</booking-overview>' . self::FOLLOWED, Renderer::render( array( 'overview' => true ) ) );
	}

	public function test_nothing_is_written_before_anybook_has_been_asked() {
		$this->assertSame( CreditTest::widget(), Renderer::render() );
	}

	public function test_a_different_business_never_shows_the_last_ones_credit_and_disconnecting_forgets_it() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow' ) );
		$this->connect( array( 'slug' => 'other-boats' ) );
		$this->assertSame( '<booking-widget business="other-boats" api="https://site.anybookapp.com"></booking-widget>', Renderer::render() );
		$this->connect();
		\Anybook\Connection::disconnect();
		$this->assertArrayNotHasKey( Credit::OPTION, $this->options );
	}

	public function test_the_settings_page_status_tells_the_admin_about_the_line() {
		$this->status_says( array( 'required' => true, 'rel' => 'follow' ) );
		$this->assertTrue( $this->options[ Credit::OPTION ]['required'] );
		$this->assertSame( 'follow', $this->options[ Credit::OPTION ]['rel'] );
	}

	public static function widget() {
		return '<booking-widget business="bribie" api="https://site.anybookapp.com"></booking-widget>';
	}
}
