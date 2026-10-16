=== Dhikra ===
Tags: reminders, quran, hadith, persian, rtl
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.6.29
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, display, save, and share Persian-first religious and reflective reminder cards in a focused standalone experience.

== Description ==

Dhikra (ذِكْرَىٰ) provides a focused, mobile-first reminder experience. A reminder is called a “Telangor” and can be a Quran verse, Hadith, prayer or supplication, wisdom quotation, or short story.

Version 1.4.0 includes:

* A standalone public app without the active theme header or footer.
* One focused card that fits the supported viewport without internal scrolling.
* Equal-chance random delivery with configurable near-repeat avoidance.
* Stable five-character short codes that avoid ambiguous characters.
* Dedicated direct URLs for every published Telangor.
* One required primary topic plus optional secondary topics.
* One shared visual design for every Telangor, independent of content type or topic.
* Local IRANSansX FaNum for Persian interface/content and Scheherazade New for Arabic source text.
* Text-and-link Web Share support with a clipboard fallback.
* Separate general, Eitaa, Bale, and Telegram share-text templates.
* Eitaa mini-app support through the official remotely loaded SDK, full-height expansion, native back button, safe-area handling, viewport updates, and Eitaa theme awareness.
* Unified mobile OTP login and registration through the required Manahel IPPanel OTP plugin, with standard WordPress authentication cookies after verification.
* Favorites and a 100-item recent-history list for signed-in users.
* Signed-in users can build balanced printable A4 card sheets from one or more topics, with four cut-ready cards per page and a fixed QR that opens the Dhikra Eitaa mini-app.
* Administrator-only digital reward campaigns with a hard unique-user capacity, an exact prize count when capacity completes, manual early closing, and optional completion of remaining prizes among current participants.
* Printing and rewards are intentionally independent: printed cards are only a physical entry channel and never carry winner state.
* Topic preferences implemented as exclusions, so newly created active topics are selected by default.
* A public read-only REST API and authenticated favorites, history, and preferences endpoints.
* Direct integration with Manahel OTP plus extension contracts for messaging-platform identity bridges.
* Validated JSON package import with stable content IDs and multilingual-ready translation groups.
* A dynamic Gutenberg block and the `[dhikra]` shortcode.
* No telemetry or tracking. The official Eitaa SDK is loaded on standalone app routes as required by Eitaa.

Dhikra validates packages and fields technically. Religious, source, translation, and editorial review remain the publisher’s responsibility.

== Premium card UI (1.6.12) ==

Version 1.6.12 refreshes the Telangor card presentation without changing card APIs or user data. Cards now use a stronger editorial hierarchy, a compact Dhikra masthead, content-type and topic chips, refined source metadata, calmer reflection treatment, premium light/dark surfaces, a clearer primary CTA, glass utility actions, and an updated skeleton loader. The layout is reviewed at 360px, 390px, and desktop widths and continues to use the existing content-fit safeguards.



== Speaker placement and hard share-return recovery (1.6.24) ==
The visible speaker attribution now appears immediately before Arabic/original content (for example `امیر مؤمنان علی علیه السّلام:`), while the footer is reserved for the source. The Eitaa full-share payload uses the same order and no longer prefixes the attribution with `گوینده:`. Share-return recovery is now deliberately fail-safe: before opening Eitaa Share the current card URL is stored, and on the first `activated`, `pageshow`, or visible-state return the mini-app performs a one-time `location.replace()` to that exact card after clearing the pending marker. This replaces the compositor-only recovery used in 1.6.23 and guarantees a fresh server-rendered card/action shell after Eitaa returns from its native share surface.

== Eitaa full-share and resume recovery (1.6.23) ==

Eitaa card sharing now uses a canonical complete payload containing the title, original text, translation, reflection, content type, topic, speaker, source, and the exact mini-app URL https://eitaa.com/talanghor_app. This mini-app share payload is independent from the editable generic share template, so older saved settings cannot silently omit fields. Inside Eitaa the native `openEitaaLink()` route is preferred over the browser Web Share API, and the app performs a guarded layout/compositor recovery after returning from the Eitaa share surface to prevent an empty white WebView.

== Installation ==

