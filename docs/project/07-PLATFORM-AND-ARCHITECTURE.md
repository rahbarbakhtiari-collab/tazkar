# بستر و معماری فنی تذکار

**شناسه سند:** `docs/07-PLATFORM-AND-ARCHITECTURE.md`  
**نسخه:** ۰٫۱  
**تاریخ:** ۱۴۰۵/۰۵/۰۲ — ۲۰۲۶/۰۷/۲۴  
**وضعیت:** پیشنهادی و نیازمند تأیید  
**دامنه:** افزونه وردپرس MVP وب

---

## ۱. هدف سند

این سند معماری سطح‌بالا، مرز ماژول‌ها، مدل ذخیره‌سازی، قراردادهای داخلی و الزامات فنی افزونه «تذکار» را تعیین می‌کند.

این سند وارد جزئیات پیاده‌سازی هر کلاس نمی‌شود؛ اما باید به‌اندازه‌ای دقیق باشد که توسعه‌دهنده بتواند ساختار پروژه، مسئولیت اجزا، وابستگی‌ها، مسیر داده و معیارهای فنی را بدون حدس‌زدن آغاز کند.

---

## ۲. تصمیم معماری اصلی

نسخه نخست تذکار به‌صورت یک **افزونه واحد اما ماژولار وردپرس** ساخته می‌شود.

```text
ChatGPT + Google Sheets
          ↓ CSV / XLSX
┌──────────────────────────────────┐
│         Tazkar WordPress         │
│                                  │
│ Content ─ Import ─ Review        │
│    │         │        │          │
│ Presentation ─ Interaction       │
│           │                      │
│        REST API                  │
└──────────────────────────────────┘
          ↓
صفحات وب و کانال‌های آینده
```

اصول معماری:

- وردپرس مرجع نهایی محتوای منتشرشده است.
- تولید هوش مصنوعی خارج از افزونه انجام می‌شود.
- محتوا از طراحی و HTML جدا نگهداری می‌شود.
- قابلیت‌های ایتا، تلگرام، اندروید و مرورگر به هسته وابسته‌اند؛ ولی هسته به آن‌ها وابسته نیست.
- API و سرویس‌های داخلی باید برای افزودن کانال‌های آینده پایدار باشند.
- افزونه در MVP هیچ تماس اجباری با سرویس خارجی ندارد.

---

## ۳. نسخه‌های هدف

### پیشنهاد سازگاری

| مؤلفه | حداقل پیشنهادی | توصیه‌شده | دامنه آزمون MVP |
|---|---:|---:|---:|
| WordPress | ۶٫۸ | آخرین نسخه پایدار | ۶٫۸ تا ۷٫۰٫۲ |
| PHP | ۸٫۱ | ۸٫۳ یا بالاتر | ۸٫۱ تا ۸٫۵ |
| MySQL | مطابق وردپرس هدف | ۸٫۰ یا بالاتر | نسخه‌های محیط آزمون |
| MariaDB | مطابق وردپرس هدف | ۱۰٫۱۱ یا بالاتر | نسخه‌های محیط آزمون |
| HTTPS | الزامی برای سایت اصلی | الزامی | فعال |

### دلیل انتخاب

- پشتیبانی از نسخه‌های بسیار قدیمی، هزینه آزمون و پیچیدگی کد را افزایش می‌دهد.
- PHP ۸٫۱ امکان تایپ‌دهی و معماری مدرن‌تری فراهم می‌کند.
- نسخه عمومی افزونه باید پیش از انتشار، با آخرین نسخه پایدار وردپرس دوباره آزمایش شود.
- نسخه آزمایشی WordPress 7.1 نباید در فیلد `Tested up to` اعلام شود تا زمانی که نسخه پایدار منتشر و آزمون کامل انجام شود.

---

## ۴. محدودیت‌های محیطی MVP

