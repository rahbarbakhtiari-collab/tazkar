# وضعیت پروژه تذکار

**شناسه سند:** `docs/management/project-status.md`  
**نسخه:** ۲٫۰  
**تاریخ:** ۲۰۲۶/۰۷/۲۸  
**وضعیت:** فعال  
**سطح پروژه:** `critical`  
**مرحله فعلی:** بازبینی مستقل نهایی Sprint 5 پیش از ادغام

---

## ۱. منبع حقیقت

- مخزن خصوصی `rahbarbakhtiari-collab/tazkar` منبع حقیقت کد، اسناد نسخه جاری، Issueها، PRها و شواهد CI است.
- وضعیت اجرایی از `main`، PR فعال و GitHub Actions استخراج می‌شود؛ گزارش‌های قدیمی‌تر فقط سابقه‌اند.
- خط مبنای نسخه WordPress باید از منبع رسمی بررسی و پیش از تغییر کد در اسناد ثبت شود.
- تصمیم صریح و جدید مالک محصول بر ارجاع‌های قدیمی‌تر و متعارض تقدم دارد.

## ۲. خلاصه اجرایی

- Sprintهای 0 تا 4 روی `main` ادغام شده‌اند.
- Issue شماره 17 دامنه Sprint 5 را تعریف می‌کند.
- Draft PR شماره 18 روی شاخه `agent/17-sprint-5-chunk-resume-rollback` باز است.
- نسخه هدف و نسخه فعلی شاخه Sprint 5 برابر `0.6.0-alpha.1` است.
- پیاده‌سازی کامل chunk، resume، lock، staging حداقلی، حذف payload و rollback امن در کد Production وجود دارد.
- ایرادهای اولیه P1 درباره نبود پردازش قطعه‌ای و نبود rollback بسته رفع شده‌اند.
- batch فعال هم‌زمان برای یک مدیر با قفل اتمی و کنترل وضعیت رد می‌شود.
- race میان process و rollback با بازخوانی وضعیت authoritative زیر batch lock بسته شده است.
- rollback فقط پس از پیش‌نمایش غیرمخرب و تأیید صریح مدیر اجرا می‌شود.
- duplicate detection و ساخت Draft میان تمام batchها و کاربران با قفل مشترک سایت سریالی شده است.
- در پیکربندی `EMPTY_TRASH_DAYS=0`، rollback پیش از هر mutation رد می‌شود و حذف دائمی رخ نمی‌دهد.
- اجرای نهایی کد روی head پاک `0aa830b421fc1d70221085311b0ad83662202ae7` همه گیت‌های CI و PR Policy را با موفقیت گذرانده است.
- PR تا بازبینی مستقل تازه روی head نهایی مستندی در حالت Draft می‌ماند.

## ۳. خروجی‌های Sprint 5

### ۳.۱ مهاجرت و داده

- schema نسخه 2 با migration تکرارپذیر؛
- آزمون مهاجرت داده‌دار از نسخه 1 به 2؛
- حفظ batch و rowهای موجود با پیشوند سفارشی پایگاه داده؛
- ثبت یک نتیجه منسجم برای هر ردیف منبع؛
- حفظ audit metadata بدون نگهداری payload بازیابی‌پذیر پس از نتیجه نهایی.

### ۳.۲ ورود قطعه‌ای و ادامه‌پذیری

- stage یک batch و ردیف‌های pending/invalid/skipped؛
- نگهداری فقط payload نرمال و allowlist‌شده ردیف pending؛
- حذف فایل آپلودشده پس از staging؛
- chunk پیش‌فرض 25 و سقف سخت 100 ردیف؛
- بودجه زمانی 20 ثانیه برای حلقه هر درخواست؛
- cursor، row selection، counts، state و lock کاملاً server-owned؛
- lock پردازش با انقضای 120 ثانیه؛
- قفل advisory مجزای هر کاربر برای ایجاد batch و جلوگیری اتمی از چند batch فعال هم‌زمان؛
- قفل advisory مشترک سایت برای سریالی‌کردن duplicate-check، ساخت post و نهایی‌سازی row در همه batchها؛
- رد batch جدید هنگامی که همان مدیر batch با وضعیت staged، processing یا rollback_processing دارد؛
- recovery ردیف processing پس از وقفه؛
- بازخوانی وضعیت batch پس از گرفتن lock و پیش از claim ردیف‌ها؛
- process و resume تکرارپذیر بدون post یا outcome تکراری؛
- حفظ دقیق `arabic_text`؛
- ساخت فقط `tazkar_item` با وضعیت Draft و `needs_review`.

### ۳.۳ بازگردانی امن

- rollback فقط برای ردیف‌های همان batch و مالک احراز‌شده؛
- پیش‌نمایش غیرمخرب پیش از rollback؛
- نمایش تعداد Draftهای واجد شرایط، ردیف‌های در انتظار، موارد منتشرشده، تأییدشده، ویرایش‌شده، گم‌شده، متعلق به مالک دیگر و تعامل‌های ثبت‌شده؛
- الزام تأیید صریح مدیر و nonce اختصاصی پس از پیش‌نمایش؛
- لغو pending/processing و حذف payload آن‌ها؛
- انتقال Draft واجد شرایط به Trash، بدون حذف دائمی؛
- مسدودکردن کامل rollback هنگام غیرفعال‌بودن WordPress Trash؛
- دفاع مستقل در Writer برای جلوگیری از fallback مخرب `wp_trash_post()`؛
- بررسی post type، batch UUID، row ID و snapshot زمان import؛
- حفاظت از محتوای منتشرشده، تأییدشده، ویرایش‌شده، گم‌شده یا متعلق به batch دیگر؛
- ثبت conflict و حفظ post متعارض؛
- idempotency در اجرای مجدد rollback.

