# AGENTS.md — Tazkar

## Mission

Tazkar is a modular Persian-first WordPress plugin for short Quran and Hadith reminders. The repository is developed agent-first under MASEF. Human testing is deferred until an official Release Candidate unless a protected product, security, legal, cost, secret, account, or irreversible production decision is required.

## Current stage

Sprint 5 is active under Issue #17 and Draft PR #18. Implement only the accepted scope of the active issue. The target version is `0.6.0-alpha.1`.

## Authoritative documents

Read, in order:

1. `docs/project/01-PROJECT-CHARTER.md`
2. `docs/management/WORDPRESS-VERSION-BASELINE.md`
3. `docs/project/03-MVP-SCOPE.md`
4. `docs/project/05-DATA-CONTRACT.md`
5. `docs/project/07-PLATFORM-AND-ARCHITECTURE.md`
6. `docs/project/09-SECURITY-AND-PRIVACY.md`
7. `docs/project/10-TEST-AND-ACCEPTANCE.md`
8. `docs/project/11-RELEASE-AND-ROLLBACK.md`
9. `docs/project/13-IMPLEMENTATION-PLAN.md`
10. `docs/project/16-AUTONOMOUS-DEVELOPMENT-POLICY.md`
11. `docs/project/17-AUTOMATED-QUALITY-GATES.md`
12. `docs/management/decision-log.md`
13. `docs/management/project-status.md`

Newest active decisions win. Historical references and review examples do not override current decisions.

## Non-negotiable constraints

- Minimum and verified WordPress baseline: exactly 7.0.2 for this project.
- Minimum PHP: 8.1.
- Do not add compatibility fallbacks for older WordPress or PHP versions.
- Alpha, Beta, RC, nightly, and unreleased WordPress builds are not production baselines.
- Namespace `Tazkar`; prefix, slug, and text domain `tazkar`.
- Plugin author: `رهبر بختیاری`.
- No runtime external requests, OpenAI API calls, telemetry, public CDN assets, or remote fonts.
- Imported content never publishes or becomes approved automatically.
- Exact Arabic source text is immutable through the import path.
- AI is never the final religious or scientific approver.
- Deactivation preserves data; uninstall preserves data by default.
- Secrets, credentials, API keys, and tokens must never be committed.

## Import safety rules

- State-changing administration actions require `import_tazkar_items`, dedicated nonces, and server-side batch ownership checks.
- Client requests may identify a batch but may not select arbitrary row IDs, cursors, statuses, or posts.
- Processing and rollback must be bounded, retry-safe, and driven by persisted server state.
- Staging stores only allowlisted pending-row payload; terminal rows remove it.
- Rollback may only act on posts referenced by rows in the selected batch and proven by hidden batch/row ownership metadata.
- Eligible owned drafts move to Trash. Published, approved, missing, edited, or ownership-mismatched posts remain untouched and become explicit conflicts.
- Repeated process, resume, migration, and rollback operations must be idempotent.

## Autonomous workflow

- Work only from an Issue marked `agent-ready` or an explicit task file.
- Use a branch named `agent/<issue-number>-<slug>`.
- Open a Draft PR using the repository template.
- Add tests before or with behavior changes.
- Run all relevant checks, including real WordPress integration when required by the issue.
- The implementer may not approve its own work.
- A CI repair loop is limited to three attempts for the same root cause.
- Never weaken a valid test merely to make CI green.
- Do not request human testing during normal Sprints.
- Use `BLOCKED` only for document conflict, required access/secret, product/privacy/religious decision, irreversible production action, or exhausted repair attempts.
- Human acceptance occurs only for an official Release Candidate.

## Protected decisions

Do not autonomously change these without `human-decision-required`:

- project charter, MVP scope, data contract, security/privacy, or monetization;
- permanent religious definitions or content approval policy;
- external network/data behavior;
- stable release decision.

## Required completion report

Every task must report:

1. Behavior changed.
2. Files changed.
3. Commands/tests and exact outcomes.
4. Remaining risk.
5. Final state: `PASS` or `BLOCKED`.
6. Suggested next task without implementing it.
