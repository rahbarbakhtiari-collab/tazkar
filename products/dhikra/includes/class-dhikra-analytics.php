<?php
/** Eitaa mini-app visit analytics. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Analytics {
	private const TZ = 'Asia/Tehran';

	public static function storage_ready(): bool {
		global $wpdb;
		$table = Dhikra_Data::table( 'visits' );
		if ( '' === $table ) { return false; }
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table;
	}

	public static function record_visit( int $audience_id, array $identity, string $visit_token = '', int $card_post_id = 0 ): void {
		if ( $audience_id <= 0 ) { return; }
		$eitaa_user_id = (string) ( $identity['eitaa_user_id'] ?? '' );
		if ( '' === $eitaa_user_id ) { return; }
		$visit_token = trim( $visit_token );
		if ( ! preg_match( '/^[A-Za-z0-9._:-]{12,120}$/', $visit_token ) ) {
			$auth_date   = absint( $identity['auth_date'] ?? 0 );
			$visit_token = 'fallback:' . $eitaa_user_id . ':' . ( $auth_date > 0 ? $auth_date : time() );
		}
		$key = hash( 'sha256', $eitaa_user_id . '|' . $visit_token );
		$now_utc = current_time( 'mysql', true );
		$tehran  = ( new DateTimeImmutable( '@' . time() ) )->setTimezone( new DateTimeZone( self::TZ ) );
		$date    = $tehran->format( 'Y-m-d' );
		$hour    = absint( $tehran->format( 'G' ) );
		global $wpdb;
		$table = Dhikra_Data::table( 'visits' );
		if ( '' === $table ) { return; }
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (visit_key,audience_id,card_post_id,visit_date,visit_hour,visited_at,last_seen_at) VALUES (%s,%d,%d,%s,%d,%s,%s)
			 ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at),card_post_id=IF(VALUES(card_post_id)>0,VALUES(card_post_id),card_post_id)",
			$key, $audience_id, max( 0, $card_post_id ), $date, $hour, $now_utc, $now_utc
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	public static function tehran_today(): string {
		return ( new DateTimeImmutable( '@' . time() ) )->setTimezone( new DateTimeZone( self::TZ ) )->format( 'Y-m-d' );
	}

	public static function start_date( int $days ): string {
		$days = max( 1, min( 365, $days ) );
		$today = new DateTimeImmutable( self::tehran_today(), new DateTimeZone( self::TZ ) );
		return $today->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
	}

	public static function summary( int $days ): array {
		global $wpdb;
		$visits   = Dhikra_Data::table( 'visits' );
		$audience = Dhikra_Data::table( 'audience' );
		$start    = self::start_date( $days );
		$today    = self::tehran_today();
		$yesterday = ( new DateTimeImmutable( $today, new DateTimeZone( self::TZ ) ) )->modify( '-1 day' )->format( 'Y-m-d' );

		$period = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) visits, COUNT(DISTINCT audience_id) visitors FROM {$visits} WHERE visit_date BETWEEN %s AND %s",
			$start, $today
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) visits, COUNT(DISTINCT audience_id) visitors FROM {$visits} WHERE visit_date=%s",
			$today
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$yesterday_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) visits, COUNT(DISTINCT audience_id) visitors FROM {$visits} WHERE visit_date=%s",
			$yesterday
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_visitors = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT v.audience_id) FROM {$visits} v INNER JOIN {$audience} a ON a.id=v.audience_id WHERE v.visit_date BETWEEN %s AND %s AND DATE(DATE_ADD(a.first_seen_at,INTERVAL 210 MINUTE)) BETWEEN %s AND %s",
			$start, $today, $start, $today
		) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all = $wpdb->get_row( "SELECT COUNT(*) visits,COUNT(DISTINCT audience_id) visitors,MIN(visit_date) first_date FROM {$visits}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$aud = $wpdb->get_row( "SELECT COUNT(*) total,SUM(allows_write_to_pm=1) write_ok,SUM(daily_enabled=1) daily_on FROM {$audience}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique = absint( $period['visitors'] ?? 0 );
		return array(
			'period_visits'     => absint( $period['visits'] ?? 0 ),
			'period_visitors'   => $unique,
			'period_new'        => $new_visitors,
			'period_returning'  => max( 0, $unique - $new_visitors ),
			'today_visits'      => absint( $today_row['visits'] ?? 0 ),
			'today_visitors'    => absint( $today_row['visitors'] ?? 0 ),
			'yesterday_visits'  => absint( $yesterday_row['visits'] ?? 0 ),
			'all_visits'        => absint( $all['visits'] ?? 0 ),
			'all_visitors'      => absint( $all['visitors'] ?? 0 ),
			'first_date'        => (string) ( $all['first_date'] ?? '' ),
			'audience_total'    => absint( $aud['total'] ?? 0 ),
			'write_ok'          => absint( $aud['write_ok'] ?? 0 ),
			'daily_on'          => absint( $aud['daily_on'] ?? 0 ),
		);
	}

	public static function daily_series( int $days ): array {
		global $wpdb;
		$table = Dhikra_Data::table( 'visits' );
		$start = self::start_date( $days );
		$today = self::tehran_today();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT visit_date,COUNT(*) visits,COUNT(DISTINCT audience_id) visitors FROM {$table} WHERE visit_date BETWEEN %s AND %s GROUP BY visit_date ORDER BY visit_date ASC",
			$start, $today
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) { $map[ (string) $row['visit_date'] ] = $row; }
		$audience = Dhikra_Data::table( 'audience' );
		$new_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(DATE_ADD(a.first_seen_at,INTERVAL 210 MINUTE)) first_day,COUNT(DISTINCT a.id) new_visitors FROM {$audience} a INNER JOIN {$table} v ON v.audience_id=a.id AND v.visit_date=DATE(DATE_ADD(a.first_seen_at,INTERVAL 210 MINUTE)) WHERE v.visit_date BETWEEN %s AND %s GROUP BY first_day",
			$start, $today
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_map = array();
		foreach ( is_array( $new_rows ) ? $new_rows : array() as $row ) { $new_map[ (string) $row['first_day'] ] = absint( $row['new_visitors'] ); }
		$out = array();
		$cursor = new DateTimeImmutable( $start, new DateTimeZone( self::TZ ) );
		$end = new DateTimeImmutable( $today, new DateTimeZone( self::TZ ) );
		while ( $cursor <= $end ) {
			$date = $cursor->format( 'Y-m-d' );
			$row  = $map[ $date ] ?? array();
			$out[] = array( 'date' => $date, 'visits' => absint( $row['visits'] ?? 0 ), 'visitors' => absint( $row['visitors'] ?? 0 ), 'new' => absint( $new_map[ $date ] ?? 0 ) );
			$cursor = $cursor->modify( '+1 day' );
		}
		return $out;
	}

	public static function hourly_series( int $days ): array {
		global $wpdb;
		$table = Dhikra_Data::table( 'visits' );
		$start = self::start_date( $days );
		$today = self::tehran_today();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT visit_hour,COUNT(*) visits FROM {$table} WHERE visit_date BETWEEN %s AND %s GROUP BY visit_hour ORDER BY visit_hour ASC",
			$start, $today
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) { $map[ absint( $row['visit_hour'] ) ] = absint( $row['visits'] ); }
		$out = array();
		for ( $hour = 0; $hour < 24; $hour++ ) { $out[] = array( 'hour' => $hour, 'visits' => absint( $map[ $hour ] ?? 0 ) ); }
		return $out;
	}

	public static function recent_visits( int $limit = 20 ): array {
		global $wpdb;
		$limit = max( 1, min( 100, $limit ) );
		$v = Dhikra_Data::table( 'visits' );
		$a = Dhikra_Data::table( 'audience' );
		$sql = $wpdb->prepare(
			"SELECT v.*,a.eitaa_user_id,a.first_name,a.last_name FROM {$v} v LEFT JOIN {$a} a ON a.id=v.audience_id ORDER BY v.id DESC LIMIT %d",
			$limit
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}
	public static function daily_message_summary( int $days ): array {
		global $wpdb;
		$messages = Dhikra_Data::table( 'messages' );
		$audience = Dhikra_Data::table( 'audience' );
		$visits   = Dhikra_Data::table( 'visits' );
		$start    = self::start_date( $days );
		$today    = self::tehran_today();
		$day_expr = "COALESCE(m.daily_send_date,DATE(DATE_ADD(m.created_at,INTERVAL 210 MINUTE)))";
		$terminal = "(m.status='failed' AND (m.attempts>=3 OR m.next_attempt_at IS NULL))";
		$pending  = "(m.status IN ('queued','sending') OR (m.status='failed' AND m.attempts<3 AND m.next_attempt_at IS NOT NULL))";

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) generated,
					SUM(m.status='sent') sent,
					SUM({$terminal}) failed,
					SUM({$pending}) pending,
					SUM(GREATEST(m.attempts-1,0)) retries,
					AVG(CASE WHEN m.status='sent' THEN m.attempts ELSE NULL END) avg_attempts
				FROM {$messages} m
				WHERE m.message_type='daily' AND {$day_expr} BETWEEN %s AND %s",
				$start,
				$today
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$today_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) generated,
					SUM(m.status='sent') sent,
					SUM({$terminal}) failed,
					SUM({$pending}) pending
				FROM {$messages} m
				WHERE m.message_type='daily' AND {$day_expr}=%s",
				$today
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$opened_after = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT m.id)
					FROM {$messages} m
					INNER JOIN {$visits} v
						ON v.audience_id=m.audience_id
						AND m.sent_at IS NOT NULL
						AND v.visited_at>=m.sent_at
						AND v.visited_at<DATE_ADD(m.sent_at,INTERVAL 24 HOUR)
					WHERE m.message_type='daily'
						AND m.status='sent'
						AND {$day_expr} BETWEEN %s AND %s",
					$start,
					$today
				)
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$today_opened_after = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT m.id)
					FROM {$messages} m
					INNER JOIN {$visits} v
						ON v.audience_id=m.audience_id
						AND m.sent_at IS NOT NULL
						AND v.visited_at>=m.sent_at
						AND v.visited_at<DATE_ADD(m.sent_at,INTERVAL 24 HOUR)
					WHERE m.message_type='daily'
						AND m.status='sent'
						AND {$day_expr}=%s",
					$today
				)
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$aud = $wpdb->get_row(
			"SELECT COUNT(*) total,
				SUM(daily_enabled=1) daily_on,
				SUM(allows_write_to_pm=1) write_ok,
				SUM(daily_enabled=1 AND allows_write_to_pm=1) deliverable
			FROM {$audience}",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'generated'          => absint( $row['generated'] ?? 0 ),
			'sent'               => absint( $row['sent'] ?? 0 ),
			'failed'             => absint( $row['failed'] ?? 0 ),
			'pending'            => absint( $row['pending'] ?? 0 ),
			'retries'            => absint( $row['retries'] ?? 0 ),
			'avg_attempts'       => isset( $row['avg_attempts'] ) ? (float) $row['avg_attempts'] : 0.0,
			'opened_after'       => $opened_after,
			'today_generated'    => absint( $today_row['generated'] ?? 0 ),
			'today_sent'         => absint( $today_row['sent'] ?? 0 ),
			'today_failed'       => absint( $today_row['failed'] ?? 0 ),
			'today_pending'      => absint( $today_row['pending'] ?? 0 ),
			'today_opened_after' => $today_opened_after,
			'audience_total'     => absint( $aud['total'] ?? 0 ),
			'daily_on'           => absint( $aud['daily_on'] ?? 0 ),
			'write_ok'           => absint( $aud['write_ok'] ?? 0 ),
			'deliverable'        => absint( $aud['deliverable'] ?? 0 ),
		);
	}

	public static function daily_message_series( int $days ): array {
		global $wpdb;
		$messages = Dhikra_Data::table( 'messages' );
		$visits   = Dhikra_Data::table( 'visits' );
		$start    = self::start_date( $days );
		$today    = self::tehran_today();
		$day_expr = "COALESCE(m.daily_send_date,DATE(DATE_ADD(m.created_at,INTERVAL 210 MINUTE)))";
		$terminal = "(m.status='failed' AND (m.attempts>=3 OR m.next_attempt_at IS NULL))";
		$pending  = "(m.status IN ('queued','sending') OR (m.status='failed' AND m.attempts<3 AND m.next_attempt_at IS NOT NULL))";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$day_expr} send_day,
					COUNT(*) generated,
					SUM(m.status='sent') sent,
					SUM({$terminal}) failed,
					SUM({$pending}) pending,
					SUM(GREATEST(m.attempts-1,0)) retries
				FROM {$messages} m
				WHERE m.message_type='daily' AND {$day_expr} BETWEEN %s AND %s
				GROUP BY send_day
				ORDER BY send_day ASC",
				$start,
				$today
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$opened_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$day_expr} send_day,COUNT(DISTINCT m.id) opened_after
				FROM {$messages} m
				INNER JOIN {$visits} v
					ON v.audience_id=m.audience_id
					AND m.sent_at IS NOT NULL
					AND v.visited_at>=m.sent_at
					AND v.visited_at<DATE_ADD(m.sent_at,INTERVAL 24 HOUR)
				WHERE m.message_type='daily'
					AND m.status='sent'
					AND {$day_expr} BETWEEN %s AND %s
				GROUP BY send_day",
				$start,
				$today
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (string) $row['send_day'] ] = $row;
		}
		$opened_map = array();
		foreach ( is_array( $opened_rows ) ? $opened_rows : array() as $row ) {
			$opened_map[ (string) $row['send_day'] ] = absint( $row['opened_after'] ?? 0 );
		}

		$out = array();
		$cursor = new DateTimeImmutable( $start, new DateTimeZone( self::TZ ) );
		$end = new DateTimeImmutable( $today, new DateTimeZone( self::TZ ) );
		while ( $cursor <= $end ) {
			$date = $cursor->format( 'Y-m-d' );
			$row  = $map[ $date ] ?? array();
			$out[] = array(
				'date'         => $date,
				'generated'    => absint( $row['generated'] ?? 0 ),
				'sent'         => absint( $row['sent'] ?? 0 ),
				'failed'       => absint( $row['failed'] ?? 0 ),
				'pending'      => absint( $row['pending'] ?? 0 ),
				'retries'      => absint( $row['retries'] ?? 0 ),
				'opened_after' => absint( $opened_map[ $date ] ?? 0 ),
			);
			$cursor = $cursor->modify( '+1 day' );
		}
		return $out;
	}

	public static function recent_daily_failures( int $limit = 20 ): array {
		global $wpdb;
		$limit    = max( 1, min( 100, $limit ) );
		$messages = Dhikra_Data::table( 'messages' );
		$audience = Dhikra_Data::table( 'audience' );
		$sql = $wpdb->prepare(
			"SELECT m.id,m.audience_id,m.eitaa_user_id,m.daily_send_date,m.status,m.attempts,m.last_error,m.created_at,m.updated_at,m.next_attempt_at,
				a.first_name,a.last_name
			FROM {$messages} m
			LEFT JOIN {$audience} a ON a.id=m.audience_id
			WHERE m.message_type='daily' AND m.status='failed'
			ORDER BY m.updated_at DESC,m.id DESC
			LIMIT %d",
			$limit
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

}
