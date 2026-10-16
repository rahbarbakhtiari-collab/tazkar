<?php
/** Reliable, batched Eitaa broadcasts. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Broadcast {
	public const MAX_LENGTH = 4000;
	private const BATCH_SIZE = 30;
	private const LOCK_TTL = 300;

	public function register(): void {
		add_action( 'dhikra_broadcast_dispatch', array( $this, 'dispatch' ) );
		add_action( 'init', array( $this, 'ensure_schedule' ) );
	}

	public function ensure_schedule(): void {
		global $wpdb;
		$table = Dhikra_Data::table( 'broadcasts' );
		$pending = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('queued','processing')" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $pending > 0 && ! wp_next_scheduled( 'dhikra_broadcast_dispatch' ) ) {
			wp_schedule_single_event( time() + 10, 'dhikra_broadcast_dispatch' );
		}
	}

	public static function create( string $message, int $user_id, string $request_key ): array|WP_Error {
		global $wpdb;
		$message = sanitize_textarea_field( $message );
		$request_key = sanitize_key( $request_key );
		if ( '' === trim( $message ) || self::text_length( $message ) > self::MAX_LENGTH ) {
			return new WP_Error( 'dhikra_broadcast_text', sprintf( __( 'متن پیام باید بین ۱ و %d نویسه باشد.', 'dhikra' ), self::MAX_LENGTH ) );
		}
		if ( '' === $request_key ) { return new WP_Error( 'dhikra_broadcast_key', __( 'شناسهٔ درخواست معتبر نیست.', 'dhikra' ) ); }
		$broadcasts = Dhikra_Data::table( 'broadcasts' );
		$audience   = Dhikra_Data::table( 'audience' );
		$messages   = Dhikra_Data::table( 'messages' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$broadcasts} WHERE request_key=%s LIMIT 1", $request_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $existing ) ) { return $existing; }
		$total = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$audience}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$eligible = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$audience} WHERE allows_write_to_pm=1" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert( $broadcasts, array( 'request_key'=>$request_key, 'message_text'=>$message, 'status'=>'queued', 'created_by'=>$user_id, 'total_audience'=>$total, 'eligible_count'=>$eligible, 'skipped_no_permission'=>max( 0, $total-$eligible ), 'queued_count'=>0, 'created_at'=>$now ), array( '%s','%s','%s','%d','%d','%d','%d','%d','%s' ) );
		if ( false === $ok ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$broadcasts} WHERE request_key=%s LIMIT 1", $request_key ), ARRAY_A );
			return is_array( $existing ) ? $existing : new WP_Error( 'dhikra_broadcast_insert', __( 'ساخت ارسال همگانی انجام نشد.', 'dhikra' ) );
		}
		$id = absint( $wpdb->insert_id );
		// One set-based insert scales without holding the admin request for API calls.
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$messages} (audience_id,broadcast_id,campaign_id,reward_entry_id,eitaa_user_id,message_type,message_text,status,attempts,last_error,created_at,updated_at,sent_at,next_attempt_at) SELECT id,%d,0,0,eitaa_user_id,'broadcast',%s,'queued',0,'',%s,%s,NULL,%s FROM {$audience} WHERE allows_write_to_pm=1", $id, $message, $now, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$queued = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages} WHERE broadcast_id=%d", $id ) ) );
		$status = $queued > 0 ? 'queued' : 'completed';
		$wpdb->update( $broadcasts, array( 'queued_count'=>$queued, 'status'=>$status, 'completed_at'=>$queued > 0 ? null : $now ), array( 'id'=>$id ), array( '%d','%s','%s' ), array( '%d' ) );
		if ( $queued > 0 && ! wp_next_scheduled( 'dhikra_broadcast_dispatch' ) ) { wp_schedule_single_event( time() + 1, 'dhikra_broadcast_dispatch' ); }
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$broadcasts} WHERE id=%d", $id ), ARRAY_A );
	}

	public static function cancel( int $id ): bool {
		global $wpdb;
		$broadcasts = Dhikra_Data::table( 'broadcasts' ); $messages = Dhikra_Data::table( 'messages' ); $now = current_time( 'mysql', true );
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$broadcasts} SET status='cancelled',cancelled_at=%s WHERE id=%d AND status IN ('queued','processing')", $now, $id ) );
		if ( $changed ) { $wpdb->query( $wpdb->prepare( "UPDATE {$messages} SET status='cancelled',next_attempt_at=NULL,updated_at=%s WHERE broadcast_id=%d AND status IN ('queued','failed')", $now, $id ) ); self::refresh_counts( $id ); }
		return (bool) $changed;
	}

	public function dispatch(): void {
		if ( ! $this->acquire_lock() ) { return; }
		global $wpdb; $broadcasts = Dhikra_Data::table( 'broadcasts' ); $messages = Dhikra_Data::table( 'messages' ); $now = current_time( 'mysql', true );
		try {
			$broadcast = $wpdb->get_row( $wpdb->prepare( "SELECT b.* FROM {$broadcasts} b WHERE b.status IN ('queued','processing') AND EXISTS(SELECT 1 FROM {$messages} m WHERE m.broadcast_id=b.id AND (m.status='queued' OR (m.status='failed' AND m.attempts<3 AND m.next_attempt_at IS NOT NULL AND m.next_attempt_at<=%s))) ORDER BY b.id ASC LIMIT 1", $now ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! is_array( $broadcast ) ) {
				$next = (string) $wpdb->get_var( "SELECT MIN(m.next_attempt_at) FROM {$messages} m INNER JOIN {$broadcasts} b ON b.id=m.broadcast_id WHERE b.status IN ('queued','processing') AND m.status='failed' AND m.attempts<3 AND m.next_attempt_at IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $next && ! wp_next_scheduled( 'dhikra_broadcast_dispatch' ) ) { wp_schedule_single_event( max( time() + 10, strtotime( $next . ' UTC' ) ), 'dhikra_broadcast_dispatch' ); }
				return;
			}
			$id = absint( $broadcast['id'] );
			$wpdb->query( $wpdb->prepare( "UPDATE {$broadcasts} SET status='processing',started_at=COALESCE(started_at,%s) WHERE id=%d AND status IN ('queued','processing')", $now, $id ) );
			// Retry due items first; queued winner/personal work is still handled outside this broadcast-only worker.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$messages} WHERE broadcast_id=%d AND ((status='queued') OR (status='failed' AND attempts<3 AND next_attempt_at IS NOT NULL AND next_attempt_at<=%s)) ORDER BY CASE WHEN status='failed' THEN 0 ELSE 1 END,next_attempt_at ASC,id ASC LIMIT %d", $id, $now, self::BATCH_SIZE ) );
			foreach ( array_map( 'absint', is_array( $ids ) ? $ids : array() ) as $message_id ) { Dhikra_Audience::attempt_queued_message( $message_id ); }
			self::refresh_counts( $id );
		} finally { $this->release_lock(); }
		$remaining = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$broadcasts} WHERE status IN ('queued','processing')" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $remaining > 0 && ! wp_next_scheduled( 'dhikra_broadcast_dispatch' ) ) { wp_schedule_single_event( time() + 15, 'dhikra_broadcast_dispatch' ); }
	}

	public static function refresh_counts( int $id ): void {
		global $wpdb; $broadcasts=Dhikra_Data::table('broadcasts'); $messages=Dhikra_Data::table('messages');
		$row=$wpdb->get_row($wpdb->prepare("SELECT status FROM {$broadcasts} WHERE id=%d",$id),ARRAY_A); if(!is_array($row)){return;}
		$counts=$wpdb->get_results($wpdb->prepare("SELECT status,COUNT(*) total FROM {$messages} WHERE broadcast_id=%d GROUP BY status",$id),OBJECT_K);
		$sent=absint($counts['sent']->total??0); $terminal_failed=absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$messages} WHERE broadcast_id=%d AND status='failed' AND (attempts>=3 OR next_attempt_at IS NULL)",$id)));
		$active=absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$messages} WHERE broadcast_id=%d AND (status='queued' OR (status='failed' AND attempts<3 AND next_attempt_at IS NOT NULL))",$id)));
		$data=array('sent_count'=>$sent,'failed_count'=>$terminal_failed); $formats=array('%d','%d');
		if('cancelled'!==$row['status'] && 0===$active){$data['status']=$terminal_failed>0?'completed_with_errors':'completed';$data['completed_at']=current_time('mysql',true);$formats[]='%s';$formats[]='%s';}
		$wpdb->update($broadcasts,$data,array('id'=>$id),$formats,array('%d'));
	}

	private function acquire_lock(): bool { $now=time(); $lock=absint(get_option('dhikra_broadcast_lock',0)); if($lock && $lock+self::LOCK_TTL>$now){return false;} delete_option('dhikra_broadcast_lock'); return add_option('dhikra_broadcast_lock',$now,'','no'); }
	private function release_lock(): void { delete_option( 'dhikra_broadcast_lock' ); }
	private static function text_length( string $text ): int { return function_exists('mb_strlen') ? mb_strlen($text,'UTF-8') : strlen($text); }
}