1. Install and activate **Manahel IPPanel OTP** (`manahel-ippanel-otp`) version 1.1.3 or newer.
2. Open **Settings > Manahel OTP**, configure the IPPanel API key, sender, and pattern, and verify a test SMS.
3. Upload the `dhikra` folder to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New > Upload Plugin.
4. Activate **Dhikra**. WordPress will block activation while the required OTP plugin is missing.
5. Open **Dhikra > Settings** and review the URL mode, global card elements, share templates, OTP status, and API settings.
6. Open **Dhikra > Topics** to review which topics are active for selection.
7. Create Telangors manually or use **Dhikra > JSON Import**.
8. Visit `/zekra/` with pretty permalinks enabled, or the query-string route shown by the plugin when pretty permalinks are disabled.
9. Add the **Dhikra — Telangor** block or `[dhikra]` shortcode to a normal page when an embedded card is needed.

The default direct URL is `/z/CODE`. Root URL mode is available for a site dedicated mainly to Dhikra and displays a collision warning before it is enabled.

Before upgrading a site with existing email-only Dhikra users, assign each existing WordPress account its verified login mobile under **Users > Profile > Manahel SMS Login Information**. This preserves the same user ID, favorites, history, and topic preferences. Do not register a second account for an existing user.


== Bank of WooCommerce prize coupons (1.6.9) ==

1. Create real, valid coupons at https://manahel.ir/shop/ in WooCommerce. For exclusive codes, set usage limit per coupon to one, and configure expiry in WooCommerce if needed.
2. In Dhikra > Rewards, create a draft campaign of type **Shop coupon**. Set participant capacity, prize count, and the exact visit number (equal min/max) or random visit range (different min/max). The opening card counts as visit #1 but never triggers a draw.
3. Use the **Coupon bank** tab to paste one existing WooCommerce coupon per line. Each coupon is unique across all Dhikra campaigns, and an already-assigned coupon cannot be returned to the available stock. Date entered here is informational stock exclusion and must match WooCommerce expiry.
4. Activate only when the campaign has enough published distinct cards for the configured maximum visit count and at least as many valid, available coupons as the prize count.
5. On a winning milestone, one code is reserved atomically for one winner. If Eitaa PM permission is already granted, Dhikra sends the code plus https://manahel.ir/shop/ via the existing message queue. Otherwise the exact same reserved code waits for permission.
6. The coupon bank shows the Eitaa user, assigned code, timestamps, PM delivery status, and error. It supports private one-to-one follow-up, manager-confirmed manual redemption, exported audit CSV, stock disabling, and an explicitly confirmed retry after automatic attempts are exhausted. Manager-only operations use capability checks and nonces.

Dhikra DOES NOT create WooCommerce coupons, validate them against the remote WooCommerce API, or automatically detect actual redemption. Check coupon validity and redemption in the shop. WP-Cron must run regularly for bounded queue retries. Do not use live recipient data in staging tests.

== Eitaa daily reminders (1.6.7) ==

On the first successful signed Eitaa launch, Dhikra offers optional daily reminders. The user chooses 08:00, 13:00, or 21:00 Asia/Tehran time and, if needed, accepts the native Eitaa message-permission dialog. Declining does not restrict any card, history, favorite, or account feature. The decision is stored per Eitaa user, rather than per device. Daily reminders can be changed or disabled from the card-screen settings button.

The queue uses a date-scoped unique database key to avoid creating two daily messages for the same audience on the same Iranian calendar day, with bounded retries before day rollover. Each run processes at most 20 due subscribers. The WP-Cron dispatch runs at five-minute intervals where a WordPress cron runner is available. For reliable delivery on a low-traffic production site, configure an external server cron to run `wp cron event run --due-now` or WordPress `wp-cron.php` every five minutes. The selected hour is the earliest eligible dispatch time, not an exact delivery-time guarantee; queue load, server traffic, and Eitaa API availability can cause delays. Each successfully delivered message consumes Eitaa application credit.

== Frequently Asked Questions ==

= Does Dhikra send content or user data to an external service? =

