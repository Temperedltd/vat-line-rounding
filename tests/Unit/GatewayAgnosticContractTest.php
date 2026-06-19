<?php
/**
 * Tests for the gateway-agnostic hook contract.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

final class GatewayAgnosticContractTest extends Tempered_VLR_Test_Case {
	public function test_rounding_does_not_expose_paypal_request_body_mutators(): void {
		self::assertFalse( method_exists( Tempered_Vat_Line_Rounding::class, 'normalize_paypal_request_body' ) );
		self::assertFalse( method_exists( Tempered_Vat_Line_Rounding::class, 'normalize_paypal_patch_body' ) );
	}

	public function test_init_registers_standard_cart_subtotal_filter_without_paypal_hooks(): void {
		Tempered_Vat_Line_Rounding::init();

		$registered_subtotal_filter = false;

		foreach ( $GLOBALS['tempered_vlr_test_filters'] as $registered_filter ) {
			if (
				'woocommerce_cart_item_subtotal' === $registered_filter['hook_name'] &&
				9999 === $registered_filter['priority'] &&
				3 === $registered_filter['accepted_args'] &&
				array( Tempered_Vat_Line_Rounding::class, 'format_cart_item_subtotal' ) === $registered_filter['callback']
			) {
				$registered_subtotal_filter = true;
			}

			self::assertStringStartsNotWith(
				'ppcp_',
				(string) $registered_filter['hook_name'],
				'Gateway-agnostic rounding must not register PayPal request-body filters.'
			);
		}

		self::assertTrue( $registered_subtotal_filter );
	}
}
