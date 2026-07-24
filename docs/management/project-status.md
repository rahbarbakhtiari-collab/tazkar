# وضعیت پروژه تذکار

**شناسه سند:** `docs/management/project-status.md`  
**نسخه:** ۱٫۴  
**تاریخ:** ۲۰۲۶/۰۷/۲۴  
**وضعیت:** فعال  
**مرحله فعلی:** اجرای خودکار Sprint 2 — validator قرارداد داده

---

## خلاصه

- مخزن خصوصی `rahbarbakhtiari-collab/tazkar` منبع حقیقت کد و CI است.
- Sprint صفر و Sprint 1 با CI واقعی و auto-merge پایان یافته‌اند.
- نسخه پایدار توسعه Sprint 1 برابر `0.2.0-alpha.1` است.
- Issue شماره ۹، validator مستقل قرارداد داده ۱٫۱ را اجرا می‌کند.
- نسخه نامزد Sprint 2 برابر `0.3.0-alpha.1` است.
- تست انسانی مرحله‌ای تا Release Candidate متوقف است.

## خروجی پایدار Sprint 1

- CPT `tazkar_item` و taxonomyها؛
- ۲۹ meta قرارداد داده؛
- allowlist عمومی REST؛
- حفظ عین متن عربی و SHA-256 داخلی؛
- تغییر فیلد حساس: `needs_recheck` و بازگشت خودکار به Draft؛
- آزمون واقعی WordPress 7.0.2 و MySQL.

## کار جاری Sprint 2

- validator خالص PHP بدون تابع WordPress؛
- schema دقیق ۳۵ ستون؛
- اعتبارسنجی header، row و batch؛
- قواعد قرآن و حدیث؛
- خطا و هشدار با کد ثابت؛
- fixtureهای معتبر و نامعتبر؛
- حفظ عین داده ورودی.

## دروازه پایان Sprint 2

- PR Policy سبز؛
- PHP 8.1، 8.3، 8.4 و 8.5 سبز؛
- PHPCS، PHPStan و PHPUnit سبز؛
- تمام fixtureها و خانواده‌های خطا/هشدار سبز؛
- آزمون WordPress/MySQL Sprint 1 همچنان سبز؛
- ZIP نسخه `0.3.0-alpha.1` ساخته شود؛
- PR با `automerge` ادغام شود؛
- Issue شماره ۹ با `PASS` بسته شود.

## مرحله بعد

پس از Sprint 2، Sprint 3 فقط پیش‌نمایش CSV و گزارش ردیف‌محور را می‌سازد. ساخت post در Sprint 3 خارج از محدوده است.

## سیاست انسانی

- تست انسانی بعدی فقط برای Release Candidate رسمی انجام می‌شود.
- تأیید نهایی علمی و دینی محتوا همچنان انسانی است.
- عامل فقط در تعارض اسناد، تصمیم محصول/حریم خصوصی/دینی، نیاز به secret یا سه شکست تکراری توقف می‌کند.
