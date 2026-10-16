<?php
/** Admin-only coupon bank: reusable codes, finite award slots, recipient-level audit. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Coupon_Admin {
	public function register(): void {
		add_action( 'admin_post_dhikra_coupon_import', array( $this, 'import' ) );
		add_action( 'admin_post_dhikra_coupon_limit', array( $this, 'update_limit' ) );
		add_action( 'admin_post_dhikra_coupon_disable', array( $this, 'disable' ) );
		add_action( 'admin_post_dhikra_coupon_redeem', array( $this, 'redeem' ) );
		add_action( 'admin_post_dhikra_coupon_retry', array( $this, 'retry' ) );
		add_action( 'admin_post_dhikra_coupon_message', array( $this, 'message' ) );
		add_action( 'admin_post_dhikra_coupon_export', array( $this, 'export' ) );
	}

	private static function url( array $params = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => 'dhikra_telangor', 'page' => 'dhikra-rewards', 'tab' => 'coupons' ), $params ), admin_url( 'edit.php' ) );
	}

	private static function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_dhikra_rewards' ) ) { wp_die( esc_html__( 'دسترسی ندارید.', 'dhikra' ) ); }
		check_admin_referer( $nonce );
	}

	private static function redirect( int $campaign_id, string $key, string $notice ): never {
		if ( $campaign_id > 0 ) { update_user_meta( get_current_user_id(), '_dhikra_last_coupon_campaign', $campaign_id ); }
		wp_safe_redirect( self::url( array( 'campaign_id' => $campaign_id, $key => $notice ) ) );
		exit;
	}

	private static function campaign( int $campaign_id ): ?array {
		global $wpdb;
		$t = Dhikra_Data::table( 'reward_campaigns' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $campaign_id ), ARRAY_A );
		return is_array( $row ) && Dhikra_Coupons::is_coupon_campaign( $row ) ? $row : null;
	}

	private static function submitted_expiry( int $campaign_id ): ?string {
		$expiry = sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) );
		if ( '' === $expiry ) { return null; }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry ) || ! checkdate( (int) substr( $expiry, 5, 2 ), (int) substr( $expiry, 8, 2 ), (int) substr( $expiry, 0, 4 ) ) || $expiry < Dhikra_Audience::tehran_now()->format( 'Y-m-d' ) ) {
			self::redirect( $campaign_id, 'dhikra_error', 'تاریخ انقضا معتبر نیست یا گذشته است.' );
		}
		return $expiry;
	}

	/** Explicit shared mode accepts one code + a per-campaign award quota. Bulk remains one-time per distinct code. */
	public function import(): never {
		self::authorize( 'dhikra_coupon_import' );
		$campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		$campaign = self::campaign( $campaign_id );
		if ( ! $campaign || ! in_array( (string) $campaign['status'], array( 'draft','active' ), true ) ) { self::redirect( $campaign_id, 'dhikra_error', 'پویش کد تخفیف معتبر یا قابل ویرایش نیست.' ); }
		if ( ! Dhikra_Coupons::storage_ready( true ) ) { self::redirect( $campaign_id, 'dhikra_error', 'ساختار بانک هنوز آماده نیست؛ پیش از ثبت، Migration پایگاه داده را بررسی کنید.' ); }
		$mode = sanitize_key( $_POST['coupon_mode'] ?? 'single_use' );
		$shared = 'reusable' === $mode;
		$raw = $shared ? (string) wp_unslash( $_POST['shared_code'] ?? '' ) : (string) wp_unslash( $_POST['coupon_codes'] ?? '' );
		if ( strlen( $raw ) > 300000 ) { self::redirect( $campaign_id, 'dhikra_error', 'حداکثر حجم ورود کدها ۳۰۰ کیلوبایت است.' ); }
		$limit = $shared ? absint( $_POST['allocation_limit'] ?? 0 ) : 1;
		if ( $limit < 1 || $limit > 100000 ) { self::redirect( $campaign_id, 'dhikra_error', 'سهمیه اهدای کد باید بین ۱ تا ۱۰۰٬۰۰۰ نفر باشد.' ); }
		$expiry = self::submitted_expiry( $campaign_id );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$codes = array(); $repeats = 0;
		foreach ( $lines as $line ) {
			$line = trim( $line, "\xEF\xBB\xBF \t" );
			if ( '' === $line ) { continue; }
			$code = Dhikra_Coupons::normalize_code( $line );
			if ( '' === $code ) { self::redirect( $campaign_id, 'dhikra_error', 'کد نامعتبر است؛ هر خط فقط یک کد، بدون فاصله یا نویسه کنترلی، داشته باشد.' ); }
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $code, 'UTF-8' ) : strtolower( $code );
			if ( isset( $codes[ $key ] ) ) { ++$repeats; }
			$codes[ $key ] = $code;
		}
		if ( empty( $codes ) || count( $codes ) > 2000 || ( $shared && ( count( $codes ) !== 1 || $repeats ) ) ) {
			self::redirect( $campaign_id, 'dhikra_error', $shared ? 'در حالت کد مشترک، فقط یک کد وارد کنید و ظرفیت آن را جداگانه تعیین کنید.' : 'در هر نوبت بین ۱ تا ۲۰۰۰ کد متفاوت وارد کنید.' );
		}
		global $wpdb;
		$t = Dhikra_Data::table( 'reward_coupons' );
		$added = 0; $duplicate = 0; $failed = 0; $updated = 0; $new_ids = array();
		foreach ( $codes as $code ) {
			$wpdb->query( 'START TRANSACTION' );
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE coupon_code=%s LIMIT 1 FOR UPDATE", $code ), ARRAY_A );
			if ( is_array( $existing ) ) {
				if ( $shared && absint( $existing['campaign_id'] ) === $campaign_id && 'disabled' !== (string) $existing['status'] ) {
					$used = Dhikra_Coupons::assigned_count( absint( $existing['id'] ) );
					if ( $limit >= $used ) {
						// Never silently shrink a live campaign below its outstanding prize count.
						if ( 'active' === (string) $campaign['status'] && $limit < absint( $existing['allocation_limit'] ) ) {
							$entries_table = Dhikra_Data::table( 'reward_entries' );
							$awarded = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries_table} WHERE campaign_id=%d AND is_winner=1", $campaign_id ) ) );
							$needed = max( 0, absint( $campaign['prize_count'] ) - $awarded );
							$future = Dhikra_Coupons::available_count( $campaign_id ) - ( absint( $existing['allocation_limit'] ) - $limit );
							if ( $future < $needed ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'این کاهش سهمیه موجودی لازم برای جوایز پویش فعال را از بین می‌برد.' ); }
						}
						$data = array( 'allocation_limit' => $limit ); $formats = array( '%d' );
						if ( null !== $expiry ) { $data['expires_at'] = $expiry; $formats[] = '%s'; }
						$changed = $wpdb->update( $t, $data, array( 'id' => absint( $existing['id'] ) ), $formats, array( '%d' ) );
						if ( false !== $changed ) {
                            $committed = $wpdb->query( 'COMMIT' );
                            $verified_limit = $wpdb->get_var( $wpdb->prepare( "SELECT allocation_limit FROM {$t} WHERE id=%d AND campaign_id=%d", absint( $existing['id'] ), $campaign_id ) );
                            if ( false !== $committed && null !== $verified_limit && absint( $verified_limit ) === $limit ) { ++$updated; continue; }
                            ++$failed; continue;
                        }
					}
				}
				$wpdb->query( 'ROLLBACK' ); ++$duplicate; continue;
			}
			$ok = $wpdb->insert( $t, array( 'campaign_id' => $campaign_id, 'coupon_code' => $code, 'allocation_limit' => $limit, 'status' => 'available', 'expires_at' => $expiry, 'created_at' => current_time( 'mysql', true ) ), array( '%d','%s','%d','%s','%s','%s' ) );
			if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); ++$failed; continue; }
			$id = absint( $wpdb->insert_id );
			$verified = $wpdb->get_row( $wpdb->prepare( "SELECT id,allocation_limit FROM {$t} WHERE id=%d AND campaign_id=%d", $id, $campaign_id ), ARRAY_A );
			if ( ! $verified || absint( $verified['allocation_limit'] ) !== $limit ) { $wpdb->query( 'ROLLBACK' ); ++$failed; continue; }
			$committed = $wpdb->query( 'COMMIT' );
            $after = $wpdb->get_var( $wpdb->prepare( "SELECT allocation_limit FROM {$t} WHERE id=%d AND campaign_id=%d", $id, $campaign_id ) );
            if ( false === $committed || null === $after || absint( $after ) !== $limit ) { ++$failed; continue; }
            ++$added; $new_ids[] = $id;
		}
		$slots = $added * $limit;
		if ( $failed ) { self::redirect( $campaign_id, 'dhikra_error', sprintf( 'نتیجه: %d کد تازه با %d سهمیه، %d کد موجود به‌روز شد، %d تکراری و %d خطای ذخیره. قبل از ورود مجدد موجودی را بررسی کنید.', $added, $slots, $updated, $duplicate, $failed ) ); }
		$message = sprintf( '%d کد تازه با مجموع %d سهمیه ذخیره شد؛ %d کد موجود به‌روز شد؛ %d تکراری.', $added, $slots, $updated, $duplicate );
		if ( $repeats ) { $message .= ' تکرار هر کد در متن، سهمیه آن را افزایش نمی‌دهد؛ از حالت کد مشترک استفاده کنید.'; }
		self::redirect( $campaign_id, 'dhikra_notice', $message );
	}

	/** Managers can increase an imported one-time code's quota, preserving every prior award. */
	public function update_limit(): never {
		self::authorize( 'dhikra_coupon_limit' );
		$campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		$id = absint( $_POST['coupon_id'] ?? 0 );
		$limit = absint( $_POST['allocation_limit'] ?? 0 );
		if ( $limit < 1 || $limit > 100000 || ! self::campaign( $campaign_id ) ) { self::redirect( $campaign_id, 'dhikra_error', 'ظرفیت اهدای کد معتبر نیست.' ); }
		global $wpdb;
		$t = Dhikra_Data::table( 'reward_coupons' );
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d AND campaign_id=%d FOR UPDATE", $id, $campaign_id ), ARRAY_A );
		if ( ! $row || 'disabled' === (string) $row['status'] ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'کد انتخاب‌شده قابل ویرایش نیست.' ); }
		$used = Dhikra_Coupons::assigned_count( $id );
		if ( $limit < $used ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'سهمیه جدید نمی‌تواند کمتر از تعداد برندگان دریافت‌کننده باشد.' ); }
		$campaign = self::campaign( $campaign_id );
		if ( $campaign && 'active' === $campaign['status'] && $limit < absint( $row['allocation_limit'] ) ) {
			$entries = Dhikra_Data::table( 'reward_entries' );
			$awarded = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id=%d AND is_winner=1", $campaign_id ) ) );
			$remaining = max( 0, absint( $campaign['prize_count'] ) - $awarded );
			$future_stock = Dhikra_Coupons::available_count( $campaign_id ) - ( absint( $row['allocation_limit'] ) - $limit );
			if ( $future_stock < $remaining ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'کاهش سهمیه موجودی لازم برای جوایز پویش فعال را از بین می‌برد.' ); }
		}
		$changed = $wpdb->update( $t, array( 'allocation_limit' => $limit ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		if ( false === $changed ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'تغییر سهمیه در پایگاه داده انجام نشد.' ); }
		$wpdb->query( 'COMMIT' );
		self::redirect( $campaign_id, 'dhikra_notice', sprintf( 'ظرفیت کد روی %d نفر تنظیم شد؛ %d نفر قبلاً دریافت کرده‌اند.', $limit, $used ) );
	}

	public function disable(): never {
		self::authorize( 'dhikra_coupon_disable' );
		$id = absint( $_POST['coupon_id'] ?? 0 ); $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		global $wpdb;
		$t = Dhikra_Data::table( 'reward_coupons' );
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d AND campaign_id=%d FOR UPDATE", $id, $campaign_id ), ARRAY_A );
		if ( ! $row || 'disabled' === (string) $row['status'] || Dhikra_Coupons::assigned_count( $id ) > 0 ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'کدی که قبلاً به برنده اختصاص داده شده، قابل غیرفعال‌سازی نیست.' ); }
		$campaign = self::campaign( $campaign_id );
		if ( $campaign && 'active' === $campaign['status'] ) {
			$e = Dhikra_Data::table( 'reward_entries' );
			$awarded = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$e} WHERE campaign_id=%d AND is_winner=1", $campaign_id ) ) );
			$need = max( 0, absint( $campaign['prize_count'] ) - $awarded );
			if ( Dhikra_Coupons::available_count( $campaign_id ) - absint( $row['allocation_limit'] ) < $need ) {
				$wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'غیرفعال‌سازی این کد موجودی لازم برای پویش فعال را کاهش می‌دهد.' );
			}
		}
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status='disabled' WHERE id=%d AND campaign_id=%d AND status='available'", $id, $campaign_id ) );
		if ( 1 !== (int) $changed ) { $wpdb->query( 'ROLLBACK' ); self::redirect( $campaign_id, 'dhikra_error', 'غیرفعال‌سازی این کد انجام نشد.' ); }
		$wpdb->query( 'COMMIT' ); self::redirect( $campaign_id, 'dhikra_notice', 'کد بدون سابقه تخصیص غیرفعال شد.' );
	}

	private static function recipient( int $campaign_id, int $entry_id ): ?array {
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$e = Dhikra_Data::table( 'reward_entries' );
		$a = Dhikra_Data::table( 'audience' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT e.*,c.coupon_code,c.expires_at,a.allows_write_to_pm FROM {$e} e JOIN {$c} c ON c.id=e.coupon_id AND c.campaign_id=e.campaign_id JOIN {$a} a ON a.id=e.audience_id AND a.eitaa_user_id=e.eitaa_user_id WHERE e.id=%d AND e.campaign_id=%d AND e.is_winner=1 LIMIT 1", $entry_id, $campaign_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Retry only the exact recipient's queued winner message, never another shared-code recipient. */
	public function retry(): never {
		self::authorize( 'dhikra_coupon_retry' );
		$entry_id = absint( $_POST['reward_entry_id'] ?? 0 ); $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		$row = self::recipient( $campaign_id, $entry_id );
		if ( ! $row || empty( $row['allows_write_to_pm'] ) || ( $row['expires_at'] && $row['expires_at'] < Dhikra_Audience::tehran_now()->format( 'Y-m-d' ) ) ) { self::redirect( $campaign_id, 'dhikra_error', 'برنده معتبر یا مجوز/تاریخ معتبر برای تلاش مجدد یافت نشد.' ); }
		global $wpdb;
		$m = Dhikra_Data::table( 'messages' );
		$message = $wpdb->get_row( $wpdb->prepare( "SELECT id,status,attempts FROM {$m} WHERE reward_entry_id=%d AND message_type='winner' ORDER BY id DESC LIMIT 1", $entry_id ), ARRAY_A );
		if ( ! $message || 'failed' !== $message['status'] || absint( $message['attempts'] ) < 3 ) { self::redirect( $campaign_id, 'dhikra_error', 'برای این برنده پیام ناموفقِ قابل تلاش مجدد وجود ندارد.' ); }
		$now = current_time( 'mysql', true );
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$m} SET status='queued',attempts=0,next_attempt_at=%s,updated_at=%s WHERE id=%d AND status='failed' AND attempts>=3", $now, $now, absint( $message['id'] ) ) );
		if ( 1 !== (int) $changed ) { self::redirect( $campaign_id, 'dhikra_error', 'پیام قبلاً پردازش شده یا هم‌اکنون در صف است.' ); }
		$delivery = Dhikra_Audience::attempt_queued_message( absint( $message['id'] ) );
		self::redirect( $campaign_id, 'dhikra_notice', 'sent' === ( $delivery['status'] ?? '' ) ? 'همان کد به همان برنده دوباره ارسال شد.' : 'تلاش مجدد انجام شد؛ وضعیت و خطای احتمالی را بررسی کنید.' );
	}

	public function redeem(): never {
		self::authorize( 'dhikra_coupon_redeem' );
		$campaign_id = absint( $_POST['campaign_id'] ?? 0 ); $entry_id = absint( $_POST['reward_entry_id'] ?? 0 );
		if ( ! self::recipient( $campaign_id, $entry_id ) ) { self::redirect( $campaign_id, 'dhikra_error', 'برنده معتبر یافت نشد.' ); }
		global $wpdb;
		$e = Dhikra_Data::table( 'reward_entries' );
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$e} SET coupon_redeemed_at=%s WHERE id=%d AND campaign_id=%d AND is_winner=1 AND coupon_id>0 AND pm_status='sent' AND coupon_redeemed_at IS NULL", current_time( 'mysql', true ), $entry_id, $campaign_id ) );
		self::redirect( $campaign_id, $changed ? 'dhikra_notice' : 'dhikra_error', $changed ? 'مصرف کد برای همین برنده به‌صورت دستی ثبت شد؛ ووکامرس جداگانه باید بررسی شود.' : 'فقط پس از ارسال موفق می‌توان مصرف کد این برنده را ثبت کرد.' );
	}

	public function message(): never {
		self::authorize( 'dhikra_coupon_message' );
		$campaign_id = absint( $_POST['campaign_id'] ?? 0 ); $entry_id = absint( $_POST['reward_entry_id'] ?? 0 );
		$message = trim( sanitize_textarea_field( wp_unslash( $_POST['message_text'] ?? '' ) ) );
		if ( '' === $message || ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) > 4000 ) || strlen( $message ) > 16000 ) { self::redirect( $campaign_id, 'dhikra_error', 'متن پیام باید غیرخالی و کوتاه‌تر از ۴۰۰۰ نویسه باشد.' ); }
		$row = self::recipient( $campaign_id, $entry_id );
		if ( ! $row || empty( $row['allows_write_to_pm'] ) ) { self::redirect( $campaign_id, 'dhikra_error', 'برنده یافت نشد یا مجوز دریافت پیام ندارد.' ); }
		$sent = Dhikra_Audience::send_logged_message( absint( $row['audience_id'] ), (string) $row['eitaa_user_id'], $message, 'personal', $campaign_id );
		self::redirect( $campaign_id, 'dhikra_notice', 'sent' === ( $sent['status'] ?? '' ) ? 'پیام اختصاصی برای همین برنده ارسال شد.' : 'پیام ارسال نشد: ' . (string) ( $sent['error'] ?? $sent['status'] ?? '' ) );
	}

	private static function safe_csv( mixed $value ): string {
		$value = (string) ( $value ?? '' );
		return preg_match( '/^[\s]*[=+\-@]/u', $value ) ? "'" . $value : $value;
	}

	/** CSV includes one row per recipient, even when multiple recipients share the same coupon code. */
	public function export(): never {
		self::authorize( 'dhikra_coupon_export' );
		$campaign_id = absint( $_GET['campaign_id'] ?? 0 );
		if ( ! self::campaign( $campaign_id ) ) { wp_die( 'پویش یافت نشد.' ); }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' ); $e = Dhikra_Data::table( 'reward_entries' );
		$a = Dhikra_Data::table( 'audience' ); $m = Dhikra_Data::table( 'messages' );
		nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename=dhikra-coupons-' . $campaign_id . '.csv' );
		$out = fopen( 'php://output', 'w' ); if ( ! $out ) { exit; }
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'کد تخفیف','ظرفیت اهدای کد','شناسه برنده','شناسه ایتا','نام','زمان تخصیص','وضعیت پیام','زمان ارسال','آخرین خطا','انقضا','مصرف تأییدشده توسط مدیر' ) );
		$offset = 0; $batch_size = 500;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT c.coupon_code,c.allocation_limit,c.expires_at,e.id AS entry_id,e.won_at,e.pm_status,e.pm_sent_at,e.pm_last_error,e.coupon_redeemed_at,e.eitaa_user_id,a.first_name,a.last_name FROM {$c} c LEFT JOIN {$e} e ON e.coupon_id=c.id AND e.is_winner=1 AND e.campaign_id=c.campaign_id LEFT JOIN {$a} a ON a.id=e.audience_id WHERE c.campaign_id=%d ORDER BY c.id,e.id LIMIT %d OFFSET %d", $campaign_id, $batch_size, $offset
			), ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				fputcsv( $out, array_map( array( self::class, 'safe_csv' ), array( $row['coupon_code'],$row['allocation_limit'],$row['entry_id'],$row['eitaa_user_id'],trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ),$row['won_at'],$row['pm_status'],$row['pm_sent_at'],$row['pm_last_error'],$row['expires_at'],$row['coupon_redeemed_at'] ) ) );
			}
			$offset += $batch_size;
		} while ( is_array( $rows ) && count( $rows ) === $batch_size );
		fclose( $out ); exit;
	}

	private static function paginator( int $page, int $count, int $per_page, string $key, int $campaign_id ): void {
		if ( $count <= $per_page ) { return; }
		echo wp_kses_post( paginate_links( array( 'base' => esc_url_raw( add_query_arg( $key, '%#%', self::url( array( 'campaign_id' => $campaign_id ) ) ) ), 'format' => '', 'current' => $page, 'total' => (int) ceil( $count / $per_page ) ) ) );
	}

	public static function render( array $campaigns ): void {
		if ( ! current_user_can( 'manage_dhikra_rewards' ) ) { return; }
		global $wpdb;
		$campaigns = array_values( array_filter( $campaigns, static fn( $x ) => Dhikra_Coupons::is_coupon_campaign( $x ) ) );
		$ids = array_map( static fn( $x ) => absint( $x['id'] ), $campaigns );
		$requested = absint( $_GET['campaign_id'] ?? 0 );
		$saved = absint( get_user_meta( get_current_user_id(), '_dhikra_last_coupon_campaign', true ) );
		$campaign_id = in_array( $requested, $ids, true ) ? $requested : ( in_array( $saved, $ids, true ) ? $saved : ( $ids[0] ?? 0 ) );
		if ( $requested && $requested === $campaign_id ) { update_user_meta( get_current_user_id(), '_dhikra_last_coupon_campaign', $campaign_id ); }
		$campaign = self::campaign( $campaign_id );
		if ( ! Dhikra_Coupons::storage_ready( true ) ) { echo '<div class="notice notice-error inline"><p>ساختار بانک یا مهاجرت رکوردهای قبلی ناقص است. تا بررسی دیتابیس کد تازه وارد نکنید.</p></div>'; return; }
		$c = Dhikra_Data::table( 'reward_coupons' ); $e = Dhikra_Data::table( 'reward_entries' );
		$a = Dhikra_Data::table( 'audience' ); $m = Dhikra_Data::table( 'messages' );
		$totals = $wpdb->get_results( "SELECT campaign_id,COUNT(*) code_count,SUM(allocation_limit) total_slots FROM {$c} GROUP BY campaign_id", ARRAY_A );
        if ( ! is_array( $totals ) ) { echo '<div class="notice notice-error inline"><p>خواندن موجودی کدها از پایگاه داده انجام نشد. پیش از ورود دوباره کدها، اتصال و ساختار بانک را بررسی کنید.</p></div>'; return; }
		?><section class="dhikra-settings-card"><h2>بانک کدهای تخفیف</h2><p>هر کد فقط یک‌بار ثبت می‌شود. کد مشترک را می‌توان با ظرفیت مشخص برای چندین برنده استفاده کرد؛ مصرف واقعی را ووکامرس کنترل می‌کند.</p>
		<details><summary>موجودی همه پویش‌ها</summary><table class="widefat striped"><thead><tr><th>پویش</th><th>کدهای متفاوت</th><th>ظرفیت ثبت‌شده</th><th>ظرفیت آزاد معتبر</th><th></th></tr></thead><tbody>
		<?php foreach ( is_array( $totals ) ? $totals : array() as $t ) : $match = null; foreach ( $campaigns as $item ) { if ( absint( $item['id'] ) === absint( $t['campaign_id'] ) ) { $match = $item; break; } } ?>
		<tr><td><?php echo esc_html( $match['title'] ?? (string) $t['campaign_id'] ); ?></td><td><?php echo esc_html( (string) $t['code_count'] ); ?></td><td><?php echo esc_html( (string) $t['total_slots'] ); ?></td><td><?php echo esc_html( (string) Dhikra_Coupons::available_count( absint( $t['campaign_id'] ) ) ); ?></td><td><a class="button" href="<?php echo esc_url( self::url( array( 'campaign_id' => absint( $t['campaign_id'] ) ) ) ); ?>">باز کردن</a></td></tr>
		<?php endforeach; ?></tbody></table></details>
		<form method="get"><input type="hidden" name="post_type" value="dhikra_telangor"><input type="hidden" name="page" value="dhikra-rewards"><input type="hidden" name="tab" value="coupons"><label for="dhikra-select-campaign">پویش:</label> <select id="dhikra-select-campaign" name="campaign_id">
		<?php foreach ( $campaigns as $item ) : ?><option value="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php selected( $campaign_id, absint( $item['id'] ) ); ?>><?php echo esc_html( $item['title'] ); ?></option><?php endforeach; ?></select> <button class="button">نمایش</button></form>
		<?php if ( ! $campaign ) : ?><p>ابتدا از تب «ایجاد پویش»، پویشی با نوع کد تخفیف بسازید.</p></section><?php return; endif;
		$available = Dhikra_Coupons::available_count( $campaign_id );
		$used = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$e} WHERE campaign_id=%d AND coupon_id>0 AND is_winner=1", $campaign_id ) ) );
		?><p>ظرفیت آماده اهدای جایزه: <strong><?php echo esc_html( (string) $available ); ?></strong> نفر | اختصاص‌یافته: <strong><?php echo esc_html( (string) $used ); ?></strong> نفر | ظرفیت پویش: <?php echo esc_html( (string) $campaign['prize_count'] ); ?> جایزه</p>
		<?php if ( in_array( $campaign['status'], array( 'draft','active' ), true ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dhikra-coupon-form"><input type="hidden" name="action" value="dhikra_coupon_import"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><?php wp_nonce_field( 'dhikra_coupon_import' ); ?>
		<fieldset><legend><strong>روش ورود کد</strong></legend><p><label><input type="radio" name="coupon_mode" value="reusable" checked> یک کد مشترک برای چند برنده</label> &emsp; <label><input type="radio" name="coupon_mode" value="single_use"> چند کد متفاوت، هرکدام برای یک برنده</label></p>
		<div data-dhikra-coupon-mode="reusable"><p><label for="dhikra-shared-code">کد مشترک</label><br><input class="regular-text code" id="dhikra-shared-code" name="shared_code" dir="ltr" placeholder="zekra1"></p>
		<p><label for="dhikra-allocation-limit">تعداد اهدای این کد</label><br><input type="number" id="dhikra-allocation-limit" name="allocation_limit" min="1" max="100000" value="20"> نفر</p></div>
		<div data-dhikra-coupon-mode="single_use"><p><label for="dhikra-unique-codes">کدهای متفاوت (هر خط یک کد)</label><br><textarea class="large-text code" rows="5" id="dhikra-unique-codes" name="coupon_codes" dir="ltr" placeholder="zekra1&#10;zekra2&#10;zekra3"></textarea></p></div>
		<p><label>تاریخ انقضا (اختیاری، مطابق فروشگاه) <input type="date" name="expires_at"></label></p>
		<p class="description">در حالت مشترک، کد را یک‌بار و تعداد برندگان را جداگانه وارد کنید. کد تکراریِ همین پویش در حالت مشترک با ظرفیت جدید به‌روز می‌شود، بدون حذف سوابق قبلی. تکرار خطوط در حالت کدهای متفاوت سهمیه را افزایش نمی‌دهد.</p>
		<button class="button button-primary">ذخیره در بانک</button></fieldset></form><?php endif; ?></section>
		<?php
		$bank_page = max( 1, absint( $_GET['bankpage'] ?? 1 ) ); $per_page = 30;
		$bank_total = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE campaign_id=%d", $campaign_id ) ) );
		$bank_rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.*,COALESCE(u.used_count,0) used_count FROM {$c} c LEFT JOIN (SELECT coupon_id,COUNT(*) used_count FROM {$e} WHERE is_winner=1 AND coupon_id>0 GROUP BY coupon_id) u ON u.coupon_id=c.id WHERE c.campaign_id=%d ORDER BY c.id DESC LIMIT %d OFFSET %d", $campaign_id, $per_page, ( $bank_page - 1 ) * $per_page ), ARRAY_A );
        if ( ! is_array( $bank_rows ) ) { echo '<div class="notice notice-error inline"><p>خواندن کدهای ذخیره‌شده ناموفق بود؛ هیچ کدی حذف نشده است. وضعیت دیتابیس را بررسی کنید.</p></div>'; return; }
		?><section class="dhikra-settings-card"><h2>کدها و ظرفیت هر کد</h2><div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>کد</th><th>سهمیه</th><th>اختصاص‌یافته</th><th>باقی‌مانده</th><th>وضعیت / انقضا</th><th>مدیریت</th></tr></thead><tbody>
		<?php foreach ( is_array( $bank_rows ) ? $bank_rows : array() as $row ) :
		$stock = 'disabled' === $row['status'] || ( $row['expires_at'] && $row['expires_at'] < Dhikra_Audience::tehran_now()->format( 'Y-m-d' ) ) ? 0 : max( 0, absint( $row['allocation_limit'] ) - absint( $row['used_count'] ) ); ?>
		<tr><td dir="ltr"><code><?php echo esc_html( $row['coupon_code'] ); ?></code></td><td><?php echo esc_html( (string) $row['allocation_limit'] ); ?></td><td><?php echo esc_html( (string) $row['used_count'] ); ?></td><td><?php echo esc_html( (string) $stock ); ?></td><td><?php echo esc_html( 'disabled' === $row['status'] ? 'غیرفعال' : ( $stock > 0 ? 'قابل تخصیص' : 'تکمیل/غیرقابل تخصیص' ) ); ?><?php if ( $row['expires_at'] ) : ?><br><small><?php echo esc_html( $row['expires_at'] ); ?></small><?php endif; ?></td><td>
		<?php if ( 'disabled' !== $row['status'] && in_array( $campaign['status'], array( 'draft','active' ), true ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dhikra_coupon_limit"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php wp_nonce_field( 'dhikra_coupon_limit' ); ?><label>سهمیه <input type="number" name="allocation_limit" value="<?php echo esc_attr( (string) $row['allocation_limit'] ); ?>" min="<?php echo esc_attr( (string) max( 1, absint( $row['used_count'] ) ) ); ?>" max="100000" style="width:85px"></label> <button class="button">ثبت</button></form>
		<?php if ( 0 === absint( $row['used_count'] ) && 'available' === $row['status'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این کد غیرفعال شود؟');"><input type="hidden" name="action" value="dhikra_coupon_disable"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php wp_nonce_field( 'dhikra_coupon_disable' ); ?><button class="button">غیرفعال</button></form><?php endif; ?>
		<?php endif; ?></td></tr><?php endforeach; ?>
		<?php if ( empty( $bank_rows ) ) : ?><tr><td colspan="6">هنوز کدی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div>
		<?php self::paginator( $bank_page, $bank_total, $per_page, 'bankpage', $campaign_id ); ?></section>
		<?php
		$delivery_page = max( 1, absint( $_GET['deliverypage'] ?? 1 ) );
		$selected_entry = absint( $_GET['entry_id'] ?? 0 );
		$entry_filter = $selected_entry ? ' AND e.id=' . $selected_entry : '';
		$delivery_count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$e} e WHERE e.campaign_id=%d AND e.coupon_id>0 AND e.is_winner=1{$entry_filter}", $campaign_id ) ) );
		$deliveries = $wpdb->get_results( $wpdb->prepare( "SELECT e.id AS entry_id,e.won_at,e.pm_status,e.pm_sent_at,e.pm_last_error,e.pm_attempts,e.coupon_redeemed_at,e.eitaa_user_id,c.coupon_code,c.expires_at,a.first_name,a.last_name,a.allows_write_to_pm,m.id AS message_id,m.status AS message_status,m.attempts AS message_attempts,m.last_error AS message_error FROM {$e} e JOIN {$c} c ON c.id=e.coupon_id AND c.campaign_id=e.campaign_id LEFT JOIN {$a} a ON a.id=e.audience_id LEFT JOIN {$m} m ON m.id=(SELECT MAX(mm.id) FROM {$m} mm WHERE mm.reward_entry_id=e.id AND mm.message_type='winner') WHERE e.campaign_id=%d AND e.is_winner=1 AND e.coupon_id>0{$entry_filter} ORDER BY e.id DESC LIMIT %d OFFSET %d", $campaign_id, $per_page, ( $delivery_page - 1 ) * $per_page ), ARRAY_A );
        if ( ! is_array( $deliveries ) ) { echo '<div class="notice notice-error inline"><p>خواندن سوابق تحویل برندگان ناموفق بود. مجدداً کد وارد نکنید؛ اتصال پایگاه داده را بررسی کنید.</p></div>'; return; }
		?><section class="dhikra-settings-card"><h2>بانک جوایز و سوابق تحویل به هر برنده (<?php echo esc_html( (string) $delivery_count ); ?>)</h2><?php if ( $selected_entry ) : ?><p><a class="button" href="<?php echo esc_url( self::url( array( 'campaign_id' => $campaign_id ) ) ); ?>">نمایش همه برندگان</a></p><?php endif; ?><p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'dhikra_coupon_export','campaign_id' => $campaign_id ), admin_url( 'admin-post.php' ) ), 'dhikra_coupon_export' ) ); ?>">CSV کدها و برندگان</a></p><div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>کد</th><th>برنده</th><th>تخصیص</th><th>پیام ایتا</th><th>مصرف</th><th>پیگیری</th></tr></thead><tbody>
		<?php foreach ( is_array( $deliveries ) ? $deliveries : array() as $row ) : ?><tr><td dir="ltr"><code><?php echo esc_html( $row['coupon_code'] ); ?></code></td><td><?php echo esc_html( trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ) ?: '—' ); ?><br><code><?php echo esc_html( $row['eitaa_user_id'] ); ?></code></td><td><?php echo esc_html( $row['won_at'] ?: '—' ); ?></td><td><?php echo esc_html( $row['message_status'] ?: $row['pm_status'] ); ?><?php if ( $row['pm_sent_at'] ) : ?><br><?php echo esc_html( $row['pm_sent_at'] ); ?><?php elseif ( $row['message_error'] ?: $row['pm_last_error'] ) : ?><br><small><?php echo esc_html( $row['message_error'] ?: $row['pm_last_error'] ); ?></small><?php endif; ?></td><td><?php if ( $row['coupon_redeemed_at'] ) : ?><?php echo esc_html( $row['coupon_redeemed_at'] ); ?><?php elseif ( 'sent' === $row['pm_status'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('مصرف کد این برنده در ووکامرس بررسی شده است؟');"><input type="hidden" name="action" value="dhikra_coupon_redeem"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><input type="hidden" name="reward_entry_id" value="<?php echo esc_attr( (string) $row['entry_id'] ); ?>"><?php wp_nonce_field( 'dhikra_coupon_redeem' ); ?><button class="button">ثبت مصرف دستی</button></form><?php else : ?>—<?php endif; ?></td><td>
		<?php if ( 'failed' === $row['message_status'] && absint( $row['message_attempts'] ) >= 3 && ! empty( $row['allows_write_to_pm'] ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('ارسال مجدد بعد از خطای مبهم ممکن است پیام تکراری ایجاد کند. مطمئن هستید؟');"><input type="hidden" name="action" value="dhikra_coupon_retry"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><input type="hidden" name="reward_entry_id" value="<?php echo esc_attr( (string) $row['entry_id'] ); ?>"><?php wp_nonce_field( 'dhikra_coupon_retry' ); ?><button class="button">ارسال مجدد همان کد</button></form><?php endif; ?>
		<details><summary>پیام اختصاصی</summary><?php if ( empty( $row['allows_write_to_pm'] ) ) : ?><p>مجوز دریافت پیام فعال نیست.</p><?php else : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dhikra_coupon_message"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>"><input type="hidden" name="reward_entry_id" value="<?php echo esc_attr( (string) $row['entry_id'] ); ?>"><?php wp_nonce_field( 'dhikra_coupon_message' ); ?><textarea name="message_text" maxlength="4000" rows="3" cols="32" placeholder="پیام اختصاصی به این برنده" required></textarea><br><button class="button">ارسال</button></form><?php endif; ?></details></td></tr><?php endforeach; ?>
		<?php if ( empty( $deliveries ) ) : ?><tr><td colspan="6">هنوز کدی به برنده‌ای اختصاص داده نشده است.</td></tr><?php endif; ?></tbody></table></div>
		<?php self::paginator( $delivery_page, $delivery_count, $per_page, 'deliverypage', $campaign_id ); ?></section>
		<?php
	}
}
