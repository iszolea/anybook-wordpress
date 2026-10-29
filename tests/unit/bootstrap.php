<?php
/**
 * Unit tests without WordPress: Brain Monkey stands in for WordPress functions; options, transients and posts
 * live in memory (see TestCase).
 *
 * @package Anybook
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ANYBOOK_VERSION', 'test' );
define( 'ANYBOOK_FILE', dirname( __DIR__, 2 ) . '/anybook.php' );
define( 'ANYBOOK_DIR', dirname( __DIR__, 2 ) . '/' );

require ANYBOOK_DIR . 'vendor/autoload.php';

// The few WordPress classes the plugin uses, reduced to what it needs.
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

class WP_Post {
	public $ID;
	public $post_type    = 'page';
	public $post_status  = 'publish';
	public $post_content = '';
	public $post_title   = '';
	public $post_name    = '';
	public function __construct( array $fields ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

foreach ( glob( ANYBOOK_DIR . 'includes/*.php' ) as $file ) {
	require_once $file;
}
require_once __DIR__ . '/TestCase.php';
