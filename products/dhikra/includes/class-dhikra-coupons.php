<?php
/** Coupon bank: one unique WooCommerce code can have several Dhikra award slots. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Coupons {
	public const SHOP_URL = 'https://manahel.ir/shop/';

	/** Never mistake an old db_version option for a successfully upgraded schema. */
	public static function storage_ready( bool $repair = false ): bool {
		if ( self::storage_intact() ) { return $repair ? self::backfill_legacy_assignments() : true; }
		if ( ! $repair ) { return false; }
		Dhikra_Data::install_schema();
		if ( ! self::storage_intact() ) { return false; }
		return self::backfill_legacy_assignments();
	}

	private static function storage_intact(): bool {
		global $wpdb;
		$coupon_table = Dhikra_Data::table( 'reward_coupons' );
		$entry_table = Dhikra_Data::table( 'reward_entries' );
		foreach ( array( $coupon_table => array( 'id','campaign_id','coupon_code','allocation_limit','status','expires_at' ), $entry_table => array( 'id','campaign_id','coupon_id','coupon_redeemed_at','pm_status' ) ) as $table => $required ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( (string) $exists !== $table ) { return false; }
			$cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // Internal, allowlisted table name.
			if ( array_diff( $required, $cols ) ) { return false; }
		}
		$indexes = (array) $wpdb->get_results( "SHOW INDEX FROM {$coupon_table}", ARRAY_A );
		foreach ( $indexes as $index ) {
			if ( 'coupon_code' === (string) ( $index['Column_name'] ?? '' ) && 0 === (int) ( $index['Non_unique'] ?? 1 ) ) { return true; }
		}
		return false;
	}

	/** Non-destructive, repeatable migration: preserve legacy recipient and delivery rows. */
	public static function backfill_legacy_assignments(): bool {
		if ( ! self::storage_intact() ) { return false; }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$e = Dhikra_Data::table( 'reward_entries' );
		$updated = $wpdb->query( "UPDATE {$e} e INNER JOIN {$c} c ON c.reward_entry_id=e.id AND c.campaign_id=e.campaign_id SET e.coupon_id=c.id,e.coupon_redeemed_at=c.redeemed_at WHERE e.coupon_id=0 AND e.is_winner=1" ); // Internal table names only.
		if ( false === $updated ) { return false; }
		// If this count is nonzero, a legacy award did not link to its winner correctly.
		$missing = $wpdb->get_var( "SELECT COUNT(*) FROM {$c} c LEFT JOIN {$e} e ON e.id=c.reward_entry_id AND e.campaign_id=c.campaign_id WHERE c.reward_entry_id IS NOT NULL AND (e.id IS NULL OR e.is_winner<>1 OR e.coupon_id<>c.id)" );
		return null !== $missing && 0 === (int) $missing;
	}

	public static function is_coupon_campaign( array $campaign ): bool {
		return 'coupon' === (string) ( $campaign['prize_mode'] ?? 'message' );
	}

	/** This counts award slots, NOT the number of distinct WooCommerce coupon strings. */
	public static function available_count( int $campaign_id ): int {
		if ( $campaign_id <= 0 ) { return 0; }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$e = Dhikra_Data::table( 'reward_entries' );
		$today = Dhikra_Audience::tehran_now()->format( 'Y-m-d' );
		return absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(GREATEST(c.allocation_limit-COALESCE(u.used_count,0),0)),0) FROM {$c} c LEFT JOIN (SELECT coupon_id,COUNT(*) used_count FROM {$e} WHERE is_winner=1 AND coupon_id>0 GROUP BY coupon_id) u ON u.coupon_id=c.id WHERE c.campaign_id=%d AND c.status<>'disabled' AND (c.expires_at IS NULL OR c.expires_at >= %s)",
			$campaign_id, $today
		) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function assigned_count( int $coupon_id ): int {
		global $wpdb;
		$e = Dhikra_Data::table( 'reward_entries' );
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$e} WHERE coupon_id=%d AND is_winner=1", $coupon_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Called within the existing locked campaign transaction. Each reward entry has only one coupon_id. */
	public static function assign_locked( int $campaign_id, int $entry_id, int $audience_id ): array|WP_Error {
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$e = Dhikra_Data::table( 'reward_entries' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM {$c} c JOIN {$e} e ON e.coupon_id=c.id WHERE e.id=%d AND e.campaign_id=%d LIMIT 1 FOR UPDATE", $entry_id, $campaign_id ), ARRAY_A );
		if ( is_array( $existing ) ) { return $existing; }
		// Existing 1.6.9 awards can also be recovered before the background backfill.
		$legacy = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$c} WHERE reward_entry_id=%d AND campaign_id=%d LIMIT 1 FOR UPDATE", $entry_id, $campaign_id ), ARRAY_A );
		if ( is_array( $legacy ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$e} SET coupon_id=%d WHERE id=%d AND campaign_id=%d AND coupon_id=0", absint( $legacy['id'] ), $entry_id, $campaign_id ) );
			return $legacy;
		}
		$today = Dhikra_Audience::tehran_now()->format( 'Y-m-d' );
		$excluded = array();
		// The coupon row lock serializes concurrent awards of the *same* reusable code.
		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$except = $excluded ? ' AND c.id NOT IN (' . implode( ',', array_map( 'absint', $excluded ) ) . ')' : '';
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT c.* FROM {$c} c LEFT JOIN (SELECT coupon_id,COUNT(*) used_count FROM {$e} WHERE is_winner=1 AND coupon_id>0 GROUP BY coupon_id) u ON u.coupon_id=c.id WHERE c.campaign_id=%d AND c.status<>'disabled' AND (c.expires_at IS NULL OR c.expires_at >= %s) AND c.allocation_limit>COALESCE(u.used_count,0){$except} ORDER BY c.id ASC LIMIT 1 FOR UPDATE",
				$campaign_id, $today
			), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! is_array( $row ) ) { return new WP_Error( 'dhikra_coupon_out_of_stock', 'ظرفیت کدهای این پویش تمام شده است.' ); }
			$used = self::assigned_count( absint( $row['id'] ) );
			if ( $used >= absint( $row['allocation_limit'] ) ) { $excluded[] = absint( $row['id'] ); continue; }
			$changed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$e} SET coupon_id=%d WHERE id=%d AND campaign_id=%d AND audience_id=%d AND is_winner=1 AND coupon_id=0",
				absint( $row['id'] ), $entry_id, $campaign_id, $audience_id
			) );
			if ( 1 !== (int) $changed ) { return new WP_Error( 'dhikra_coupon_assignment_conflict', 'اختصاص این جایزه به‌دلیل درخواست تکراری یا خطای دیتابیس انجام نشد.' ); }
			return $row;
		}
		return new WP_Error( 'dhikra_coupon_busy', 'بانک کدها موقتاً شلوغ است؛ بعداً دوباره تلاش کنید.' );
	}

	public static function for_entry( int $entry_id ): ?array {
		if ( $entry_id <= 0 ) { return null; }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$e = Dhikra_Data::table( 'reward_entries' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM {$e} e JOIN {$c} c ON c.id=e.coupon_id AND c.campaign_id=e.campaign_id WHERE e.id=%d AND e.is_winner=1 LIMIT 1", $entry_id ), ARRAY_A );
		if ( ! is_array( $row ) ) { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$c} WHERE reward_entry_id=%d LIMIT 1", $entry_id ), ARRAY_A ); }
		return is_array( $row ) ? $row : null;
	}

	public static function winner_message( array $campaign, array $coupon ): string {
		$intro = trim( (string) ( $campaign['winner_pm_message'] ?? '' ) );
		if ( '' === $intro ) { $intro = 'تبریک! شما برنده کد تخفیف فروشگاه مناهل شدید. 🌱'; }
		return $intro . "\n\n" . '🎁 کد تخفیف شما: ' . (string) $coupon['coupon_code'] . "\n\n" . '🛍 فروشگاه مناهل: ' . self::SHOP_URL;
	}

	public static function entry_message( array $campaign, int $entry_id ): string|WP_Error {
		if ( ! self::is_coupon_campaign( $campaign ) ) { return (string) ( $campaign['winner_pm_message'] ?? '' ); }
		$coupon = self::for_entry( $entry_id );
		if ( ! $coupon || absint( $coupon['campaign_id'] ) !== absint( $campaign['id'] ) ) { return new WP_Error( 'dhikra_coupon_missing', 'کد مربوط به برنده یافت نشد؛ ارسال متوقف شد.' ); }
		if ( ! empty( $coupon['expires_at'] ) && (string) $coupon['expires_at'] < Dhikra_Audience::tehran_now()->format( 'Y-m-d' ) ) { return new WP_Error( 'dhikra_coupon_expired', 'کد برنده منقضی شده است؛ مدیر باید بررسی کند.' ); }
		return self::winner_message( $campaign, $coupon );
	}

	/** Legacy bank columns retained as historic snapshot; queue ownership is reward_entry_id. */
	public static function bind_message( int $entry_id, int $message_id ): void {
		if ( $entry_id <= 0 || $message_id <= 0 ) { return; }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$c} SET message_id=%d WHERE reward_entry_id=%d AND (message_id IS NULL OR message_id=%d)", $message_id, $entry_id, $message_id ) );
	}

	public static function sync_message( int $entry_id, string $status, ?string $sent_at ): void {
		if ( $entry_id <= 0 || 'sent' !== $status ) { return; }
		global $wpdb;
		$c = Dhikra_Data::table( 'reward_coupons' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$c} SET status='sent',delivered_at=%s WHERE reward_entry_id=%d AND status IN ('assigned','sent')", $sent_at ?: current_time( 'mysql', true ), $entry_id ) );
	}

	public static function normalize_code( string $code ): string {
		$code = trim( sanitize_text_field( $code ) );
		if ( '' === $code || strlen( $code ) > 190 || preg_match( '/[\x00-\x1F\x7F\s]/u', $code ) ) { return ''; }
		return $code;
	}
}
