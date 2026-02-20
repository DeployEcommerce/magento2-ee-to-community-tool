# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

A CLI tool that migrates Magento 2 from Enterprise Edition (EE) to Community Edition (CE) by:
1. **Database migration** — drops ~90 EE-only tables and rewrites primary keys (`row_id` → `entity_id`)
2. **Composer migration** — swaps EE package dependencies to CE equivalents
3. **Verification** — compares pre/post migration database snapshots to confirm success

## Commands

```bash
# Run all tests
vendor/bin/pest

# Run only feature or unit tests
vendor/bin/pest tests/Feature
vendor/bin/pest tests/Unit

# Run a single test file
vendor/bin/pest tests/Unit/SomeTest.php

# Code formatting (PSR-12 via Laravel Pint)
vendor/bin/pint

# Build standalone PHAR (requires box globally installed)
box compile
```

## Architecture

**Framework**: Laravel Zero (zero-config Laravel CLI application)

**Three-phase migration flow**:
1. Snapshot pre-migration DB state → `snapshot-before-{timestamp}.json`
2. Execute 12 sequential SQL files from `sql/`
3. Capture post-migration snapshot and compare

**Layer structure**:
- `app/Commands/` — Console commands (entry points for user interaction)
- `app/Services/` — Business logic, grouped by domain (`Database/`, `Snapshot/`, `Composer/`)
- `app/Contracts/` — Interfaces for every service (enables mockery-based testing)
- `app/ValueObjects/` — Immutable readonly data containers (`DatabaseConfig`, `SnapshotReport`, `MigrationResult`, `ComposerAnalysis`)
- `app/Providers/AppServiceProvider.php` — All DI bindings: interfaces → implementations
- `sql/` — 12 numbered migration files (`01_attributes.sql` … `12_wishlist.sql`)

**Key commands**:
- `MigrateCommand` — orchestrator; runs db:migrate → composer:migrate → verify
- `DatabaseMigrateCommand` — runs SQL files sequentially via `SqlFileRunner`
- `ComposerMigrateCommand` — rewrites `composer.json` EE→CE packages
- `VerifyCommand` — loads snapshots and delegates to `SnapshotComparator`

**Safety mechanisms**:
- `DisclaimerService` — interactive confirmation gate on every command; bypass with `--accept-terms`
- `--dry-run` — captures snapshot but skips SQL execution
- `--from=N` — resume migration from a specific SQL file number after a failure
- SQL log written to `ee-to-ce-migration-{timestamp}.sql.log`

## Conventions

- Commit messages must follow [Conventional Commits](https://www.conventionalcommits.org/) format
- Every service has a matching interface in `app/Contracts/`; always inject the interface, not the concrete class
- SQL files are processed in alphabetical order (numeric prefix preserves sequence)
- Value objects use `readonly` properties and provide `toArray()`/`fromArray()` for snapshot serialization
- CLI output uses Symfony Console color tags: `<fg=green>✓</>`, `<fg=red>✗</>`, `<fg=yellow>⚠</>`
- PDO is configured with `MYSQL_ATTR_MULTI_STATEMENTS` enabled and `utf8mb4` charset