Dhikra does not transmit stored content or user data for telemetry, tracking, CDN delivery, or AI image generation. For OTP authentication, the required Manahel plugin sends the normalized mobile number and one-time code to IPPanel Edge. Standalone app pages also request the official Eitaa SDK from `developer.eitaa.com`; Eitaa-signed initData is validated server-side; Dhikra stores a mapped Eitaa user ID and an optional daily-delivery preference after consent.

= Does the plugin create an SVG or image for each card? =

No. Version 1.2.0 renders every Telangor with one shared HTML/CSS card component. Sharing sends the configured text and direct link, so no generated card file is stored in uploads.

= How is the Eitaa mini-app connected? =

Register the HTTPS address shown under **Dhikra > Settings > Eitaa mini-app** as the destination URL in EitaaYar without a redirect. The public Eitaa link is `https://eitaa.com/talanghor_app`. The plugin loads the official Eitaa SDK directly from `developer.eitaa.com` and initializes it only when the SDK is available.

= What content types are supported? =

Quran verse, Hadith, prayer or supplication, wisdom quotation, and short story. Required fields vary by type. A reflection sentence is optional and is not used for short stories.

= How do login and registration work? =

Both use the same mobile OTP form supplied by the required Manahel IPPanel OTP plugin. An existing mobile number signs in to its WordPress account. A new verified number creates a WordPress account after the registration fields are completed. Dhikra no longer exposes its former email/password forms or password-reset route.

= Can bots use the same favorites as the website? =

The core includes central user data tables and PHP identity-linking contracts. A provider-specific bot or bridge add-on must verify the platform identity and call those contracts. Provider credentials never belong in the core plugin.

= What happens when the plugin is deactivated or deleted? =

Deactivation removes no data. Uninstall preserves data by default. Complete deletion occurs only when the administrator enables the explicit uninstall option first.

== REST API ==

Public endpoints, when enabled:

* `GET /wp-json/dhikra/v1/telangors`
* `GET /wp-json/dhikra/v1/telangors/random`
* `GET /wp-json/dhikra/v1/telangors/{CODE}`
* `GET /wp-json/dhikra/v1/topics`

Authenticated endpoints use WordPress REST authentication:

* `GET /wp-json/dhikra/v1/favorites`
* `POST /wp-json/dhikra/v1/favorites/{CODE}`
* `GET|POST /wp-json/dhikra/v1/preferences`
* `GET /wp-json/dhikra/v1/history`
* `POST /wp-json/dhikra/v1/identity/link` after a compatible bridge verifies the external identity through the documented filter.

== Privacy ==

For signed-in users, Dhikra stores favorites, up to 100 recently viewed Telangors, excluded topic IDs, and optional external identity mappings created by compatible add-ons. Dhikra does not transmit those records outside the site. During OTP authentication, the required Manahel plugin processes the mobile number and sends the mobile number plus one-time code to IPPanel Edge; it stores normalized mobile metadata on the WordPress user. Standalone app pages request the official Eitaa SDK from `developer.eitaa.com`; signed Eitaa identity is verified on the server, and Dhikra stores the mapped Eitaa user ID, write-access status, and optional daily-delivery settings. Privacy exporter and eraser integrations are included.

== Upgrade Notice ==

= 1.4.0 =

Adds independent printable-card and administrator-only digital reward modules. Database schema is upgraded automatically; existing Telangors, short URLs, accounts, favorites, history, and OTP login remain unchanged.

= 1.3.0 =

Replaces Dhikra's email/password authentication pages with unified mobile OTP login and registration through Manahel IPPanel OTP 1.1.3 or newer. Install and configure the dependency before updating.

= 1.2.0 =

Unifies every card design, removes per-card SVG generation, loads the provided local Persian/Arabic fonts, and adds official Eitaa mini-app integration.

= 1.1.0 =

Adds the approved card-image, content-type, favorites, history, topic-theme, API, and identity-bridge architecture. Existing 1.0.x content is migrated in safe administrator-triggered batches; existing images are not silently replaced.

== Changelog ==

= 1.6.29 =
* Expanded the Eitaa analytics dashboard with daily-message delivery metrics: queued, sent, terminal failures, pending retries, retry counts, and success rate.
* Added daily delivery trend charts, recent failure diagnostics, current deliverable-audience counts, and 24-hour post-message app-open attribution.
* Read receipts are explicitly marked as unavailable because the Eitaa app messaging API does not expose message-read status to this plugin.
* Added reporting indexes for daily-message and post-send visit analytics; database schema version is now 1.6.29.

