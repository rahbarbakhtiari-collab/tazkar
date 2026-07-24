# وضعیت پروژه تذکار

**نسخه:** ۱٫۳  
**تاریخ:** ۲۰۲۶/۰۷/۲۴  
**وضعیت:** فعال  
**مرحله فعلی:** تکمیل زیرساخت توسعه خودکار و آماده‌سازی Sprint 1

## وضعیت قطعی

- مخزن GitHub: `rahbarbakhtiari-collab/tazkar`؛
- Sprint صفر در Pull Request شماره ۱ با CI واقعی ادغام شده است؛
- merge commit خط مبنا: `56b6598048eba8e3539e1a2e61ccf84a9120b4cc`؛
- PHP 8.1، 8.3، 8.4 و 8.5 سبز؛
- نصب، فعال‌سازی، غیرفعال‌سازی و فعال‌سازی مجدد روی WordPress 7.0.2 و MySQL 8 سبز؛
- تست انسانی میانی حذف شده است؛
- پذیرش انسانی فقط روی artifact رسمی Release Candidate انجام می‌شود؛
- تأیید علمی نهایی محتوای دینی همچنان انسانی است.

## کار جاری

Issue شماره ۲ اسناد حاکم، fixtureهای نسخه ۱٫۱، workflow شبانه، workflow Release Candidate، قالب Issue/PR و راهنمای Orchestrator را به مخزن اضافه می‌کند.

## کار بعدی

پس از ادغام Issue شماره ۲:

1. تعریف Issue اجرایی Sprint 1؛
2. پیاده‌سازی CPT، taxonomy و metaهای ثبت‌شده؛
3. اجرای CI و بازبینی عامل مستقل؛
4. ادامه Sprintها بدون تست انسانی تا Release Candidate.

## مانع بیرونی باقی‌مانده

GitHub Actions و policyها فعال‌اند، اما فراخوانی خودکار Codex از Issue/CI نیازمند یک‌بار اتصال repository به Codex Environment و ساخت Automationهای Issue Dispatcher و CI Repair است. تا انجام آن، این گفتگو می‌تواند نقش orchestrator فعال را انجام دهد، اما فرایند دائمی و بدون جلسه نیست.
