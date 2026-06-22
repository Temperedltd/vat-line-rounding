# Changelog

All notable changes to this project will be documented in this file.

This project follows the principles of [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Nothing yet.

## [1.0.2] - 2026-06-22

### Added

- The plugin now defines `WC_TAX_ROUNDING_MODE` as `PHP_ROUND_HALF_UP` when the constant is not already defined.

### Fixed

- Fixed tax-exclusive cart lines so VAT is rounded from the stored WooCommerce tax rate instead of preserving an already-rounded-up tax amount.

## [1.0.1] - 2026-06-20

### Added

- Added GPLv2 licence metadata and included the licence file in release artefacts.

## [1.0.0] - 2026-06-19

### Added

- Initial public release of VAT Line Rounding for WooCommerce.
- Gross-preserving VAT allocation for eligible tax-inclusive cart and order line items.
- Cart and checkout item-row subtotal formatting based on normalised line data.
- WooCommerce admin notice for required subtotal tax rounding and half-up rounding mode configuration.
- PHPUnit test coverage for allocation, cart normalisation, order recalculation, configuration diagnostics, gateway-agnostic behaviour, and release packaging.
- GitHub Actions release workflow for production ZIP artefacts.

### Security

- Added private vulnerability reporting guidance in `SECURITY.md`.
