<?php
/**
 * Shared set-up: a small in-memory WordPress.
 *
 * @package Anybook
 */

namespace Anybook\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/** @var array Options by name. */
	protected $options = array();
	/** @var array Autoload flag by option name. */
	protected $autoload = array();
	/** @var array Transients by name. */
	protected $transients = array();
	/** @var \WP_Post[] Posts by id. */
	protected $posts = array();
	/** @var array Requests made with wp_remote_get. */
	protected $requests = array();
	/** @var callable Answers wp_remote_get: url => response array or WP_Error. */
	protected $http;
	/** @var array Capabilities the current user has. */
	protected $caps = array( 'manage_options', 'edit_posts' );
	/** @var array Scripts registered and enqueued. */
	protected $scripts = array(
		'registered' => array(),
		'enqueued'   => array(),
	);
	/** @var string This site's home address. */
	protected $home = 'https://example.com';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();

		$t = $this;
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) use ( $t ) {
				return array_key_exists( $name, $t->options ) ? $t->options[ $name ] : $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value, $autoload = null ) use ( $t ) {
				$t->options[ $name ]  = $value;
				$t->autoload[ $name ] = $autoload;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( $name ) use ( $t ) {
				return array_key_exists( $name, $t->transients ) ? $t->transients[ $name ] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value ) use ( $t ) {
				$t->transients[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( $name ) use ( $t ) {
				unset( $t->transients[ $name ] );
				return true;
			}
		);
		Functions\when( 'untrailingslashit' )->alias(
			function ( $s ) {
				return rtrim( $s, '/\\' );
			}
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'sanitize_text_field' )->alias(
			function ( $s ) {
				return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
			}
		);
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'home_url' )->alias(
			function () use ( $t ) {
				return $t->home;
			}
		);
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) use ( $t ) {
				return $t->home . '/wp-admin/' . $path;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Example & Co' );
		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) use ( $t ) {
				return in_array( $cap, $t->caps, true );
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_generate_password' )->alias(
			function ( $length = 12 ) {
				return substr( bin2hex( random_bytes( $length ) ), 0, $length );
			}
		);

		// HTTP.
		$this->http = function () {
			return new \WP_Error( 'http_request_failed', 'no answer set' );
		};
		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args = array() ) use ( $t ) {
				$t->requests[] = array( $url, $args );
				return call_user_func( $t->http, $url );
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $r ) {
				return isset( $r['response']['code'] ) ? $r['response']['code'] : '';
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $r ) {
				return isset( $r['body'] ) ? $r['body'] : '';
			}
		);

		// Posts.
		Functions\when( 'get_post' )->alias(
			function ( $id ) use ( $t ) {
				return isset( $t->posts[ $id ] ) ? $t->posts[ $id ] : null;
			}
		);
		Functions\when( 'get_post_type' )->alias(
			function ( $id ) use ( $t ) {
				return isset( $t->posts[ $id ] ) ? $t->posts[ $id ]->post_type : false;
			}
		);
		Functions\when( 'get_post_status' )->alias(
			function ( $id ) use ( $t ) {
				return isset( $t->posts[ $id ] ) ? $t->posts[ $id ]->post_status : false;
			}
		);
		Functions\when( 'wp_insert_post' )->alias(
			function ( $fields ) use ( $t ) {
				$id               = count( $t->posts ) + 100;
				$t->posts[ $id ] = new \WP_Post( array_merge( $fields, array( 'ID' => $id ) ) );
				return $id;
			}
		);
		Functions\when( 'wp_update_post' )->alias(
			function ( $fields ) use ( $t ) {
				foreach ( $fields as $key => $value ) {
					$t->posts[ $fields['ID'] ]->$key = $value;
				}
				return $fields['ID'];
			}
		);
		Functions\when( 'has_block' )->alias(
			function ( $name, $content ) {
				return false !== strpos( (string) $content, '<!-- wp:' . $name );
			}
		);
		Functions\when( 'has_shortcode' )->alias(
			function ( $content, $tag ) {
				return (bool) preg_match( '/\[' . preg_quote( $tag, '/' ) . '[\s\]]/', (string) $content );
			}
		);

		// Scripts.
		Functions\when( 'wp_register_script' )->alias(
			function ( $handle, $src, $deps, $ver, $args ) use ( $t ) {
				$t->scripts['registered'][ $handle ] = compact( 'src', 'ver', 'args' );
				return true;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			function ( $handle ) use ( $t ) {
				$t->scripts['enqueued'][ $handle ] = true;
			}
		);
		Functions\when( 'wp_script_is' )->alias(
			function ( $handle, $list = 'enqueued' ) use ( $t ) {
				return isset( $t->scripts[ $list ][ $handle ] );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Stores a connected business. */
	protected function connect( array $extra = array() ) {
		$this->options['anybook_settings'] = array_merge(
			array(
				'site' => 'https://app.anybookapp.com',
				'slug' => 'bribie',
				'name' => 'Bribie Houseboat Holidays',
			),
			$extra
		);
	}

	/**
	 * A JSON answer for wp_remote_get.
	 *
	 * @param int   $code HTTP status.
	 * @param mixed $body Data to encode.
	 */
	protected static function json( $code, $body ) {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => is_string( $body ) ? $body : json_encode( $body ),
		);
	}

	/** A typical site-status answer. */
	protected static function status_body( array $extra = array() ) {
		return array_merge(
			array(
				'name'          => 'Bribie Houseboat Holidays',
				'onlineBooking' => true,
				'approved'      => true,
				'originAllowed' => true,
				'guestPage'     => 'this-site',
				'hasUnits'      => true,
				'hasServices'   => false,
				'cspSources'    => array(
					array(
						'directive' => 'script-src',
						'sources'   => array( 'https://app.anybookapp.com', 'https://js.stripe.com' ),
					),
				),
			),
			$extra
		);
	}
}