= 1.6.28 =
* Added a dedicated WordPress analytics dashboard for the Eitaa mini-app.
* Tracks privacy-conscious app sessions only (no IP, location, or user-agent storage).
* Added today, 7/30/90-day visits, unique visitors, new/returning visitors, return rate, daily trend, hourly traffic, audience status, and recent app opens.
* Added session-token deduplication so internal reloads (including share recovery) do not inflate visit counts.
* Added the `dhikra_visits` table; database schema version is now 1.6.28.


= 1.6.27 =
* بازطراحی کامل صفحه «ذخیره‌شده‌ها» با کارت‌های خواناتر، پیش‌نمایش متن، منبع و تاریخ ذخیره.
* جستجوی زنده، فیلتر موضوعی و مرتب‌سازی جدیدترین/قدیمی‌ترین برای نسخه وب و برنامک ایتا.
* حذف سریع ذخیره‌ها همراه با «برگردان» بدون بارگذاری مجدد صفحه.
* وضعیت خالی و بدون نتیجه، شمارنده و Dark Mode اختصاصی برای مجموعه ذخیره‌ها.
* مسیر تأییدشده تلنگر روزانه، اشتراک و کارت اصلی بدون تغییر منطقی باقی مانده است.

= 1.6.26 =
* تلنگر روزانه برای مخاطب جدید به‌صورت پیش‌فرض روشن است؛ فقط خاموش‌کردن دستی آن را متوقف می‌کند.
* رد کردن/نبودن مجوز بومی پیام ایتا دیگر اشتراک روزانه را خاموش یا به‌عنوان انصراف ثبت نمی‌کند.
* اگر روزانه روشن باشد ولی مجوز ایتا موجود نباشد، در ورود بعدی دوباره درخواست مجوز انجام می‌شود.
* گرفتن مجوز پیام برای جایزه یا یادآوری، انصراف دستی قبلی از تلنگر روزانه را لغو نمی‌کند.
* برچسب «روزانه !» وضعیت روشن ولی منتظر مجوز ایتا را از «روزانه ✓» قابل تشخیص می‌کند.


= 1.6.25 =
* ممیزی عملکرد و منطق اجرا: autoload کلاس‌ها، عدم بارگذاری کنترلرهای مدیریت در برنامک، cache درون‌درخواستی داده کارت، حذف query شخصی غیرضروری و اصلاح share جایزه.

= 1.6.24 =
* Moved the speaker attribution above the Arabic/original text and removed the literal `گوینده:` prefix from Eitaa sharing.
* The card footer now shows source metadata only.
* Replaced post-share compositor repaint with a one-time hard restore of the exact current card after returning from Eitaa Share.

= 1.6.23 =
* Eitaa card sharing now sends the complete card content plus the exact mini-app URL `https://eitaa.com/talanghor_app`, independent of older saved share-template settings.
* Eitaa sharing now prefers the SDK-native `openEitaaLink()` route instead of `navigator.share`, keeping the mini-app open while the Eitaa share surface is used.
* Added guarded resume recovery on `activated`, `pageshow`, and visibility return, including card restore/refit and a two-frame WebView compositor repaint to prevent the blank white screen reported after returning from Share.
* Bumped the short-lived resume-card cache key so pre-upgrade cached card HTML cannot keep the older incomplete Eitaa share payload.

= 1.6.22 =
* Added bidirectional vertical card navigation: pull down at the top or swipe up at the bottom to load the next telangor without interfering with normal card scrolling.
* Removed the extra first-run Dhikra activation sheet. On the first Eitaa launch, Dhikra immediately requests Eitaa's native write permission; granting it automatically enables the daily telangor subscription.
* If signed Eitaa initData already confirms write permission on a first-time subscription, daily delivery is enabled automatically. Users can still stop daily delivery at any time from the in-app Daily settings.
* The app never treats opening/clicking the mini-app ID as a substitute for Eitaa's native consent; the native permission dialog remains the authoritative grant.

