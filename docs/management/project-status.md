# وضعیت پروژه تذکار

**شناسه سند:** `docs/management/project-status.md`  
**نسخه:** ۱٫۳  
**تاریخ:** ۲۰۲۶/۰۷/۲۴  
**وضعیت:** فعال  
**مرحله فعلی:** اجرای خودکار Sprint 1 — مدل محتوا

---

## خلاصه

- مخزن خصوصی `rahbarbakhtiari-collab/tazkar` منبع حقیقت کد و CI است.
- Sprint صفر با CI واقعی، WordPress 7.0.2، MySQL و یک نصب موفق Local پایان یافته است.
- تست انسانی مرحله‌ای تا Release Candidate متوقف است.
- Issue شماره ۴، مدل محتوای Sprint 1 را اجرا می‌کند.
- نسخه نامزد Sprint 1 برابر `0.2.0-alpha.1` است.

## خروجی پایدار Sprint صفر

- Bootstrap و autoload؛
- بررسی WordPress 6.8+ و PHP 8.1+؛
- capabilityهای پایه؛
- migration نسخه‌دار و چهار جدول عملیاتی؛
- صفحه وضعیت مدیریت؛
- build قطعی ZIP و checksum؛
- PHPCS، PHPStan، PHPUnit و GitHub Actions؛
- نصب، فعال‌سازی، غیرفعال‌سازی و فعال‌سازی مجدد روی WordPress/MySQL.

## کار جاری Sprint 1

- ثبت CPT `tazkar_item`؛
- ثبت taxonomyهای `tazkar_topic` و `tazkar_tag`؛
- ثبت ۲۹ meta متناظر با قرارداد داده ۱٫۱؛
- allowlist صریح REST؛
- حفظ عین متن عربی و SHA-256 داخلی؛
- بازگرداندن `approved` به `needs_recheck` پس از تغییر فیلد حساس؛
- آزمون واحد و smoke واقعی WordPress/MySQL.

## دروازه پایان Sprint 1

Sprint 1 فقط پس از این موارد Done است:

- PR Policy سبز؛
- PHP 8.1، 8.3، 8.4 و 8.5 سبز؛
- PHPCS، PHPStan و PHPUnit سبز؛
- آزمون WordPress/MySQL مدل محتوا سبز؛
- ZIP نسخه `0.2.0-alpha.1` ساخته شود؛
- PR با `automerge` بدون تصمیم انسانی ادغام شود؛
- Issue شماره ۴ با گزارش `PASS` بسته شود.

## مرحله بعد

پس از پایان Sprint 1، Sprint 2 به validator مستقل قرارداد داده ۱٫۱ اختصاص دارد. درون‌ریز CSV هنوز آغاز نمی‌شود.

## سیاست انسانی

- تست انسانی بعدی فقط برای Release Candidate رسمی انجام می‌شود.
- تأیید نهایی علمی و دینی محتوا همچنان انسانی است.
- عامل فقط در تعارض اسناد، تصمیم محصول/حریم خصوصی/دینی، نیاز به secret یا سه شکست تکراری توقف می‌کند.
