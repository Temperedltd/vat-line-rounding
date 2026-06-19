<?php
/**
 * Tests for WooCommerce tax configuration diagnostics.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

final class ConfigDiagnosticsTest extends Tempered_VLR_Test_Case {
	public function test_configuration_requires_no_attention_when_expected_tax_rounding_is_enabled(): void {
		self::assertFalse( Tempered_Vat_Line_Rounding::config_requires_attention( 'yes', PHP_ROUND_HALF_UP ) );
	}

	public function test_configuration_requires_attention_when_subtotal_tax_rounding_is_disabled(): void {
		self::assertTrue( Tempered_Vat_Line_Rounding::config_requires_attention( 'no', PHP_ROUND_HALF_UP ) );
	}

	public function test_configuration_requires_attention_when_tax_rounding_is_not_half_up(): void {
		self::assertTrue( Tempered_Vat_Line_Rounding::config_requires_attention( 'yes', PHP_ROUND_HALF_DOWN ) );
	}

	public function test_admin_notice_is_translatable_and_dismissible(): void {
		ob_start();
		Tempered_Vat_Line_Rounding::render_admin_notice();
		$notice = (string) ob_get_clean();

		self::assertStringContainsString( 'notice notice-warning is-dismissible', $notice );
		self::assertStringContainsString( 'VAT Line Rounding expects WooCommerce subtotal tax rounding', $notice );
		self::assertSame(
			array(
				array(
					'text'   => 'VAT Line Rounding expects WooCommerce subtotal tax rounding and WC_TAX_ROUNDING_MODE set to PHP_ROUND_HALF_UP.',
					'domain' => 'vat-line-rounding',
				),
			),
			$GLOBALS['tempered_vlr_test_translations']
		);
	}
}
