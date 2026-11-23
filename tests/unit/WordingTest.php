<?php
namespace Anybook\Tests;

/**
 * Wording rules for the plugin's source, checked on the files themselves (AB#287 WPP-07, WPP-08).
 */
class WordingTest extends \PHPUnit\Framework\TestCase {

	/** Files that ship (or document the plugin), without tests and node_modules. */
	private function shipped_files() {
		$root  = dirname( __DIR__, 2 );
		$files = array( $root . '/readme.txt', $root . '/anybook.php', $root . '/uninstall.php', $root . '/languages/anybook.pot' );
		foreach ( array( 'includes', 'blocks' ) as $dir ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( false !== strpos( $file->getPathname(), '/test/' ) || false !== strpos( $file->getPathname(), '/node_modules/' ) ) {
					continue;
				}
				if ( in_array( $file->getExtension(), array( 'php', 'js', 'json', 'txt', 'scss' ), true ) ) {
					$files[] = $file->getPathname();
				}
			}
		}
		return $files;
	}

	public function test_no_real_business_names_in_examples() {
		foreach ( $this->shipped_files() as $file ) {
			$this->assertDoesNotMatchRegularExpression( '/bribie|belle/i', file_get_contents( $file ), basename( $file ) . ' names a real business' );
		}
	}

	public function test_no_user_facing_approval_wording() {
		$root = dirname( __DIR__, 2 );
		foreach ( glob( $root . '/includes/*.php' ) as $file ) {
			preg_match_all( '/\b_[_ex]\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"[^"]*")/', file_get_contents( $file ), $m );
			foreach ( $m[1] as $string ) {
				$this->assertDoesNotMatchRegularExpression( '/approv/i', $string, basename( $file ) . ': ' . $string );
			}
		}
		$this->assertDoesNotMatchRegularExpression( '/approv/i', file_get_contents( $root . '/languages/anybook.pot' ) );
	}
}
