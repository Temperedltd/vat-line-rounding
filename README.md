# VAT Line Rounding

Version: 1.0.3

VAT Line Rounding keeps WooCommerce tax-inclusive line items aligned with gross-first accounting typical of most accounting software. It recalculates eligible line net and VAT values from the rounded gross value, then writes the normalised values back to cart and order line items.

This means your WooCommerce store should align with your accountancy software when determining VAT.

## Requirements

- WordPress 7.0 or later.
- WooCommerce.
- PHP 8.2 or later.
- WooCommerce prices entered inclusive of tax.

## Why

### The 69p problem

If a product costs 69p inclusive of tax and tax is 20%, what are the net revenue and tax amounts?

Sounds like a school maths question and if you said 11.5p, well done you!

When a WooCommerce price already includes VAT, the gross amount is the price the customer has agreed to pay. However, splitting that amount into net revenue and VAT can produce fractions of a penny.

For a 69p line at 20% VAT:

`69p × 20 ÷ 120 = 11.5p VAT`

There is no half-penny in GBP, so that amount must become either 11p or 12p. WooCommerce's tax-inclusive midpoint rounding will allocate the line as:

- 58p net
- 11p VAT
- 69p total

A gross-preserving, half-up allocation instead produces:

- 57p net
- 12p VAT
- 69p total

Both allocations still add up to the customer's 69p price, but the one-penny difference can prevent WooCommerce orders from matching the figures produced by an accounting or ERP system.

So in your accounting software 69p may well be 57p net and 12p tax; in WooCommerce, by default, it is 58p net and 11p tax!

Now you might think, surely we can just round up, and you would be right, WooCommerce has a constant you can flip to round up. Except now your 69p item costs your customer 70p.

### Is this actually a problem?

No, yes, maybe.

For tax purposes (we are not accountants), probably not; certainly in the UK, HMRC allows a little slippage and will accept rounding down or rounding up as long as it's *consistent*[^1].

The bigger issue is that WooCommerce actually applies this inconsistently and, because the tax is worked out dynamically, doesn't always call the right function, so sometimes it will show the wrong tax amount.

Also, chances are if you have custom code that touches tax, you rounded it up. The result is some orders can be a penny out, or at least show as a penny out to a user or customer.

## What It Does

The plugin modifies WooCommerce so that it rounds line items up rather than down, hopefully resulting in a more consistent experience and less hair pulling for your accountant.

- Preserves each eligible line item's gross value when splitting inclusive prices into net and VAT.
- Preserves per-unit VAT allocation for eligible multi-quantity inclusive-price lines.
- Normalises cart line totals, subtotals, and aggregate cart tax totals after WooCommerce calculates totals.
- Preserves normalised order line values around WooCommerce tax recalculation.
- Formats cart and checkout item-row subtotals from the normalised line data.

### For Accountants & Maths Folk

VAT Line Rounding treats the rounded gross amount as the source of truth. For multi-quantity inclusive-price lines with an exact unit gross value, it allocates one rounded unit first and then multiplies that allocation by quantity.

For each supported line, it calculates:

`VAT = round_half_up(gross × rate ÷ (100 + rate))`

`net = gross − VAT`

For the 69p example:

`VAT = round_half_up(69 × 20 ÷ 120)`

`VAT = round_half_up(11.5) = 12p`

`net = 69p − 12p = 57p`

The calculation is performed in integer minor units, pence for GBP, rather than relying on floating-point currency arithmetic.

The customer's gross total remains unchanged. The plugin only normalises how that total is divided between net revenue and VAT, and writes the result back to the WooCommerce cart and order line.

The plugin is intentionally conservative. It normalises line items when it can identify one positive, non-compound tax amount and infer a positive tax rate from the line data.

It leaves unsupported lines unchanged, including zero-tax lines, compound-rate lines, multiple-rate lines, and values that cannot be represented safely with integer minor-unit arithmetic.

## Installation

1. Copy the plugin directory to `wp-content/plugins/vat-line-rounding`.
2. Activate VAT Line Rounding in WordPress.
3. Confirm WooCommerce is configured to round tax at subtotal level.
4. Confirm the plugin admin notice is not shown. The plugin defines `WC_TAX_ROUNDING_MODE` as `PHP_ROUND_HALF_UP` when it is not already defined; if WooCommerce or site configuration has already defined a different value, define it in `wp-config.php` before WooCommerce loads.

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

The GitHub Actions release workflow builds a production ZIP artefact containing the plugin file, `includes/`, `README.md`, `LICENSE`, `SECURITY.md`, and `CHANGELOG.md`.

## Security

Please report suspected vulnerabilities privately using the process in `SECURITY.md`.

[^1]: https://www.gov.uk/hmrc-internal-manuals/vat-trader-records/vatrec12020
