<?php
/**
 * Tests for the plugin bootstrap file.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class PluginBootstrapTest extends PHPUnit\Framework\TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_plugin_defines_tax_rounding_mode_when_absent(): void {
		define( 'ABSPATH', sys_get_temp_dir() . '/' );

		self::assertFalse( defined( 'WC_TAX_ROUNDING_MODE' ) );

		require dirname( __DIR__, 2 ) . '/vat-line-rounding.php';

		self::assertTrue( defined( 'WC_TAX_ROUNDING_MODE' ) );
		self::assertSame( PHP_ROUND_HALF_UP, WC_TAX_ROUNDING_MODE );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_plugin_preserves_existing_tax_rounding_mode(): void {
		define( 'ABSPATH', sys_get_temp_dir() . '/' );
		define( 'WC_TAX_ROUNDING_MODE', PHP_ROUND_HALF_DOWN );

		require dirname( __DIR__, 2 ) . '/vat-line-rounding.php';

		self::assertSame( PHP_ROUND_HALF_DOWN, WC_TAX_ROUNDING_MODE );
	}
}
