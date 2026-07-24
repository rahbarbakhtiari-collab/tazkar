# راه‌اندازی Orchestrator توسعه تذکار

## واقعیت اجرایی

GitHub Actions می‌تواند تست، policy، artifact و auto-merge را انجام دهد؛ اما خودش عامل Codex را برای نوشتن یا تعمیر کد فراخوانی نمی‌کند.

برای اجرای بدون حضور انسان، یکی از دو سطح زیر لازم است.

## سطح A — Codex Environment + Code Review + Automations

مناسب شروع پروژه:

1. repository `tazkar` را به Codex متصل کنید.
2. یک Environment با دستورهای `AGENTS.md` بسازید.
3. Automatic Code Review را برای repository فعال کنید.
4. دو Automation دوره‌ای بسازید:
   - Issue Dispatcher: Issueهای `agent-ready` بدون PR را پیدا و اجرا کند.
   - CI Repair: PRهای `agent/*` با check failed را بررسی و حداکثر سه بار اصلاح کند.
5. GitHub auto-merge را فعال کنید.

### Prompt آماده Issue Dispatcher

```text
Every hour, inspect rahbarbakhtiari-collab/tazkar for open issues labeled agent-ready that do not already have an active pull request. Select the oldest unblocked issue. Read AGENTS.md and all authoritative documents referenced by the issue. Implement only the stated scope on an agent/<issue-number>-<slug> branch, add or update tests, run the repository checks, push the branch, and open a draft pull request using the repository template. Add the labels agent-in-progress and automerge only when Human decision is no. If the issue requires a product, privacy, religious, credential, or scope decision, label it blocked and human-decision-required, explain the exact decision needed, and do not implement speculative behavior.
```

### Prompt آماده CI Repair

```text
Every hour, inspect open pull requests in rahbarbakhtiari-collab/tazkar whose head branch starts with agent/ and whose required checks are failing. Read the failing job logs and the pull request task. Fix only the concrete root cause, add a regression test when appropriate, run the relevant checks, and push the repair to the same branch. Do not weaken or delete valid tests. Track repair attempts in the PR body or a comment. After three failed attempts for the same root cause, label the PR blocked, remove automerge, add human-decision-required only if an actual external decision is needed, and provide the evidence.
```

## سطح B — Orchestrator مبتنی بر Codex SDK

برای اجرای event-driven واقعی، یک سرویس جدا لازم است که:

- webhookهای GitHub را دریافت کند؛
- Taskهای Codex SDK را ایجاد کند؛
- وضعیت و سقف retry را نگه دارد؛
- نتیجه را به Issue/PR بازگرداند؛
- policyهای دسترسی و secret را اعمال کند؛
- از ایجاد حلقه بی‌نهایت جلوگیری کند.

این سرویس خودش یک زیرپروژه است و بهتر است بعد از تثبیت دو Sprint اول ساخته شود. سطح A برای شروع کافی است، ولی کاملاً event-driven نیست و بر Automationهای دوره‌ای تکیه می‌کند.
