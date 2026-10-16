<?php
/**
 * Security and privacy integrations.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Security {
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_text' ) );
	}

	public function register_exporter( array $exporters ): array {
		$exporters['dhikra'] = array(
			'exporter_friendly_name' => Dhikra_Helpers::PRODUCT_NAME,
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	public function register_eraser( array $erasers ): array {
		$erasers['dhikra'] = array(
			'eraser_friendly_name' => Dhikra_Helpers::PRODUCT_NAME,
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public function export_personal_data( string $email_address, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return array( 'data' => array(), 'done' => true );
		}
		$favorites = Dhikra_Data::favorite_ids( $user->ID, 1, 1000 );
		$history   = Dhikra_Data::history_ids( $user->ID, 1, 100 );
		global $wpdb;
		$identity_table = Dhikra_Data::table( 'identities' );
		$providers      = $wpdb->get_col( $wpdb->prepare( "SELECT provider FROM {$identity_table} WHERE user_id = %d ORDER BY provider ASC", $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$reward_entries = Dhikra_Data::table( 'reward_entries' );
		$reward_campaigns = Dhikra_Data::table( 'reward_campaigns' );
		$reward_rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.title,c.status,e.sequence_no,e.is_winner,e.created_at,e.won_at FROM {$reward_entries} e INNER JOIN {$reward_campaigns} c ON c.id=e.campaign_id WHERE e.user_id=%d ORDER BY e.id DESC", $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$reward_summary = array();
		foreach ( is_array( $reward_rows ) ? $reward_rows : array() as $reward_row ) {
			$reward_summary[] = sprintf( '%1$s | #%2$d | %3$s | %4$s', (string) $reward_row['title'], absint( $reward_row['sequence_no'] ), ! empty( $reward_row['is_winner'] ) ? __( 'برنده', 'dhikra' ) : __( 'غیربرنده', 'dhikra' ), (string) $reward_row['status'] );
		}
		$data      = array(
			array( 'name' => __( 'موضوعات غیرفعال‌شده', 'dhikra' ), 'value' => implode( ', ', array_map( 'strval', Dhikra_Data::excluded_topic_ids( $user->ID ) ) ) ),
			array( 'name' => __( 'تلنگرهای علاقه‌مندی', 'dhikra' ), 'value' => implode( ', ', array_map( static fn( int $id ): string => Dhikra_Data::get_short_code( $id ), $favorites ) ) ),
			array( 'name' => __( 'تاریخچهٔ تلنگرها', 'dhikra' ), 'value' => implode( ', ', array_map( static fn( int $id ): string => Dhikra_Data::get_short_code( $id ), $history ) ) ),
			array( 'name' => __( 'بسترهای هویتی پیوندخورده', 'dhikra' ), 'value' => implode( ', ', array_map( 'sanitize_key', is_array( $providers ) ? $providers : array() ) ) ),
			array( 'name' => __( 'سوابق طرح‌های جایزه', 'dhikra' ), 'value' => implode( "\n", $reward_summary ) ),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => 'dhikra',
					'group_label' => Dhikra_Helpers::PRODUCT_NAME,
					'item_id'     => 'dhikra-user-' . $user->ID,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$wpdb->delete( Dhikra_Data::table( 'favorites' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		$wpdb->delete( Dhikra_Data::table( 'history' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		$wpdb->delete( Dhikra_Data::table( 'identities' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		delete_user_meta( $user->ID, '_dhikra_excluded_topics' );
		$reward_entries = Dhikra_Data::table( 'reward_entries' );
		$reward_count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reward_entries} WHERE user_id = %d", $user->ID ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages = array();
		if ( $reward_count > 0 ) {
			$messages[] = __( 'سوابق طرح جایزه برای حفظ صحت ظرفیت، ترتیب مشارکت و نتیجهٔ قرعه نگهداری شد.', 'dhikra' );
		}
		return array( 'items_removed' => true, 'items_retained' => $reward_count > 0, 'messages' => $messages, 'done' => true );
	}

	public function add_privacy_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			Dhikra_Helpers::PRODUCT_NAME,
			wp_kses_post( '<p>' . __( 'ذِكْرَىٰ برای کاربران واردشده، علاقه‌مندی‌ها، حداکثر ۱۰۰ تلنگر اخیر، فهرست موضوعات غیرفعال‌شده و در صورت فعال بودن طرح جایزه، شناسه کاربر، ترتیب مشارکت و نتیجه برنده/غیربرنده را در همان سایت ذخیره می‌کند. ورود و ثبت‌نام با افزونه «ورود پیامکی مناهل با IPPanel» انجام می‌شود؛ این افزونه شماره موبایل و کد یک‌بارمصرف را برای ارسال پیامک به IPPanel Edge می‌فرستد و شماره نرمال‌شده را در شناسنامه کاربر وردپرس نگه می‌دارد. در صفحات مستقل برنامک، SDK رسمی ایتا مستقیماً از developer.eitaa.com بارگیری می‌شود. این نسخه هیچ دادهٔ هویتی ایتا را بدون اعتبارسنجی سمت سرور قبول یا ذخیره نمی‌کند.', 'dhikra' ) . '</p>' )
		);
	}
}
