# دروازه‌های کیفیت خودکار تذکار

**شناسه:** `docs/17-AUTOMATED-QUALITY-GATES.md`  
**نسخه:** ۱٫۰  
**وضعیت:** فعال

## دروازه‌های اجباری هر PR

1. Composer validation و dependency install؛
2. PHP syntax؛
3. PHPCS / WordPress Coding Standards؛
4. PHPStan؛
5. PHPUnit و smoke tests؛
6. activation smoke؛
7. build ZIP؛
8. نصب ZIP روی WordPress واقعی؛
9. کنترل PR policy؛
10. secret pattern scan؛
11. `composer audit`؛
12. کنترل عدم تغییر فایل‌های حاکم بدون اعلام تصمیم انسانی.

## دروازه‌های افزوده بر اساس حوزه

| حوزه | آزمون افزوده |
|---|---|
| migration | activation تکراری، upgrade، prefix سفارشی، rollback |
| importer | فایل سالم/خراب/تکراری/بزرگ، formula injection، resume |
| REST | permission callback، schema، nonce/auth، rate limit |
| frontend | Playwright، axe، RTL، mobile viewport، visual regression |
| privacy | exporter، eraser، retention، no external requests |
| release | build reproducibility، checksum، install matrix، artifact manifest |

## قانون آزمون

عامل اجازه ندارد برای سبزشدن CI، آزمون معتبر را ضعیف یا حذف کند. تغییر آزمون باید همراه با توضیح تغییر رفتار مصوب و آزمون جایگزین باشد.

## ماتریس پایه

- PHP: 8.1، 8.3 و نسخه‌های پشتیبانی‌شده جدید؛
- WordPress: حداقل مصوب و آخرین نسخه پایدار؛
- Database: MySQL 8 و در RC یک اجرای MariaDB؛
- Prefix: `wp_` و prefix سفارشی؛
- build: دو بار ساخت و تطبیق SHA-256.

## شرط PASS

- هیچ check اجباری failed یا cancelled نباشد؛
- warningهای امنیتی بحرانی صفر باشند؛
- artifact ساخت موجود باشد؛
- گزارش عامل شامل فرمان‌ها و نتایج واقعی باشد.
