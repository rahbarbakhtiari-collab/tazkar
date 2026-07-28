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
- only one active staged, processing or rollback-processing batch is allowed per authenticated administrator;
- batch creation is serialized with a per-user database advisory lock so concurrent requests cannot create overlapping active batches;
- processing across all users and batches is serialized by a site-wide deduplication advisory lock around duplicate detection, post creation and row finalization;
- process requests re-read the authoritative batch state after acquiring the batch lock, preventing processing from continuing after rollback has started;
- interrupted `processing` rows are recovered and retried without duplicate terminal outcomes;
- exact Arabic text is preserved through staging and resumed processing;
- imported content is always created as `tazkar_item` Draft and never approved automatically;
- staged payload is removed whenever a row becomes terminal;
- rollback cancels unprocessed rows and moves only eligible, untouched, batch-owned drafts to Trash;
- published, approved, edited, missing or foreign-owned posts are preserved and recorded as conflicts;
- a non-mutating rollback preview reports eligible drafts, pending rows, protected/conflicting posts and recorded interactions before execution;
- rollback execution requires a dedicated batch-bound nonce and explicit administrator confirmation after preview;
- when WordPress Trash is disabled through `EMPTY_TRASH_DAYS=0`, preview reports the condition and rollback is rejected before any mutation, preventing permanent deletion;
- repeated process and rollback requests are idempotent;
- the Persian administration screen exposes progress, counts, resume, rollback preview and confirmed rollback actions with escaped outcomes.

## 2. Persistence and migration

- Database schema version is `2`.
- Migration uses `dbDelta()` and is idempotent.
- A populated schema-version-1 batch and row are migrated twice in the real WordPress/MySQL smoke test without data loss or duplicate rows.
- Custom WordPress database prefixes are covered.
- Batch audit metadata and terminal row outcomes remain after completion or rollback, while recoverable staged payloads do not.

## 3. Security and privacy controls

- All administration actions require `import_tazkar_items`.
- Start, process, resume, rollback preview and rollback requests use dedicated nonces.
- Batch UUID input is validated and every operation resolves the batch by UUID plus authenticated owner ID.
- Client-supplied cursor, row IDs, counts, state, post IDs and ownership data are not trusted.
- Active-batch creation is protected by a per-user MySQL advisory lock and a server-side active-state query.
- A shared site-wide MySQL advisory lock serializes duplicate checks and write finalization across different users and batches.
- Processing validates the current batch state again while holding the batch lock, closing the process-versus-rollback race.
- SQL identifiers and values use WordPress prepared-query facilities; the reserved `row_number` identifier is explicitly quoted.
- Staging rejects HTML-bearing values and excludes server-owned import fields.
- Rollback preview is non-mutating and recalculates current post classifications and interaction counts from server data.
- Rollback is blocked at both service and writer layers when WordPress Trash is disabled, so `wp_trash_post()` cannot fall through to permanent deletion.
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
- `tests/unit/CsvPreviewPageImportSecurityTest.php`
- `tests/wordpress-schema-v2-migration-smoke.php`
- `tests/wordpress-chunked-import-smoke.php`
- `.github/workflows/ci.yml`
- `tazkar.php`, `readme.txt`, `README.md` and `AGENTS.md`

## 5. Automated evidence

The authoritative clean implementation head `0aa830b421fc1d70221085311b0ad83662202ae7` passed:

- CI run `30366482657` / run number `265`;
- PR Policy run `30366482414` / run number `114`;
- repository and fixture validation;
- GitHub workflow YAML validation;
- Composer validation and dependency audit;
- secret scan;
- PHP syntax lint;
- PHPCS;
- PHPStan;
- PHPUnit, including active-batch rejection, post-lock state recheck, shared deduplication lock contention, non-mutating rollback preview, explicit rollback confirmation and disabled-Trash rejection;
- activation smoke;
- deterministic plugin ZIP build;
- PHP 8.1, 8.3, 8.4 and 8.5 quality matrix;
- WordPress 7.0.2 + MySQL 8 installation, activation, deactivation and reactivation;
- populated schema v1-to-v2 migration and repeated migration;
- Sprint 0–4 regression smokes;
- a 63-row Sprint 5 scenario proving multiple requests, interruption recovery, no duplicate posts, active-batch rejection, coherent terminal outcomes, exact Arabic preservation, payload removal, non-mutating rollback preview, interaction counting, safe rollback conflicts and repeated rollback idempotency;
- PR Policy.

## 6. Review findings addressed

The independent current-generation findings were addressed as follows:

1. **Multiple active batches for one administrator:** batch creation uses a per-user advisory lock and rejects an existing active batch before persistence.
2. **Rollback executed without preview:** the administration flow requires a non-mutating preview followed by explicit confirmed execution.
3. **Process/rollback state race:** process re-reads and validates batch state while holding the batch lock before recovering or claiming rows.
4. **Cross-batch duplicate race:** a site-wide deduplication advisory lock serializes duplicate detection and post finalization across all active batches and users.
5. **Permanent deletion when Trash is disabled:** rollback preview exposes the disabled state, the execution button is withheld, the service rejects execution before mutation and the writer independently refuses destructive fallback.

The earlier schema-only findings about missing chunking and missing rollback apply to superseded commits and were already resolved.

## 7. Remaining real risks and limitations

- Human exploratory testing remains intentionally deferred until an official Release Candidate.
- Staging parses the validated upload in the initial administration request; the existing 500-row, 60-column and 10 MB limits bound this phase.
- Lock expiry is 120 seconds and each normal processing loop has a 20-second budget; exceptionally slow third-party hooks may still require operational observation during Release Candidate testing.
- The shared deduplication lock deliberately serializes import processing across the site to prioritize correctness over parallel throughput.
- Rollback never permanently deletes content and intentionally leaves conflicts for an administrator to review.
- XLSX, editorial metaboxes, bulk scientific approval and publication remain outside Sprint 5.

## 8. Final state

**Engineering and automated-gate result: PASS.**

PR #18 remains Draft only until a fresh independent review is completed against the final documented head. With no actionable blocker, the PR can be marked ready and merged according to repository policy.

## 9. Suggested next task

After Sprint 5 merges, reconcile Issues #13 and #14 with the Sprint 7 editorial-review plan before starting the next implementation Sprint.