= 1.6.21 =
* Removed image sharing, image preview, image saving/downloading, and all image-share controls from the mini app.
* Removed the temporary Eitaa image-upload REST endpoint and client-side PNG generation code.
* Added one-time cleanup for legacy `dhikra-temp` PNG files and scheduled cleanup hooks left by 1.6.20.
* The compact More menu now contains only personal note, remind me again, and copy text.

= 1.6.20 =
* Replaced unreliable WebView long-press/blob downloads with the official Eitaa `downloadFile()` flow.
* Image previews are uploaded temporarily to a random HTTPS URL for up to six hours and are not added to the Media Library.
* When direct file sharing is unsupported, the share action opens the generated image in the external browser for system sharing instead of silently doing nothing.
* Removed the misleading long-press-in-WebView fallback.


= 1.6.19 =
* Replaced ambiguous image-share fallback with an in-app PNG preview before any share or save action.
* Added explicit Share image and Save image controls; generated images stay client-side and are never uploaded to the Dhikra server.
* Use the native file picker when available so a successful save can be confirmed; otherwise only report that a download request was sent and keep the preview visible for long-press saving.
* Never claim that an image was saved when the Eitaa WebView cannot confirm the download.

= 1.6.18 =
* Added a compact More sheet: personal note, remind me again, image sharing, and copy text.
* Personal notes persist per signed-in/Eitaa identity without adding controls to the card itself.
* Eitaa reminders can be scheduled for tonight, tomorrow, or next week and are delivered through the existing reliable message queue.
* Added client-side branded PNG generation for sharing a telangor as an image.

= 1.6.9 =
* Added a per-campaign bank of unique, pre-created Manahel WooCommerce discount codes and a manager-only audit/export and one-to-one follow-up interface.
* Atomic coupon reservation on an actual winning card; same reserved code preserved on provider failure and retry; private PM sends the code and shop URL.
* Configurable draw threshold per campaign (exact card number or randomized range). Opening and repeated cards never create another draw.
* Separate server-rendered Rewards tabs for campaigns, creation, bank, participants, and winners, with paginated tables and manual redemption tracking.
* Safe migration for prize-mode, visit-range, coupon inventory and winner-message deduplication.

= 1.6.8 =
* Reward draws no longer happen at first Eitaa entry. Users browse 4–8 different server-selected campaign cards before exactly one random, capacity-limited draw.
* First launch/refresh does not trigger or advance a draw; repeated cards and duplicate requests do not increase progress.
* Added per-audience reward browsing progress schema and wired the winner dialog to the new-card response.

= 1.6.7 =
* Offer opt-in daily Eitaa reminders immediately on the first authenticated launch; persist accept/decline per Eitaa user.
* Request native message permission on the user's activation action; reuse one permission-request guard for winner messages and daily reminders.
* Allow subscribers to choose 08:00, 13:00, or 21:00 Asia/Tehran time and continue to select topics in daily settings.
* Use five-minute WP-Cron batches, Tehran-local day boundaries, bounded retries, a unique per-recipient per-day queue key, and an atomic message claim to prevent overlapping sends.
* Preserve a freshly accepted native permission while the current signed launch data is older, and accept a newer signed revocation.
* Send daily private-message content without an appended URL.


= 1.6.3 =
* Added reliable Eitaa broadcasts with a unique queue, controlled batches, retries, cancellation, progress, and recipient reports.

= 1.6.2 =
* Fix activation on sites without the optional Manahel OTP integration; Eitaa, REST, content, rewards, and admin features now remain available while login pages keep their existing dependency notice.


= 1.6.1 =

* Fix Eitaa Mini App outbound messaging to use the official `/api/app/sendMessage` endpoint with the application token in the request body.
* Treat only the documented `{ok:true,result:"success"}` response as a successful delivery and preserve useful API error details.
* Keep Eitaa write-access state synchronized with current validated initData instead of permanently retaining stale grants.
* Hold winner private messages until write access exists, request permission for winners, and safely deliver pending winner messages after permission is granted.
* Prevent duplicate winner messages when a pending/failed reward delivery is retried.
* Clarify that the configured Eitaa credential must be the application token, not a sender/channel token.

