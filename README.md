# Easy Digital Downloads Stubs

[![Test](https://github.com/artkrsk/easy-digital-downloads-stubs/actions/workflows/integrate.yml/badge.svg)](https://github.com/artkrsk/easy-digital-downloads-stubs/actions/workflows/integrate.yml)
[![Latest Release](https://img.shields.io/github/v/release/artkrsk/easy-digital-downloads-stubs)](https://github.com/artkrsk/easy-digital-downloads-stubs/releases/latest)
[![PHP Version](https://img.shields.io/packagist/dependency-v/arts/easy-digital-downloads-stubs/php)](https://packagist.org/packages/arts/easy-digital-downloads-stubs)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-yellow?logo=buy-me-a-coffee)](https://buymeacoffee.com/artemsemkin)

Comprehensive PHPStan stubs for Easy Digital Downloads, EDD Software Licensing, and EDD ConvertKit.

Get full IDE autocomplete, IntelliSense, and type safety when developing EDD-powered WordPress plugins, themes, and bridges.

## Features

- Full IDE autocomplete for all EDD core, EDD Software Licensing, and EDD ConvertKit classes and functions
- Type safety and static analysis with PHPStan
- Catch errors before runtime when building EDD integrations and order pipelines
- Self-contained — includes WordPress core stubs as a transitive dependency
- Generated directly from upstream plugin source

## Requirements

- PHP 8.0 or higher
- PHPStan for static analysis
- Automatically pulls in WordPress and WP-CLI stubs as dependencies

## Installation

```bash
composer require --dev arts/easy-digital-downloads-stubs
```

## Usage with PHPStan

Add to your `phpstan.neon`:

```yaml
parameters:
    bootstrapFiles:
        - vendor/php-stubs/wordpress-stubs/wordpress-stubs.php
        - vendor/php-stubs/wp-cli-stubs/wp-cli-stubs.php
        - vendor/arts/easy-digital-downloads-stubs/easy-digital-downloads-stubs.php
```

The stubs include EDD core, EDD Software Licensing, and EDD ConvertKit type definitions in a single file.

> **Note:** EDD Software Licensing and EDD ConvertKit are commercial add-ons. The generator only includes stubs for the add-ons you have a local copy of — `EDD_SL_PATH` and `EDD_CONVERTKIT_PATH` in `.env` are optional. Without them, only EDD core stubs are generated.

## Regenerating Stubs

For contributors or to generate stubs from specific plugin versions:

1. Copy `.env.example` to `.env`
2. Set `EDD_PATH` to your Easy Digital Downloads installation (required)
3. Optionally set `EDD_SL_PATH` and `EDD_CONVERTKIT_PATH` for the add-on stubs
4. Run: `composer generate`

```bash
cp .env.example .env
# Edit .env with your paths
composer generate
```

## 💖 Support

If you find this package useful, consider buying me a coffee:

<a href="https://buymeacoffee.com/artemsemkin" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" ></a>

---

Made with ❤️ by [Artem Semkin](https://artemsemkin.com)