- نصب تک‌سایتی وردپرس هدف اصلی است.
- Network Activation در Multisite در MVP رسماً پشتیبانی نمی‌شود.
- افزونه نباید به Elementor، LearnDash، WooCommerce یا قالب خاصی وابسته باشد.
- افزونه باید با قالب‌های کلاسیک و بلوکی رایج کار کند.
- پردازش فایل در محدوده محتوای ۵۰ تا ۱۵۰ ردیف بهینه می‌شود؛ طراحی باید امکان افزایش آینده را حفظ کند.
- منابع PDF و Word داخل افزونه پردازش نمی‌شوند.

---

## ۵. نام‌ها و شناسه‌های فنی

| مورد | مقدار پیشنهادی |
|---|---|
| نام کاری افزونه | Tazkar |
| Slug | `tazkar` |
| Text Domain | `tazkar` |
| PHP Namespace | `Tazkar` |
| پیشوند توابع عمومی | `tazkar_` |
| نوع نوشته | `tazkar_item` |
| موضوع‌ها | `tazkar_topic` |
| برچسب‌ها | `tazkar_tag` |
| REST Namespace | `tazkar/v1` |
| Option Prefix | `tazkar_` |
| Database Table Prefix | `{$wpdb->prefix}tazkar_` |
| CSS Prefix | `tazkar-` |

آزادبودن نهایی نام و slug پیش از ارسال به مخزن دوباره بررسی می‌شود.

---

## ۶. ساختار پوشه پیشنهادی

```text
tazkar/
├── tazkar.php
├── uninstall.php
├── readme.txt
├── LICENSE
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
├── src/
│   ├── Bootstrap.php
│   ├── Core/
│   ├── Content/
│   ├── Import/
│   ├── Review/
│   ├── Presentation/
│   ├── Interaction/
│   ├── Rest/
│   ├── Admin/
│   ├── Privacy/
│   └── Infrastructure/
├── templates/
│   ├── single-tazkar_item.php
│   ├── archive-tazkar_item.php
│   └── parts/
├── assets/
│   ├── src/
│   └── build/
├── languages/
├── vendor-prefixed/
├── tests/
│   ├── unit/
│   ├── integration/
│   ├── e2e/
│   └── fixtures/
└── tools/
```

### قواعد

- فایل اصلی افزونه فقط Bootstrap و بررسی نیازمندی‌ها را انجام دهد.
- منطق کسب‌وکار در فایل اصلی یا فایل‌های نمایشی نوشته نشود.
- کلاس‌ها با PSR-4 بارگذاری شوند.
- وابستگی‌های Composer در بسته انتشار وجود داشته باشند؛ کاربر نهایی نباید Composer اجرا کند.
- کد منبع فایل‌های build و دستور ساخت برای بررسی مخزن نگهداری شوند.

---

## ۷. روش Bootstrap

یک Bootstrap مرکزی مسئول این امور است:

1. بررسی نسخه WordPress و PHP؛
2. بارگذاری autoloader؛
3. ثبت Service Providerهای ماژول‌ها؛
4. اتصال hookها؛
5. اجرای migrationهای لازم؛
6. فعال‌سازی ماژول‌ها بر اساس تنظیمات.

از Container پیچیده یا Service Locator سراسری در MVP استفاده نمی‌شود. وابستگی‌ها به‌صورت constructor injection ساده یا factory محدود تزریق می‌شوند.

---

## ۸. ماژول‌های اصلی

### ۸.۱. Core

مسئول:

- ثابت‌ها و نسخه افزونه؛
- activation و deactivation؛
- migration پایگاه داده؛
- تنظیمات عمومی؛
- capabilityها؛
- ثبت رخدادها و خطاها؛
- هماهنگی ماژول‌ها.

Core نباید منطق جزئی درون‌ریز یا نمایش را در خود نگه دارد.

### ۸.۲. Content

مسئول:

