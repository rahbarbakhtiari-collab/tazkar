# Tazkar

Tazkar is a Persian-first WordPress plugin for safely managing short Quran and Hadith reflections.

## Requirements

- WordPress 7.0.2
- PHP 8.1 or newer
- MySQL 8 for the verified integration environment

Pre-release WordPress versions are not production baselines. Any future baseline change starts in the authoritative project documentation before code, headers, CI, or tests are changed.

## Current scope: Sprint 5

Version `0.6.0-alpha.1` includes:

- the `tazkar_item` content model, taxonomies, and registered metadata;
- exact Arabic-text preservation and sensitive-change safeguards;
- secure CSV preview and validation;
- draft-only CSV import with persistent batch and row records;
- allowlisted staging payloads removed at terminal row states;
- bounded chunk processing with server-owned selection and retry-safe resume;
- conflict-aware, idempotent rollback that only trashes eligible batch-owned drafts;
- Persian and RTL administration screens with translation-ready strings.

The plugin does not publish imported content automatically, make external runtime requests, or send telemetry.

## Quality commands

```bash
composer install
python3 scripts/validate_repository.py
composer validate --strict
composer audit --no-interaction --format=plain
python3 scripts/scan_secrets.py
composer lint
composer test
php tests/activation-smoke.php
vendor/bin/phpcs
vendor/bin/phpstan analyse
vendor/bin/phpunit
composer build
```

The GitHub Actions WordPress smoke job installs the built ZIP on WordPress 7.0.2 with MySQL 8 and runs content-model, CSV preview, Sprint 4 regression, schema migration, and Sprint 5 chunk/resume/rollback tests.

## Data lifecycle

- Deactivation preserves all plugin data.
- Uninstall preserves data by default.
- Complete deletion is only allowed when the explicit plugin option `tazkar_delete_data_on_uninstall=yes` is enabled.
- The original CSV upload is removed after staging.
- Pending staged payload is minimal and allowlisted; terminal rows retain outcomes, not source payload.

## Development governance

Start with [`AGENTS.md`](AGENTS.md), then follow the authoritative project and management documents. Work is issue-driven on `agent/*` branches, validated by required checks, and independently reviewed. Human acceptance is reserved for an official Release Candidate unless a real protected decision or external-access blocker occurs.
