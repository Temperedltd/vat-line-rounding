<?php
/**
 * Tests for cart line and cart total normalization.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

final class CartNormalizationTest extends Tempered_VLR_Test_Case {
	public function test_cart_line_normalization_preserves_gross_and_tax_rate_id(): void {
		$normalized = Tempered_Vat_Line_Rounding::normalize_cart_line_array( self::badLine() );

		self::assertMoneySame( 0.57, $normalized['line_total'], 'Cart line net should be gross minus rounded VAT.' );
		self::assertMoneySame( 0.12, $normalized['line_tax'], 'Cart line VAT should be half-up rounded.' );
		self::assertMoneySame( 0.69, $normalized['line_total'] + $normalized['line_tax'], 'Cart line gross should recover the original inclusive price.' );
		self::assertMoneySame( 0.57, $normalized['line_subtotal'], 'Cart subtotal net should be normalized.' );
		self::assertMoneySame( 0.12, $normalized['line_subtotal_tax'], 'Cart subtotal VAT should be normalized.' );
		self::assertSame( array( 42 => 0.12 ), $normalized['line_tax_data']['total'], 'Cart tax data should preserve the real tax rate ID.' );
		self::assertSame( array( 42 => 0.12 ), $normalized['line_tax_data']['subtotal'], 'Cart subtotal tax data should preserve the real tax rate ID.' );
	}

	public function test_cart_line_normalization_is_idempotent(): void {
		$normalized   = Tempered_Vat_Line_Rounding::normalize_cart_line_array( self::badLine() );
		$renormalized = Tempered_Vat_Line_Rounding::normalize_cart_line_array( $normalized );

		self::assertMoneySame( 0.57, $renormalized['line_total'], 'Cart normalization should be idempotent for net.' );
		self::assertMoneySame( 0.12, $renormalized['line_tax'], 'Cart normalization should be idempotent for VAT.' );
		self::assertMoneySame( 0.69, $renormalized['line_total'] + $renormalized['line_tax'], 'Cart normalization should be idempotent for gross.' );
	}

	public function test_cart_item_subtotal_display_uses_normalized_values(): void {
		$normalized = Tempered_Vat_Line_Rounding::normalize_cart_line_array( self::badLine() );

		self::assertSame(
			'$0.57 <small class="tax_label">(ex. VAT)</small>',
			Tempered_Vat_Line_Rounding::format_cart_item_subtotal(
				'$0.58 <small class="tax_label">(ex. VAT)</small>',
				$normalized,
				'cart-line'
			),
			'Cart item subtotal display should use the normalized ex-VAT line subtotal.'
		);

		WC()->cart->display_prices_including_tax = true;

		self::assertSame(
			'$0.69',
			Tempered_Vat_Line_Rounding::format_cart_item_subtotal( '$0.70', $normalized, 'cart-line' ),
			'Cart item subtotal display should preserve normalized gross when displaying prices including VAT.'
		);
	}

	public function test_cart_item_subtotal_display_escapes_tax_label(): void {
		$normalized                              = Tempered_Vat_Line_Rounding::normalize_cart_line_array( self::badLine() );
		WC()->countries->ex_tax_or_vat           = '<span>ex. VAT</span>';
		WC()->cart->display_prices_including_tax = false;

		self::assertSame(
			'$0.57 <small class="tax_label">&lt;span&gt;ex. VAT&lt;/span&gt;</small>',
			Tempered_Vat_Line_Rounding::format_cart_item_subtotal(
				'$0.58 <small class="tax_label"><span>ex. VAT</span></small>',
				$normalized,
				'cart-line'
			),
			'Cart item subtotal display should escape the tax label.'
		);
	}

	public function test_cart_item_subtotal_display_leaves_unsupported_data_unchanged(): void {
		self::assertSame(
			'unchanged',
			Tempered_Vat_Line_Rounding::format_cart_item_subtotal( 'unchanged', array(), 'cart-line' )
		);
	}

	public function test_zero_rated_multiple_rate_extreme_and_compound_lines_are_unchanged(): void {
		$zero_line = array(
			'line_subtotal'     => 0.69,
			'line_subtotal_tax' => 0.0,
			'line_total'        => 0.69,
			'line_tax'          => 0.0,
			'line_tax_data'     => array(
				'subtotal' => array(),
				'total'    => array(),
			),
		);

		self::assertSame( $zero_line, Tempered_Vat_Line_Rounding::normalize_cart_line_array( $zero_line ), 'Zero-rated cart lines should be left unchanged.' );

		$multi_rate_line = array(
			'line_subtotal'     => 0.575,
			'line_subtotal_tax' => 0.115,
			'line_total'        => 0.575,
			'line_tax'          => 0.115,
			'line_tax_data'     => array(
				'subtotal' => array(
					42 => 0.06,
					43 => 0.055,
				),
				'total'    => array(
					42 => 0.06,
					43 => 0.055,
				),
			),
		);

		self::assertSame( $multi_rate_line, Tempered_Vat_Line_Rounding::normalize_cart_line_array( $multi_rate_line ), 'Multiple-rate cart lines should be left unchanged.' );

		$extreme_line = array(
			'line_subtotal'     => 41666666666666.664,
			'line_subtotal_tax' => 8333333333333.336,
			'line_total'        => 41666666666666.664,
			'line_tax'          => 8333333333333.336,
			'line_tax_data'     => array(
				'subtotal' => array( 42 => 8333333333333.336 ),
				'total'    => array( 42 => 8333333333333.336 ),
			),
		);

		self::assertSame( $extreme_line, Tempered_Vat_Line_Rounding::normalize_cart_line_array( $extreme_line ), 'Extreme cart lines should be left unchanged instead of overflowing allocator arithmetic.' );

		$compound_line = array(
			'line_subtotal'     => 0.575,
			'line_subtotal_tax' => 0.115,
			'line_total'        => 0.575,
			'line_tax'          => 0.115,
			'line_tax_data'     => array(
				'subtotal' => array( 55 => 0.115 ),
				'total'    => array( 55 => 0.115 ),
			),
		);

		WC_Tax::$compound_rates[55] = true;

		self::assertSame( $compound_line, Tempered_Vat_Line_Rounding::normalize_cart_line_array( $compound_line ), 'Compound-rate cart lines should be left unchanged.' );
	}

	public function test_cart_totals_are_rebuilt_from_normalized_lines(): void {
		$cart = new Tempered_VLR_Test_Cart(
			array( 'line' => self::badLine() ),
			array(
				'cart_contents_total' => 0.58,
				'cart_contents_tax'   => 0.12,
				'cart_contents_taxes' => array( 42 => 0.12 ),
				'subtotal'            => 0.58,
				'subtotal_tax'        => 0.12,
				'total'               => 0.70,
				'total_tax'           => 0.13,
				'shipping_tax'        => 0.0,
				'fee_tax'             => 0.0,
			)
		);

		Tempered_Vat_Line_Rounding::normalize_cart_totals( $cart );

		self::assertMoneySame( 0.57, $cart->totals['cart_contents_total'], 'Cart contents net total should be normalized.' );
		self::assertMoneySame( 0.12, $cart->totals['cart_contents_tax'], 'Cart contents tax should be normalized.' );
		self::assertMoneySame( 0.69, $cart->totals['total'], 'Cart total should recover 69p when there is no shipping or fee.' );
		self::assertMoneySame( 0.12, $cart->totals['total_tax'], 'Cart total tax should not preserve a stale rounded aggregate.' );
	}

	public function test_cart_totals_preserve_shipping_and_fee_tax(): void {
		$second_bad_line = array(
			'line_subtotal'     => 0.833333333333,
			'line_subtotal_tax' => 0.17,
			'line_total'        => 0.833333333333,
			'line_tax'          => 0.17,
			'line_tax_data'     => array(
				'subtotal' => array( 42 => 0.166666666667 ),
				'total'    => array( 42 => 0.166666666667 ),
			),
		);

		$cart = new Tempered_VLR_Test_Cart(
			array(
				'line-a' => self::badLine(),
				'line-b' => $second_bad_line,
			),
			array(
				'cart_contents_total' => 1.41,
				'cart_contents_tax'   => 0.29,
				'cart_contents_taxes' => array( 42 => 0.29 ),
				'subtotal'            => 1.41,
				'subtotal_tax'        => 0.29,
				'total'               => 6.94,
				'total_tax'           => 1.16,
				'shipping_tax'        => 0.83,
				'fee_tax'             => 0.04,
			)
		);

		Tempered_Vat_Line_Rounding::normalize_cart_totals( $cart );

		self::assertMoneySame( 1.40, $cart->totals['cart_contents_total'], 'Multi-item cart contents net should be normalized from each WooCommerce line.' );
		self::assertMoneySame( 0.29, $cart->totals['cart_contents_tax'], 'Multi-item cart contents tax should sum normalized line tax.' );
		self::assertMoneySame( 6.93, $cart->totals['total'], 'Multi-item cart total should preserve non-product totals while replacing product totals.' );
		self::assertMoneySame( 1.16, $cart->totals['total_tax'], 'Multi-item cart total tax should preserve shipping tax plus fee tax plus normalized product tax.' );
		self::assertMoneySame( 0.29, $cart->totals['cart_contents_taxes'][42], 'Multi-item tax buckets should be rebuilt from normalized product lines.' );
	}

	public function test_large_cart_totals_sum_normalized_line_values(): void {
		$cart_contents           = array();
		$old_contents_total      = 0.0;
		$old_contents_tax        = 0.0;
		$expected_contents_total = 0.0;
		$expected_contents_tax   = 0.0;
		$expected_tax_buckets    = array();

		for ( $index = 1; $index <= 45; $index++ ) {
			$quantity = 1 + ( $index % 3 );
			$tax_id   = 0 === $index % 2 ? 42 : 43;
			$rate     = 42 === $tax_id ? 20.0 : 5.0;
			$gross    = round( ( 0.29 + ( $index % 7 ) * 0.11 ) * $quantity, 2 );
			$net      = $gross / ( 1 + ( $rate / 100 ) );
			$raw_tax  = $gross - $net;

			$cart_contents[ 'large-' . $index ] = array(
				'quantity'          => $quantity,
				'line_subtotal'     => $net,
				'line_subtotal_tax' => $raw_tax,
				'line_total'        => $net,
				'line_tax'          => $raw_tax,
				'line_tax_data'     => array(
					'subtotal' => array( $tax_id => $raw_tax ),
					'total'    => array( $tax_id => $raw_tax ),
				),
			);

			$allocated = Tempered_Vat_Line_Allocator::allocate_inclusive_line( $gross, $rate );
			self::assertNotNull( $allocated, 'Large cart fixture unexpectedly failed to allocate.' );

			$old_contents_total             += $net;
			$old_contents_tax               += $raw_tax;
			$expected_contents_total        += $allocated['net'];
			$expected_contents_tax          += $allocated['tax'];
			$expected_tax_buckets[ $tax_id ] = ( $expected_tax_buckets[ $tax_id ] ?? 0.0 ) + $allocated['tax'];
		}

		$non_product_gross = 12.39;
		$shipping_tax      = 1.75;
		$fee_tax           = 0.14;
		$cart              = new Tempered_VLR_Test_Cart(
			$cart_contents,
			array(
				'cart_contents_total' => $old_contents_total,
				'cart_contents_tax'   => $old_contents_tax,
				'cart_contents_taxes' => array(),
				'subtotal'            => $old_contents_total,
				'subtotal_tax'        => $old_contents_tax,
				'total'               => $old_contents_total + $old_contents_tax + $non_product_gross,
				'total_tax'           => $old_contents_tax + $shipping_tax + $fee_tax,
				'shipping_tax'        => $shipping_tax,
				'fee_tax'             => $fee_tax,
			)
		);

		Tempered_Vat_Line_Rounding::normalize_cart_totals( $cart );

		self::assertMoneySame( $expected_contents_total, $cart->totals['cart_contents_total'], 'Large cart contents net should sum normalized line nets.' );
		self::assertMoneySame( $expected_contents_tax, $cart->totals['cart_contents_tax'], 'Large cart contents tax should sum normalized line VAT.' );
		self::assertMoneySame( $expected_contents_total + $expected_contents_tax + $non_product_gross, $cart->totals['total'], 'Large cart total should preserve non-product gross totals.' );
		self::assertMoneySame( $expected_contents_tax + $shipping_tax + $fee_tax, $cart->totals['total_tax'], 'Large cart total tax should preserve shipping and fee tax.' );
		self::assertMoneySame( $expected_tax_buckets[42], $cart->totals['cart_contents_taxes'][42], 'Large cart should preserve rate 42 tax bucket.' );
		self::assertMoneySame( $expected_tax_buckets[43], $cart->totals['cart_contents_taxes'][43], 'Large cart should preserve rate 43 tax bucket.' );
	}

	/**
	 * Return a cart line with raw WooCommerce inclusive tax values.
	 *
	 * @return array<string,mixed>
	 */
	private static function badLine(): array {
		return array(
			'line_subtotal'     => 0.575,
			'line_subtotal_tax' => 0.12,
			'line_total'        => 0.575,
			'line_tax'          => 0.12,
			'line_tax_data'     => array(
				'subtotal' => array( 42 => 0.115 ),
				'total'    => array( 42 => 0.115 ),
			),
		);
	}
}
