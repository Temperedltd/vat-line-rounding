<?php
/**
 * Tests for order line recalculation normalization.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

final class OrderRecalculationTest extends Tempered_VLR_Test_Case {
	public function test_order_line_normalization_preserves_gross_and_tax_rate_id(): void {
		$normalized = Tempered_Vat_Line_Rounding::normalize_order_line_values(
			array(
				'subtotal' => 0.575,
				'total'    => 0.575,
				'taxes'    => array(
					'subtotal' => array( 99 => 0.115 ),
					'total'    => array( 99 => 0.115 ),
				),
			)
		);

		self::assertMoneySame( 0.57, $normalized['total'], 'Order line total should be gross minus rounded VAT.' );
		self::assertMoneySame( 0.57, $normalized['subtotal'], 'Order line subtotal should be gross minus rounded VAT.' );
		self::assertSame( array( 99 => 0.12 ), $normalized['taxes']['total'], 'Order line tax data should preserve the actual tax rate ID.' );
		self::assertSame( array( 99 => 0.12 ), $normalized['taxes']['subtotal'], 'Order line subtotal tax data should preserve the actual tax rate ID.' );
		self::assertMoneySame( 0.69, $normalized['total'] + $normalized['taxes']['total'][99], 'Order line gross should remain 69p.' );
	}

	public function test_multiple_rate_and_compound_order_lines_are_unchanged(): void {
		$multi_rate_line = array(
			'subtotal' => 0.575,
			'total'    => 0.575,
			'taxes'    => array(
				'subtotal' => array(
					99  => 0.06,
					100 => 0.055,
				),
				'total'    => array(
					99  => 0.06,
					100 => 0.055,
				),
			),
		);

		self::assertSame( $multi_rate_line, Tempered_Vat_Line_Rounding::normalize_order_line_values( $multi_rate_line ), 'Multiple-rate order lines should be left unchanged.' );

		$compound_line = array(
			'subtotal' => 0.575,
			'total'    => 0.575,
			'taxes'    => array(
				'subtotal' => array( 55 => 0.115 ),
				'total'    => array( 55 => 0.115 ),
			),
		);

		WC_Tax::$compound_rates[55] = true;

		self::assertSame( $compound_line, Tempered_Vat_Line_Rounding::normalize_order_line_values( $compound_line ), 'Compound-rate order lines should be left unchanged.' );
	}

	public function test_captured_order_line_values_are_restored_after_tax_recalculation(): void {
		$order_items = array(
			new Tempered_VLR_Test_Order_Item(
				0.575,
				0.575,
				array(
					'subtotal' => array( 99 => 0.115 ),
					'total'    => array( 99 => 0.115 ),
				)
			),
			new Tempered_VLR_Test_Order_Item(
				1.666666666667,
				1.666666666667,
				array(
					'subtotal' => array( 100 => 0.333333333333 ),
					'total'    => array( 100 => 0.333333333333 ),
				),
				2
			),
		);

		Tempered_Vat_Line_Rounding::capture_order_line_values( array(), new Tempered_VLR_Test_Order( $order_items ) );

		foreach ( $order_items as $order_item ) {
			$order_item->set_subtotal( 999.99 );
			$order_item->set_total( 999.99 );
			$order_item->set_taxes(
				array(
					'subtotal' => array( 999 => 999.99 ),
					'total'    => array( 999 => 999.99 ),
				)
			);

			Tempered_Vat_Line_Rounding::restore_order_line_values( $order_item, array() );
		}

		self::assertMoneySame( 0.57, $order_items[0]->get_total(), 'Captured order item 1 total should be restored as normalized net.' );
		self::assertSame( array( 99 => 0.12 ), $order_items[0]->get_taxes()['total'], 'Captured order item 1 tax bucket should be restored.' );
		self::assertMoneySame( 1.67, $order_items[1]->get_total(), 'Captured aggregated quantity order item total should be restored as normalized net.' );
		self::assertSame( array( 100 => 0.33 ), $order_items[1]->get_taxes()['total'], 'Captured aggregated quantity order item tax bucket should be restored.' );
	}
}
