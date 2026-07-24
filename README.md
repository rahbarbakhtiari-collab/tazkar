# Tazkar – Sprint 0

نخستین اسکلت نصب‌شدنی افزونه تذکار.

## نیازمندی‌ها

- WordPress 6.8+
- PHP 8.1+

## بررسی محلی بدون Composer

```bash
bash bin/lint.sh
php tests/smoke.php
php tests/activation-smoke.php
bash bin/build.sh
```

## بررسی کامل با Composer

```bash
composer install
composer lint
composer test
vendor/bin/phpcs
vendor/bin/phpstan analyse
vendor/bin/phpunit
composer build
```

## محدوده این نسخه

این نسخه فقط Bootstrap، migration، capabilityها و صفحه وضعیت مدیریت را دارد. CPT، درون‌ریز و frontend در Sprintهای بعدی ساخته می‌شوند.

## سیاست داده

- Deactivation هیچ داده دائمی را حذف نمی‌کند.
- Uninstall نیز به‌صورت پیش‌فرض داده را حفظ می‌کند.
- حذف کامل فقط با گزینه صریح `tazkar_delete_data_on_uninstall=yes` انجام می‌شود؛ رابط این گزینه هنوز ساخته نشده است.

## مستندات توسعه

- قرارداد Codex: [`AGENTS.md`](AGENTS.md)
- راه‌اندازی GitHub: [`docs/development/GITHUB-BOOTSTRAP.md`](docs/development/GITHUB-BOOTSTRAP.md)
- نصب و تست Local: [`docs/development/LOCAL-SETUP-AND-HUMAN-TEST.md`](docs/development/LOCAL-SETUP-AND-HUMAN-TEST.md)
- روال همکاری Codex: [`docs/development/CODEX-COLLABORATION-WORKFLOW.md`](docs/development/CODEX-COLLABORATION-WORKFLOW.md)
- نخستین Task: [`tasks/CODEX-00-SPRINT-0-BASELINE-AUDIT.md`](tasks/CODEX-00-SPRINT-0-BASELINE-AUDIT.md)


## Autonomous development

Intermediate human testing is disabled. Agent tasks are defined through GitHub Issues, implemented on `agent/*` branches, validated by required checks, independently reviewed, and eligible for controlled auto-merge. Human acceptance is reserved for an official Release Candidate. See:

- `docs/project/16-AUTONOMOUS-DEVELOPMENT-POLICY.md`
- `docs/project/17-AUTOMATED-QUALITY-GATES.md`
- `docs/development/ORCHESTRATOR-SETUP.md`