- ثبت `tazkar_item`؛
- ثبت taxonomyها؛
- ثبت فیلدهای meta؛
- قواعد اعتبارسنجی محتوایی در سطح وردپرس؛
- تشخیص فیلد حساس؛
- بازگرداندن وضعیت به `needs_recheck` پس از تغییر حساس؛
- محاسبه اثر انگشت متن و کلیدهای تکرار.

پیشنهاد تنظیم نوع نوشته:

```php
public              => true
show_ui             => true
show_in_rest        => true
has_archive         => true
rewrite             => [ 'slug' => 'reminders' ]
supports            => [ 'title', 'revisions', 'author' ]
exclude_from_search => false
```

`post_content` مرجع داده اصلی نیست و HTML کارت در آن ذخیره نمی‌شود.

### ۸.۳. Import

مسئول:

- بارگذاری CSV/XLSX؛
- بررسی نسخه قرارداد؛
- نگاشت ستون‌ها؛
- اعتبارسنجی ردیف؛
- تشخیص تکرار؛
- پیش‌نمایش؛
- ورود chunked؛
- گزارش خطا؛
- ادامه عملیات نیمه‌تمام؛
- بازگردانی بسته.

#### انتخاب کتابخانه XLSX

پیشنهاد نهایی MVP:

> **OpenSpout نسخه ۴، با namespace پیشوندگذاری‌شده در build انتشار**

دلایل:

- خواندن streaming و مصرف حافظه پایین؛
- پشتیبانی از CSV و XLSX؛
- مناسب‌تر از کتابخانه‌های سنگین برای کاربرد درون‌ریز؛
- مجوزهای MIT و Apache-2.0 سازگار با توزیع افزونه.

#### جلوگیری از تداخل وابستگی

وابستگی‌های ثالث در build عمومی با ابزاری مانند PHP-Scoper یا Strauss به namespace اختصاصی تذکار منتقل می‌شوند. خروجی پیشوندگذاری‌شده همراه افزونه منتشر می‌شود و کد منبع و روش build مستند می‌ماند.

#### اجرای ورود

برای ۵۰ تا ۱۵۰ ردیف:

- فایل در سرور بارگذاری و اعتبارسنجی می‌شود؛
- پیش‌نمایش بدون ذخیره محتوا ساخته می‌شود؛
- ورود با درخواست‌های chunked، مثلاً ۲۰ یا ۲۵ ردیف، اجرا می‌شود؛
- cursor و وضعیت هر chunk در جدول بسته ثبت می‌شود؛
- صفحه مدیریت پیشرفت را نمایش می‌دهد؛
- قطع مرورگر باعث ناشناخته‌ماندن وضعیت نمی‌شود.

در MVP از WP-Cron به‌عنوان موتور اصلی ورود استفاده نمی‌شود.

### ۸.۴. Review

مسئول:

- صف‌های بررسی؛
- نمونه‌برداری؛
- تأیید تکی؛
- تأیید گروهی؛
- رد؛
- بازبینی مجدد؛
- ثبت سابقه تصمیم؛
- جداسازی تأیید از انتشار.

### ۸.۵. Presentation

مسئول:

- template loader؛
- صفحه تکی؛
- آرشیو و موضوعات؛
- کارت‌های مرتبط؛
- محتوای تصادفی؛
- متادیتای پایه اشتراک‌گذاری؛
- بارگذاری شرطی CSS/JS؛
- حالت روشن و تیره؛
- accessibility.

اولویت قالب:

1. `your-theme/tazkar/single-tazkar_item.php`
2. قالب داخلی افزونه
3. fallback استاندارد وردپرس

### ۸.۶. Interaction

مسئول:

- علاقه‌مندی؛
- بازخورد؛
- گزارش اشکال؛
- اشتراک‌گذاری؛
- شمارش‌های پایه و غیرشخصی.

علاقه‌مندی مهمان در `localStorage` ذخیره می‌شود.

برای کاربران واردشده، پیشنهاد معماری استفاده از جدول رابطه‌ای اختصاصی با کلید یکتای `(user_id, post_id)` است؛ این روش از آرایه سریال‌شده User Meta برای query، حذف و توسعه چندکاناله مناسب‌تر است.

