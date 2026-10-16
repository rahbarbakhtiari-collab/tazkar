<?php
/** Central audience registry and reliable Eitaa message queue. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Audience {
	public function register(): void {
		add_action( 'dhikra_daily_telangor_dispatch', array( $this, 'dispatch_daily_batch' ) );
		add_action( 'dhikra_daily_telangor_dispatch', array( $this, 'retry_failed_messages' ), 20 );
		add_action( 'dhikra_personal_reminder_send', array( $this, 'send_personal_reminder' ), 10, 4 );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'ensure_cron' ) );
	}

	public function cron_schedules( array $schedules ): array {
		$schedules['dhikra_five_minutes'] = array( 'interval' => 300, 'display' => 'Dhikra every five minutes' );
		return $schedules;
	}

	public function ensure_cron(): void {
		if ( ! wp_next_scheduled( 'dhikra_daily_telangor_dispatch' ) ) {
			// Five-minute heartbeat: the due-hour check uses Asia/Tehran.
			$next = (int) ( ceil( ( time() + 1 ) / 300 ) * 300 );
			wp_schedule_event( $next, 'dhikra_five_minutes', 'dhikra_daily_telangor_dispatch' );
		}
	}

	public static function upsert( array $identity ): array|WP_Error {
		$id = (string) ( $identity['eitaa_user_id'] ?? '' );
		if ( '' === $id ) { return new WP_Error( 'dhikra_audience_invalid', __( 'شناسهٔ مخاطب معتبر نیست.', 'dhikra' ) ); }
		global $wpdb;
		$table = Dhikra_Data::table( 'audience' );
		$now   = current_time( 'mysql', true );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE eitaa_user_id = %s LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data  = array( 'first_name' => (string) ( $identity['first_name'] ?? '' ), 'last_name' => (string) ( $identity['last_name'] ?? '' ), 'language_code' => (string) ( $identity['language_code'] ?? '' ), 'allows_write_to_pm' => ! empty( $identity['allows_write_to_pm'] ) ? 1 : 0, 'last_seen_at' => $now );
		if ( is_array( $row ) ) {
			// A fresh native permission callback can be newer than this launch's
			// signed initData. Preserve it until a *later* signed launch reports
			// the grant was revoked (or the real API rejects delivery).
			$callback_at = ! empty( $row['write_access_callback_at'] ) ? strtotime( (string) $row['write_access_callback_at'] . ' UTC' ) : 0;
			$signed_at   = absint( $identity['auth_date'] ?? 0 );
			if ( $callback_at && ! empty( $row['allows_write_to_pm'] ) && $signed_at <= $callback_at ) {
				$data['allows_write_to_pm'] = 1;
			} elseif ( $callback_at && $signed_at > $callback_at ) {
				$data['write_access_callback_at'] = null;
			}
			$formats = array( '%s', '%s', '%s', '%d', '%s' );
			if ( array_key_exists( 'write_access_callback_at', $data ) ) { $formats[] = '%s'; }
			$wpdb->update( $table, $data, array( 'id' => absint( $row['id'] ) ), $formats, array( '%d' ) );
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $row['id'] ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$data['eitaa_user_id'] = $id; $data['first_seen_at'] = $now; $data['daily_enabled'] = 1; $data['daily_topic_ids'] = '[]';
		$ok = $wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' ) );
		if ( false === $ok ) { return new WP_Error( 'dhikra_audience_insert_failed', __( 'ثبت مخاطب انجام نشد.', 'dhikra' ) ); }
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $wpdb->insert_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function set_write_access( int $audience_id, bool $granted ): bool {
		if ( $audience_id <= 0 ) { return false; }
		global $wpdb;
		return false !== $wpdb->update(
			Dhikra_Data::table( 'audience' ),
			array( 'allows_write_to_pm' => $granted ? 1 : 0, 'write_access_callback_at' => $granted ? current_time( 'mysql', true ) : null, 'last_seen_at' => current_time( 'mysql', true ) ),
			array( 'id' => $audience_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/** Global daily delivery time, controlled only by administrators. */
	public static function daily_send_time(): string {
		$value = trim( (string) Dhikra_Helpers::setting( 'daily_send_time', '08:00' ) );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '08:00';
	}

	/** Ensure the default-on daily preference without recording a manual choice. */
	public static function ensure_daily_default( int $audience_id ): bool {
		if ( $audience_id <= 0 ) { return false; }
		global $wpdb;
		return false !== $wpdb->update(
			Dhikra_Data::table( 'audience' ),
			array( 'daily_enabled' => 1, 'daily_topic_ids' => '[]' ),
			array( 'id' => $audience_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/** Enable/disable the user's subscription by explicit user choice. */
	public static function set_daily( int $audience_id, bool $enabled ): bool {
		if ( $audience_id <= 0 ) { return false; }
		global $wpdb;
		return false !== $wpdb->update(
			Dhikra_Data::table( 'audience' ),
			array(
				'daily_enabled'           => $enabled ? 1 : 0,
				'daily_topic_ids'         => '[]',
				'daily_prompt_decided_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $audience_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function decline_daily_prompt( int $audience_id ): bool {
		if ( $audience_id <= 0 ) { return false; }
		global $wpdb;
		return false !== $wpdb->update( Dhikra_Data::table( 'audience' ), array( 'daily_prompt_decided_at' => current_time( 'mysql', true ) ), array( 'id' => $audience_id ), array( '%s' ), array( '%d' ) );
	}

	public static function send_logged_message( int $audience_id, string $eitaa_user_id, string $message, string $type, int $campaign_id = 0, int $reward_entry_id = 0, bool $permission_just_granted = false, ?string $daily_date = null ): array {
		global $wpdb;
		$table = Dhikra_Data::table( 'messages' );
		$type  = sanitize_key( $type );
		$daily_date = 'daily' === $type && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $daily_date ) ? $daily_date : null;
		$now   = current_time( 'mysql', true );
		$dedupe_key = $reward_entry_id > 0 && 'winner' === $type ? 'winner:' . $reward_entry_id : null;

		// A reward must never create duplicate outbound messages when permission
		// is granted after the winner was already registered.
		if ( $reward_entry_id > 0 ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE reward_entry_id = %d AND message_type = %s ORDER BY id DESC LIMIT 1", $reward_entry_id, $type ),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( is_array( $existing ) ) {
				if ( 'sent' === (string) $existing['status'] ) {
					return array( 'status' => 'sent', 'attempts' => absint( $existing['attempts'] ), 'sent_at' => (string) $existing['sent_at'], 'message_id' => absint( $existing['id'] ) );
				}
				if ( 'failed' === (string) $existing['status'] && ! $permission_just_granted ) {
					return array( 'status' => 'failed', 'attempts' => absint( $existing['attempts'] ), 'error' => (string) $existing['last_error'], 'message_id' => absint( $existing['id'] ) );
				}
				// A newly granted permission is a meaningful state change. Start a
				// fresh retry cycle because older failures may only reflect denial.
				if ( $permission_just_granted && 'failed' === (string) $existing['status'] ) {
					$wpdb->update( $table, array( 'status' => 'queued', 'attempts' => 0, 'last_error' => '', 'updated_at' => $now, 'next_attempt_at' => $now ), array( 'id' => absint( $existing['id'] ) ), array( '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );
				}
				$result = self::attempt_message( absint( $existing['id'] ) );
				$result['message_id'] = absint( $existing['id'] );
				return $result;
			}
		}

		$inserted = $wpdb->insert( $table, array( 'audience_id' => $audience_id, 'campaign_id' => $campaign_id, 'reward_entry_id' => $reward_entry_id, 'dedupe_key' => $dedupe_key, 'eitaa_user_id' => $eitaa_user_id, 'message_type' => $type, 'daily_send_date' => $daily_date, 'message_text' => $message, 'status' => 'queued', 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now, 'next_attempt_at' => $now ), array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );
		if ( false === $inserted ) {
			if ( $dedupe_key ) {
				$existing_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_key=%s", $dedupe_key ) ) );
				if ( $existing_id ) { return array( 'status' => 'already_queued', 'message_id' => $existing_id ); }
			}
			return array( 'status' => 'failed', 'error' => __( 'ثبت پیام در صف انجام نشد یا پیام این روز قبلاً ثبت شده است.', 'dhikra' ) );
		}
		$message_id = absint( $wpdb->insert_id );
		$result = self::attempt_message( $message_id );
		$result['message_id'] = $message_id;
		return $result;
	}

	public static function attempt_queued_message( int $message_id ): array { return self::attempt_message( $message_id ); }

	private static function attempt_message( int $message_id ): array {
		global $wpdb;
		$table = Dhikra_Data::table( 'messages' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $message_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) ) { return array( 'status' => 'failed', 'error' => __( 'رکورد پیام پیدا نشد.', 'dhikra' ) ); }
		if ( 'daily' === (string) $row['message_type'] && ! empty( $row['daily_send_date'] ) && (string) $row['daily_send_date'] !== self::tehran_now()->format( 'Y-m-d' ) ) {
			$wpdb->update( $table, array( 'status' => 'failed', 'last_error' => 'Daily reminder expired at end of scheduled Tehran date.', 'next_attempt_at' => null ), array( 'id' => $message_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			return array( 'status' => 'expired' );
		}
		if ( absint( $row['broadcast_id'] ?? 0 ) > 0 ) {
			$broadcast_status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Dhikra_Data::table( 'broadcasts' ) . ' WHERE id=%d', absint( $row['broadcast_id'] ) ) );
			if ( 'cancelled' === $broadcast_status || ! in_array( (string) $row['status'], array( 'queued', 'failed' ), true ) ) { return array( 'status' => 'cancelled' ); }
		}
		// Atomic claim prevents a retry worker and a dispatch worker from sending
		// the same message at the same time. Ambiguous interrupted sends remain
		// in the sending state for manual reconciliation; blind auto-retry could
		// duplicate a message already accepted by the provider.
		$now = current_time( 'mysql', true );
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status='sending', updated_at=%s WHERE id=%d AND status IN ('queued','failed')",
			$now, $message_id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 !== (int) $claimed ) { return array( 'status' => 'already_processing' ); }
		$attempts = absint( $row['attempts'] ) + 1;
		if ( absint( $row['broadcast_id'] ?? 0 ) > 0 ) {
			$state = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Dhikra_Data::table( 'broadcasts' ) . ' WHERE id=%d', absint( $row['broadcast_id'] ) ) );
			if ( 'cancelled' === $state ) {
				$wpdb->update( $table, array( 'status' => 'cancelled' ), array( 'id' => $message_id ), array( '%s' ), array( '%d' ) );
				return array( 'status' => 'cancelled' );
			}
		}
		$sent     = Dhikra_Eitaa::send_message( (string) $row['eitaa_user_id'], (string) $row['message_text'] );
		$now      = current_time( 'mysql', true );
		if ( true === $sent ) {
			$wpdb->update( $table, array( 'status' => 'sent', 'attempts' => $attempts, 'last_error' => '', 'updated_at' => $now, 'sent_at' => $now, 'next_attempt_at' => null ), array( 'id' => $message_id ), array( '%s', '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			self::sync_delivery( $row, 'sent', $attempts, '', $now );
			if ( absint( $row['broadcast_id'] ?? 0 ) > 0 ) { Dhikra_Broadcast::refresh_counts( absint( $row['broadcast_id'] ) ); }
			return array( 'status' => 'sent', 'attempts' => $attempts, 'sent_at' => $now );
		}
		$error = is_wp_error( $sent ) ? self::error_text( $sent ) : __( 'ارسال پیام ایتا ناموفق بود.', 'dhikra' );
		$delay = 1 === $attempts ? 15 * MINUTE_IN_SECONDS : 2 * HOUR_IN_SECONDS;
		$next  = $attempts < 3 ? gmdate( 'Y-m-d H:i:s', time() + $delay ) : null;
		$wpdb->update( $table, array( 'status' => 'failed', 'attempts' => $attempts, 'last_error' => $error, 'updated_at' => $now, 'next_attempt_at' => $next ), array( 'id' => $message_id ), array( '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );
		self::sync_delivery( $row, 'failed', $attempts, $error, null );
		if ( absint( $row['broadcast_id'] ?? 0 ) > 0 ) { Dhikra_Broadcast::refresh_counts( absint( $row['broadcast_id'] ) ); }
		return array( 'status' => 'failed', 'attempts' => $attempts, 'error' => $error );
	}

	private static function error_text( WP_Error $error ): string {
		$text = trim( $error->get_error_message() );
		$data = $error->get_error_data();
		if ( is_array( $data ) ) {
			$details = array();
			if ( ! empty( $data['http_code'] ) ) { $details[] = 'HTTP ' . absint( $data['http_code'] ); }
			if ( ! empty( $data['response'] ) && is_array( $data['response'] ) ) {
				foreach ( array( 'description', 'message', 'error', 'result' ) as $key ) {
					if ( isset( $data['response'][ $key ] ) && is_scalar( $data['response'][ $key ] ) ) {
						$value = trim( sanitize_text_field( (string) $data['response'][ $key ] ) );
						if ( '' !== $value && 'success' !== strtolower( $value ) ) { $details[] = $value; break; }
					}
				}
			}
			if ( ! empty( $details ) ) {
				$detail_text = implode( ' — ', array_unique( $details ) );
				if ( false === strpos( $text, $detail_text ) ) { $text .= ' [' . $detail_text . ']'; }
			}
		}
		return substr( $text ?: __( 'ارسال پیام ایتا ناموفق بود.', 'dhikra' ), 0, 2000 );
	}

	private static function sync_delivery( array $message, string $status, int $attempts, string $error, ?string $sent_at ): void {
		global $wpdb;
		if ( absint( $message['reward_entry_id'] ?? 0 ) > 0 ) {
			Dhikra_Coupons::sync_message( absint( $message['reward_entry_id'] ), $status, $sent_at );
			$wpdb->update( Dhikra_Data::table( 'reward_entries' ), array( 'pm_status' => $status, 'pm_attempts' => $attempts, 'pm_last_error' => $error, 'pm_sent_at' => $sent_at ), array( 'id' => absint( $message['reward_entry_id'] ) ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );
		}
		if ( 'sent' === $status && 'daily' === (string) $message['message_type'] ) {
			$wpdb->update( Dhikra_Data::table( 'audience' ), array( 'daily_last_sent_date' => ! empty( $message['daily_send_date'] ) ? (string) $message['daily_send_date'] : self::tehran_now()->format( 'Y-m-d' ), 'last_message_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $message['audience_id'] ) ), array( '%s', '%s' ), array( '%d' ) );
		}
	}

	public function retry_failed_messages(): void {
		global $wpdb;
		$table = Dhikra_Data::table( 'messages' );
		$now   = current_time( 'mysql', true );
		$broadcasts = Dhikra_Data::table( 'broadcasts' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT m.id FROM {$table} m LEFT JOIN {$broadcasts} b ON b.id=m.broadcast_id WHERE m.status='failed' AND m.attempts<3 AND m.next_attempt_at IS NOT NULL AND m.next_attempt_at<=%s AND (m.broadcast_id IS NULL OR b.status<>'cancelled') AND (m.message_type <> 'daily' OR (m.daily_send_date IS NULL AND m.created_at >= %s AND m.created_at < %s) OR m.daily_send_date = %s) ORDER BY CASE m.message_type WHEN 'winner' THEN 0 WHEN 'personal' THEN 1 WHEN 'daily' THEN 2 ELSE 3 END,m.next_attempt_at ASC LIMIT 50", $now, self::tehran_now()->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), self::tehran_now()->setTime( 0, 0 )->modify( '+1 day' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), self::tehran_now()->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( array_map( 'absint', is_array( $ids ) ? $ids : array() ) as $id ) { self::attempt_message( $id ); }
	}


	/** Schedule a one-off private reminder for an Eitaa user. */
	public static function schedule_personal_reminder( int $user_id, int $audience_id, int $post_id, string $preset ): array|WP_Error {
		if ( $user_id <= 0 || $audience_id <= 0 || $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
			return new WP_Error( 'dhikra_reminder_invalid', __( 'اطلاعات یادآوری معتبر نیست.', 'dhikra' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$audience = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Dhikra_Data::table( 'audience' ) . ' WHERE id=%d LIMIT 1', $audience_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $audience ) || empty( $audience['allows_write_to_pm'] ) ) {
			return new WP_Error( 'dhikra_reminder_permission_required', __( 'برای یادآوری دوباره، اجازه دریافت پیام ایتا لازم است.', 'dhikra' ), array( 'status' => 403 ) );
		}

		$now = self::tehran_now();
		$send_time = self::daily_send_time();
		[ $hour, $minute ] = array_map( 'intval', explode( ':', $send_time ) );
		switch ( sanitize_key( $preset ) ) {
			case 'tonight':
				$due = $now->setTime( 21, 0 );
				if ( $due->getTimestamp() <= $now->modify( '+5 minutes' )->getTimestamp() ) {
					$due = $due->modify( '+1 day' );
					$label = __( 'فردا ساعت ۲۱', 'dhikra' );
				} else {
					$label = __( 'امشب ساعت ۲۱', 'dhikra' );
				}
				break;
			case 'tomorrow':
				$due = $now->modify( '+1 day' )->setTime( $hour, $minute );
				$label = sprintf( __( 'فردا ساعت %s', 'dhikra' ), $send_time );
				break;
			case 'week':
				$due = $now->modify( '+7 days' )->setTime( $hour, $minute );
				$label = sprintf( __( 'یک هفته بعد ساعت %s', 'dhikra' ), $send_time );
				break;
			default:
				return new WP_Error( 'dhikra_reminder_preset', __( 'زمان یادآوری معتبر نیست.', 'dhikra' ), array( 'status' => 400 ) );
		}

		$timestamp = $due->getTimestamp();
		$old = Dhikra_Data::card_reminder( $user_id, $post_id );
		if ( ! empty( $old['timestamp'] ) && ! empty( $old['audience_id'] ) ) {
			wp_unschedule_event(
				absint( $old['timestamp'] ),
				'dhikra_personal_reminder_send',
				array( $user_id, absint( $old['audience_id'] ), $post_id, absint( $old['timestamp'] ) )
			);
		}
		$record = array(
			'timestamp'   => $timestamp,
			'audience_id' => $audience_id,
			'preset'      => sanitize_key( $preset ),
			'label'       => $label,
			'local_iso'   => $due->format( DATE_ATOM ),
		);
		if ( ! Dhikra_Data::save_card_reminder( $user_id, $post_id, $record ) ) {
			return new WP_Error( 'dhikra_reminder_save_failed', __( 'ذخیره یادآوری انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		if ( ! wp_schedule_single_event( $timestamp, 'dhikra_personal_reminder_send', array( $user_id, $audience_id, $post_id, $timestamp ) ) ) {
			Dhikra_Data::save_card_reminder( $user_id, $post_id, array() );
			return new WP_Error( 'dhikra_reminder_schedule_failed', __( 'زمان‌بندی یادآوری انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		return $record;
	}

	/** Deliver a scheduled one-off reminder through the existing reliable queue. */
	public function send_personal_reminder( int $user_id, int $audience_id, int $post_id, int $scheduled_ts ): void {
		$record = Dhikra_Data::card_reminder( $user_id, $post_id );
		if ( empty( $record ) || absint( $record['timestamp'] ?? 0 ) !== $scheduled_ts || absint( $record['audience_id'] ?? 0 ) !== $audience_id ) {
			return;
		}
		global $wpdb;
		$audience = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Dhikra_Data::table( 'audience' ) . ' WHERE id=%d LIMIT 1', $audience_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $audience ) || empty( $audience['allows_write_to_pm'] ) ) {
			Dhikra_Data::save_card_reminder( $user_id, $post_id, array() );
			return;
		}
		$data = Dhikra_Random::telangor_data( $post_id, 'eitaa', false );
		if ( empty( $data ) ) {
			Dhikra_Data::save_card_reminder( $user_id, $post_id, array() );
			return;
		}
		$text = "⏰ یادآوری ذِكْرَىٰ\n\n" . trim( (string) ( $data['share_texts']['eitaa'] ?? $data['share_text'] ?? '' ) );
		$result = self::send_logged_message( $audience_id, (string) $audience['eitaa_user_id'], $text, 'reminder' );
		if ( ! empty( $result['message_id'] ) ) {
			// Once handed to the reliable message queue, retries are handled there.
			Dhikra_Data::save_card_reminder( $user_id, $post_id, array() );
			return;
		}
		// A database-level queue failure should not silently lose the user's reminder.
		wp_schedule_single_event( time() + 300, 'dhikra_personal_reminder_send', array( $user_id, $audience_id, $post_id, $scheduled_ts ) );
	}

	/** Daily scheduling is always interpreted in Iran time. */
	public static function tehran_now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tehran' ) );
	}

	public function dispatch_daily_batch(): void {
		global $wpdb;
		$audience_table = Dhikra_Data::table( 'audience' );
		$message_table  = Dhikra_Data::table( 'messages' );
		$local          = self::tehran_now();
		$today          = $local->format( 'Y-m-d' );
		$send_time      = self::daily_send_time();
		$current_hm     = $local->format( 'H:i' );
		// The administrator owns the single daily send time for every subscriber.
		if ( $current_hm < $send_time ) { return; }
		// Legacy rows from before daily_send_date existed are also considered.
		$start_utc = $local->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$end_utc   = $local->setTime( 0, 0 )->modify( '+1 day' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		// NOT EXISTS precedes LIMIT: permanently failed/queued recipients must
		// never starve other recipients when the audience exceeds 100 members.
		$sql = "SELECT a.* FROM {$audience_table} a WHERE a.daily_enabled=1 AND a.allows_write_to_pm=1 AND (a.daily_last_sent_date IS NULL OR a.daily_last_sent_date <> %s) AND NOT EXISTS (SELECT 1 FROM {$message_table} m WHERE m.audience_id=a.id AND m.message_type='daily' AND (m.daily_send_date=%s OR (m.daily_send_date IS NULL AND m.created_at >= %s AND m.created_at < %s))) ORDER BY a.id ASC LIMIT 20";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $today, $today, $start_utc, $end_utc ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$post_id = Dhikra_Random::random_post_id();
			if ( $post_id <= 0 ) { continue; }
			$data = Dhikra_Random::telangor_data( $post_id, 'eitaa', false );
			self::send_logged_message( absint( $row['id'] ), (string) $row['eitaa_user_id'], self::daily_message_text( $data ), 'daily', 0, 0, false, $today );
		}
	}

	private static function daily_message_text( array $data ): string {
		$parts = array( '🌱 ' . (string) ( $data['title'] ?? __( 'تلنگر امروز', 'dhikra' ) ) );
		if ( ! empty( $data['original'] ) ) { $parts[] = (string) $data['original']; }
		if ( ! empty( $data['translation'] ) ) { $parts[] = (string) $data['translation']; }
		if ( ! empty( $data['source'] ) ) { $parts[] = '— ' . (string) $data['source']; }
		// Daily private messages intentionally contain no trailing link.
		return implode( "\n\n", $parts );
	}
}
