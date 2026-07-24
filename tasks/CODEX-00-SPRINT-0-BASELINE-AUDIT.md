# Codex Task 00 — Audit and stabilize Sprint 0 baseline

## Goal

Audit the existing Sprint 0 WordPress plugin baseline, run the complete quality and WordPress smoke test suite, and fix only issues that block the baseline from passing CI and local installation. Do not implement Sprint 1 features.

## Required context

Read before changing code:

- `AGENTS.md`
- `README.md`
- `docs/project/07-PLATFORM-AND-ARCHITECTURE.md`
- `docs/project/09-SECURITY-AND-PRIVACY.md`
- `docs/project/10-TEST-AND-ACCEPTANCE.md`
- `docs/project/13-IMPLEMENTATION-PLAN.md`
- `docs/project/14-SPRINT-00-IMPLEMENTATION-REPORT.md`
- `docs/management/decision-log.md`

## Work

1. Inspect the repository and current GitHub Actions workflow.
2. Run:
   - `composer validate --strict`
   - `composer install --no-interaction --prefer-dist`
   - `composer lint`
   - `composer test`
   - `php tests/activation-smoke.php`
   - `vendor/bin/phpcs`
   - `vendor/bin/phpstan analyse`
   - `vendor/bin/phpunit`
   - `composer build`
3. Fix concrete failures while preserving the Sprint 0 scope.
4. Verify the release ZIP contains runtime files only and installs as plugin slug `tazkar`.
5. Verify activation is idempotent, deactivation preserves data, and the four operational tables use the WordPress table prefix.
6. Update documentation only where actual behavior changed.
7. Open a Draft PR.

## Acceptance criteria

- All quality jobs pass on PHP 8.1, 8.3, 8.4, and 8.5.
- The WordPress smoke job installs WordPress, activates the built ZIP, verifies `tazkar_db_version=1`, deactivates, and reactivates successfully.
- PHPCS, PHPStan, PHPUnit, smoke tests, and build pass.
- The release ZIP is reproducible and excludes tests, CI files, and development configuration.
- No CPT, importer, REST endpoint, frontend, feedback, or favorites feature is added.
- No external runtime request is introduced.

## Out of scope

- Sprint 1 content model.
- Changing product requirements.
- Adding frameworks or runtime dependencies.
- Rewriting architecture without a demonstrated blocker.
- Publishing a stable release.

## Completion response

Report:

1. Changes made.
2. Root cause of each fixed failure.
3. Commands/tests and exact outcomes.
4. PR link and commit SHA.
5. Remaining risks or tests requiring Local on Windows.
