# VAT Line Rounding

Version: 1.0.0

VAT Line Rounding keeps WooCommerce tax-inclusive line items aligned with gross-first accounting. It recalculates eligible line net and VAT values from the rounded gross value, then writes the normalised values back to cart and order line items.

This means your WooCommerce store should align with your accountancy software when determining VAT.

## Requirements

- WordPress 7.0 or later.
- WooCommerce.
- PHP 8.2 or later.
- WooCommerce prices entered inclusive of tax.

## Why

### The 69 problem
If a product costs 69p inclusive of tax and tax is 20%, what are the gross and tax amounts?
Sounds like a school maths question and if you said 11.5p, well done you!
But you can't have 11.5p when dealing with GBP; we round to the nearest pence.

So now you have either 11p or 12p. If you are scratching your head and thinking, surely you round up and it can only be 12p. We agree, as does HMRC (His Majesty's Revenue and Customs), but WooCommerce rounds down.

So in your accounting software 69p will be 57p gross and 12p tax, in WooCommerce by default it is 58p gross and 11p tax!

Now you might think, surely we can just round up, and you would be right, WooCommerce has a constant you can flip to round up. Except now your 69p item costs your customer 70p.

### Is this actually a problem?
No, yes, maybe.
For tax purposes (we are not accountants), probably not; certainly in the UK, HMRC allows a little slippage and will accept round down or round up as long as it's consistent. The bigger issue is WooCommerce actually applies this inconsistently and, because the tax is worked out dynamically, doesn't always call the right function, so sometimes it will show the wrong tax amount. Also, chances are if you have custom code that touches tax, you rounded up. The result is some orders can be a penny out.

## What It Does

- Preserves each eligible line item's gross value when splitting inclusive prices into net and VAT.
- Normalises cart line totals, subtotals, and aggregate cart tax totals after WooCommerce calculates totals.
- Preserves normalised order line values around WooCommerce tax recalculation.
- Formats cart and checkout item-row subtotals from the normalised line data.
- Warns WooCommerce managers when subtotal tax rounding or the WooCommerce rounding mode does not match the expected configuration.

## Supported Lines

The plugin is intentionally conservative. It normalises line items when it can identify one positive, non-compound tax amount and infer a positive tax rate from the line data.

It leaves unsupported lines unchanged, including zero-tax lines, compound-rate lines, multiple-rate lines, and values that cannot be represented safely with integer minor-unit arithmetic.

## Installation

1. Copy the plugin directory to `wp-content/plugins/vat-line-rounding`.
2. Activate VAT Line Rounding in WordPress.
3. Confirm WooCommerce is configured to round tax at subtotal level.
4. Confirm `WC_TAX_ROUNDING_MODE` is `PHP_ROUND_HALF_UP`.

## Development

Install development dependencies with Composer:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Run WordPress Coding Standards checks:

```bash
composer lint:phpcs
```

Coverage is configured for `includes/`, but local coverage reporting requires Xdebug or PCOV.

## Releases

Releases are driven by the plugin header version in `vat-line-rounding.php`. When preparing a release:

1. Update the `Version:` header.
2. Add a matching release section to `CHANGELOG.md`.
3. Push the version change to `main`.

The GitHub Actions release workflow builds a production ZIP artefact containing the plugin file, `includes/`, `README.md`, `SECURITY.md`, and `CHANGELOG.md`.

## Security

Please report suspected vulnerabilities privately using the process in `SECURITY.md`.
