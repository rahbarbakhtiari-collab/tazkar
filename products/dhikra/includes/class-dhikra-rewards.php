<?php
/** Digital reward engine, independent from printing. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Rewards {
	public function register(): void {}
	public static function frontend_notice(): array { return array( '', 'info' ); }

	public static function install_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( $role instanceof WP_Role ) { $role->add_cap( 'manage_dhikra_rewards' ); }
	}

	public static function campaign_topic_ids( array $campaign ): array {
		if ( ! empty( $campaign['all_topics'] ) ) { return Dhikra_Data::active_topic_ids(); }
		$decoded = json_decode( (string) ( $campaign['topic_ids'] ?? '' ), true );
		return array_values( array_intersect( Dhikra_Data::active_topic_ids(), array_filter( array_map( 'absint', is_array( $decoded ) ? $decoded : array() ) ) ) );
	}

	public static function first_card_for_active_campaign( array $recent_codes = array() ): int {
		$campaign = Dhikra_Data::active_reward_campaign();
		return empty( $campaign ) ? 0 : Dhikra_Random::next_for_topics( self::campaign_topic_ids( $campaign ), $recent_codes );
	}

	/** The first card counts as one visit, but a draw is only made on a later Next action. */
	public static function browse_range( array $campaign ): array {
		$minimum = max( 2, min( 100, absint( $campaign['min_browse_cards'] ?? 4 ) ) );
		$maximum = max( $minimum, min( 100, absint( $campaign['max_browse_cards'] ?? 8 ) ) );
		return array( $minimum, $maximum );
	}

	private static function can_send_winner( array $campaign ): bool {
		return Dhikra_Coupons::is_coupon_campaign( $campaign ) || '' !== trim( (string) ( $campaign['winner_pm_message'] ?? '' ) );
	}

	/** Use the immutable coupon bound to this entry, including on permission retry. */
	public static function dispatch_winner( array $campaign, int $entry_id, int $audience_id, string $eitaa_user_id, bool $fresh_permission = false ): array {
		$message = Dhikra_Coupons::entry_message( $campaign, $entry_id );
		if ( is_wp_error( $message ) ) { return array( 'status' => 'failed', 'error' => $message->get_error_message() ); }
		if ( '' === trim( $message ) ) { return array( 'status' => 'not_required' ); }
		$delivery = Dhikra_Audience::send_logged_message( $audience_id, $eitaa_user_id, $message, 'winner', absint( $campaign['id'] ), $entry_id, $fresh_permission );
		if ( Dhikra_Coupons::is_coupon_campaign( $campaign ) && ! empty( $delivery['message_id'] ) ) {
			Dhikra_Coupons::bind_message( $entry_id, absint( $delivery['message_id'] ) );
		}
		return $delivery;
	}

	/** Count published cards in the campaign; never promise a draw for a tiny pool. */
	public static function eligible_card_count( array $topic_ids ): int {
		if ( empty( $topic_ids ) ) { return 0; }
		$ids = get_posts( array(
			'post_type' => 'dhikra_telangor', 'post_status' => 'publish',
			'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true,
			'update_post_meta_cache' => false, 'update_post_term_cache' => false,
			'meta_query' => array( array( 'key' => '_dhikra_main_topic_id', 'value' => array_values( $topic_ids ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ),
		) );
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/** Unique cards already seen by an Eitaa audience in the current active campaign. */
	public static function seen_cards( int $audience_id ): array {
		$campaign = Dhikra_Data::active_reward_campaign();
		if ( empty( $campaign ) || $audience_id <= 0 ) { return array(); }
		global $wpdb;
		$progress = Dhikra_Data::table( 'reward_progress' );
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT seen_post_ids FROM {$progress} WHERE campaign_id = %d AND audience_id = %d", absint( $campaign['id'] ), $audience_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = json_decode( (string) $raw, true );
		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
	}

	/**
	 * Record only cards chosen by our server. No prize is drawn on launch;
	 * each audience gets exactly one draw after 4–8 DIFFERENT campaign cards.
	 * Row locks serialize concurrent requests and the final draw remains inside
	 * register_audience(), which locks the campaign and enforces prize capacity.
	 */
	public static function browse_card( int $audience_id, string $eitaa_user_id, int $user_id, int $card_post_id, bool $allows_write_to_pm, bool $is_launch = false ): array|WP_Error {
		if ( $audience_id <= 0 || $user_id <= 0 || '' === $eitaa_user_id ) { return array( 'eligible' => false ); }
		$campaign = Dhikra_Data::active_reward_campaign();
		if ( empty( $campaign ) ) { return array( 'eligible' => false ); }
		$topic_ids = self::campaign_topic_ids( $campaign );
		$card_topic_id = absint( get_post_meta( $card_post_id, '_dhikra_main_topic_id', true ) );
		if ( $card_post_id <= 0 || 'publish' !== get_post_status( $card_post_id ) || ! in_array( $card_topic_id, $topic_ids, true ) ) {
			return array( 'eligible' => false );
		}
		global $wpdb;
		$campaign_id = absint( $campaign['id'] );
		$campaigns = Dhikra_Data::table( 'reward_campaigns' );
		$entries = Dhikra_Data::table( 'reward_entries' );
		$progress = Dhikra_Data::table( 'reward_progress' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		// Include the configured browsing range; id/status alone would silently use 4–8 for every campaign.
		$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns} WHERE id = %d FOR UPDATE", $campaign_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $locked ) || 'active' !== (string) $locked['status'] ) { $wpdb->query( 'ROLLBACK' ); return array( 'eligible' => false ); }
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$entries} WHERE campaign_id = %d AND audience_id = %d LIMIT 1", $campaign_id, $audience_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $existing ) ) {
			$wpdb->query( 'COMMIT' );
			// Existing entries (including legacy winners) never receive a second draw.
			return $is_launch ? self::result_payload( $campaign, $existing, false, $allows_write_to_pm ) : array( 'eligible' => true, 'draw_completed' => true );
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$progress} WHERE campaign_id = %d AND audience_id = %d FOR UPDATE", $campaign_id, $audience_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$now = current_time( 'mysql', true );
		if ( ! is_array( $row ) ) {
			$pool_count = self::eligible_card_count( $topic_ids );
			list( $min_cards, $max_cards ) = self::browse_range( $locked );
			if ( $pool_count < $max_cards ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'eligible' => false, 'insufficient_cards' => true );
			}
			$target = random_int( $min_cards, $max_cards );
			$inserted = $wpdb->insert( $progress, array( 'campaign_id' => $campaign_id, 'audience_id' => $audience_id, 'seen_post_ids' => wp_json_encode( array( $card_post_id ) ), 'reveal_after' => $target, 'created_at' => $now, 'updated_at' => $now ), array( '%d', '%d', '%s', '%d', '%s', '%s' ) );
			if ( false === $inserted ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'dhikra_reward_progress_failed', __( 'ثبت پیشرفت کارت‌ها انجام نشد.', 'dhikra' ) ); }
			$wpdb->query( 'COMMIT' );
			return array( 'eligible' => true, 'browsing' => true );
		}
		// Reopening the application must never advance browsing or earn a prize.
		// Only an explicit server-side New Card request can advance progress.
		if ( $is_launch ) { $wpdb->query( 'COMMIT' ); return array( 'eligible' => true, 'browsing' => true ); }
		$seen = json_decode( (string) $row['seen_post_ids'], true );
		$seen = is_array( $seen ) ? array_values( array_unique( array_filter( array_map( 'absint', $seen ) ) ) ) : array();
		if ( ! in_array( $card_post_id, $seen, true ) ) {
			$seen[] = $card_post_id;
			$updated = $wpdb->update( $progress, array( 'seen_post_ids' => wp_json_encode( $seen ), 'updated_at' => $now ), array( 'campaign_id' => $campaign_id, 'audience_id' => $audience_id ), array( '%s', '%s' ), array( '%d', '%d' ) );
			if ( false === $updated ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'dhikra_reward_progress_failed', __( 'به‌روزرسانی پیشرفت کارت‌ها انجام نشد.', 'dhikra' ) ); }
		}
		$ready = count( $seen ) >= max( 2, absint( $row['reveal_after'] ) );
		$wpdb->query( 'COMMIT' );
		// Even an already-met target never awards on opening/refreshing the app.
		if ( ! $ready || $is_launch ) { return array( 'eligible' => true, 'browsing' => true ); }
		return self::register_audience( $audience_id, $eitaa_user_id, $card_post_id, $allows_write_to_pm, $user_id );
	}

	/** Assign once, only after an eligible browsing milestone. */
	public static function register_audience( int $audience_id, string $eitaa_user_id, int $card_post_id = 0, bool $allows_write_to_pm = false, int $user_id = 0 ): array|WP_Error {
		if ( $audience_id <= 0 || '' === $eitaa_user_id ) { return new WP_Error( 'dhikra_reward_invalid_audience', __( 'مخاطب معتبر نیست.', 'dhikra' ) ); }
		$campaign = Dhikra_Data::active_reward_campaign();
		if ( empty( $campaign ) ) { return array( 'eligible' => false ); }

		$topic_ids = self::campaign_topic_ids( $campaign );
		if ( empty( $topic_ids ) ) { return new WP_Error( 'dhikra_reward_empty_scope', __( 'برای دسته‌های این پویش کارت منتشرشده‌ای وجود ندارد.', 'dhikra' ) ); }
		$card_topic_id = $card_post_id > 0 ? absint( get_post_meta( $card_post_id, '_dhikra_main_topic_id', true ) ) : 0;
		if ( $card_post_id <= 0 || 'publish' !== get_post_status( $card_post_id ) || ! in_array( $card_topic_id, $topic_ids, true ) ) {
			$card_post_id = Dhikra_Random::random_post_id_for_topics( $topic_ids );
		}
		if ( $card_post_id <= 0 ) { return new WP_Error( 'dhikra_reward_no_card', __( 'کارت معتبری برای ثبت مشارکت این پویش وجود ندارد.', 'dhikra' ) ); }

		global $wpdb;
		$campaigns   = Dhikra_Data::table( 'reward_campaigns' );
		$entries     = Dhikra_Data::table( 'reward_entries' );
		$campaign_id = absint( $campaign['id'] );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns} WHERE id = %d FOR UPDATE", $campaign_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $locked ) || 'active' !== (string) $locked['status'] ) { $wpdb->query( 'ROLLBACK' ); return array( 'eligible' => false ); }

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$entries} WHERE campaign_id = %d AND audience_id = %d LIMIT 1", $campaign_id, $audience_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $existing ) ) {
			$wpdb->query( 'COMMIT' );
			$result = self::result_payload( $locked, $existing, false, $allows_write_to_pm );
			if ( ! empty( $existing['is_winner'] ) && self::can_send_winner( $locked ) && $allows_write_to_pm && 'sent' !== (string) ( $existing['pm_status'] ?? '' ) ) {
				$delivery = self::dispatch_winner( $locked, absint( $existing['id'] ), $audience_id, $eitaa_user_id );
				$result['pm_status'] = (string) $delivery['status'];
				$result['needs_write_access'] = false;
			}
			return $result;
		}

		$entry_count  = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id = %d", $campaign_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$winner_count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id = %d AND is_winner = 1", $campaign_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$capacity     = absint( $locked['capacity'] );
		$prize_count  = absint( $locked['prize_count'] );
		if ( $entry_count >= $capacity ) {
			$wpdb->update( $campaigns, array( 'status' => 'completed', 'closed_at' => current_time( 'mysql', true ) ), array( 'id' => $campaign_id ), array( '%s', '%s' ), array( '%d' ) );
			$wpdb->query( 'COMMIT' );
			return array( 'eligible' => false, 'capacity_reached' => true );
		}

		$remaining_slots  = $capacity - $entry_count;
		$remaining_prizes = max( 0, $prize_count - $winner_count );
		$is_winner        = $remaining_prizes > 0 && random_int( 1, $remaining_slots ) <= $remaining_prizes;
		// Do not promise a coupon if it was disabled or expired since activation.
		if ( $is_winner && Dhikra_Coupons::is_coupon_campaign( $locked ) && Dhikra_Coupons::available_count( $campaign_id ) < 1 ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'eligible' => true, 'browsing' => true, 'prize_unavailable' => true );
		}
		$sequence         = $entry_count + 1;
		$now              = current_time( 'mysql', true );
		$pm_status        = $is_winner && self::can_send_winner( $locked ) ? ( $allows_write_to_pm ? 'queued' : 'awaiting_permission' ) : 'not_required';
		$inserted         = $wpdb->insert(
			$entries,
			array( 'campaign_id' => $campaign_id, 'user_id' => $user_id > 0 ? $user_id : $audience_id, 'audience_id' => $audience_id, 'eitaa_user_id' => $eitaa_user_id, 'card_post_id' => $card_post_id, 'sequence_no' => $sequence, 'is_winner' => $is_winner ? 1 : 0, 'created_at' => $now, 'won_at' => $is_winner ? $now : null, 'pm_status' => $pm_status ),
			array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'dhikra_reward_insert_failed', __( 'ثبت مشارکت جایزه انجام نشد.', 'dhikra' ) ); }
		$entry_id = absint( $wpdb->insert_id );
		if ( $is_winner && Dhikra_Coupons::is_coupon_campaign( $locked ) ) {
			$coupon = Dhikra_Coupons::assign_locked( $campaign_id, $entry_id, $audience_id );
			if ( is_wp_error( $coupon ) ) { $wpdb->query( 'ROLLBACK' ); return $coupon; }
		}
		$entry = array( 'id' => $entry_id, 'campaign_id' => $campaign_id, 'audience_id' => $audience_id, 'eitaa_user_id' => $eitaa_user_id, 'card_post_id' => $card_post_id, 'sequence_no' => $sequence, 'is_winner' => $is_winner ? 1 : 0, 'won_at' => $is_winner ? $now : null, 'pm_status' => $pm_status );
		if ( $sequence >= $capacity ) { $wpdb->update( $campaigns, array( 'status' => 'completed', 'closed_at' => $now ), array( 'id' => $campaign_id ), array( '%s', '%s' ), array( '%d' ) ); }
		$wpdb->query( 'COMMIT' );

		$result = self::result_payload( $locked, $entry, true, $allows_write_to_pm );
		if ( $is_winner && self::can_send_winner( $locked ) && $allows_write_to_pm ) {
			$delivery = self::dispatch_winner( $locked, $entry_id, $audience_id, $eitaa_user_id );
			$result['pm_status'] = (string) $delivery['status'];
		}
		return $result;
	}

	public static function deliver_pending_winner_messages( int $audience_id, string $eitaa_user_id ): array {
		if ( $audience_id <= 0 || '' === $eitaa_user_id ) { return array(); }
		global $wpdb;
		$entries   = Dhikra_Data::table( 'reward_entries' );
		$campaigns = Dhikra_Data::table( 'reward_campaigns' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.id AS entry_id, e.campaign_id, e.pm_status, c.* FROM {$entries} e INNER JOIN {$campaigns} c ON c.id = e.campaign_id WHERE e.audience_id = %d AND e.eitaa_user_id = %s AND e.is_winner = 1 AND (c.prize_mode='coupon' OR (c.winner_pm_message IS NOT NULL AND c.winner_pm_message <> '')) AND e.pm_status <> 'sent' ORDER BY e.id ASC LIMIT 10",
				$audience_id,
				$eitaa_user_id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deliveries = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$delivery = self::dispatch_winner( $row, absint( $row['entry_id'] ), $audience_id, $eitaa_user_id, true );
			$deliveries[] = array( 'reward_entry_id' => absint( $row['entry_id'] ), 'status' => (string) ( $delivery['status'] ?? 'failed' ), 'error' => (string) ( $delivery['error'] ?? '' ) );
		}
		return $deliveries;
	}

	private static function result_payload( array $campaign, array $entry, bool $created, bool $allows_write_to_pm = false ): array {
		$card_post_id = absint( $entry['card_post_id'] ?? 0 );
		$display_post_id = $created ? $card_post_id : Dhikra_Random::next_for_request();
		$is_winner = ! empty( $entry['is_winner'] );
		$has_pm    = $is_winner && self::can_send_winner( $campaign );
		$pm_status = (string) ( $entry['pm_status'] ?? ( $has_pm ? 'awaiting_permission' : 'not_required' ) );
		return array(
			'eligible' => true, 'created' => $created, 'campaign_id' => absint( $campaign['id'] ), 'campaign_title' => (string) $campaign['title'],
			'sequence_no' => absint( $entry['sequence_no'] ?? 0 ), 'capacity' => absint( $campaign['capacity'] ), 'is_winner' => $is_winner,
			'winner_message' => $is_winner ? (string) ( $campaign['winner_app_message'] ?: ( Dhikra_Coupons::is_coupon_campaign( $campaign ) ? 'تبریک! کد تخفیف اختصاصی شما برای دریافت در پیام خصوصی ایتا آماده است.' : __( 'تبریک! شما برنده یکی از جوایز این پویش شدید.', 'dhikra' ) ) ) : '',
			'winner_cta_label' => (string) ( $campaign['winner_cta_label'] ?? '' ), 'winner_cta_url' => esc_url_raw( (string) ( $campaign['winner_cta_url'] ?? '' ) ),
			'pm_status' => $pm_status,
			'needs_write_access' => $has_pm && ! $allows_write_to_pm && 'sent' !== $pm_status,
			'card_post_id' => $card_post_id, 'card_title' => $card_post_id > 0 ? get_the_title( $card_post_id ) : '',
			'card_html' => $display_post_id > 0 ? Dhikra_Random::render_card( $display_post_id ) : '',
		);
	}
}
