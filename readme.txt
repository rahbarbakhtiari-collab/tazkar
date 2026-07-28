=== Tazkar – Quran & Hadith Reflections ===
Contributors: rahbarbakhtiari
Tags: quran, hadith, islamic, reflection, rtl
Requires at least: 7.0.2
Tested up to: 7.0.2
Requires PHP: 8.1
Stable tag: 0.6.0-alpha.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Persian-first workflow for safely importing, reviewing, and publishing short Quran and Hadith reflections.

== Description ==

Tazkar provides a secure, resumable CSV import workflow for Quran and Hadith reminder content.

Version 0.6.0-alpha.1 adds persistent staging, bounded chunk processing, interruption recovery, row-level outcomes, and conflict-aware batch rollback. Imported content is always created as a draft and marked for review. Source-approved rows are not imported automatically, exact duplicate rows are skipped, and the exact Arabic source text is preserved.

The plugin does not make external runtime requests, does not load public CDN assets, and does not send telemetry.

== Installation ==

1. Upload and activate the plugin for each site where it is needed.
2. Open **Tazkar → CSV** in WordPress administration.
3. Preview a CSV file before importing it.
4. Stage the file, then process or resume one bounded chunk at a time.
5. Use batch rollback when eligible imported drafts must be moved to Trash. Published, approved, missing, or ownership-mismatched posts are preserved and reported as conflicts.

== Frequently Asked Questions ==

= Does an import publish content automatically? =

No. Imported items are always created as drafts and forced to the `needs_review` state.

= Is the uploaded CSV retained? =

No. The original upload is deleted after staging. Only the minimum allowlisted payload required for pending rows is stored, and that payload is removed when each row reaches a terminal state.

= Can an interrupted import continue? =

Yes. Batch progress and row outcomes are persisted on the server. Processing and rollback use server-owned row selection and bounded chunks.

= Does rollback permanently delete posts? =

No. Eligible drafts owned by the batch are moved to Trash. Conflicting posts are preserved and reported.

== Changelog ==

= 0.6.0-alpha.1 =
* Added persistent CSV staging and coherent batch/row progress counters.
* Added bounded chunk processing with a default of 25 rows and a hard maximum of 100 rows per request.
* Added retry-safe interruption recovery and server-owned row selection.
* Added batch-owned, idempotent rollback that trashes eligible drafts and reports conflicts.
* Added terminal staged-payload removal, capability checks, batch-bound nonces, and WordPress 7.0.2/MySQL 8 smoke coverage.
* Set the supported baseline to WordPress 7.0.2 and PHP 8.1.

= 0.5.0-alpha.1 =
* Added draft-only CSV import, batch and row persistence, duplicate controls, and row-level reporting.

= 0.4.0-alpha.1 =
* Added secure CSV reading, resource limits, row-level reporting, and an administration preview page without persistence.

= 0.3.0-alpha.1 =
* Added an independent data-contract validator, stable issue codes, warnings, and batch validation.

= 0.2.0-alpha.1 =
* Added the custom post type, taxonomies, contract metadata, exact Arabic hashing, and sensitive-change guards.

= 0.1.0-alpha.1 =
* Added the plugin skeleton, initial migration, capabilities, and administration status page.