= 1.6.0 =
* Added topic-scoped first cards for reward campaigns while keeping later cards unrestricted.
* Added configurable live campaign metrics, transparent winner delivery reports, and CSV exports.
* Added daily reminder preferences with topic selection and a retryable, indexed message queue.
* Added four global card styles, a real loading skeleton, swipe navigation, and a premium winner dialog.
* Justified card content on screen and in print, while keeping card titles centered.
* Added View and secure Preview row actions for Telangor content.

= 1.5.2 =
* افزودن نورپردازی چندلایه، حاشیه داخلی و نقش هندسی اسلامی بسیار محو فقط با CSS.
* بهبود قاب متن عربی، شرح، منبع و نوار یک‌ردیفی عملیات بدون افزودن عناصر مزاحم.
* افزودن انیمیشن کوتاه ورود کارت، وضعیت بارگذاری و بازخورد تصویری برای ذخیره، اشتراک و کپی.
* تبدیل پیام‌های عملیاتی به اعلان شناور و حذف فضای دائمی پیام از پایین صفحه.
* طراحی کامل حالت تاریک عمومی و هماهنگ‌سازی رنگ‌های برنامک با پوسته روشن و تاریک ایتا.

= 1.5.1 =
* بازطراحی مینیمال صفحه اصلی کارت و حذف هدر، نشان و دسته‌بندی نمایشی از مسیر تلنگر.
* کاهش اندازه عنوان، تفکیک بهتر متن اصلی، ترجمه، شرح و منبع و جلوگیری از هم‌پوشانی محتوای بلند.
* حفظ همه دکمه‌های عملیات در یک ردیف با تأکید بیشتر بر دکمه تلنگر جدید.
* تبدیل گزینه دائمی تلنگر روزانه به پیشنهاد یک‌باره پس از نخستین دریافت کارت تازه.

= 1.5.0 =
* Eitaa initData audience identity, instant reward participation on first app open, editable campaign winner messages, direct audience messaging, and opt-in daily random telangors.


= 1.4.0 =

* Added signed-in user card printing with one/multiple/all topic selection and a configurable per-output card limit.
* Added balanced selection that avoids repetition until the eligible content pool is consumed.
* Added A4 portrait print sheets with four cut-ready cards per page, local Persian/Arabic fonts, print-safe typography, source metadata, and a bundled high-resolution QR for the Eitaa mini-app.
* Added temporary, user-bound print previews without persistent print-history tables.
* Added administrator-only digital reward campaigns with capacity, exact prize count, active/draft/completed/closed states, and one active campaign at a time.
* Added transaction-locked unique-user reward enrollment and controlled random prize allocation that guarantees the configured prize count if capacity is reached.
* Added manual early closing with either cancellation of unallocated prizes or random completion among current non-winners.
* Kept printing and reward data models completely independent.

= 1.3.0 =

* Added a declared WordPress plugin dependency on `manahel-ippanel-otp`.
* Embedded the Manahel OTP form into both Dhikra login and registration routes with safe return URLs.
* Removed Dhikra's email/password login, registration, lost-password, and reset-password handlers and forms.
* Redirected legacy password-recovery routes to mobile OTP login.
* Preserved standard WordPress user IDs, cookies, roles, favorites, history, and topic preferences after OTP authentication.
* Added OTP dependency/status information to Dhikra settings and an administrator notice for a missing runtime dependency.
* Added styling and Eitaa dark-theme support for the embedded OTP form.
* Removed obsolete email/auth-mode options during the 1.3.0 upgrade.

= 1.2.0 =

* Replaced topic-dependent card themes with one shared card design.
* Removed SVG themes, generated background/share files, image regeneration, and browser SVG-to-PNG conversion.
* Added local IRANSansX FaNum font faces for Persian UI/content and Scheherazade New for Arabic text.
* Added the official Eitaa Web App SDK with ready/expand, viewport and safe-area synchronization, native back navigation, bar colors, and theme-state handling.
* Added the Eitaa public link and exact HTTPS destination URL to the settings screen.
* Kept text/link sharing and API compatibility without creating card media files.

= 1.1.0 =