### ۳.۴ پنل و امنیت

- اقدام‌های مدیریتی جداگانه برای start، process، resume، rollback preview و rollback تأییدشده؛
- capability الزامی `import_tazkar_items`؛
- nonce اختصاصی برای هر عملیات و batch؛
- اعتبارسنجی UUID و resolution مالکیت در سمت سرور؛
- عدم اعتماد به cursor، state، row ID، post ID یا شمارنده ارسالی کاربر؛
- نمایش فارسی و escape‌شده وضعیت batch، شمارنده‌ها، پیش‌نمایش rollback و نتیجه ردیف‌ها؛
- نمایش هشدار و حذف دکمه اجرا در صورت غیرفعال‌بودن Trash؛
- عدم REST write عمومی، telemetry، درخواست بیرونی، daemon یا نگهداری فایل upload.

## ۴. شواهد آزمون

اجرای معتبر نهایی کد شامل موارد زیر موفق بوده است:

- CI run شماره `30366482657`، run number `265`؛
- PR Policy run شماره `30366482414`، run number `114`؛
- PHP 8.1، 8.3، 8.4 و 8.5؛
- validation مخزن و fixtureها؛
- Composer validate و dependency audit؛
- secret scan؛
- lint، PHPCS، PHPStan و PHPUnit؛
- آزمون رد دومین batch فعال برای یک کاربر؛
- آزمون بازخوانی وضعیت batch پس از lock؛
- آزمون انتظار امن هنگام در دسترس نبودن قفل مشترک deduplication؛
- آزمون غیرمخرب بودن rollback preview و طبقه‌بندی وضعیت‌ها؛
- آزمون الزام تأیید صریح rollback؛
- آزمون رد rollback هنگام غیرفعال‌بودن WordPress Trash و عدم mutation؛
- activation smoke؛
- build قطعی ZIP؛
- نصب و فعال‌سازی واقعی روی WordPress `7.0.2` و MySQL 8؛
- غیرفعال‌سازی و فعال‌سازی مجدد بدون حذف داده؛
- migration واقعی v1→v2 و اجرای دوباره آن؛
- regression Sprintهای 0 تا 4؛
- سناریوی 63 ردیفی Sprint 5 در چند درخواست؛
- interruption/resume، عدم duplicate، حفظ دقیق متن عربی، حذف payload، جلوگیری از batch فعال دوم، rollback preview، شمارش interaction و rollback تعارض‌محور؛
- PR Policy.

گزارش تفصیلی در `docs/project/23-SPRINT-05-CHUNKED-IMPORT-IMPLEMENTATION-REPORT.md` ثبت شده است.

## ۵. خط مبنای فنی لازم‌الاجرا

- حداقل PHP: `8.1`؛
- حداقل WordPress: `7.0.2`؛
- نسخه رسمی پایدار و نسخه آزمون‌شده: `7.0.2`؛
- نسخه‌های Alpha، Beta و RC معیار Production نیستند؛
- سازگاری با WordPress پایین‌تر از `7.0.2` هدف نیست؛
- مجوز: `GPL-2.0-or-later`؛
- نویسنده: `رهبر بختیاری`؛
- Slug و Text Domain: `tazkar`؛
- رابط اصلی فارسی، RTL و ترجمه‌پذیر؛
- بدون Telemetry و بارگذاری مستقیم CDN؛
- حفظ داده در Deactivation و Uninstall پیش‌فرض.

هر ارجاع قدیمی به WordPress `7.2` یا حداقل `6.8` منسوخ است. هدر افزونه، readme و CI در Sprint 5 به `7.0.2` همگام شده‌اند.

## ۶. خطرها و محدودیت‌های واقعی باقی‌مانده

- آزمون اکتشافی انسانی تا Release Candidate به تعویق مانده است.
- staging اولیه تحت محدودیت موجود 10 MB، 500 ردیف و 60 ستون در یک درخواست انجام می‌شود.
- hookهای ثالث بسیار کند باید در مرحله Release Candidate از نظر اثر بر lock و زمان اجرا مشاهده شوند.
- قفل مشترک deduplication عمداً throughput موازی import را کاهش می‌دهد تا یکتایی و سازگاری داده حفظ شود.
- conflictهای rollback عمداً برای تصمیم مدیر باقی می‌مانند و هیچ حذف دائمی انجام نمی‌شود.
- XLSX، metabox ویرایشی و تأیید یا انتشار گروهی خارج از Sprint 5 هستند.

## ۷. دروازه باقی‌مانده Sprint 5

1. موفقیت CI روی head مستندی نهایی؛
2. پاسخ و بستن threadهای review رفع‌شده؛
3. بازبینی مستقل تازه معماری و امنیت روی head نهایی؛
4. رفع finding عملیاتی احتمالی، فقط در صورت وجود؛
5. خروج PR شماره 18 از Draft و ادغام طبق سیاست مخزن؛
6. همگام‌سازی `main` و بستن Issue شماره 17.

هیچ اقدام انسانی از مالک محصول در این مرحله لازم نیست.

## ۸. Backlog نزدیک

- Issue شماره 13: رابط ساختاریافته و امن ویرایش `tazkar_item`؛
- Issue شماره 14: ستون‌ها و فیلترهای مدیریتی بازبینی؛
- Sprint 6: XLSX با وابستگی مجاز و پیشوندگذاری‌شده؛
- Sprint 7: صف بازبینی، تأیید تکی و گروهی و جدایی تأیید از انتشار.

پیش از Sprint بعدی، جای دقیق Issues #13 و #14 در برنامه Sprint 7 باید در اسناد محصول یکپارچه شود.
