# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHPStan stubs for Easy Digital Downloads core. The stubs are auto-generated from upstream plugin source using `php-stubs/generator`.

## Commands

```bash
# Run all tests (PHPUnit + PHPStan + PHPCS)
composer test

# Individual test commands
composer test:phpunit    # Run PHPUnit tests
composer test:phpstan    # Run PHPStan analysis (uses tests/phpstan.neon)
composer test:cs         # Run PHP CodeSniffer
composer test:cs:fix     # Auto-fix coding style issues

# Generate stubs (requires EDD_PATH env var)
composer generate

# Regenerate CHANGELOG from git history (requires git-cliff installed)
composer changelog
```

## CHANGELOG Management

The CHANGELOG is automatically generated using [git-cliff](https://git-cliff.org).

**Requirements:**
- git-cliff must be installed: `brew install git-cliff` (macOS) or see [installation docs](https://git-cliff.org/docs/installation)
- Configuration: `cliff.toml`

**Manual Regeneration:**
```bash
composer changelog  # Regenerates entire CHANGELOG from git history
```

**Automated:**
- The `generate.yml` workflow automatically updates CHANGELOG when generating new stubs
- GitHub releases use auto-generated notes (configured in `.github/release.yml`)

**Commit Message Format:**
Follow conventional commits for automatic CHANGELOG categorization:
- `feat:` → `added:` in changelog
- `fix:` → `fixed:` in changelog
- `docs:` → `improved:` in changelog
- `chore:` → removed or contextual prefix
- `chore(stubs):` → `changed:` in changelog
- Add `!` after type for breaking changes (e.g., `feat!:`, `chore!:`)

## Generating Stubs

The `generate.php` script requires the `EDD_PATH` environment variable pointing at a local Easy Digital Downloads source tree.

Set it via `.env` file (copy from `.env.example`) or export directly.

## Architecture

### Key Files
- `easy-digital-downloads-stubs.php` - Generated output file containing all EDD core stubs
- `generate.php` - Stub generation script with post-processing:
  - `removeStrayCodeStatements` — drops template-style procedural code, bare `define()` calls, and stray `$var = $this->...` assignments
  - `neutralizeAbstractMethods` — converts abstract methods to concrete stubs so child classes the generator emits without method bodies don't fatal at parse time
  - `addSelfContainedConstants` — defines `EDD_VERSION` and `EDD_PLUGIN_*` constants with `defined()` guards
  - `fixMissingTypeStubs` — iteratively detects "Class/Interface/Trait X not found" fatals and appends empty stubs for them (catches vendored Doctrine, Symfony, Illuminate references that aren't in scan scope)

### GitHub Workflows
- `generate.yml` — Manual workflow to generate stubs from a specific EDD version (downloads from WordPress.org, includes CHANGELOG update + auto-release)
- `check-updates.yml` — Biweekly check for new EDD releases on WordPress.org (auto-triggers `generate.yml`)
- `integrate.yml` — CI tests on push/PR (PHPUnit + PHPStan + PHPCS)
- `release.yml` — Creates a GitHub Release with auto-generated notes when a `v*` tag is pushed
- `claude.yml` / `claude-code-review.yml` — Claude Code integration for issues / PRs

### Testing
- PHPStan runs at `max` level against the stubs file
- PHPUnit verifies stub syntax is valid PHP and core EDD symbols resolve
- PHPCS enforces WordPress coding standards on `generate.php` and tests

## Coding Standards

Uses WordPress-Core coding standards with exceptions for:
- Modern file naming (PSR-4 style)
- camelCase function/variable names
