<?php
/** Administrator-only reward campaign management. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Reward_Admin {
	private const PAGE = 'dhikra-rewards';
	private const DASHBOARD_FIELDS = array( 'capacity', 'participants', 'winners', 'remaining_prizes', 'percent', 'latest_entries' );

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_dhikra_reward_create', array( $this, 'create_campaign' ) );
		add_action( 'admin_post_dhikra_reward_messages', array( $this, 'update_campaign' ) );
		add_action( 'admin_post_dhikra_reward_activate', array( $this, 'activate_campaign' ) );
		add_action( 'admin_post_dhikra_reward_close', array( $this, 'close_campaign' ) );
		add_action( 'admin_post_dhikra_reward_export', array( $this, 'export_csv' ) );
	}

	public function admin_menu(): void {
		add_submenu_page( 'edit.php?post_type=dhikra_telangor', __( 'جوایز ذکری', 'dhikra' ), __( 'جوایز', 'dhikra' ), 'manage_dhikra_rewards', self::PAGE, array( $this, 'render_page' ) );
	}

	private function require_access( string $nonce ): void {
		if ( ! current_user_can( 'manage_dhikra_rewards' ) ) { wp_die( esc_html__( 'دسترسی ندارید.', 'dhikra' ) ); }
		check_admin_referer( $nonce );
	}

	private function page_url( array $args = array() ): string {
		$url = admin_url( 'edit.php?post_type=dhikra_telangor&page=' . self::PAGE );
		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	private function posted_topics(): array {
		return array_values( array_intersect( Dhikra_Data::active_topic_ids(), array_filter( array_map( 'absint', (array) wp_unslash( $_POST['topic_ids'] ?? array() ) ) ) ) );
	}

	private function posted_dashboard_fields(): array {
		return array_values( array_intersect( self::DASHBOARD_FIELDS, array_map( 'sanitize_key', (array) wp_unslash( $_POST['dashboard_fields'] ?? array() ) ) ) );
	}

	public function create_campaign(): never {
		$this->require_access( 'dhikra_reward_create' );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$capacity    = absint( $_POST['capacity'] ?? 0 );
		$prizes      = absint( $_POST['prize_count'] ?? 0 );
		$all_topics  = ! empty( $_POST['all_topics'] );
		$topic_ids   = $this->posted_topics();
		$dashboard   = $this->posted_dashboard_fields();
		$app_message = sanitize_textarea_field( wp_unslash( $_POST['winner_app_message'] ?? '' ) );
		$pm_message  = sanitize_textarea_field( wp_unslash( $_POST['winner_pm_message'] ?? '' ) );
		$cta_label   = sanitize_text_field( wp_unslash( $_POST['winner_cta_label'] ?? '' ) );
		$cta_url     = esc_url_raw( wp_unslash( $_POST['winner_cta_url'] ?? '' ) );
		$prize_mode = sanitize_key( wp_unslash( $_POST['prize_mode'] ?? 'coupon' ) );
		$minimum = absint( $_POST['min_browse_cards'] ?? 4 );
		$maximum = absint( $_POST['max_browse_cards'] ?? 8 );
		if ( '' === $title || $capacity < 1 || $capacity > 100000 || $prizes < 1 || $prizes > $capacity || ( ! $all_topics && empty( $topic_ids ) ) || ! in_array( $prize_mode, array( 'coupon','message' ), true ) || $minimum < 2 || $maximum > 100 || $maximum < $minimum ) {
			wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'اطلاعات طرح یا دسته‌های انتخابی صحیح نیست.', 'dhikra' ) ) ) ); exit;
		}
		if ( empty( $dashboard ) ) { $dashboard = self::DASHBOARD_FIELDS; }
		global $wpdb;
		$created = $wpdb->insert( Dhikra_Data::table( 'reward_campaigns' ), array( 'title' => $title, 'capacity' => $capacity, 'prize_count' => $prizes, 'status' => 'draft', 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ), 'winner_app_message' => $app_message, 'winner_pm_message' => $pm_message, 'topic_ids' => wp_json_encode( $topic_ids ), 'all_topics' => $all_topics ? 1 : 0, 'dashboard_fields' => wp_json_encode( $dashboard ), 'winner_cta_label' => $cta_label, 'winner_cta_url' => $cta_url, 'prize_mode' => $prize_mode, 'min_browse_cards' => $minimum, 'max_browse_cards' => $maximum ), array( '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' ) );
		if ( false === $created ) {
			wp_safe_redirect( $this->page_url( array( 'tab' => 'create', 'dhikra_error' => __( 'ثبت پویش در پایگاه داده انجام نشد.', 'dhikra' ) ) ) ); exit;
		}
		wp_safe_redirect( $this->page_url( array( 'tab' => 'campaigns', 'dhikra_notice' => __( 'طرح جایزه ساخته شد. برای پویش کد تخفیف، پیش از فعال‌سازی کدها را وارد کنید.', 'dhikra' ) ) ) ); exit;
	}

	public function update_campaign(): never {
		$this->require_access( 'dhikra_reward_messages' );
		$id = absint( $_POST['campaign_id'] ?? 0 );
		global $wpdb;
		$table = Dhikra_Data::table( 'reward_campaigns' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) || in_array( (string) $row['status'], array( 'completed', 'closed' ), true ) ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'این طرح دیگر قابل ویرایش نیست.', 'dhikra' ) ) ) ); exit; }
		$data = array( 'winner_app_message' => sanitize_textarea_field( wp_unslash( $_POST['winner_app_message'] ?? '' ) ), 'winner_pm_message' => sanitize_textarea_field( wp_unslash( $_POST['winner_pm_message'] ?? '' ) ), 'dashboard_fields' => wp_json_encode( $this->posted_dashboard_fields() ), 'winner_cta_label' => sanitize_text_field( wp_unslash( $_POST['winner_cta_label'] ?? '' ) ), 'winner_cta_url' => esc_url_raw( wp_unslash( $_POST['winner_cta_url'] ?? '' ) ) );
		$formats = array( '%s', '%s', '%s', '%s', '%s' );
		if ( 'draft' === (string) $row['status'] ) {
			$prizes = absint( $_POST['prize_count'] ?? $row['prize_count'] );
			if ( $prizes < 1 || $prizes > absint( $row['capacity'] ) ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => 'تعداد جوایز از ظرفیت پویش بیشتر است.' ) ) ); exit; }
			$data['prize_count'] = $prizes; $formats[] = '%d';
			$all_topics = ! empty( $_POST['all_topics'] ); $topics = $this->posted_topics();
			if ( ! $all_topics && empty( $topics ) ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'دست‌کم یک دسته انتخاب کنید.', 'dhikra' ) ) ) ); exit; }
			$data['all_topics'] = $all_topics ? 1 : 0; $data['topic_ids'] = wp_json_encode( $topics ); $formats[] = '%d'; $formats[] = '%s';
			$minimum = absint( $_POST['min_browse_cards'] ?? 4 ); $maximum = absint( $_POST['max_browse_cards'] ?? 8 );
			if ( $minimum < 2 || $maximum < $minimum || $maximum > 100 ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => 'بازه نمایش کارت‌ها معتبر نیست.' ) ) ); exit; }
			$data['min_browse_cards'] = $minimum; $data['max_browse_cards'] = $maximum; $formats[] = '%d'; $formats[] = '%d';
		}
		$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		wp_safe_redirect( $this->page_url( array( 'dhikra_notice' => __( 'تنظیمات طرح به‌روزرسانی شد.', 'dhikra' ) ) ) ); exit;
	}

	public function activate_campaign(): never {
		$this->require_access( 'dhikra_reward_activate' ); $id = absint( $_POST['campaign_id'] ?? 0 );
		if ( ! empty( Dhikra_Data::active_reward_campaign() ) ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'فقط یک طرح می‌تواند فعال باشد.', 'dhikra' ) ) ) ); exit; }
		global $wpdb; $table = Dhikra_Data::table( 'reward_campaigns' ); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) || 'draft' !== (string) $row['status'] ) { wp_safe_redirect( $this->page_url() ); exit; }
		if ( Dhikra_Rewards::eligible_card_count( Dhikra_Rewards::campaign_topic_ids( $row ) ) < Dhikra_Rewards::browse_range( $row )[1] ) { wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'تعداد کارت‌های منتشرشده در دسته‌های انتخابی باید دست‌کم برابر بیشترین بازدید تعیین‌شده باشد.', 'dhikra' ) ) ) ); exit; }
		if ( Dhikra_Coupons::is_coupon_campaign( $row ) && Dhikra_Coupons::available_count( $id ) < absint( $row['prize_count'] ) ) { wp_safe_redirect( $this->page_url( array( 'tab' => 'coupons', 'campaign_id' => $id, 'dhikra_error' => 'تعداد کدهای معتبر و آماده کمتر از تعداد جایزه‌هاست. ابتدا بانک را تکمیل کنید.' ) ) ); exit; }
		$wpdb->update( $table, array( 'status' => 'active', 'activated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		wp_safe_redirect( $this->page_url( array( 'dhikra_notice' => __( 'پویش فعال شد؛ قرعه‌کشی فقط هنگام گشتن در کارت‌ها انجام می‌شود.', 'dhikra' ) ) ) ); exit;
	}

	public function close_campaign(): never {
		$this->require_access( 'dhikra_reward_close' ); $id = absint( $_POST['campaign_id'] ?? 0 ); $mode = sanitize_key( wp_unslash( $_POST['close_mode'] ?? 'cancel' ) ); $mode = in_array( $mode, array( 'cancel', 'complete' ), true ) ? $mode : 'cancel';
		global $wpdb; $campaigns = Dhikra_Data::table( 'reward_campaigns' ); $entries = Dhikra_Data::table( 'reward_entries' ); $wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns} WHERE id = %d FOR UPDATE", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) || 'active' !== (string) $row['status'] ) { $wpdb->query( 'ROLLBACK' ); wp_safe_redirect( $this->page_url( array( 'dhikra_error' => __( 'طرح فعال نیست.', 'dhikra' ) ) ) ); exit; }
		$filled = 0; $new_winners = array();
		if ( 'complete' === $mode ) {
			$current = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id = %d AND is_winner = 1", $id ) ) ); $need = max( 0, absint( $row['prize_count'] ) - $current ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( Dhikra_Coupons::is_coupon_campaign( $row ) && Dhikra_Coupons::available_count( $id ) < $need ) { $wpdb->query( 'ROLLBACK' ); wp_safe_redirect( $this->page_url( array( 'tab' => 'coupons', 'campaign_id' => $id, 'dhikra_error' => 'برای تکمیل باقیمانده، کد کافی موجود نیست.' ) ) ); exit; }
			$candidates = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$entries} WHERE campaign_id = %d AND is_winner = 0 ORDER BY RAND() LIMIT %d", $id, $need ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( is_array( $candidates ) ? $candidates : array() as $winner ) {
				$status = ( Dhikra_Coupons::is_coupon_campaign( $row ) || ! empty( $row['winner_pm_message'] ) ) ? 'awaiting_permission' : 'not_required';
				$entry_id = absint( $winner['id'] );
				$ok = $wpdb->update( $entries, array( 'is_winner' => 1, 'won_at' => current_time( 'mysql', true ), 'pm_status' => $status ), array( 'id' => $entry_id ), array( '%d', '%s', '%s' ), array( '%d' ) );
				if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); wp_die( 'به‌روزرسانی برنده ناموفق بود.' ); }
				if ( Dhikra_Coupons::is_coupon_campaign( $row ) ) {
					$assigned = Dhikra_Coupons::assign_locked( $id, $entry_id, absint( $winner['audience_id'] ) );
					if ( is_wp_error( $assigned ) ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html( $assigned->get_error_message() ) ); }
				}
				$new_winners[] = $winner; ++$filled;
			}
		}
		$wpdb->update( $campaigns, array( 'status' => 'closed', 'closed_at' => current_time( 'mysql', true ), 'close_mode' => $mode ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) ); $wpdb->query( 'COMMIT' );
		if ( 'complete' === $mode && ( Dhikra_Coupons::is_coupon_campaign( $row ) || ! empty( $row['winner_pm_message'] ) ) ) {
			foreach ( $new_winners as $winner ) {
				$audience = Dhikra_Data::table( 'audience' );
				$allowed = absint( $wpdb->get_var( $wpdb->prepare( "SELECT allows_write_to_pm FROM {$audience} WHERE id=%d AND eitaa_user_id=%s", absint( $winner['audience_id'] ), (string) $winner['eitaa_user_id'] ) ) );
				if ( $allowed ) { Dhikra_Rewards::dispatch_winner( $row, absint( $winner['id'] ), absint( $winner['audience_id'] ), (string) $winner['eitaa_user_id'] ); }
			}
		}
		$message = 'complete' === $mode ? sprintf( __( 'طرح بسته شد و %d جایزه باقی‌مانده تکمیل شد.', 'dhikra' ), $filled ) : __( 'طرح بسته شد؛ جوایز تخصیص‌نیافته لغو شدند.', 'dhikra' );
		wp_safe_redirect( $this->page_url( array( 'dhikra_notice' => $message ) ) ); exit;
	}

	public function export_csv(): never {
		$id = absint( $_GET['campaign_id'] ?? 0 ); $type = sanitize_key( wp_unslash( $_GET['export_type'] ?? 'participants' ) );
		$this->require_access( 'dhikra_reward_export_' . $id . '_' . $type );
		global $wpdb; $entries = Dhikra_Data::table( 'reward_entries' ); $audience = Dhikra_Data::table( 'audience' ); $winner_where = 'winners' === $type ? ' AND e.is_winner = 1' : '';
		$coupons = Dhikra_Data::table( 'reward_coupons' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.*,a.first_name,a.last_name,c.coupon_code FROM {$entries} e LEFT JOIN {$audience} a ON a.id=e.audience_id LEFT JOIN {$coupons} c ON c.id=e.coupon_id AND c.campaign_id=e.campaign_id WHERE e.campaign_id=%d{$winner_where} ORDER BY e.sequence_no ASC", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename=dhikra-campaign-' . $id . '-' . $type . '.csv' );
		$output = fopen( 'php://output', 'w' ); if ( false === $output ) { exit; } fwrite( $output, "\xEF\xBB\xBF" ); fputcsv( $output, array( 'شماره', 'شناسه ایتا', 'نام', 'زمان مشارکت', 'کارت', 'نتیجه', 'زمان برد', 'وضعیت پیام', 'زمان ارسال', 'تعداد تلاش', 'خطای آخر', 'کد تخفیف' ) );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) { fputcsv( $output, array_map( array( self::class, 'csv_cell' ), array( $row['sequence_no'], $row['eitaa_user_id'], trim( $row['first_name'] . ' ' . $row['last_name'] ), $row['created_at'], get_the_title( absint( $row['card_post_id'] ) ), ! empty( $row['is_winner'] ) ? 'برنده' : 'غیربرنده', $row['won_at'], $row['pm_status'], $row['pm_sent_at'], $row['pm_attempts'], $row['pm_last_error'], (string) ( $row['coupon_code'] ?? '' ) ) ) ); }
		fclose( $output ); exit;
	}

	private static function csv_cell( mixed $value ): string {
		$text = (string) ( $value ?? '' );
		return preg_match( '/^\s*[=+\-@]/u', $text ) ? "'" . $text : $text;
	}

	private function render_topic_fields( array $selected, bool $all, bool $disabled = false ): void {
		$terms = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false, 'include' => Dhikra_Data::active_topic_ids() ) ); ?>
		<div class="dhikra-reward-topic-grid"><label class="dhikra-reward-all"><input type="checkbox" name="all_topics" value="1" <?php checked( $all ); ?> <?php disabled( $disabled ); ?>> <span><?php esc_html_e( 'همه دسته‌ها', 'dhikra' ); ?></span></label><?php if ( ! is_wp_error( $terms ) ) : foreach ( $terms as $term ) : ?><label><input type="checkbox" name="topic_ids[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $selected, true ) ); ?> <?php disabled( $disabled ); ?>> <span><?php echo esc_html( $term->name ); ?></span></label><?php endforeach; endif; ?></div>
		<?php
	}

	private function render_dashboard_fields( array $selected ): void {
		$labels = array( 'capacity' => 'ظرفیت کل', 'participants' => 'تعداد شرکت‌کنندگان', 'winners' => 'برندگان مشخص‌شده', 'remaining_prizes' => 'جایزه‌های باقی‌مانده', 'percent' => 'درصد تکمیل', 'latest_entries' => 'آخرین مشارکت‌ها' ); ?>
		<div class="dhikra-check-grid"><?php foreach ( $labels as $key => $label ) : ?><label><input type="checkbox" name="dashboard_fields[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div><?php
	}

	/** Admin tabs render only their own data rather than loading all participants on every visit. */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_dhikra_rewards' ) ) { return; }
		global $wpdb;
		$table = Dhikra_Data::table( 'reward_campaigns' );
		$campaigns = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tabs = array( 'campaigns' => 'پویش‌ها', 'create' => 'ایجاد پویش', 'coupons' => 'بانک کدهای تخفیف', 'participants' => 'مشارکت‌کنندگان', 'winners' => 'برندگان' );
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'campaigns' ) );
		if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'campaigns'; }
		?>
		<div class="wrap dhikra-settings-wrap dhikra-rewards-admin">
		<h1>مدیریت پویش‌ها و جوایز ذِكْرَىٰ</h1>
		<p>بازکردن برنامک به‌تنهایی جایزه ندارد. برای هر پویش، تعداد کارت‌های متفاوت پیش از قرعه‌کشی را تعیین کنید. کدهای تخفیف باید از قبل در ووکامرس فروشگاه مناهل ساخته شوند.</p>
		<nav class="nav-tab-wrapper" aria-label="بخش‌های مدیریت جوایز">
		<?php foreach ( $tabs as $key => $label ) : ?>
		<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->page_url( array( 'tab' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?></nav>
		<?php if ( ! empty( $_GET['dhikra_notice'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['dhikra_notice'] ) ) ); ?></p></div><?php endif; ?>
		<?php if ( ! empty( $_GET['dhikra_error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['dhikra_error'] ) ) ); ?></p></div><?php endif; ?>
		<?php
		switch ( $tab ) {
			case 'create': $this->render_create(); break;
			case 'coupons': Dhikra_Coupon_Admin::render( $campaigns ); break;
			case 'participants': $this->render_participants( $campaigns ); break;
			case 'winners': $this->render_winners( $campaigns ); break;
			default: $this->render_campaigns( $campaigns );
		}
		?></div><?php
	}

	private function render_create(): void { ?>
		<section class="dhikra-settings-card"><h2>ایجاد پویش جایزه</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="dhikra_reward_create"><?php wp_nonce_field( 'dhikra_reward_create' ); ?>
		<table class="form-table" role="presentation">
		<tr><th><label for="dhikra-reward-title">عنوان</label></th><td><input class="regular-text" id="dhikra-reward-title" name="title" required></td></tr>
		<tr><th>نوع جایزه</th><td><label><input type="radio" name="prize_mode" value="coupon" checked> کد تخفیف فروشگاه مناهل</label><br><label><input type="radio" name="prize_mode" value="message"> جایزه/پیام معمولی (پویش‌های قبلی)</label><p class="description">پس از ایجاد پویش، کدهای ووکامرس را از تب بانک کد وارد کنید.</p></td></tr>
		<tr><th>ظرفیت / تعداد برنده</th><td><input type="number" name="capacity" min="1" max="100000" value="400" required> / <input type="number" name="prize_count" min="1" max="100000" value="5" required></td></tr>
		<tr><th>تعداد بازدید کارت برای قرعه‌کشی</th><td><?php $this->browse_inputs( 4, 8 ); ?></td></tr>
		<tr><th>دسته‌های کارت</th><td><?php $this->render_topic_fields( array(), true ); ?></td></tr>
		<tr><th>شاخص‌های داشبورد</th><td><?php $this->render_dashboard_fields( self::DASHBOARD_FIELDS ); ?></td></tr>
		<tr><th>پیام برنده در برنامک</th><td><textarea class="large-text" name="winner_app_message" rows="3">تبریک! شما برنده جایزه این پویش شدید. کد تخفیف شما از طریق پیام خصوصی ایتا ارسال می‌شود.</textarea></td></tr>
		<tr><th>متن آغاز پیام خصوصی ایتا</th><td><textarea class="large-text" name="winner_pm_message" rows="3">تبریک! شما برنده جایزه ذِكْرَىٰ شدید 🌱</textarea><p class="description">برای پویش کد تخفیف، ذکری خودکار کد اختصاصی و آدرس فروشگاه را به انتهای پیام اضافه می‌کند؛ خودتان کد ننویسید.</p></td></tr>
		<tr><th>دکمه اطلاعات جایزه (اختیاری)</th><td><input name="winner_cta_label" placeholder="مشاهده اطلاعات جایزه"> <input class="regular-text" name="winner_cta_url" type="url" dir="ltr" placeholder="https://..."></td></tr>
		</table><button class="button button-primary">ساخت پیش‌نویس</button></form></section>
		<?php
	}

	private function browse_inputs( int $minimum, int $maximum, bool $disabled = false ): void { ?>
		<label>از کارت <input type="number" style="width:85px" name="min_browse_cards" min="2" max="100" value="<?php echo esc_attr( (string) $minimum ); ?>" <?php disabled( $disabled ); ?> required></label>
		<label>تا کارت <input type="number" style="width:85px" name="max_browse_cards" min="2" max="100" value="<?php echo esc_attr( (string) $maximum ); ?>" <?php disabled( $disabled ); ?> required></label>
		<p class="description">برای نمایش شانس در کارت مشخص، هر دو مقدار را یکسان بگذارید؛ برای نمایش تصادفی، بازه تعیین کنید. کارت اول شمارش می‌شود اما هرگز جایزه نمی‌دهد. شمارش فقط برای کارت‌های متفاوت است.</p>
		<?php
	}

	private function render_campaigns( array $campaigns ): void {
		global $wpdb;
		$entries = Dhikra_Data::table( 'reward_entries' );
		$labels = array( 'draft' => 'پیش‌نویس', 'active' => 'فعال', 'completed' => 'تکمیل ظرفیت', 'closed' => 'بسته‌شده' );
		if ( empty( $campaigns ) ) { echo '<p>هنوز پویشی ایجاد نشده است.</p>'; return; }
		foreach ( $campaigns as $campaign ) :
			$id = absint( $campaign['id'] );
			$parts = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id=%d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wins = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id=%d AND is_winner=1", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$dashboard = json_decode( (string) $campaign['dashboard_fields'], true );
			$dashboard = is_array( $dashboard ) ? $dashboard : self::DASHBOARD_FIELDS;
			$topics = json_decode( (string) $campaign['topic_ids'], true );
			$topics = array_map( 'absint', is_array( $topics ) ? $topics : array() );
			list( $minimum, $maximum ) = Dhikra_Rewards::browse_range( $campaign );
			$percent = round( 100 * $parts / max( 1, absint( $campaign['capacity'] ) ), 1 );
			?>
			<section class="dhikra-settings-card dhikra-campaign-card">
			<div class="dhikra-reward-heading"><div><h2><?php echo esc_html( $campaign['title'] ); ?></h2><span class="dhikra-status-pill"><?php echo esc_html( $labels[ $campaign['status'] ] ?? $campaign['status'] ); ?></span></div><strong><?php echo esc_html( $parts . ' / ' . absint( $campaign['capacity'] ) ); ?></strong></div>
			<p>نوع جایزه: <strong><?php echo Dhikra_Coupons::is_coupon_campaign( $campaign ) ? 'کد تخفیف مناهل' : 'جایزه معمولی'; ?></strong> | زمان قرعه‌کشی: <?php echo esc_html( $minimum === $maximum ? 'کارت ' . $minimum : 'بین کارت ' . $minimum . ' تا ' . $maximum ); ?> | برندگان: <?php echo esc_html( (string) $wins ); ?> / <?php echo esc_html( (string) $campaign['prize_count'] ); ?> | پیشرفت: <?php echo esc_html( $percent . '%' ); ?></p>
			<?php if ( Dhikra_Coupons::is_coupon_campaign( $campaign ) ) : ?><p>کدهای آماده: <strong><?php echo esc_html( (string) Dhikra_Coupons::available_count( $id ) ); ?></strong> <a class="button" href="<?php echo esc_url( $this->page_url( array( 'tab' => 'coupons','campaign_id' => $id ) ) ); ?>">مدیریت کدهای این پویش</a></p><?php endif; ?>
			<?php if ( in_array( $campaign['status'], array( 'draft','active' ), true ) ) : ?>
			<details><summary><strong>تنظیمات پویش</strong></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dhikra_reward_messages"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $id ); ?>"><?php wp_nonce_field( 'dhikra_reward_messages' ); ?>
			<?php if ( 'draft' === $campaign['status'] ) : ?><p><label>تعداد برندگان این پویش <input type="number" name="prize_count" min="1" max="<?php echo esc_attr( (string) $campaign['capacity'] ); ?>" value="<?php echo esc_attr( (string) $campaign['prize_count'] ); ?>" required></label></p><?php endif; ?>
			<h4>بازه کارت‌ها</h4><?php $this->browse_inputs( $minimum, $maximum, 'active' === $campaign['status'] ); ?>
			<h4>دسته‌های کارت‌ها</h4><?php $this->render_topic_fields( $topics, ! empty( $campaign['all_topics'] ), 'active' === $campaign['status'] ); ?>
			<h4>شاخص‌های داشبورد</h4><?php $this->render_dashboard_fields( $dashboard ); ?>
			<p><label>پیام برنده در برنامک<br><textarea class="large-text" rows="3" name="winner_app_message"><?php echo esc_textarea( (string) $campaign['winner_app_message'] ); ?></textarea></label></p>
			<p><label>متن آغاز پیام ایتا<br><textarea class="large-text" rows="3" name="winner_pm_message"><?php echo esc_textarea( (string) $campaign['winner_pm_message'] ); ?></textarea></label></p>
			<p><label>دکمه اطلاعات <input name="winner_cta_label" value="<?php echo esc_attr( (string) $campaign['winner_cta_label'] ); ?>"></label> <label>آدرس <input class="regular-text" type="url" dir="ltr" name="winner_cta_url" value="<?php echo esc_attr( (string) $campaign['winner_cta_url'] ); ?>"></label></p>
			<p><button class="button">ذخیره تنظیمات</button></p></form></details>
			<?php endif; ?>
			<?php if ( 'draft' === $campaign['status'] ) : ?><form class="dhikra-inline-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dhikra_reward_activate"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $id ); ?>"><?php wp_nonce_field( 'dhikra_reward_activate' ); ?><button class="button button-primary">فعال‌سازی پویش</button></form>
			<?php elseif ( 'active' === $campaign['status'] ) : ?><div class="dhikra-close-actions"><?php foreach ( array( 'cancel' => 'پایان و لغو باقی‌مانده', 'complete' => 'تکمیل جوایز و پایان' ) as $mode => $label ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dhikra_reward_close"><input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $id ); ?>"><input type="hidden" name="close_mode" value="<?php echo esc_attr( $mode ); ?>"><?php wp_nonce_field( 'dhikra_reward_close' ); ?><button class="button <?php echo 'complete' === $mode ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button></form><?php endforeach; ?></div><?php endif; ?>
			<a class="button" href="<?php echo esc_url( $this->page_url( array( 'tab' => 'participants','campaign_id' => $id ) ) ); ?>">مشارکت‌کنندگان</a> <a class="button" href="<?php echo esc_url( $this->page_url( array( 'tab' => 'winners','campaign_id' => $id ) ) ); ?>">برندگان</a>
			</section>
		<?php endforeach;
	}

	private function selection( array $campaigns, string $tab ): int {
		$id = absint( $_GET['campaign_id'] ?? 0 );
		if ( ! $id && ! empty( $campaigns ) ) { $id = absint( $campaigns[0]['id'] ); }
		?><form method="get"><input type="hidden" name="post_type" value="dhikra_telangor"><input type="hidden" name="page" value="dhikra-rewards"><input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>"><label>پویش: <select name="campaign_id"><?php foreach ( $campaigns as $item ) : ?><option value="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php selected( $id, absint( $item['id'] ) ); ?>><?php echo esc_html( $item['title'] ); ?></option><?php endforeach; ?></select></label> <button class="button">نمایش</button></form><?php
		return in_array( $id, array_map( static fn( $item ) => absint( $item['id'] ), $campaigns ), true ) ? $id : 0;
	}

	private function render_participants( array $campaigns ): void {
		$id = $this->selection( $campaigns, 'participants' );
		if ( ! $id ) { return; }
		global $wpdb; $entries = Dhikra_Data::table( 'reward_entries' ); $audience = Dhikra_Data::table( 'audience' );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) ); $per_page = 30;
		$total = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id=%d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.*,a.first_name,a.last_name FROM {$entries} e LEFT JOIN {$audience} a ON a.id=e.audience_id WHERE e.campaign_id=%d ORDER BY e.sequence_no DESC LIMIT %d OFFSET %d", $id, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?><section class="dhikra-settings-card"><h2>مشارکت‌کنندگان (<?php echo esc_html( (string) $total ); ?>)</h2><p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dhikra_reward_export&campaign_id=' . $id . '&export_type=participants' ), 'dhikra_reward_export_' . $id . '_participants' ) ); ?>">CSV مشارکت‌کنندگان</a></p><div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>#</th><th>شناسه ایتا</th><th>نام</th><th>کارت</th><th>تاریخ</th><th>نتیجه</th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['sequence_no'] ); ?></td><td><code><?php echo esc_html( $row['eitaa_user_id'] ); ?></code></td><td><?php echo esc_html( trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ) ); ?></td><td><?php echo esc_html( get_the_title( absint( $row['card_post_id'] ) ) ?: '—' ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td><td><?php echo empty( $row['is_winner'] ) ? 'شرکت‌کننده' : 'برنده'; ?></td></tr><?php endforeach; ?>
		</tbody></table></div><?php $this->page_links( 'participants', $id, $total, $page, $per_page ); ?></section><?php
	}

	private function render_winners( array $campaigns ): void {
		$id = $this->selection( $campaigns, 'winners' );
		if ( ! $id ) { return; }
		global $wpdb; $entries = Dhikra_Data::table( 'reward_entries' ); $audience = Dhikra_Data::table( 'audience' ); $coupons = Dhikra_Data::table( 'reward_coupons' );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) ); $per_page = 30;
		$total = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$entries} WHERE campaign_id=%d AND is_winner=1", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.*,a.first_name,a.last_name,c.coupon_code,c.id AS coupon_id,c.status AS coupon_status FROM {$entries} e LEFT JOIN {$audience} a ON a.id=e.audience_id LEFT JOIN {$coupons} c ON c.id=e.coupon_id AND c.campaign_id=e.campaign_id WHERE e.campaign_id=%d AND e.is_winner=1 ORDER BY e.sequence_no DESC LIMIT %d OFFSET %d", $id, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?><section class="dhikra-settings-card"><h2>برندگان (<?php echo esc_html( (string) $total ); ?>)</h2><p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dhikra_reward_export&campaign_id=' . $id . '&export_type=winners' ), 'dhikra_reward_export_' . $id . '_winners' ) ); ?>">CSV برندگان</a></p><div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>#</th><th>نام / شناسه ایتا</th><th>کارت</th><th>کد جایزه</th><th>زمان برد</th><th>وضعیت تحویل</th><th>پیگیری</th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['sequence_no'] ); ?></td><td><?php echo esc_html( trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ) ); ?><br><code><?php echo esc_html( $row['eitaa_user_id'] ); ?></code></td><td><?php echo esc_html( get_the_title( absint( $row['card_post_id'] ) ) ?: '—' ); ?></td><td><code dir="ltr"><?php echo esc_html( $row['coupon_code'] ?: '—' ); ?></code></td><td><?php echo esc_html( $row['won_at'] ?: '—' ); ?></td><td><?php echo esc_html( $row['pm_status'] ); ?><?php if ( $row['pm_sent_at'] ) : ?><br><?php echo esc_html( $row['pm_sent_at'] ); ?><?php endif; ?></td><td><?php if ( $row['coupon_id'] ) : ?><a class="button" href="<?php echo esc_url( $this->page_url( array( 'tab' => 'coupons', 'campaign_id' => $id, 'entry_id' => absint( $row['id'] ) ) ) ); ?>">بانک کدها / ارسال پیام</a><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table></div><?php $this->page_links( 'winners', $id, $total, $page, $per_page ); ?></section><?php
	}

	private function page_links( string $tab, int $campaign_id, int $total, int $page, int $per_page ): void {
		if ( $total <= $per_page ) { return; }
		echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', $this->page_url( array( 'tab' => $tab, 'campaign_id' => $campaign_id ) ) ), 'current' => $page, 'total' => (int) ceil( $total / $per_page ) ) ) );
	}
}
