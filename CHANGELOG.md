# Changelog

All notable changes to this project will be documented in this file.

This project follows the principles of [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Nothing yet.

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
