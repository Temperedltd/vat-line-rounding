<?php
/**
 * Plugin Name: VAT Line Rounding
 * Description: Gross-preserving VAT line rounding for WooCommerce inclusive prices.
 * Version: 1.0.1
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: Tempered Ltd
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vat-line-rounding
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		require_once __DIR__ . '/includes/class-tempered-vat-line-allocator.php';
		require_once __DIR__ . '/includes/class-tempered-vat-line-rounding.php';

		Tempered_Vat_Line_Rounding::init();
	},
	20
);
