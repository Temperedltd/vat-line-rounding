<?php
/**
 * Tests for VAT line allocation.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

final class AllocatorTest extends Tempered_VLR_Test_Case {
	public function test_half_penny_vat_rounds_up_while_preserving_gross(): void {
		self::assertAllocationSame(
			array(
				'gross' => 0.69,
				'tax'   => 0.12,
				'net'   => 0.57,
			),
			Tempered_Vat_Line_Allocator::allocate_inclusive_line( 0.69, 20.0 )
		);
	}

	public function test_non_midpoint_vat_rounds_to_nearest_penny(): void {
		self::assertAllocationSame(
			array(
				'gross' => 1.00,
				'tax'   => 0.17,
				'net'   => 0.83,
			),
			Tempered_Vat_Line_Allocator::allocate_inclusive_line( 1.00, 20.0 )
		);
	}

	public function test_zero_rated_lines_keep_gross_as_net(): void {
		self::assertAllocationSame(
			array(
				'gross' => 0.69,
				'tax'   => 0.00,
				'net'   => 0.69,
			),
			Tempered_Vat_Line_Allocator::allocate_inclusive_line( 0.69, 0.0 )
		);
	}

	public function test_tiny_lines_do_not_use_ceil_style_rounding(): void {
		self::assertAllocationSame(
			array(
				'gross' => 0.01,
				'tax'   => 0.00,
				'net'   => 0.01,
			),
			Tempered_Vat_Line_Allocator::allocate_inclusive_line( 0.01, 20.0 )
		);
	}

	public function test_unsafe_gross_values_are_declined(): void {
		self::assertNull( Tempered_Vat_Line_Allocator::allocate_inclusive_line( 50000000000000.0, 20.0 ) );
	}

	/**
	 * Assert allocation values.
	 *
	 * @param array<string,float>      $expected Expected allocation.
	 * @param array<string,float>|null $actual   Actual allocation.
	 * @return void
	 */
	private static function assertAllocationSame( array $expected, ?array $actual ): void {
		self::assertNotNull( $actual );

		foreach ( array( 'gross', 'tax', 'net' ) as $key ) {
			self::assertMoneySame( $expected[ $key ], $actual[ $key ], 'Unexpected allocation value for ' . $key . '.' );
		}
	}
}
