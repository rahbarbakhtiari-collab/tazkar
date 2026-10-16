<?php
/**
 * Custom data tables and user data services.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Data {
	public static function table( string $name ): string {
		global $wpdb;
		$allowed = array( 'favorites', 'history', 'short_codes', 'identities', 'audience', 'reward_campaigns', 'reward_entries', 'reward_progress', 'reward_coupons', 'broadcasts', 'messages', 'visits' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'dhikra_' . $name;
	}

	public static function install_schema(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = array(
			'CREATE TABLE ' . self::table( 'favorites' ) . " (
				user_id bigint(20) unsigned NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (user_id,post_id),
				KEY post_id (post_id)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'history' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				viewed_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_post (user_id,post_id),
				KEY user_viewed (user_id,viewed_at),
				KEY post_id (post_id)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'short_codes' ) . " (
				code char(5) NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				deleted_at datetime NULL,
				PRIMARY KEY  (code),
				UNIQUE KEY post_id (post_id)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'identities' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider varchar(40) NOT NULL,
				external_user_id varchar(191) NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_external (provider,external_user_id),
				KEY user_id (user_id)
			) $charset_collate;",

			'CREATE TABLE ' . self::table( 'audience' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				eitaa_user_id varchar(32) NOT NULL,
				first_name varchar(120) NOT NULL DEFAULT '',
				last_name varchar(120) NOT NULL DEFAULT '',
				language_code varchar(20) NOT NULL DEFAULT '',
				allows_write_to_pm tinyint(1) unsigned NOT NULL DEFAULT 0,
				daily_enabled tinyint(1) unsigned NOT NULL DEFAULT 0,
				daily_topic_ids longtext NULL,
				daily_send_hour tinyint(2) unsigned NOT NULL DEFAULT 8,
				daily_prompt_decided_at datetime NULL,
				write_access_callback_at datetime NULL,
				daily_last_sent_date date NULL,
				first_seen_at datetime NOT NULL,
				last_seen_at datetime NOT NULL,
				last_message_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY eitaa_user_id (eitaa_user_id),
				KEY daily_due (daily_enabled,allows_write_to_pm,daily_last_sent_date),
				KEY daily_hour_due (daily_enabled,allows_write_to_pm,daily_send_hour,daily_last_sent_date),
				KEY last_seen_at (last_seen_at)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'reward_campaigns' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(191) NOT NULL,
				capacity int(10) unsigned NOT NULL,
				prize_count int(10) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				created_by bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				activated_at datetime NULL,
				closed_at datetime NULL,
				close_mode varchar(20) NULL,
				winner_app_message text NULL,
				winner_pm_message text NULL,
				topic_ids longtext NULL,
				all_topics tinyint(1) unsigned NOT NULL DEFAULT 1,
				dashboard_fields longtext NULL,
				winner_cta_label varchar(120) NOT NULL DEFAULT '',
				winner_cta_url text NULL,
				prize_mode varchar(20) NOT NULL DEFAULT 'message',
				min_browse_cards tinyint(3) unsigned NOT NULL DEFAULT 4,
				max_browse_cards tinyint(3) unsigned NOT NULL DEFAULT 8,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY created_by (created_by)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'reward_entries' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				audience_id bigint(20) unsigned NOT NULL DEFAULT 0,
				eitaa_user_id varchar(32) NOT NULL DEFAULT '',
				card_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				sequence_no int(10) unsigned NOT NULL,
				is_winner tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				won_at datetime NULL,
				coupon_id bigint(20) unsigned NOT NULL DEFAULT 0,
				coupon_redeemed_at datetime NULL,
				pm_status varchar(20) NOT NULL DEFAULT 'not_required',
				pm_sent_at datetime NULL,
				pm_last_error text NULL,
				pm_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_user (campaign_id,user_id),
				UNIQUE KEY campaign_sequence (campaign_id,sequence_no),
				UNIQUE KEY campaign_audience (campaign_id,audience_id),
				KEY eitaa_user_id (eitaa_user_id),
				KEY campaign_winner (campaign_id,is_winner),
				KEY campaign_created (campaign_id,created_at),
				KEY card_post_id (card_post_id),
				KEY user_id (user_id),
				KEY coupon_id (coupon_id)
			) $charset_collate;",
			// Browsing progress does not consume campaign capacity; the prize draw
			// occurs only after several different, server-selected cards were viewed.
			'CREATE TABLE ' . self::table( 'reward_progress' ) . " (
				campaign_id bigint(20) unsigned NOT NULL,
				audience_id bigint(20) unsigned NOT NULL,
				seen_post_ids longtext NOT NULL,
				reveal_after tinyint(3) unsigned NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (campaign_id,audience_id),
				KEY audience_id (audience_id)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'reward_coupons' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				coupon_code varchar(190) NOT NULL,
				allocation_limit int(10) unsigned NOT NULL DEFAULT 1,
				status varchar(20) NOT NULL DEFAULT 'available',
				reward_entry_id bigint(20) unsigned NULL,
				audience_id bigint(20) unsigned NULL,
				message_id bigint(20) unsigned NULL,
				expires_at date NULL,
				created_at datetime NOT NULL,
				assigned_at datetime NULL,
				delivered_at datetime NULL,
				redeemed_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY coupon_code (coupon_code),
				UNIQUE KEY reward_entry_id (reward_entry_id),
				UNIQUE KEY message_id (message_id),
				KEY inventory (campaign_id,status,expires_at),
				KEY audience_id (audience_id)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'broadcasts' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_key varchar(64) NOT NULL,
				message_text text NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'queued',
				created_by bigint(20) unsigned NOT NULL,
				total_audience bigint(20) unsigned NOT NULL DEFAULT 0,
				eligible_count bigint(20) unsigned NOT NULL DEFAULT 0,
				skipped_no_permission bigint(20) unsigned NOT NULL DEFAULT 0,
				queued_count bigint(20) unsigned NOT NULL DEFAULT 0,
				sent_count bigint(20) unsigned NOT NULL DEFAULT 0,
				failed_count bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				started_at datetime NULL,
				completed_at datetime NULL,
				cancelled_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY request_key (request_key),
				KEY status_created (status,created_at),
				KEY created_by (created_by)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'messages' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audience_id bigint(20) unsigned NOT NULL DEFAULT 0,
				broadcast_id bigint(20) unsigned NULL,
				campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
				reward_entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
				dedupe_key varchar(80) NULL,
				eitaa_user_id varchar(32) NOT NULL DEFAULT '',
				message_type varchar(30) NOT NULL,
				daily_send_date date NULL,
				message_text text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'queued',
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				sent_at datetime NULL,
				next_attempt_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY broadcast_audience (broadcast_id,audience_id),
				UNIQUE KEY daily_audience_day (audience_id,daily_send_date),
				KEY status_due (status,next_attempt_at),
				KEY broadcast_status (broadcast_id,status,next_attempt_at),
				KEY audience_created (audience_id,created_at),
				KEY campaign_type (campaign_id,message_type),
				KEY daily_stats (message_type,daily_send_date,status),
				KEY daily_sent (message_type,sent_at,audience_id),
				KEY reward_entry_id (reward_entry_id),
				UNIQUE KEY dedupe_key (dedupe_key)
			) $charset_collate;",
			'CREATE TABLE ' . self::table( 'visits' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				visit_key char(64) NOT NULL,
				audience_id bigint(20) unsigned NOT NULL,
				card_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				visit_date date NOT NULL,
				visit_hour tinyint(2) unsigned NOT NULL DEFAULT 0,
				visited_at datetime NOT NULL,
				last_seen_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY visit_key (visit_key),
				KEY visit_date (visit_date),
				KEY audience_date (audience_id,visit_date),
				KEY audience_visited (audience_id,visited_at),
				KEY date_hour (visit_date,visit_hour),
				KEY visited_at (visited_at)
			) $charset_collate;",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public static function get_short_code( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$meta = Dhikra_Helpers::sanitize_short_code( (string) get_post_meta( $post_id, '_dhikra_short_code', true ) );
		if ( '' !== $meta ) {
			return $meta;
		}
		global $wpdb;
		$table = self::table( 'short_codes' );
		$code  = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE post_id = %d AND deleted_at IS NULL LIMIT 1", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_string( $code ) ? $code : '';
	}

	public static function find_post_by_code( string $code, bool $published_only = true ): int {
		$code = Dhikra_Helpers::sanitize_short_code( $code );
		if ( '' === $code ) {
			return 0;
		}
		global $wpdb;
		$table   = self::table( 'short_codes' );
		$post_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE code = %s AND deleted_at IS NULL LIMIT 1", $code ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $post_id <= 0 ) {
			return 0;
		}
		if ( $published_only && 'publish' !== get_post_status( $post_id ) ) {
			return 0;
		}
		return $post_id;
	}

	public static function short_code_available( string $code, int $post_id = 0 ): bool {
		$code = Dhikra_Helpers::sanitize_short_code( $code );
		if ( '' === $code ) {
			return false;
		}
		global $wpdb;
		$table = self::table( 'short_codes' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT post_id, deleted_at FROM {$table} WHERE code = %s LIMIT 1", $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) ) {
			return true;
		}
		return $post_id > 0 && absint( $row['post_id'] ) === $post_id && empty( $row['deleted_at'] );
	}

	public static function assign_short_code( int $post_id, string $requested = '' ): string|WP_Error {
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_invalid_post', __( 'شناسهٔ تلنگر نامعتبر است.', 'dhikra' ) );
		}
		$current = self::get_short_code( $post_id );
		$code    = Dhikra_Helpers::sanitize_short_code( $requested );

		if ( '' !== $current && '' === $code ) {
			return $current;
		}
		if ( '' !== $current && $current === $code ) {
			return $current;
		}
		if ( '' !== $current && '' !== $code && $current !== $code ) {
			return new WP_Error( 'dhikra_code_immutable', __( 'کد کوتاه پس از ایجاد تغییر نمی‌کند.', 'dhikra' ) );
		}

		if ( '' !== $requested && '' === $code ) {
			return new WP_Error( 'dhikra_invalid_code', __( 'کد کوتاه باید دقیقاً پنج نویسه و بدون ۰/O و ۱/I باشد.', 'dhikra' ) );
		}

		if ( '' === $code ) {
			for ( $attempt = 0; $attempt < 40; $attempt++ ) {
				$candidate = Dhikra_Helpers::random_short_code();
				if ( self::short_code_available( $candidate ) ) {
					$code = $candidate;
					break;
				}
			}
		}
		if ( '' === $code ) {
			return new WP_Error( 'dhikra_code_exhausted', __( 'ساخت کد کوتاه یکتا ممکن نشد.', 'dhikra' ) );
		}
		if ( ! self::short_code_available( $code, $post_id ) ) {
			return new WP_Error( 'dhikra_duplicate_code', __( 'این کد کوتاه قبلاً استفاده شده است.', 'dhikra' ) );
		}

		global $wpdb;
		$table  = self::table( 'short_codes' );
		$result = $wpdb->insert(
			$table,
			array(
				'code'       => $code,
				'post_id'    => $post_id,
				'created_at' => current_time( 'mysql', true ),
				'deleted_at' => null,
			),
			array( '%s', '%d', '%s', '%s' )
		);
		if ( false === $result ) {
			return new WP_Error( 'dhikra_code_insert_failed', __( 'ذخیرهٔ کد کوتاه انجام نشد.', 'dhikra' ) );
		}
		update_post_meta( $post_id, '_dhikra_short_code', $code );
		return $code;
	}

	public static function tombstone_short_code( int $post_id ): void {
		global $wpdb;
		$table = self::table( 'short_codes' );
		$wpdb->update(
			$table,
			array( 'deleted_at' => current_time( 'mysql', true ) ),
			array( 'post_id' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function favorite_ids( int $user_id, int $page = 1, int $per_page = 10 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$table  = self::table( 'favorites' );
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_filter( array_map( 'absint', is_array( $rows ) ? $rows : array() ) ) );
	}

	/**
	 * Return saved cards together with their saved timestamp.
	 *
	 * @return array<int,array{post_id:int,created_at:string}>
	 */
	public static function favorite_rows( int $user_id, int $page = 1, int $per_page = 20 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$table    = self::table( 'favorites' );
		$per_page = min( 100, max( 1, $per_page ) );
		$offset   = max( 0, ( max( 1, $page ) - 1 ) * $per_page );
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map(
					static function ( array $row ): array {
						return array(
							'post_id'    => absint( $row['post_id'] ?? 0 ),
							'created_at' => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
						);
					},
					$rows
				),
				static fn( array $row ): bool => $row['post_id'] > 0
			)
		);
	}

	public static function favorite_count( int $user_id ): int {
		global $wpdb;
		$table = self::table( 'favorites' );
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function is_favorite( int $user_id, int $post_id ): bool {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$table = self::table( 'favorites' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE user_id = %d AND post_id = %d LIMIT 1", $user_id, $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function set_favorite( int $user_id, int $post_id, bool $favorite ): bool {
		if ( $user_id <= 0 || $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		global $wpdb;
		$table = self::table( 'favorites' );
		if ( $favorite ) {
			$result = $wpdb->replace(
				$table,
				array(
					'user_id'    => $user_id,
					'post_id'    => $post_id,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s' )
			);
			return false !== $result;
		}
		return false !== $wpdb->delete( $table, array( 'user_id' => $user_id, 'post_id' => $post_id ), array( '%d', '%d' ) );
	}

	public static function record_history( int $user_id, int $post_id ): void {
		if ( $user_id <= 0 || $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		global $wpdb;
		$table = self::table( 'history' );
		$wpdb->replace(
			$table,
			array(
				'user_id'   => $user_id,
				'post_id'   => $post_id,
				'viewed_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s' )
		);

		$keep_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d ORDER BY viewed_at DESC, id DESC LIMIT 100", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $keep_ids ) && count( $keep_ids ) >= 100 ) {
			$oldest_kept = absint( end( $keep_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d AND id < %d", $user_id, $oldest_kept ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	public static function recent_history_ids( int $user_id, int $limit = 12 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$table = self::table( 'history' );
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE user_id = %d ORDER BY viewed_at DESC, id DESC LIMIT %d", $user_id, max( 1, min( 100, $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_filter( array_map( 'absint', is_array( $rows ) ? $rows : array() ) ) );
	}

	public static function history_ids( int $user_id, int $page = 1, int $per_page = 10 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$table  = self::table( 'history' );
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE user_id = %d ORDER BY viewed_at DESC, id DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_filter( array_map( 'absint', is_array( $rows ) ? $rows : array() ) ) );
	}

	public static function history_count( int $user_id ): int {
		global $wpdb;
		$table = self::table( 'history' );
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function excluded_topic_ids( int $user_id ): array {
		$value = get_user_meta( $user_id, '_dhikra_excluded_topics', true );
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	public static function save_excluded_topic_ids( int $user_id, array $excluded_ids ): void {
		$active_ids = self::active_topic_ids();
		$excluded   = array_values( array_intersect( $active_ids, array_unique( array_filter( array_map( 'absint', $excluded_ids ) ) ) ) );
		update_user_meta( $user_id, '_dhikra_excluded_topics', $excluded );
	}


	/** Retrieve a private per-user note for a telangor. */
	public static function card_note( int $user_id, int $post_id ): string {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return '';
		}
		$notes = get_user_meta( $user_id, '_dhikra_card_notes', true );
		if ( ! is_array( $notes ) ) {
			return '';
		}
		return isset( $notes[ $post_id ] ) ? (string) $notes[ $post_id ] : '';
	}

	/** Save or remove a private per-user note. Empty text removes the note. */
	public static function save_card_note( int $user_id, int $post_id, string $note ): bool {
		if ( $user_id <= 0 || $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		$notes = get_user_meta( $user_id, '_dhikra_card_notes', true );
		$notes = is_array( $notes ) ? $notes : array();
		$note  = trim( sanitize_textarea_field( $note ) );
		if ( function_exists( 'mb_substr' ) ) {
			$note = mb_substr( $note, 0, 1000, 'UTF-8' );
		} else {
			$note = substr( $note, 0, 1000 );
		}
		$before = isset( $notes[ $post_id ] ) ? (string) $notes[ $post_id ] : '';
		if ( '' === $note ) {
			unset( $notes[ $post_id ] );
		} else {
			$notes[ $post_id ] = $note;
		}
		if ( $before === $note ) {
			return true;
		}
		return false !== update_user_meta( $user_id, '_dhikra_card_notes', $notes );
	}

	/** Return saved reminder metadata for one card. */
	public static function card_reminder( int $user_id, int $post_id ): array {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return array();
		}
		$items = get_user_meta( $user_id, '_dhikra_card_reminders', true );
		if ( ! is_array( $items ) || empty( $items[ $post_id ] ) || ! is_array( $items[ $post_id ] ) ) {
			return array();
		}
		return $items[ $post_id ];
	}

	/** Persist reminder metadata. Passing an empty record removes it. */
	public static function save_card_reminder( int $user_id, int $post_id, array $record ): bool {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}
		$items = get_user_meta( $user_id, '_dhikra_card_reminders', true );
		$items = is_array( $items ) ? $items : array();
		$before = $items[ $post_id ] ?? null;
		if ( empty( $record ) ) {
			unset( $items[ $post_id ] );
		} else {
			$items[ $post_id ] = $record;
		}
		if ( $before === ( empty( $record ) ? null : $record ) ) {
			return true;
		}
		return false !== update_user_meta( $user_id, '_dhikra_card_reminders', $items );
	}

	public static function active_topic_ids(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'dhikra_topic',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map( 'absint', $terms ),
				static fn( int $term_id ): bool => Dhikra_Themes::is_topic_active( $term_id )
			)
		);
	}

	public static function merge_user_data( int $source_user_id, int $target_user_id ): bool {
		if ( $source_user_id <= 0 || $target_user_id <= 0 || $source_user_id === $target_user_id ) {
			return false;
		}
		global $wpdb;
		$favorites = self::table( 'favorites' );
		$history   = self::table( 'history' );

		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$favorites} (user_id,post_id,created_at) SELECT %d,post_id,created_at FROM {$favorites} WHERE user_id = %d", $target_user_id, $source_user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$source_history = self::recent_history_ids( $source_user_id, 100 );
		foreach ( array_reverse( $source_history ) as $post_id ) {
			self::record_history( $target_user_id, $post_id );
		}
		$excluded = array_values( array_unique( array_merge( self::excluded_topic_ids( $source_user_id ), self::excluded_topic_ids( $target_user_id ) ) ) );
		self::save_excluded_topic_ids( $target_user_id, $excluded );

		$source_notes = get_user_meta( $source_user_id, '_dhikra_card_notes', true );
		$target_notes = get_user_meta( $target_user_id, '_dhikra_card_notes', true );
		if ( is_array( $source_notes ) ) {
			update_user_meta( $target_user_id, '_dhikra_card_notes', array_replace( $source_notes, is_array( $target_notes ) ? $target_notes : array() ) );
		}
		$source_reminders = get_user_meta( $source_user_id, '_dhikra_card_reminders', true );
		$target_reminders = get_user_meta( $target_user_id, '_dhikra_card_reminders', true );
		if ( is_array( $source_reminders ) ) {
			update_user_meta( $target_user_id, '_dhikra_card_reminders', array_replace( $source_reminders, is_array( $target_reminders ) ? $target_reminders : array() ) );
		}

		$wpdb->delete( $favorites, array( 'user_id' => $source_user_id ), array( '%d' ) );
		$wpdb->delete( $history, array( 'user_id' => $source_user_id ), array( '%d' ) );
		do_action( 'dhikra_user_data_merged', $source_user_id, $target_user_id );
		return true;
	}
	public static function active_reward_campaign(): array {
		global $wpdb;
		$table = self::table( 'reward_campaigns' );
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array( $row ) ? $row : array();
	}

}