### ۸.۷. REST API

مسئول:

- ارائه داده عمومی منتشرشده؛
- endpoint تصادفی؛
- endpoint مرتبط؛
- ثبت بازخورد؛
- مدیریت علاقه‌مندی کاربر واردشده؛
- schema و permission callback.

مسیرهای پیشنهادی:

```text
GET /wp-json/wp/v2/tazkar-items
GET /wp-json/tazkar/v1/random
GET /wp-json/tazkar/v1/related/{id}
GET /wp-json/tazkar/v1/topics
POST /wp-json/tazkar/v1/feedback
```

API عمومی فقط محتوای `publish + approved` و فیلدهای نمایشی allowlist‌شده را ارائه می‌کند.

### ۸.۸. Admin

- صفحه‌های ساده با PHP و کامپوننت‌های بومی وردپرس؛
- استفاده محدود از `@wordpress/components` برای تعاملات پیچیده؛
- عدم ساخت SPA بزرگ در MVP؛
- ارتباط async از طریق REST API و `@wordpress/api-fetch`؛
- همه رشته‌ها ترجمه‌پذیر.

### ۸.۹. Privacy

- متن پیشنهادی سیاست حریم خصوصی؛
- exporter و eraser داده شخصی؛
- نگهداری و پاک‌سازی دوره‌ای؛
- تنظیم حذف داده هنگام uninstall.

---

## ۹. مدل ذخیره‌سازی

### ۹.۱. جداول استاندارد وردپرس

- `wp_posts`: عنوان، slug، وضعیت، تاریخ، author و revisions.
- `wp_postmeta`: فیلدهای ساختاریافته محتوا و مدیریت.
- taxonomy tables: موضوع‌ها و برچسب‌ها.

هر meta با `register_post_meta()`، نوع داده، `sanitize_callback`، `auth_callback` و schema ثبت می‌شود. فقط فیلدهای عمومی لازم `show_in_rest` خواهند داشت.

### ۹.۲. جداول اختصاصی پیشنهادی

#### `wp_tazkar_import_batches`

شناسه بسته، hash فایل، نسخه قرارداد، status، شمارنده‌ها، cursor، کاربر و زمان‌ها.

#### `wp_tazkar_import_rows`

شماره ردیف، record_id، نتیجه، post_id، error/warning محدود و hash داده.

#### `wp_tazkar_feedback`

post_id، user_id اختیاری، نوع، دسته، پیام محدود، status، rate key و زمان‌ها.

#### `wp_tazkar_favorites`

user_id، post_id و created_at با کلید یکتای مرکب.

داده خام کامل فایل در جدول دائمی نگهداری نمی‌شود.

---

## ۱۰. migration و نسخه schema

- نسخه در `tazkar_db_version` ذخیره می‌شود.
- جدول‌ها با `dbDelta()` ساخته می‌شوند.
- migrationها idempotent هستند.
- migration طولانی در frontend اجرا نمی‌شود.
- downgrade خودکار schema در MVP پشتیبانی نمی‌شود.
- پیش از migration مخرب، backup و روش بازگشت لازم است.

---

## ۱۱. وضعیت‌های محتوا

```text
extracted
needs_review
approved
rejected
needs_recheck
```

API عمومی فقط ترکیب `approved + publish` را ارائه می‌دهد.

تغییر متن عربی، ترجمه، مأخذ، گوینده یا مشخصات آیه پس از تأیید، وضعیت را به `needs_recheck` بازمی‌گرداند.

---

## ۱۲. سرویس‌های اصلی

- `ItemRepository`
- `ImportBatchRepository`
- `DuplicateDetector`
- `RowValidator`
- `ReviewService`
- `PublicationService`
- `TemplateLoader`
- `RelatedItemsService`
- `RandomItemService`
- `FeedbackService`
- `FavoriteService`
- `PrivacyService`

