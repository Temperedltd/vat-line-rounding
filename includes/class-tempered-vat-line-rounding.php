<?php
/**
 * WooCommerce VAT line normalization hooks.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || defined( 'TEMPERED_VLR_TESTING' ) || exit;

/**
 * Coordinates gross-preserving VAT line normalization across WooCommerce.
 */
final class Tempered_Vat_Line_Rounding {
	/**
	 * Whether cart normalization is already in progress.
	 *
	 * @var bool
	 */
	private static bool $normalizing_cart = false;

	/**
	 * Normalized order line values captured before WooCommerce tax recalculation.
	 *
	 * @var array<int|string,array<string,mixed>>
	 */
	private static array $order_line_values = array();

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'normalize_cart_totals' ), 9999 );
		add_action( 'woocommerce_order_before_calculate_taxes', array( __CLASS__, 'capture_order_line_values' ), -1000, 2 );
		add_action( 'woocommerce_order_item_after_calculate_taxes', array( __CLASS__, 'restore_order_line_values' ), 9999, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'format_cart_item_subtotal' ), 9999, 3 );
	}

	/**
	 * Normalize a WooCommerce cart line array.
	 *
	 * @param array<string,mixed> $line Cart line data.
	 * @return array<string,mixed> Normalized line data.
	 */
	public static function normalize_cart_line_array( array $line ): array {
		$total      = self::normalize_amount_pair( $line, 'line_total', 'line_tax_data', 'total' );
		$subtotal   = self::normalize_amount_pair( $line, 'line_subtotal', 'line_tax_data', 'subtotal' );
		$normalized = $line;

		if ( $total ) {
			$normalized['line_total']             = $total['net'];
			$normalized['line_tax']               = $total['tax'];
			$normalized['line_tax_data']['total'] = array( $total['tax_id'] => $total['tax'] );
		}

		if ( $subtotal ) {
			$normalized['line_subtotal']             = $subtotal['net'];
			$normalized['line_subtotal_tax']         = $subtotal['tax'];
			$normalized['line_tax_data']['subtotal'] = array( $subtotal['tax_id'] => $subtotal['tax'] );
		}

		return $normalized;
	}

	/**
	 * Normalize captured order line values.
	 *
	 * @param array<string,mixed> $line Order line data.
	 * @return array<string,mixed> Normalized line data.
	 */
	public static function normalize_order_line_values( array $line ): array {
		$total      = self::normalize_amount_pair( $line, 'total', 'taxes', 'total' );
		$subtotal   = self::normalize_amount_pair( $line, 'subtotal', 'taxes', 'subtotal' );
		$normalized = $line;

		if ( $total ) {
			$normalized['total']          = $total['net'];
			$normalized['total_tax']      = $total['tax'];
			$normalized['taxes']['total'] = array( $total['tax_id'] => $total['tax'] );
			$normalized['total_gross']    = $total['gross'];
		}

		if ( $subtotal ) {
			$normalized['subtotal']          = $subtotal['net'];
			$normalized['subtotal_tax']      = $subtotal['tax'];
			$normalized['taxes']['subtotal'] = array( $subtotal['tax_id'] => $subtotal['tax'] );
			$normalized['subtotal_gross']    = $subtotal['gross'];
		}

		return $normalized;
	}

	/**
	 * Format cart and checkout item-row subtotals from normalized cart line data.
	 *
	 * WooCommerce's default row subtotal recalculates from product price and
	 * quantity. Inclusive 69p lines can therefore display 58p ex VAT even after
	 * the cart line itself has been normalized to 57p net + 12p VAT.
	 *
	 * @param string              $product_subtotal Existing formatted subtotal.
	 * @param array<string,mixed> $cart_item        Cart item data.
	 * @param string              $cart_item_key    Cart item key.
	 * @return string Formatted subtotal.
	 */
	public static function format_cart_item_subtotal( string $product_subtotal, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( ! isset( $cart_item['line_subtotal'] ) || ! is_numeric( $cart_item['line_subtotal'] ) ) {
			return $product_subtotal;
		}

		$subtotal = self::normalize_amount_pair( $cart_item, 'line_subtotal', 'line_tax_data', 'subtotal' );
		if ( null === $subtotal ) {
			return $product_subtotal;
		}

		$amount    = self::cart_displays_prices_including_tax() ? $subtotal['gross'] : $subtotal['net'];
		$formatted = self::format_price( $amount );
		$tax_label = self::cart_item_tax_label( $subtotal['tax'] );

		if ( '' !== $tax_label ) {
			$formatted .= ' <small class="tax_label">' . esc_html( $tax_label ) . '</small>';
		}

		return $formatted;
	}

	/**
	 * Normalize cart contents and aggregate totals after WooCommerce calculation.
	 *
	 * @param object $cart WooCommerce cart object.
	 * @return void
	 */
	public static function normalize_cart_totals( object $cart ): void {
		if ( self::$normalizing_cart || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		self::$normalizing_cart = true;

		$contents       = $cart->get_cart();
		$new_contents   = $contents;
		$contents_total = 0.0;
		$contents_tax   = 0.0;
		$contents_taxes = array();
		$subtotal       = 0.0;
		$subtotal_tax   = 0.0;
		$changed        = false;

		foreach ( $contents as $cart_item_key => $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$normalized = self::normalize_cart_line_array( $cart_item );
			if ( $normalized !== $cart_item ) {
				$changed = true;
			}

			$new_contents[ $cart_item_key ] = $normalized;
			$contents_total                += isset( $normalized['line_total'] ) ? (float) $normalized['line_total'] : 0.0;
			$contents_tax                  += isset( $normalized['line_tax'] ) ? (float) $normalized['line_tax'] : 0.0;
			$subtotal                      += isset( $normalized['line_subtotal'] ) ? (float) $normalized['line_subtotal'] : 0.0;
			$subtotal_tax                  += isset( $normalized['line_subtotal_tax'] ) ? (float) $normalized['line_subtotal_tax'] : 0.0;

			if ( isset( $normalized['line_tax_data']['total'] ) && is_array( $normalized['line_tax_data']['total'] ) ) {
				foreach ( $normalized['line_tax_data']['total'] as $tax_id => $tax_amount ) {
					$contents_taxes[ $tax_id ] = ( $contents_taxes[ $tax_id ] ?? 0.0 ) + (float) $tax_amount;
				}
			}
		}//end foreach

		if ( $changed ) {
			if ( method_exists( $cart, 'set_cart_contents' ) ) {
				$cart->set_cart_contents( $new_contents );
			} elseif ( property_exists( $cart, 'cart_contents' ) ) {
				$cart->cart_contents = $new_contents;
			}

			self::set_cart_totals( $cart, $contents_total, $contents_tax, $contents_taxes, $subtotal, $subtotal_tax );
		}

		self::$normalizing_cart = false;
	}

	/**
	 * Capture normalized order line values before WooCommerce recalculates taxes.
	 *
	 * @param array<string,mixed> $args  WooCommerce tax calculation arguments.
	 * @param object              $order WooCommerce order object.
	 * @return void
	 */
	public static function capture_order_line_values( array $args, object $order ): void {
		self::$order_line_values = array();

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return;
		}

		foreach ( $order->get_items( array( 'line_item' ) ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_taxes' ) || ! method_exists( $item, 'get_total' ) || ! method_exists( $item, 'get_subtotal' ) ) {
				continue;
			}

			$line = array(
				'subtotal' => (float) $item->get_subtotal( 'edit' ),
				'total'    => (float) $item->get_total( 'edit' ),
				'taxes'    => $item->get_taxes( 'edit' ),
			);

			self::$order_line_values[ self::object_key( $item ) ] = self::normalize_order_line_values( $line );
		}
	}

	/**
	 * Restore gross-preserving line values after WooCommerce tax calculation.
	 *
	 * @param object              $item               WooCommerce order item.
	 * @param array<string,mixed> $_calculate_tax_for WooCommerce tax context.
	 * @return void
	 */
	public static function restore_order_line_values( object $item, array $_calculate_tax_for ): void {
		unset( $_calculate_tax_for );

		$key = self::object_key( $item );
		if ( ! isset( self::$order_line_values[ $key ] ) || ! is_object( $item ) ) {
			return;
		}

		$line = self::$order_line_values[ $key ];

		if ( ! isset( $line['taxes']['total'], $line['taxes']['subtotal'] ) || ! method_exists( $item, 'set_taxes' ) ) {
			return;
		}

		if ( method_exists( $item, 'set_subtotal' ) && isset( $line['subtotal'] ) ) {
			$item->set_subtotal( $line['subtotal'] );
		}

		if ( method_exists( $item, 'set_total' ) && isset( $line['total'] ) ) {
			$item->set_total( $line['total'] );
		}

		$item->set_taxes( $line['taxes'] );
	}

	/**
	 * Render an admin warning when the required WooCommerce tax settings are absent.
	 *
	 * @return void
	 */
	public static function render_admin_notice(): void {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! self::config_requires_attention() ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'VAT Line Rounding expects WooCommerce subtotal tax rounding and WC_TAX_ROUNDING_MODE set to PHP_ROUND_HALF_UP.', 'vat-line-rounding' ) . '</p></div>';
	}

	/**
	 * Determine whether the active WooCommerce tax configuration needs attention.
	 *
	 * @param string|null $round_at_subtotal WooCommerce subtotal rounding option.
	 * @param int|null    $rounding_mode     PHP rounding mode constant.
	 * @return bool True when the configuration should warn administrators.
	 */
	public static function config_requires_attention( ?string $round_at_subtotal = null, ?int $rounding_mode = null ): bool {
		if ( null === $round_at_subtotal && function_exists( 'get_option' ) ) {
			$round_at_subtotal = get_option( 'woocommerce_tax_round_at_subtotal' );
		}

		if ( null === $rounding_mode ) {
			$rounding_mode = defined( 'WC_TAX_ROUNDING_MODE' ) ? WC_TAX_ROUNDING_MODE : null;
		}

		return 'yes' !== $round_at_subtotal || PHP_ROUND_HALF_UP !== $rounding_mode;
	}

	/**
	 * Normalize a net/tax pair from a WooCommerce cart or order line.
	 *
	 * @param array<string,mixed> $line              Line data.
	 * @param string              $net_key           Net amount key.
	 * @param string              $tax_container_key Tax container key.
	 * @param string              $tax_data_key      Tax amount group key.
	 * @return array<string,float|int|string>|null Normalized values, or null when unsupported.
	 */
	private static function normalize_amount_pair( array $line, string $net_key, string $tax_container_key, string $tax_data_key ): ?array {
		if ( ! array_key_exists( $net_key, $line ) ) {
			return null;
		}

		$tax_info = self::single_tax_amount( $line, $tax_container_key, $tax_data_key );
		if ( ! $tax_info || $tax_info['amount'] <= 0 ) {
			return null;
		}

		if ( self::is_compound_tax_rate( $tax_info['tax_id'] ) ) {
			return null;
		}

		$net = (float) $line[ $net_key ];
		if ( $net <= 0 ) {
			return null;
		}

		$rate = self::infer_rate( $line, $net, $tax_info['amount'] );
		if ( $rate <= 0 ) {
			return null;
		}

		$gross     = $net + $tax_info['amount'];
		$allocated = Tempered_Vat_Line_Allocator::allocate_inclusive_line( $gross, $rate );
		if ( null === $allocated ) {
			return null;
		}

		return array(
			'gross'  => $allocated['gross'],
			'net'    => $allocated['net'],
			'tax'    => $allocated['tax'],
			'tax_id' => $tax_info['tax_id'],
		);
	}

	/**
	 * Extract a single tax amount and tax rate ID from line tax data.
	 *
	 * @param array<string,mixed> $line              Line data.
	 * @param string              $tax_container_key Tax container key.
	 * @param string              $tax_data_key      Tax amount group key.
	 * @return array{tax_id:int|string,amount:float}|null Tax data, or null when unsupported.
	 */
	private static function single_tax_amount( array $line, string $tax_container_key, string $tax_data_key ): ?array {
		if (
			empty( $line[ $tax_container_key ][ $tax_data_key ] ) ||
			! is_array( $line[ $tax_container_key ][ $tax_data_key ] ) ||
			1 !== count( $line[ $tax_container_key ][ $tax_data_key ] )
		) {
			return null;
		}

		$tax_id = array_key_first( $line[ $tax_container_key ][ $tax_data_key ] );

		return array(
			'tax_id' => $tax_id,
			'amount' => (float) $line[ $tax_container_key ][ $tax_data_key ][ $tax_id ],
		);
	}

	/**
	 * Determine whether a WooCommerce tax rate is compound.
	 *
	 * @param int|string $tax_id WooCommerce tax rate ID.
	 * @return bool True when WooCommerce identifies the rate as compound.
	 */
	private static function is_compound_tax_rate( int|string $tax_id ): bool {
		if ( ! class_exists( 'WC_Tax' ) || ! is_callable( array( 'WC_Tax', 'is_compound' ) ) ) {
			return false;
		}

		return (bool) WC_Tax::is_compound( $tax_id );
	}

	/**
	 * Infer the tax rate percentage from net and raw tax amounts.
	 *
	 * @param array<string,mixed> $line    Line data.
	 * @param float               $net     Net amount.
	 * @param float               $raw_tax Raw tax amount.
	 * @return float Tax rate percentage.
	 */
	private static function infer_rate( array $line, float $net, float $raw_tax ): float {
		if ( isset( $line['vat_line_rounding_rate'] ) ) {
			return (float) $line['vat_line_rounding_rate'];
		}

		if ( $net <= 0 || $raw_tax <= 0 ) {
			return 0.0;
		}

		return ( $raw_tax / $net ) * 100;
	}

	/**
	 * Write normalized cart totals back to a WooCommerce cart object.
	 *
	 * @param object           $cart            WooCommerce cart object.
	 * @param float            $contents_total  Cart contents total.
	 * @param float            $contents_tax    Cart contents tax total.
	 * @param array<int,float> $contents_taxes  Cart contents taxes by tax ID.
	 * @param float            $subtotal        Cart subtotal.
	 * @param float            $subtotal_tax    Cart subtotal tax.
	 * @return void
	 */
	private static function set_cart_totals( object $cart, float $contents_total, float $contents_tax, array $contents_taxes, float $subtotal, float $subtotal_tax ): void {
		$old_contents_total = method_exists( $cart, 'get_cart_contents_total' ) ? (float) $cart->get_cart_contents_total() : $contents_total;
		$old_contents_tax   = method_exists( $cart, 'get_cart_contents_tax' ) ? (float) $cart->get_cart_contents_tax() : $contents_tax;
		$old_total          = method_exists( $cart, 'get_total' ) ? (float) $cart->get_total( 'edit' ) : $contents_total + $contents_tax;
		$shipping_tax       = method_exists( $cart, 'get_shipping_tax' ) ? (float) $cart->get_shipping_tax() : 0.0;
		$fee_tax            = method_exists( $cart, 'get_fee_tax' ) ? (float) $cart->get_fee_tax() : 0.0;

		$new_total     = $old_total - $old_contents_total - $old_contents_tax + $contents_total + $contents_tax;
		$new_total_tax = $contents_tax + $shipping_tax + $fee_tax;

		self::call_if_exists( $cart, 'set_cart_contents_total', $contents_total );
		self::call_if_exists( $cart, 'set_cart_contents_tax', $contents_tax );
		self::call_if_exists( $cart, 'set_cart_contents_taxes', $contents_taxes );
		self::call_if_exists( $cart, 'set_subtotal', $subtotal );
		self::call_if_exists( $cart, 'set_subtotal_tax', $subtotal_tax );
		self::call_if_exists( $cart, 'set_total', $new_total );
		self::call_if_exists( $cart, 'set_total_tax', $new_total_tax );
	}

	/**
	 * Format a price with WooCommerce when available.
	 *
	 * @param float $amount Price amount.
	 * @return string Formatted price.
	 */
	private static function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}

		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Determine whether the active cart displays prices including tax.
	 *
	 * @return bool True when cart item subtotals should include tax.
	 */
	private static function cart_displays_prices_including_tax(): bool {
		$cart = self::get_wc_cart();

		return is_object( $cart ) && method_exists( $cart, 'display_prices_including_tax' ) && (bool) $cart->display_prices_including_tax();
	}

	/**
	 * Build the WooCommerce tax label for an item-row subtotal.
	 *
	 * @param float $tax_amount Line subtotal tax amount.
	 * @return string Tax label HTML/text, or an empty string when no label applies.
	 */
	private static function cart_item_tax_label( float $tax_amount ): string {
		if ( $tax_amount <= 0.0 || ! self::cart_has_subtotal_tax() || ! function_exists( 'wc_prices_include_tax' ) ) {
			return '';
		}

		if ( self::cart_displays_prices_including_tax() ) {
			return wc_prices_include_tax() ? '' : self::country_tax_label( 'inc_tax_or_vat' );
		}

		return wc_prices_include_tax() ? self::country_tax_label( 'ex_tax_or_vat' ) : '';
	}

	/**
	 * Determine whether the cart has taxable subtotal amounts.
	 *
	 * @return bool True when the cart has subtotal tax, or when the cart cannot answer.
	 */
	private static function cart_has_subtotal_tax(): bool {
		$cart = self::get_wc_cart();

		if ( is_object( $cart ) && method_exists( $cart, 'get_subtotal_tax' ) ) {
			return (float) $cart->get_subtotal_tax() > 0.0;
		}

		return true;
	}

	/**
	 * Get a WooCommerce country tax label.
	 *
	 * @param string $method Countries label method.
	 * @return string Tax label.
	 */
	private static function country_tax_label( string $method ): string {
		$countries = self::get_wc_countries();

		if ( is_object( $countries ) && method_exists( $countries, $method ) ) {
			return (string) $countries->$method();
		}

		return '';
	}

	/**
	 * Return the active WooCommerce cart object when available.
	 *
	 * @return object|null WooCommerce cart object, or null.
	 */
	private static function get_wc_cart(): ?object {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		return is_object( $woocommerce ) && isset( $woocommerce->cart ) && is_object( $woocommerce->cart ) ? $woocommerce->cart : null;
	}

	/**
	 * Return the active WooCommerce countries object when available.
	 *
	 * @return object|null WooCommerce countries object, or null.
	 */
	private static function get_wc_countries(): ?object {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		return is_object( $woocommerce ) && isset( $woocommerce->countries ) && is_object( $woocommerce->countries ) ? $woocommerce->countries : null;
	}

	/**
	 * Call a setter when it exists on the target object.
	 *
	 * @param object $target Target object.
	 * @param string $method Method name.
	 * @param mixed  $value  Value to pass to the method.
	 * @return void
	 */
	private static function call_if_exists( object $target, string $method, mixed $value ): void {
		if ( method_exists( $target, $method ) ) {
			$target->$method( $value );
		}
	}

	/**
	 * Return a stable key for an object during the current request.
	 *
	 * @param object $target Object to identify.
	 * @return int|string
	 */
	private static function object_key( object $target ): int|string {
		return function_exists( 'spl_object_id' ) ? spl_object_id( $target ) : spl_object_hash( $target );
	}
}
