# Sprint 5 Chunked Import Implementation Report

**Document:** `docs/project/23-SPRINT-05-CHUNKED-IMPORT-IMPLEMENTATION-REPORT.md`  
**Date:** 2026-07-28  
**Issue:** #17  
**Pull request:** #18  
**Target version:** `0.6.0-alpha.1`  
**Platform baseline:** PHP 8.1+ and WordPress 7.0.2

## 1. Behavior changed

Sprint 5 replaces the single-request CSV draft importer with a persistent, resumable and idempotent workflow:

- validated CSV rows are staged into one batch and one row record per source row;
- only an allowlisted normalized payload is retained for pending rows;
- the original uploaded file is removed after staging;
- import processing is limited to a configurable chunk, default 25 and hard maximum 100;
- row selection, cursor, lock, counts and state transitions remain server-owned;
- interrupted `processing` rows are recovered and retried without duplicate terminal outcomes;
- exact Arabic text is preserved through staging and resumed processing;
- imported content is always created as `tazkar_item` Draft and never approved automatically;
- staged payload is removed whenever a row becomes terminal;
- rollback cancels unprocessed rows and moves only eligible, untouched, batch-owned drafts to Trash;
- published, approved, edited, missing or foreign-owned posts are preserved and recorded as conflicts;
- repeated process and rollback requests are idempotent;
- the Persian administration screen exposes progress, counts, resume and rollback actions with escaped row outcomes.

## 2. Persistence and migration

- Database schema version is `2`.
- Migration uses `dbDelta()` and is idempotent.
- A populated schema-version-1 batch and row are migrated twice in the real WordPress/MySQL smoke test without data loss or duplicate rows.
- Custom WordPress database prefixes are covered.
- Batch audit metadata and terminal row outcomes remain after completion or rollback, while recoverable staged payloads do not.

## 3. Security and privacy controls

- All administration actions require `import_tazkar_items`.
- Start, process, resume and rollback requests use dedicated nonces.
- Batch UUID input is validated and every operation resolves the batch by UUID plus authenticated owner ID.
- Client-supplied cursor, row IDs, counts, state, post IDs and ownership data are not trusted.
- SQL identifiers and values use WordPress prepared-query facilities; the reserved `row_number` identifier is explicitly quoted.
- Staging rejects HTML-bearing values and excludes server-owned import fields.
- No public REST write route, external request, telemetry, daemon, CDN asset or retained upload file was introduced.
- Rollback verifies post type, batch ownership meta, row ownership meta, Draft status, review status and an import-time content snapshot before moving a post to Trash.

## 4. Main files changed

- `src/Import/ChunkedImportService.php`
- `src/Import/ChunkedImportRepository.php`
- `src/Import/WordPressChunkedImportRepository.php`
- `src/Import/ChunkedImportProgress.php`
- `src/Import/RollbackAwareContentWriter.php`
- `src/Import/WordPressContentWriter.php`
- `src/Admin/CsvPreviewPage.php`
- `src/Infrastructure/Database/Schema.php`
- `tests/unit/ChunkedImportServiceTest.php`
- `tests/wordpress-schema-v2-migration-smoke.php`
- `tests/wordpress-chunked-import-smoke.php`
- `.github/workflows/ci.yml`
- `tazkar.php`, `readme.txt`, `README.md` and `AGENTS.md`

## 5. Automated evidence

The authoritative GitHub Actions run for code head `55c1febabe14c44365f1495569bce9b71539381f` passed:

- repository and fixture validation;
- GitHub workflow YAML validation;
- Composer validation and dependency audit;
- secret scan;
- PHP syntax lint;
- PHPCS;
- PHPStan;
- PHPUnit;
- activation smoke;
- deterministic plugin ZIP build;
- PHP 8.1, 8.3, 8.4 and 8.5 quality matrix;
- WordPress 7.0.2 + MySQL 8 installation, activation, deactivation and reactivation;
- populated schema v1-to-v2 migration and repeated migration;
- Sprint 0–4 regression smokes;
- a 63-row Sprint 5 scenario proving multiple requests, interruption recovery, no duplicate posts, coherent terminal outcomes, payload removal, safe rollback conflicts and repeated rollback idempotency;
- PR Policy.

## 6. Remaining real risks and limitations

- Human exploratory testing remains intentionally deferred until an official Release Candidate.
- Staging parses the validated upload in the initial administration request; the existing 500-row, 60-column and 10 MB limits bound this phase.
- Lock expiry is 120 seconds and each normal processing loop has a 20-second budget; exceptionally slow third-party hooks may still require operational observation during Release Candidate testing.
- Rollback never permanently deletes content and intentionally leaves conflicts for an administrator to review.
- XLSX, editorial metaboxes, bulk scientific approval and publication remain outside Sprint 5.

## 7. Final state

**Engineering and automated-gate result: PASS.**

PR #18 remains Draft until a fresh independent architecture/security review confirms no actionable blocker. After that review, the PR may be marked ready and merged according to repository policy.

## 8. Suggested next task

After Sprint 5 merges, reconcile Issues #13 and #14 with the Sprint 7 editorial-review plan before starting the next implementation Sprint.
