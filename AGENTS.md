# AGENTS.md — Tazkar

## Mission

Tazkar is a modular Persian-first WordPress plugin for short Quran and Hadith reminders. The repository is developed agent-first. Human testing is deferred until an official Release Candidate.

## Current stage

The repository contains the Sprint 0 baseline. Do not implement Sprint 1 or later unless the Issue explicitly scopes it.

## Authoritative documents

Read, in order:

1. `docs/project/03-MVP-SCOPE.md`
2. `docs/project/05-DATA-CONTRACT.md`
3. `docs/project/07-PLATFORM-AND-ARCHITECTURE.md`
4. `docs/project/09-SECURITY-AND-PRIVACY.md`
5. `docs/project/10-TEST-AND-ACCEPTANCE.md`
6. `docs/project/11-RELEASE-AND-ROLLBACK.md`
7. `docs/project/13-IMPLEMENTATION-PLAN.md`
8. `docs/project/16-AUTONOMOUS-DEVELOPMENT-POLICY.md`
9. `docs/project/17-AUTOMATED-QUALITY-GATES.md`
10. `docs/management/decision-log.md`

Newest active decisions win. A single review example is not a permanent product rule.

## Non-negotiable constraints

- Minimum WordPress 6.8 and PHP 8.1.
- Namespace `Tazkar`; prefix, slug, and text domain `tazkar`.
- No runtime external requests or OpenAI API in MVP.
- Imported content never publishes automatically.
- Arabic source text is immutable.
- AI is never the final religious or scientific approver.
- Deactivation preserves data; uninstall preserves data by default.

## Autonomous workflow

- Work only from an Issue marked `agent-ready` or an explicit task file.
- Use a branch named `agent/<issue-number>-<slug>`.
- Open a Draft PR using the repository template.
- Add tests before or with behavior changes.
- Run all relevant checks.
- The implementer may not approve its own work.
- A CI repair loop is limited to three attempts for the same root cause.
- Never weaken a valid test merely to make CI green.
- Do not request human testing during normal Sprints.
- Use `BLOCKED` only for document conflict, required access/secret, product/privacy/religious decision, or exhausted repair attempts.
- Human acceptance occurs only for an official Release Candidate.

## Protected decisions

Do not autonomously change these without `human-decision-required`:

- project charter, MVP scope, data contract, security/privacy, monetization;
- permanent religious definitions or content approval policy;
- external network/data behavior;
- Stable release decision.

## Required completion report

Every task must report:

1. Behavior changed.
2. Files changed.
3. Commands/tests and exact outcomes.
4. Remaining risk.
5. Final state: `PASS` or `BLOCKED`.
6. Suggested next task without implementing it.
