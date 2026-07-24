# وضعیت پروژه تذکار

**شناسه سند:** `docs/management/project-status.md`  
**نسخه:** ۱٫۵  
**تاریخ:** ۲۰۲۶/۰۷/۲۴  
**وضعیت:** فعال  
**مرحله فعلی:** اجرای خودکار Sprint 3 — پیش‌نمایش امن CSV

---

## خلاصه

- مخزن خصوصی `rahbarbakhtiari-collab/tazkar` منبع حقیقت کد و CI است.
- Sprint صفر، Sprint 1 و Sprint 2 با CI واقعی، بازبینی مستقل و auto-merge پایان یافته‌اند.
- نسخه پایدار توسعه Sprint 2 برابر `0.3.0-alpha.1` است.
- Issue شماره ۱۱، پیش‌نمایش امن و غیرماندگار CSV را اجرا می‌کند.
- نسخه نامزد Sprint 3 برابر `0.4.0-alpha.1` است.
- تست انسانی مرحله‌ای تا Release Candidate متوقف است.

## خروجی پایدار Sprint 2

- validator خالص PHP بدون تابع WordPress؛
- schema دقیق ۳۵ ستون قرارداد داده ۱٫۱؛
- اعتبارسنجی header، row و batch؛
- قواعد شرطی قرآن و حدیث؛
- کدهای ثابت خطا و هشدار؛
- fixtureهای معتبر و نامعتبر؛
- حفظ عین داده ورودی و متن عربی؛
- آزمون واقعی WordPress 7.0.2 و MySQL برای regression مدل محتوا.

## کار جاری Sprint 3

- صفحه مدیریت پیش‌نمایش CSV؛
- capability و nonce؛
- اعتبارسنجی upload، پسوند، MIME و اندازه؛
- BOM و UTF-8؛
- محدودیت ۱۰MB، ۵۰۰ ردیف و ۶۰ ستون؛
- نگاشت header مستقل از ترتیب؛
- اتصال parser به validator Sprint 2؛
- هشدار سلول فرمول‌نما بدون اجرا یا بازنویسی؛
- گزارش ردیف‌محور escape‌شده؛
- عدم ایجاد post، batch، import row یا فایل ماندگار.

## دروازه پایان Sprint 3

- PR Policy سبز؛
- PHP 8.1، 8.3، 8.4 و 8.5 سبز؛
- PHPCS، PHPStan و PHPUnit سبز؛
- آزمون‌های BOM، UTF-8، نقل‌قول، محدودیت منابع، MIME و formula-like سبز؛
- آزمون capability و nonce سبز؛
- آزمون WordPress/MySQL پیش‌نمایش بدون persistence سبز؛
- ZIP نسخه `0.4.0-alpha.1` ساخته شود؛
- PR با `automerge` ادغام شود؛
- Issue شماره ۱۱ با `PASS` بسته شود.

## مرحله بعد

پس از Sprint 3، Sprint 4 اجرای CSV import را با ساخت Draft، ثبت batch/row و کنترل duplicate آغاز می‌کند. XLSX، resume و rollback همچنان به Sprintهای بعدی تعلق دارند.

## سیاست انسانی

- تست انسانی بعدی فقط برای Release Candidate رسمی انجام می‌شود.
- تأیید نهایی علمی و دینی محتوا همچنان انسانی است.
- عامل فقط در تعارض اسناد، تصمیم محصول/حریم خصوصی/دینی، نیاز به secret یا سه شکست تکراری توقف می‌کند.