SQL مستقیم روی جدول‌های اختصاصی فقط در Repositoryها مجاز است.

---

## ۱۳. hookهای توسعه

Actionهای نمونه:

```text
tazkar/import/batch_created
tazkar/import/row_imported
tazkar/item/approved
tazkar/item/published
tazkar/item/needs_recheck
tazkar/feedback/created
```

Filterهای نمونه:

```text
tazkar/item/public_payload
tazkar/template/path
tazkar/related/query_args
tazkar/card/view_model
tazkar/import/max_rows
```

نام hookهای عمومی پیش از نسخه ۱٫۰ تثبیت می‌شوند.

---

## ۱۴. سئو و سازگاری

- صفحات server-rendered هستند.
- CPT در sitemap وردپرس قرار می‌گیرد.
- افزونه با ابزارهای سئو تداخل ایجاد نمی‌کند.
- در حضور افزونه سئو، از hookهای آن استفاده می‌شود.
- در نبود آن، fallback محدود برای canonical، description و Open Graph وجود دارد.
- سیستم کامل سئوی موازی ساخته نمی‌شود.

---

## ۱۵. عملکرد

### frontend

- assetها فقط در صفحات مرتبط؛
- query مرتبط محدود و cache‌شده؛
- پرهیز از `ORDER BY RAND()` در بانک بزرگ؛
- عدم درخواست خارجی در بارگذاری صفحه.

### مدیریت

- صفحه‌بندی؛
- import chunked؛
- گزارش‌های محدود و قابل دانلود؛
- index پایگاه داده برای status، batch، post و user.

---

## ۱۶. ابزارهای توسعه

- Composer
- WordPress Coding Standards
- PHP_CodeSniffer
- PHPStan
- PHPUnit و WordPress test suite
- ESLint و Stylelint
- Playwright
- Plugin Check
- اسکن مجوز و vulnerability وابستگی‌ها

مراحل CI: syntax، standards، static analysis، test، build و آزمون نصب بسته.

---

## ۱۷. بسته انتشار

باید شامل کد، build نهایی، dependency پیشوندگذاری‌شده، readme، مجوزها، POT و uninstall باشد.

نباید شامل `.env`، کلید API، node_modules، cache توسعه، فونت تجاری یا فایل منبع دارای محدودیت انتشار باشد.

---

## ۱۸. حذف افزونه

غیرفعال‌سازی داده را حذف نمی‌کند.

در uninstall نیز پیش‌فرض حفظ داده است. حذف کامل فقط با opt-in قبلی و هشدار روشن انجام می‌شود.

---

## ۱۹. کانال‌های آینده

Connector ایتا یا سایر کانال‌ها:

- مستقیم به جداول دسترسی ندارند؛
- از API یا سرویس عمومی استفاده می‌کنند؛
- رضایت و تنظیمات کانال را مستقل نگه می‌دارند؛
- خرابی آن‌ها هسته وب را مختل نمی‌کند.

---

## ۲۰. تصمیم‌های پیشنهادی برای تصویب

1. حداقل WordPress برابر ۶٫۸ باشد.
2. حداقل PHP برابر ۸٫۱ و PHP توصیه‌شده ۸٫۳ باشد.
3. OpenSpout 4 برای XLSX انتخاب شود.
4. وابستگی‌های ثالث در build پیشوندگذاری شوند.
5. محتوا در CPT/meta و عملیات گروهی در جداول اختصاصی ذخیره شوند.
6. علاقه‌مندی کاربران واردشده در جدول رابطه‌ای مستقل ذخیره شود.
7. ورود فایل با درخواست‌های chunked و قابل ادامه انجام شود.
8. Multisite Network Activation خارج از MVP باشد.

---

## ۲۱. منابع رسمی مبنا

- WordPress Plugin Handbook
- WordPress REST API Handbook
- WordPress Plugin Directory Guidelines
- WordPress Requirements
- WordPress PHP Compatibility documentation
- OpenSpout official repository and package metadata
