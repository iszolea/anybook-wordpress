<?php
/**
 * The Anybook booking block (ADR-6).
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `anybook/booking` from the built block.json and renders it on the server through the same renderer
 * as the shortcode, so the block and `[anybook]` give the same element. The editor shows a preview card only:
 * no remote script runs in the admin.
 */
class Block {

	const NAME = 'anybook/booking';

	/** Hooks the block in. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/** Registers the block, if it has been built. */
	public static function register() {
		$dir = ANYBOOK_DIR . 'build/booking';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type(
			$dir,
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
		$handle = generate_block_asset_handle( self::NAME, 'editorScript' );
		wp_set_script_translations( $handle, 'anybook', ANYBOOK_DIR . 'languages' );
	}

	/**
	 * What the editor needs to know before it asks for the catalogue: whether a business is connected, its name,
	 * and where to connect (for people who may).
	 */
	public static function editor_data() {
		$handle = generate_block_asset_handle( self::NAME, 'editorScript' );
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}
		$data = array(
			'connected'   => Settings::is_connected(),
			'name'        => (string) Settings::get( 'name' ),
			// The default colours from the settings page, for the preview when the block sets none.
			'accent'      => Settings::clean_colour( Settings::get( 'accent' ) ),
			'text'        => Settings::clean_colour( Settings::get( 'text' ) ),
			'settingsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=' . Admin::PAGE ) : '',
		);
		wp_add_inline_script( $handle, 'window.anybookBlock = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * The block's HTML on the page.
	 *
	 * @param array $attributes Block attributes (block.json).
	 */
	public static function render( $attributes ) {
		return Renderer::render( Renderer::options_for( self::choice( is_array( $attributes ) ? $attributes : array() ) ) );
	}

	/**
	 * The block's attributes in the shortcode's words.
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function choice( array $attributes ) {
		$map    = array(
			'show'           => 'show',
			'unit'           => 'unit',
			'service'        => 'service',
			'accent'         => 'accent',
			'text'           => 'text',
			'buttonText'     => 'button_text',
			'detailsHeading' => 'details_heading',
			'datesText'      => 'dates_text',
			'units'          => 'units',
			'rows'           => 'rows',
		);
		$choice = array( 'show' => 'all' );
		foreach ( $map as $from => $to ) {
			if ( isset( $attributes[ $from ] ) && is_string( $attributes[ $from ] ) ) {
				$choice[ $to ] = $attributes[ $from ];
			}
		}
		$choice['guest_links'] = isset( $attributes['guestLinks'] ) && true === $attributes['guestLinks'];
		// The overview's weeks (a number) and prices (on or off).
		if ( isset( $attributes['weeks'] ) && is_int( $attributes['weeks'] ) ) {
			$choice['weeks'] = $attributes['weeks'];
		}
		$choice['prices'] = isset( $attributes['prices'] ) && true === $attributes['prices'];
		return $choice;
	}
}
