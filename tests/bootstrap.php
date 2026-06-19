<?php
/**
 * PHPUnit bootstrap and WooCommerce test doubles.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

define( 'TEMPERED_VLR_TESTING', true );

if ( ! class_exists( 'WC_Tax' ) ) {
	class WC_Tax {
		/**
		 * Compound tax-rate flags keyed by tax rate ID.
		 *
		 * @var array<int|string,bool>
		 */
		public static array $compound_rates = array();

		/**
		 * Determine whether a tax rate is compound.
		 *
		 * @param mixed $key_or_rate Tax rate ID.
		 * @return bool
		 */
		public static function is_compound( mixed $key_or_rate ): bool {
			return self::$compound_rates[ $key_or_rate ] ?? false;
		}
	}
}

class Tempered_VLR_Test_Cart_Display {
	public bool $display_prices_including_tax = false;
	public float $subtotal_tax                = 0.12;

	public function display_prices_including_tax(): bool {
		return $this->display_prices_including_tax;
	}

	public function get_subtotal_tax(): float {
		return $this->subtotal_tax;
	}
}

class Tempered_VLR_Test_Countries {
	public string $ex_tax_or_vat  = '(ex. VAT)';
	public string $inc_tax_or_vat = '(incl. VAT)';

	public function ex_tax_or_vat(): string {
		return $this->ex_tax_or_vat;
	}

	public function inc_tax_or_vat(): string {
		return $this->inc_tax_or_vat;
	}
}

class Tempered_VLR_Test_WooCommerce {
	public Tempered_VLR_Test_Cart_Display $cart;
	public Tempered_VLR_Test_Countries $countries;

	public function __construct() {
		$this->cart      = new Tempered_VLR_Test_Cart_Display();
		$this->countries = new Tempered_VLR_Test_Countries();
	}
}

class Tempered_VLR_Test_Cart {
	/**
	 * Cart line contents.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public array $cart_contents;

	/**
	 * Cart totals.
	 *
	 * @var array<string,mixed>
	 */
	public array $totals;

	/**
	 * Constructor.
	 *
	 * @param array<string,array<string,mixed>> $cart_contents Cart line contents.
	 * @param array<string,mixed>              $totals        Cart totals.
	 */
	public function __construct( array $cart_contents, array $totals ) {
		$this->cart_contents = $cart_contents;
		$this->totals        = $totals;
	}

	public function get_cart(): array {
		return $this->cart_contents;
	}

	public function set_cart_contents( array $value ): void {
		$this->cart_contents = $value;
	}

	public function get_cart_contents_total(): float {
		return $this->totals['cart_contents_total'];
	}

	public function get_cart_contents_tax(): float {
		return $this->totals['cart_contents_tax'];
	}

	public function get_total( string $context = 'view' ): float {
		unset( $context );

		return $this->totals['total'];
	}

	public function get_total_tax(): float {
		return $this->totals['total_tax'];
	}

	public function get_shipping_tax(): float {
		return $this->totals['shipping_tax'];
	}

	public function get_fee_tax(): float {
		return $this->totals['fee_tax'];
	}

	public function set_cart_contents_total( float $value ): void {
		$this->totals['cart_contents_total'] = $value;
	}

	public function set_cart_contents_tax( float $value ): void {
		$this->totals['cart_contents_tax'] = $value;
	}

	public function set_cart_contents_taxes( array $value ): void {
		$this->totals['cart_contents_taxes'] = $value;
	}

	public function set_subtotal( float $value ): void {
		$this->totals['subtotal'] = $value;
	}

	public function set_subtotal_tax( float $value ): void {
		$this->totals['subtotal_tax'] = $value;
	}

	public function set_total( float $value ): void {
		$this->totals['total'] = $value;
	}

	public function set_total_tax( float $value ): void {
		$this->totals['total_tax'] = $value;
	}
}

class Tempered_VLR_Test_Order_Item {
	private float $subtotal;
	private float $total;
	private array $taxes;
	public int $quantity;

	public function __construct( float $subtotal, float $total, array $taxes, int $quantity = 1 ) {
		$this->subtotal = $subtotal;
		$this->total    = $total;
		$this->taxes    = $taxes;
		$this->quantity = $quantity;
	}

	public function get_subtotal( string $context = 'view' ): float {
		unset( $context );

		return $this->subtotal;
	}

	public function get_total( string $context = 'view' ): float {
		unset( $context );

		return $this->total;
	}

	public function get_taxes( string $context = 'view' ): array {
		unset( $context );

		return $this->taxes;
	}

	public function set_subtotal( float $value ): void {
		$this->subtotal = $value;
	}

	public function set_total( float $value ): void {
		$this->total = $value;
	}

	public function set_taxes( array $value ): void {
		$this->taxes = $value;
	}
}

class Tempered_VLR_Test_Order {
	/**
	 * Order line items.
	 *
	 * @var array<int,Tempered_VLR_Test_Order_Item>
	 */
	private array $items;

	/**
	 * Constructor.
	 *
	 * @param array<int,Tempered_VLR_Test_Order_Item> $items Order items.
	 */
	public function __construct( array $items ) {
		$this->items = $items;
	}

	public function get_items( array $types ): array {
		unset( $types );

		return $this->items;
	}
}

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce global helper stub.
function WC(): Tempered_VLR_Test_WooCommerce {
	return $GLOBALS['tempered_vlr_test_woocommerce'];
}

function wc_price( float|int|string $price ): string {
	return '$' . number_format( (float) $price, 2, '.', '' );
}

function wc_prices_include_tax(): bool {
	return (bool) $GLOBALS['tempered_vlr_test_prices_include_tax'];
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function add_action( string $hook_name, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['tempered_vlr_test_actions'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
}

function add_filter( string $hook_name, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['tempered_vlr_test_filters'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
}

function tempered_vlr_reset_test_environment(): void {
	$GLOBALS['tempered_vlr_test_actions']            = array();
	$GLOBALS['tempered_vlr_test_filters']            = array();
	$GLOBALS['tempered_vlr_test_prices_include_tax'] = true;
	$GLOBALS['tempered_vlr_test_woocommerce']        = new Tempered_VLR_Test_WooCommerce();

	WC_Tax::$compound_rates = array();

	$reflection = new ReflectionClass( Tempered_Vat_Line_Rounding::class );

	$normalizing_cart = $reflection->getProperty( 'normalizing_cart' );
	$normalizing_cart->setValue( null, false );

	$order_line_values = $reflection->getProperty( 'order_line_values' );
	$order_line_values->setValue( null, array() );
}

require_once __DIR__ . '/../includes/class-tempered-vat-line-allocator.php';
require_once __DIR__ . '/../includes/class-tempered-vat-line-rounding.php';

class Tempered_VLR_Test_Case extends PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		parent::setUp();

		tempered_vlr_reset_test_environment();
	}

	/**
	 * Assert that two monetary values match to two decimal places.
	 *
	 * @param float|int|string $expected Expected amount.
	 * @param float|int|string $actual   Actual amount.
	 * @param string           $message  Failure message.
	 * @return void
	 */
	protected static function assertMoneySame( float|int|string $expected, float|int|string $actual, string $message = '' ): void {
		self::assertSame(
			number_format( (float) $expected, 2, '.', '' ),
			number_format( (float) $actual, 2, '.', '' ),
			$message
		);
	}
}