* Standardized the display name as `ذِكْرَىٰ`.
* Added five content types with type-specific required fields.
* Added one required primary topic and controlled semantic theme palettes.
* Added sixteen bundled SVG theme assets and local per-Telangor background/share-card generation.
* Added fixed image persistence, explicit regeneration, previous-image restoration, and optional raster background replacement.
* Added browser-side SVG-to-PNG file sharing with configurable general and platform-specific text templates.
* Added stable non-ambiguous five-character short codes with tombstones that prevent reuse after deletion.
* Added subpath and dedicated-site root URL modes.
* Added favorites, a 100-item history, account topic exclusions, and ten-item history pagination.
* Added public read-only and authenticated personal REST endpoints.
* Added external identity and SMS provider extension contracts without changing other site login flows.
* Added stricter no-scroll content validation and responsive floating card layout.
* Added privacy exporter and eraser support.
* Added safe opt-in uninstall cleanup for plugin-owned content, tables, files, and generated external-only accounts.

= 1.0.1 =

* Previous local release with a standalone app, email authentication, random Telangors, JSON import, shortcode, and block.

= 1.0.0 =

* Initial local release.




== Centered Arabic source text (1.6.16) ==
- Arabic original/source text on Telangor cards is centered on every line, including the final line.
- Persian translation, reflection, metadata, and source layout remain unchanged.
- No database migration is required.


== Fixed card reading-box width (1.6.15) ==
- The main quotation/original-text box now uses a stable full reading-column width instead of shrinking around short text.
- Reflection boxes follow the same stable reading-column width for a consistent card silhouette.
- No database migration is required.

== Simplified daily Eitaa reminders (1.6.14) ==

* Eitaa write-access consent now doubles as the one required permission for automatic daily Telangor delivery.
* Removed per-user topic and send-hour selection from the mini-app; users only enable or stop daily delivery.
* Added one administrator-controlled Iran-time delivery time in Dhikra settings.
* Daily messages are selected from all active topics and remain deduplicated to at most one daily message per audience per Tehran date.
* Stopping daily delivery does not revoke the underlying Eitaa write-access permission, so users can re-enable delivery later without a second permission prompt when the permission is still valid.

== Coupon persistence diagnostic fix (1.6.10) ==

For previous 1.6.9 installs, the coupon tab now validates and, if needed, non-destructively repairs the inventory table before importing. Coupon database errors are no longer mislabeled as duplicates. New inserts are read back before the UI reports saved. The selected coupon campaign is remembered for each administrator and a read-only summary shows total inventory for all campaigns. Existing entries, reservations and assigned codes are never deleted or released by this update. If storage repair fails, stop importing and check database permissions/logs. The DB version remains 1.6.9 because no new columns are required.

== Shared coupon allocation (1.6.11) ==

* One WooCommerce discount code can now be stored once with a finite per-campaign Dhikra award quota; bulk entry of distinct single-use codes remains supported.
* Added allocation_limit on reward_coupons and a coupon_id and coupon_redeemed_at on reward_entries. Existing assignment, delivery, and redemption snapshots are migrated non-destructively.
* Remaining prize inventory now counts available award slots, not distinct coupon strings. Previously awarded slots are never recycled by message retries.
* Each awarded user has independent delivery, manual redemption, CSV and follow-up records even when they receive the same discount code.
* Draft campaign winner count can be adjusted before activation; live campaigns cannot be resized through this setting.
* WooCommerce remains the source of truth for discount validation and real purchase/redemption limits; Dhikra quotas limit awards only.
* Before upgrading a real site, back up its plugin directory and WordPress database and test a legacy assigned code in staging.


== Daily Companion review fixes (1.6.17) ==
- Added a non-white critical app shell, deferred Eitaa SDK/application scripts, a short private WebView cache window, and session card restore to make resume/reopen feel immediate.
- Replaced the previous upward/horizontal card gesture with a top-to-bottom pull gesture that triggers a new Telangor only when the card content is already at the top.
- Added visible discovery actions on the home screen: search, topic browsing, saved items, and daily reminder status/settings.
- Added search across title, Arabic/original text, translation, reflection, speaker, source, and topic name.
- Added in-app topic browsing and secure Eitaa card opening that records history and preserves favorite state.
- Unified Eitaa write-access consent with Dhikra daily activation: granting message permission also enables the automatic daily reminder; users can still stop daily delivery from inside the mini app.
- No database migration is required; DB version remains 1.6.11.
