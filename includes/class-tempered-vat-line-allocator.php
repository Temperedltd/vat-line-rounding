<?php
/**
 * VAT allocation helper.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || defined( 'TEMPERED_VLR_TESTING' ) || exit;

/**
 * Allocates inclusive gross line values into gross, tax, and net amounts.
 */
final class Tempered_Vat_Line_Allocator {
	/**
	 * Allocate a tax-inclusive line while preserving the rounded gross value.
	 *
	 * Returns null when the supplied values cannot be represented safely with
	 * integer minor-unit arithmetic.
	 *
	 * @param float|int|string $gross        Tax-inclusive gross amount.
	 * @param float|int|string $rate_percent Tax rate percentage, for example 20.
	 * @param int              $decimals     Currency decimal places.
	 * @return array<string,float>|null Allocation values, or null when unsafe.
	 */
	public static function allocate_inclusive_line( float|int|string $gross, float|int|string $rate_percent, int $decimals = 2 ): ?array {
		$decimals          = (int) $decimals;
		$scale             = 10 ** $decimals;
		$gross_minor       = self::safe_round_half_up_int( (float) $gross * $scale );
		$rate_basis_points = self::safe_round_half_up_int( (float) $rate_percent * 100 );
		$tax_minor         = 0;

		if ( null === $gross_minor || null === $rate_basis_points ) {
			return null;
		}

		if ( $gross_minor > 0 && $rate_basis_points > 0 ) {
			if ( ! self::can_round_half_up_divide( $gross_minor, $rate_basis_points ) ) {
				return null;
			}

			$tax_minor = self::round_half_up_divide(
				$gross_minor * $rate_basis_points,
				10000 + $rate_basis_points
			);
		}

		$net_minor = $gross_minor - $tax_minor;

		return array(
			'gross' => self::minor_to_decimal( $gross_minor, $scale, $decimals ),
			'tax'   => self::minor_to_decimal( $tax_minor, $scale, $decimals ),
			'net'   => self::minor_to_decimal( $net_minor, $scale, $decimals ),
		);
	}

	/**
	 * Allocate a tax-inclusive line by rounded unit value and quantity.
	 *
	 * @param float|int|string $gross        Tax-inclusive gross line amount.
	 * @param float|int|string $rate_percent Tax rate percentage, for example 20.
	 * @param int              $quantity     Line item quantity.
	 * @param int              $decimals     Currency decimal places.
	 * @return array<string,float>|null Allocation values, or null when unsupported.
	 */
	public static function allocate_inclusive_quantity_line( float|int|string $gross, float|int|string $rate_percent, int $quantity, int $decimals = 2 ): ?array {
		if ( $quantity <= 1 ) {
			return null;
		}

		$decimals    = (int) $decimals;
		$scale       = 10 ** $decimals;
		$gross_minor = self::safe_round_half_up_int( (float) $gross * $scale );

		if ( null === $gross_minor || 0 !== $gross_minor % $quantity ) {
			return null;
		}

		$unit_gross = self::minor_to_decimal( intdiv( $gross_minor, $quantity ), $scale, $decimals );
		$allocated  = self::allocate_inclusive_line( $unit_gross, $rate_percent, $decimals );

		if ( null === $allocated ) {
			return null;
		}

		return array(
			'gross' => round( $allocated['gross'] * $quantity, $decimals ),
			'tax'   => round( $allocated['tax'] * $quantity, $decimals ),
			'net'   => round( $allocated['net'] * $quantity, $decimals ),
		);
	}

	/**
	 * Allocate a tax-exclusive line while preserving the rounded net value.
	 *
	 * @param float|int|string $net          Tax-exclusive net amount.
	 * @param float|int|string $rate_percent Tax rate percentage, for example 20.
	 * @param int              $decimals     Currency decimal places.
	 * @return array<string,float>|null Allocation values, or null when unsafe.
	 */
	public static function allocate_exclusive_line( float|int|string $net, float|int|string $rate_percent, int $decimals = 2 ): ?array {
		$decimals          = (int) $decimals;
		$scale             = 10 ** $decimals;
		$net_minor         = self::safe_round_half_up_int( (float) $net * $scale );
		$rate_basis_points = self::safe_round_half_up_int( (float) $rate_percent * 100 );
		$tax_minor         = 0;

		if ( null === $net_minor || null === $rate_basis_points ) {
			return null;
		}

		if ( $net_minor > 0 && $rate_basis_points > 0 ) {
			if ( ! self::can_round_half_up_divide_values( $net_minor, $rate_basis_points, 10000 ) ) {
				return null;
			}

			$tax_minor = self::round_half_up_divide( $net_minor * $rate_basis_points, 10000 );
		}

		$gross_minor = $net_minor + $tax_minor;

		return array(
			'gross' => self::minor_to_decimal( $gross_minor, $scale, $decimals ),
			'tax'   => self::minor_to_decimal( $tax_minor, $scale, $decimals ),
			'net'   => self::minor_to_decimal( $net_minor, $scale, $decimals ),
		);
	}

	/**
	 * Round a numeric value to the nearest integer using half-up semantics.
	 *
	 * @param float|int $value Numeric value to round.
	 * @return int
	 */
	private static function round_half_up_int( float|int $value ): int {
		return (int) floor( $value + 0.5 );
	}

	/**
	 * Round a value only when it is safe to cast to an integer.
	 *
	 * @param float|int $value Numeric value to round.
	 * @return int|null Rounded value, or null when unsafe.
	 */
	private static function safe_round_half_up_int( float|int $value ): ?int {
		if ( ! is_finite( $value ) || $value > PHP_INT_MAX - 1 || $value < -PHP_INT_MAX ) {
			return null;
		}

		return self::round_half_up_int( $value );
	}

	/**
	 * Determine whether half-up division can run without integer overflow.
	 *
	 * @param int $gross_minor       Gross amount in minor units.
	 * @param int $rate_basis_points Tax rate in basis points.
	 * @return bool
	 */
	private static function can_round_half_up_divide( int $gross_minor, int $rate_basis_points ): bool {
		if ( $rate_basis_points > PHP_INT_MAX - 10000 ) {
			return false;
		}

		return self::can_round_half_up_divide_values( $gross_minor, $rate_basis_points, 10000 + $rate_basis_points );
	}

	/**
	 * Determine whether half-up division can run without integer overflow.
	 *
	 * @param int $amount      Amount in minor units.
	 * @param int $multiplier  Multiplication factor.
	 * @param int $denominator Division denominator.
	 * @return bool
	 */
	private static function can_round_half_up_divide_values( int $amount, int $multiplier, int $denominator ): bool {
		$safe_numerator_limit = intdiv( PHP_INT_MAX - $denominator, 2 );

		return $amount <= intdiv( $safe_numerator_limit, $multiplier );
	}

	/**
	 * Divide integers using half-up rounding.
	 *
	 * @param int $numerator   Division numerator.
	 * @param int $denominator Division denominator.
	 * @return int
	 */
	private static function round_half_up_divide( int $numerator, int $denominator ): int {
		return intdiv( ( 2 * $numerator ) + $denominator, 2 * $denominator );
	}

	/**
	 * Convert a minor-unit integer amount back to a decimal amount.
	 *
	 * @param int $minor    Minor-unit amount.
	 * @param int $scale    Minor-unit scale.
	 * @param int $decimals Currency decimal places.
	 * @return float
	 */
	private static function minor_to_decimal( int $minor, int $scale, int $decimals ): float {
		return round( $minor / $scale, $decimals );
	}
}
