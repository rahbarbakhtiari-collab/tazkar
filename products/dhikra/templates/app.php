<?php
/**
 * Standalone Dhikra application template.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dhikra_render_pagination' ) ) {
	/**
	 * Render compact pagination.
	 */
	function dhikra_render_pagination( int $current, int $pages, string $base_url ): string {
		if ( $pages <= 1 ) {
			return '';
		}
		$output = '<nav class="dhikra-pagination" aria-label="' . esc_attr__( 'صفحه‌بندی', 'dhikra' ) . '">';
		if ( $current > 1 ) {
			$output .= '<a href="' . esc_url( add_query_arg( 'dhikra_page', $current - 1, $base_url ) ) . '">' . esc_html__( 'قبلی', 'dhikra' ) . '</a>';
		}
		$output .= '<span>' . sprintf( esc_html__( 'صفحهٔ %1$d از %2$d', 'dhikra' ), $current, $pages ) . '</span>';
		if ( $current < $pages ) {
			$output .= '<a href="' . esc_url( add_query_arg( 'dhikra_page', $current + 1, $base_url ) ) . '">' . esc_html__( 'بعدی', 'dhikra' ) . '</a>';
		}
		return $output . '</nav>';
	}
}

if ( ! function_exists( 'dhikra_render_saved_item' ) ) {
	/** Render one server-side saved card as a no-JS fallback and hydration target. */
	function dhikra_render_saved_item( array $row ): string {
		$post_id = absint( $row['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return '';
		}
		$item = Dhikra_Random::telangor_data( $post_id, 'general', false, false );
		if ( empty( $item ) ) {
			return '';
		}
		$preview = trim( (string) ( $item['translation'] ?: ( $item['reflection'] ?: $item['original'] ) ) );
		$preview = wp_trim_words( wp_strip_all_tags( $preview ), 24, '…' );
		$saved_at = (string) ( $row['created_at'] ?? '' );
		$saved_ts = '' !== $saved_at ? strtotime( $saved_at . ' UTC' ) : false;
		$saved_label = false !== $saved_ts ? get_date_from_gmt( $saved_at, get_option( 'date_format' ) ) : '';
		ob_start();
		?>
		<article class="dhikra-saved-card" data-dhikra-saved-item data-code="<?php echo esc_attr( (string) $item['code'] ); ?>" data-title="<?php echo esc_attr( (string) $item['title'] ); ?>" data-topic="<?php echo esc_attr( (string) $item['main_topic_name'] ); ?>" data-saved-ts="<?php echo esc_attr( false !== $saved_ts ? (string) $saved_ts : '0' ); ?>">
			<div class="dhikra-saved-card__top">
				<div class="dhikra-saved-card__chips">
					<?php if ( '' !== (string) $item['main_topic_name'] ) : ?><span><?php echo esc_html( (string) $item['main_topic_name'] ); ?></span><?php endif; ?>
					<?php if ( '' !== (string) $item['type_label'] ) : ?><span class="is-type"><?php echo esc_html( (string) $item['type_label'] ); ?></span><?php endif; ?>
				</div>
				<span class="dhikra-saved-card__heart" aria-hidden="true">♥</span>
			</div>
			<h2><a href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></h2>
			<?php if ( '' !== $preview ) : ?><p class="dhikra-saved-card__excerpt"><?php echo esc_html( $preview ); ?></p><?php endif; ?>
			<div class="dhikra-saved-card__meta">
				<?php if ( '' !== (string) $item['source'] ) : ?><span><?php echo esc_html( (string) $item['source'] ); ?></span><?php endif; ?>
				<?php if ( '' !== $saved_label ) : ?><time datetime="<?php echo esc_attr( $saved_at ); ?>"><?php echo esc_html( $saved_label ); ?></time><?php endif; ?>
			</div>
			<div class="dhikra-saved-card__actions">
				<a href="<?php echo esc_url( (string) $item['url'] ); ?>"><span aria-hidden="true">↗</span><?php esc_html_e( 'باز کردن', 'dhikra' ); ?></a>
				<form method="post"><input type="hidden" name="dhikra_account_action" value="remove_favorite"><input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>"><?php wp_nonce_field( 'dhikra_remove_favorite', 'dhikra_nonce' ); ?><button type="submit" data-dhikra-saved-remove><span aria-hidden="true">×</span><?php esc_html_e( 'حذف', 'dhikra' ); ?></button></form>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}
}

$route = Dhikra_Helpers::request_route();
$route = '' !== $route ? $route : 'home';
$notice = isset( $_GET['dhikra_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['dhikra_notice'] ) ) : '';
$error  = isset( $_GET['dhikra_error'] ) ? sanitize_text_field( wp_unslash( $_GET['dhikra_error'] ) ) : '';
$return_to = isset( $_GET['return_to'] ) ? Dhikra_Helpers::safe_return_url( esc_url_raw( wp_unslash( $_GET['return_to'] ) ), Dhikra_Helpers::app_url( 'account' ) ) : Dhikra_Helpers::app_url( 'account' );
list( $reward_notice, $reward_notice_type ) = Dhikra_Rewards::frontend_notice();
$recent_codes = array();
if ( isset( $_COOKIE['dhikra_recent'] ) ) {
	$recent_codes = array_filter( array_map( array( 'Dhikra_Helpers', 'sanitize_short_code' ), explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['dhikra_recent'] ) ) ) ) );
}

$card_post_id = 0;
$preview_id = isset( $_GET['dhikra_preview'] ) ? absint( $_GET['dhikra_preview'] ) : 0;
$preview_ok = $preview_id > 0 && current_user_can( 'edit_post', $preview_id ) && isset( $_GET['dhikra_preview_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['dhikra_preview_nonce'] ) ), 'dhikra_preview_' . $preview_id );
if ( $preview_ok ) {
	$card_post_id = $preview_id;
} elseif ( 'home' === $route ) {
	$card_post_id = Dhikra_Rewards::first_card_for_active_campaign( $recent_codes );
	if ( $card_post_id <= 0 ) { $card_post_id = Dhikra_Random::next_for_request( $recent_codes ); }
} elseif ( 'telangor' === $route ) {
	$card_post_id = Dhikra_Data::find_post_by_code( (string) get_query_var( 'dhikra_code' ), true );
	if ( is_user_logged_in() && $card_post_id > 0 ) {
		Dhikra_Data::record_history( get_current_user_id(), $card_post_id );
	}
}

$card_routes = in_array( $route, array( 'home', 'telangor' ), true );
$body_class  = 'dhikra-app dhikra-route-' . sanitize_html_class( $route ) . ( $card_routes ? ' dhikra-app--card-route' : ' dhikra-app--page-route' );
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="<?php echo esc_attr( $preview_ok || in_array( $route, array( 'login', 'register', 'account', 'favorites', 'history', 'print', 'print-preview' ), true ) ? 'noindex,nofollow' : 'max-image-preview:large' ); ?>">
	<?php if ( $card_post_id > 0 ) : $seo = Dhikra_Random::telangor_data( $card_post_id, 'general', false, false ); ?>
		<meta property="og:title" content="<?php echo esc_attr( $seo['title'] ); ?>">
		<meta property="og:description" content="<?php echo esc_attr( wp_trim_words( $seo['translation'] ?: $seo['original'], 30 ) ); ?>">
		<meta property="og:url" content="<?php echo esc_url( $seo['url'] ); ?>">
	<?php endif; ?>
	<style id="dhikra-critical">html{background:#eef4fb}body.dhikra-app{margin:0;background:#eef4fb;color:#102a43}body.dhikra-app--card-route{min-height:100svh}.dhikra-shell{min-height:100svh}.dhikra-card-slot{background:#f8fbff;border-radius:28px}</style>
	<?php wp_head(); ?>
</head>
<body class="<?php echo esc_attr( $body_class ); ?>" data-dhikra-route="<?php echo esc_attr( $route ); ?>">
<a class="dhikra-skip-link" href="#dhikra-main"><?php esc_html_e( 'رفتن به محتوای اصلی', 'dhikra' ); ?></a>
<div class="dhikra-shell">
	<?php if ( ! $card_routes ) : ?>
	<header class="dhikra-topbar">
		<a class="dhikra-logo" href="<?php echo esc_url( Dhikra_Helpers::app_url( 'home' ) ); ?>"><span aria-hidden="true">ذ</span><strong><?php echo esc_html( Dhikra_Helpers::PRODUCT_NAME ); ?></strong></a>
		<nav class="dhikra-nav" aria-label="<?php esc_attr_e( 'پیمایش ذِكْرَىٰ', 'dhikra' ); ?>">
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'print' ) ); ?>"><?php esc_html_e( 'چاپ کارت', 'dhikra' ); ?></a>
				<a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'favorites' ) ); ?>"><?php esc_html_e( 'ذخیره‌شده‌ها', 'dhikra' ); ?></a>
				<a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'account' ) ); ?>"><?php esc_html_e( 'حساب من', 'dhikra' ); ?></a>
			<?php else : ?>
				<span data-dhikra-eitaa-nav hidden><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'home' ) ); ?>">تلنگرها</a> <a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'favorites' ) ); ?>">ذخیره‌شده‌ها</a> <a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'history' ) ); ?>">تاریخچه</a> <a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'account' ) ); ?>">حساب من</a> <a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'print' ) ); ?>">چاپ</a></span>
				<a data-dhikra-anonymous-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'home' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>">ورود</a>
			<?php endif; ?>
		</nav>
	</header>
	<?php endif; ?>

	<main id="dhikra-main" class="dhikra-main">
		<?php if ( '' !== $notice ) : echo wp_kses_post( Dhikra_Helpers::notice( $notice, 'success' ) ); endif; ?>
		<?php if ( '' !== $error ) : echo wp_kses_post( Dhikra_Helpers::notice( $error, 'error' ) ); endif; ?>
		<?php if ( '' !== $reward_notice ) : echo wp_kses_post( Dhikra_Helpers::notice( $reward_notice, $reward_notice_type ) ); endif; ?>

		<?php if ( $card_routes ) : ?>
			<section class="dhikra-focus" data-dhikra-widget>
				<nav class="dhikra-discovery-bar" aria-label="<?php esc_attr_e( 'امکانات ذِكْرَىٰ', 'dhikra' ); ?>">
					<button type="button" data-dhikra-search-open><span aria-hidden="true">⌕</span><span><?php esc_html_e( 'جستجو', 'dhikra' ); ?></span></button>
					<button type="button" data-dhikra-topics-open><span aria-hidden="true">◫</span><span><?php esc_html_e( 'موضوعات', 'dhikra' ); ?></span></button>
					<a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'favorites' ) ); ?>"><span aria-hidden="true">♡</span><span><?php esc_html_e( 'ذخیره‌ها', 'dhikra' ); ?></span></a>
					<button type="button" data-dhikra-daily-settings-open hidden><span aria-hidden="true">◌</span><span data-dhikra-daily-short-label><?php esc_html_e( 'روزانه', 'dhikra' ); ?></span></button>
				</nav>
				<div class="dhikra-pull-indicator" data-dhikra-pull-indicator aria-hidden="true"><span aria-hidden="true">↓</span><strong><?php esc_html_e( 'برای تلنگر جدید بالا یا پایین بکشید', 'dhikra' ); ?></strong></div>
				<div class="dhikra-card-slot" data-dhikra-card-slot>
					<?php echo Dhikra_Random::render_card( $card_post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="dhikra-actions" aria-label="<?php esc_attr_e( 'عملیات کارت', 'dhikra' ); ?>">
					<button type="button" class="dhikra-action dhikra-action--primary" data-dhikra-next><span aria-hidden="true">↻</span><span><?php echo esc_html( (string) Dhikra_Helpers::setting( 'button_label', __( 'تلنگر جدید', 'dhikra' ) ) ); ?></span></button>
					<button type="button" class="dhikra-action dhikra-action--icon" data-dhikra-favorite aria-pressed="<?php echo $card_post_id > 0 && is_user_logged_in() && Dhikra_Data::is_favorite( get_current_user_id(), $card_post_id ) ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'ذخیره', 'dhikra' ); ?>"><span aria-hidden="true">♡</span><span><?php esc_html_e( 'ذخیره', 'dhikra' ); ?></span></button>
					<button type="button" class="dhikra-action dhikra-action--icon" data-dhikra-share><span aria-hidden="true">↗</span><span><?php esc_html_e( 'اشتراک', 'dhikra' ); ?></span></button>
					<button type="button" class="dhikra-action dhikra-action--icon" data-dhikra-tools-open aria-haspopup="dialog"><span aria-hidden="true">•••</span><span><?php esc_html_e( 'بیشتر', 'dhikra' ); ?></span></button>
				</div>
				<div class="dhikra-live" data-dhikra-live aria-live="polite"></div>
				<aside class="dhikra-daily-prompt dhikra-daily-settings" data-dhikra-daily-settings hidden role="dialog" aria-modal="true" aria-labelledby="dhikra-daily-settings-title"><div class="dhikra-daily-prompt__sheet"><div class="dhikra-daily-prompt__handle" aria-hidden="true"></div><button type="button" class="dhikra-daily-prompt__close" data-dhikra-daily-settings-close aria-label="<?php esc_attr_e( 'بستن تنظیمات', 'dhikra' ); ?>">×</button><div class="dhikra-daily-prompt__heading"><span aria-hidden="true">◌</span><h2 id="dhikra-daily-settings-title"><?php esc_html_e( 'تلنگر روزانه', 'dhikra' ); ?></h2></div><label class="dhikra-daily-toggle"><input type="checkbox" data-dhikra-daily-enabled><span><?php esc_html_e( 'دریافت خودکار تلنگر روزانه', 'dhikra' ); ?></span></label><p><?php printf( esc_html__( 'دریافت تلنگر روزانه به‌صورت پیش‌فرض فعال است. تلنگرها از همه موضوعات فعال انتخاب می‌شوند و حدود ساعت %s به وقت ایران ارسال می‌شوند. فقط اگر خودتان این گزینه را خاموش کنید، دریافت متوقف می‌شود.', 'dhikra' ), esc_html( (string) Dhikra_Helpers::setting( 'daily_send_time', '08:00' ) ) ); ?></p><div class="dhikra-daily-prompt__actions"><button type="button" data-dhikra-daily-save><?php esc_html_e( 'ذخیره', 'dhikra' ); ?></button><button type="button" data-dhikra-daily-settings-close><?php esc_html_e( 'انصراف', 'dhikra' ); ?></button></div></div></aside>
				<aside class="dhikra-discovery-dialog" data-dhikra-search-panel hidden role="dialog" aria-modal="true" aria-labelledby="dhikra-search-title">
					<div class="dhikra-discovery-dialog__sheet"><button type="button" class="dhikra-discovery-dialog__close" data-dhikra-discovery-close aria-label="<?php esc_attr_e( 'بستن', 'dhikra' ); ?>">×</button><h2 id="dhikra-search-title"><?php esc_html_e( 'جستجو در تلنگرها', 'dhikra' ); ?></h2><p><?php esc_html_e( 'در عنوان، متن، ترجمه، تلنگر و منبع جستجو کنید.', 'dhikra' ); ?></p><form data-dhikra-search-form class="dhikra-search-form"><input type="search" data-dhikra-search-input minlength="2" placeholder="مثلاً امید، صبر، خانواده…" autocomplete="off"><button type="submit"><?php esc_html_e( 'جستجو', 'dhikra' ); ?></button></form><div class="dhikra-discovery-status" data-dhikra-search-status aria-live="polite"></div><div class="dhikra-discovery-results" data-dhikra-search-results></div></div>
				</aside>
				<aside class="dhikra-discovery-dialog" data-dhikra-topics-panel hidden role="dialog" aria-modal="true" aria-labelledby="dhikra-topics-title">
					<div class="dhikra-discovery-dialog__sheet"><button type="button" class="dhikra-discovery-dialog__close" data-dhikra-discovery-close aria-label="<?php esc_attr_e( 'بستن', 'dhikra' ); ?>">×</button><h2 id="dhikra-topics-title"><?php esc_html_e( 'مرور بر اساس موضوع', 'dhikra' ); ?></h2><p><?php esc_html_e( 'یک موضوع را انتخاب کنید و تلنگرهای مرتبط را ببینید.', 'dhikra' ); ?></p><div class="dhikra-discovery-topics" data-dhikra-topics-list></div><div class="dhikra-discovery-status" data-dhikra-topics-status aria-live="polite"></div><div class="dhikra-discovery-results" data-dhikra-topics-results></div></div>
				</aside>
				<aside class="dhikra-tools-dialog" data-dhikra-tools-dialog hidden role="dialog" aria-modal="true" aria-labelledby="dhikra-tools-title">
					<div class="dhikra-tools-dialog__sheet">
						<div class="dhikra-tools-dialog__handle" aria-hidden="true"></div>
						<button type="button" class="dhikra-tools-dialog__close" data-dhikra-tools-close aria-label="<?php esc_attr_e( 'بستن', 'dhikra' ); ?>">×</button>
						<div data-dhikra-tools-menu>
							<h2 id="dhikra-tools-title"><?php esc_html_e( 'بیشتر', 'dhikra' ); ?></h2>
							<p><?php esc_html_e( 'امکانات تکمیلی این تلنگر، بدون شلوغ شدن کارت.', 'dhikra' ); ?></p>
							<div class="dhikra-tools-grid">
								<button type="button" data-dhikra-note-open><span aria-hidden="true">✎</span><strong><?php esc_html_e( 'یادداشت شخصی', 'dhikra' ); ?></strong><small><?php esc_html_e( 'یک نکته فقط برای خودتان', 'dhikra' ); ?></small></button>
								<button type="button" data-dhikra-reminder-open><span aria-hidden="true">◷</span><strong><?php esc_html_e( 'یادآوری دوباره', 'dhikra' ); ?></strong><small><?php esc_html_e( 'امشب، فردا یا هفته بعد', 'dhikra' ); ?></small></button>
								<button type="button" data-dhikra-copy><span aria-hidden="true">⧉</span><strong><?php esc_html_e( 'کپی متن', 'dhikra' ); ?></strong><small><?php esc_html_e( 'متن کامل + لینک برنامک', 'dhikra' ); ?></small></button>
							</div>
						</div>
						<div data-dhikra-note-pane hidden>
							<button type="button" class="dhikra-tools-back" data-dhikra-tools-back>← <?php esc_html_e( 'بازگشت', 'dhikra' ); ?></button>
							<h2><?php esc_html_e( 'یادداشت شخصی', 'dhikra' ); ?></h2>
							<p><?php esc_html_e( 'این یادداشت خصوصی است و در متن اشتراک‌گذاری‌شده نمایش داده نمی‌شود.', 'dhikra' ); ?></p>
							<textarea data-dhikra-note-input maxlength="1000" rows="6" placeholder="نکته‌ای که می‌خواهم بعداً به یاد داشته باشم…"></textarea>
							<div class="dhikra-tools-pane-actions"><button type="button" data-dhikra-note-save><?php esc_html_e( 'ذخیره یادداشت', 'dhikra' ); ?></button><button type="button" data-dhikra-note-clear><?php esc_html_e( 'پاک کردن', 'dhikra' ); ?></button></div>
						</div>
						<div data-dhikra-reminder-pane hidden>
							<button type="button" class="dhikra-tools-back" data-dhikra-tools-back>← <?php esc_html_e( 'بازگشت', 'dhikra' ); ?></button>
							<h2><?php esc_html_e( 'یادآوری دوباره', 'dhikra' ); ?></h2>
							<p><?php esc_html_e( 'این تلنگر در پیام خصوصی ایتا دوباره برای شما ارسال می‌شود.', 'dhikra' ); ?></p>
							<div class="dhikra-reminder-options">
								<button type="button" data-dhikra-reminder-preset="tonight"><?php esc_html_e( 'امشب', 'dhikra' ); ?><small><?php esc_html_e( 'ساعت ۲۱', 'dhikra' ); ?></small></button>
								<button type="button" data-dhikra-reminder-preset="tomorrow"><?php esc_html_e( 'فردا', 'dhikra' ); ?><small><?php esc_html_e( 'در ساعت روزانه ذِكْرَىٰ', 'dhikra' ); ?></small></button>
								<button type="button" data-dhikra-reminder-preset="week"><?php esc_html_e( 'هفته بعد', 'dhikra' ); ?><small><?php esc_html_e( 'در ساعت روزانه ذِكْرَىٰ', 'dhikra' ); ?></small></button>
							</div>
						</div>
					</div>
				</aside>
				<aside class="dhikra-reward-result" data-dhikra-reward-result hidden role="dialog" aria-modal="true" aria-labelledby="dhikra-reward-title"><div class="dhikra-reward-result__card"><div class="dhikra-reward-result__glow" aria-hidden="true"></div><span class="dhikra-reward-result__spark" aria-hidden="true">✦</span><p class="dhikra-reward-result__eyebrow" data-dhikra-reward-campaign></p><h2 id="dhikra-reward-title"><?php esc_html_e( 'این تلنگر برای شما خوش‌یمن بود', 'dhikra' ); ?></h2><p data-dhikra-reward-text></p><div class="dhikra-reward-result__actions"><a data-dhikra-reward-cta hidden target="_blank" rel="noopener noreferrer"></a><button type="button" data-dhikra-reward-share><?php esc_html_e( 'اشتراک خبر برنده‌شدن', 'dhikra' ); ?></button><button type="button" data-dhikra-reward-close><?php esc_html_e( 'بستن', 'dhikra' ); ?></button></div></div></aside>
			</section>
		<?php elseif ( in_array( $route, array( 'login', 'register' ), true ) ) : ?>
			<section class="dhikra-auth-panel dhikra-mobile-auth" aria-label="<?php esc_attr_e( 'ورود و ثبت‌نام با موبایل', 'dhikra' ); ?>">
				<?php do_action( 'dhikra_render_mobile_auth', $return_to ); ?>
			</section>
		<?php elseif ( 'account' === $route ) : ?>
			<?php if ( is_user_logged_in() ) : ?>
			<?php $user_id = get_current_user_id(); $active_ids = Dhikra_Data::active_topic_ids(); $excluded_ids = Dhikra_Data::excluded_topic_ids( $user_id ); $selected_ids = array_diff( $active_ids, $excluded_ids ); $terms = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false, 'include' => $active_ids ) ); ?>
			<section class="dhikra-panel dhikra-account-panel"><div class="dhikra-panel-heading"><div><p class="dhikra-kicker"><?php esc_html_e( 'حساب کاربری', 'dhikra' ); ?></p><h1><?php echo esc_html( wp_get_current_user()->display_name ); ?></h1></div><a class="dhikra-text-link" href="<?php echo esc_url( wp_nonce_url( Dhikra_Helpers::app_url( 'logout' ), 'dhikra_logout' ) ); ?>"><?php esc_html_e( 'خروج', 'dhikra' ); ?></a></div><div class="dhikra-dashboard-links dhikra-dashboard-links--three"><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'favorites' ) ); ?>"><strong><?php echo esc_html( (string) Dhikra_Data::favorite_count( $user_id ) ); ?></strong><span><?php esc_html_e( 'ذخیره‌شده', 'dhikra' ); ?></span></a><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'history' ) ); ?>"><strong><?php echo esc_html( (string) Dhikra_Data::history_count( $user_id ) ); ?></strong><span><?php esc_html_e( 'تلنگر اخیر', 'dhikra' ); ?></span></a><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'print' ) ); ?>"><strong aria-hidden="true">A4</strong><span><?php esc_html_e( 'چاپ کارت‌ها', 'dhikra' ); ?></span></a></div><h2><?php esc_html_e( 'موضوعات دلخواه', 'dhikra' ); ?></h2><p><?php esc_html_e( 'موضوعات تازه به‌صورت پیش‌فرض فعال‌اند. دست‌کم یک موضوع باید انتخاب بماند.', 'dhikra' ); ?></p><form method="post" class="dhikra-topic-form"><input type="hidden" name="dhikra_account_action" value="save_topics"><?php wp_nonce_field( 'dhikra_save_topics', 'dhikra_nonce' ); ?><div class="dhikra-topic-grid"><?php if ( ! is_wp_error( $terms ) ) : foreach ( $terms as $term ) : ?><label><input type="checkbox" name="dhikra_topics[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $selected_ids, true ) ); ?>><span><?php echo esc_html( $term->name ); ?></span></label><?php endforeach; endif; ?></div><button class="dhikra-submit" type="submit"><?php esc_html_e( 'ذخیرهٔ موضوعات', 'dhikra' ); ?></button></form></section>

			<?php else : ?>
				<section class="dhikra-panel dhikra-personal-shell" data-dhikra-personal-page="account" >
					<p data-dhikra-personal-status>در حال بررسی هویت ایتا…</p>
					<div data-dhikra-personal-content></div>
					<a class="dhikra-submit" data-dhikra-web-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'account' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>">ورود با شماره موبایل</a>
				</section>
			<?php endif; ?>
		<?php elseif ( 'print' === $route ) : ?>
			<?php if ( is_user_logged_in() ) : ?>
			<?php $print_terms = Dhikra_Print::topic_options(); $print_max = max( 1, absint( Dhikra_Helpers::setting( 'print_max_cards', 500 ) ) ); ?>
			<section class="dhikra-panel dhikra-print-builder">
				<div class="dhikra-panel-heading"><div><p class="dhikra-kicker"><?php esc_html_e( 'ذکری فیزیکی', 'dhikra' ); ?></p><h1><?php esc_html_e( 'ساخت کارت‌های چاپی', 'dhikra' ); ?></h1></div><a class="dhikra-text-link" href="<?php echo esc_url( Dhikra_Helpers::app_url( 'account' ) ); ?>"><?php esc_html_e( 'بازگشت به حساب', 'dhikra' ); ?></a></div>
				<p><?php esc_html_e( 'یک یا چند دسته را انتخاب کنید. کارت‌ها برای برگه A4، چهار کارت در هر صفحه، با QR ثابت ورود به برنامک ایتا آماده می‌شوند. چاپ هیچ ارتباطی با سیستم جایزه ندارد.', 'dhikra' ); ?></p>
				<form method="post" class="dhikra-print-form">
					<input type="hidden" name="dhikra_print_action" value="generate">
					<?php wp_nonce_field( 'dhikra_generate_print', 'dhikra_print_nonce' ); ?>
					<div class="dhikra-print-form__head"><h2><?php esc_html_e( '۱. انتخاب دسته‌ها', 'dhikra' ); ?></h2><label class="dhikra-select-all"><input type="checkbox" data-dhikra-select-all> <span><?php esc_html_e( 'انتخاب همه', 'dhikra' ); ?></span></label></div>
					<div class="dhikra-topic-grid dhikra-print-topic-grid" data-dhikra-print-topics>
						<?php foreach ( $print_terms as $term ) : ?><label><input type="checkbox" name="dhikra_print_topics[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>"><span><strong><?php echo esc_html( $term->name ); ?></strong><small><?php printf( esc_html__( '%d محتوا', 'dhikra' ), absint( $term->count ) ); ?></small></span></label><?php endforeach; ?>
					</div>
					<h2><?php esc_html_e( '۲. تعداد کارت‌ها', 'dhikra' ); ?></h2>
					<div class="dhikra-print-count"><input type="number" name="dhikra_print_count" min="1" max="<?php echo esc_attr( (string) $print_max ); ?>" value="100" required><span><?php printf( esc_html__( 'حداکثر %d کارت در هر خروجی', 'dhikra' ), $print_max ); ?></span></div>
					<div class="dhikra-print-summary"><strong><?php esc_html_e( 'قالب خروجی', 'dhikra' ); ?></strong><span><?php esc_html_e( 'A4 عمودی — ۴ کارت (۲×۲) — دارای خط برش — QR برنامک ایتا', 'dhikra' ); ?></span></div>
					<button class="dhikra-submit" type="submit"><?php esc_html_e( 'ساخت پیش‌نمایش چاپ', 'dhikra' ); ?></button>
				</form>
			</section>
			<?php else : ?>
				<section class="dhikra-panel dhikra-personal-shell" data-dhikra-personal-page="print" >
					<p data-dhikra-personal-status>در حال بررسی هویت ایتا…</p>
					<div data-dhikra-personal-content></div>
					<a class="dhikra-submit" data-dhikra-web-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'print' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>">ورود با شماره موبایل</a>
				</section>
			<?php endif; ?>
		<?php elseif ( 'print-preview' === $route ) : ?>
			<?php if ( is_user_logged_in() ) : ?>
			<?php $print_payload = Dhikra_Print::preview_payload(); ?>
			<section class="dhikra-panel dhikra-print-preview-shell">
				<div class="dhikra-print-toolbar">
					<div><p class="dhikra-kicker"><?php esc_html_e( 'پیش‌نمایش نهایی', 'dhikra' ); ?></p><h1><?php esc_html_e( 'کارت‌های آماده چاپ', 'dhikra' ); ?></h1></div>
					<div class="dhikra-print-toolbar__actions"><a class="dhikra-text-link" href="<?php echo esc_url( Dhikra_Helpers::app_url( 'print' ) ); ?>"><?php esc_html_e( 'ساخت خروجی تازه', 'dhikra' ); ?></a><button type="button" class="dhikra-submit" data-dhikra-print-now><?php esc_html_e( 'چاپ / ذخیره PDF', 'dhikra' ); ?></button></div>
				</div>
				<?php if ( is_wp_error( $print_payload ) ) : ?>
					<?php echo wp_kses_post( Dhikra_Helpers::notice( $print_payload->get_error_message(), 'error' ) ); ?>
				<?php else : ?>
					<div class="dhikra-print-report"><span><?php printf( esc_html__( '%d کارت', 'dhikra' ), count( $print_payload['post_ids'] ) ); ?></span><span><?php printf( esc_html__( '%d محتوای یکتا', 'dhikra' ), absint( $print_payload['unique'] ) ); ?></span><?php if ( absint( $print_payload['repeats'] ) > 0 ) : ?><span class="is-warning"><?php printf( esc_html__( '%d تکرار پس از مصرف محتوای یکتا', 'dhikra' ), absint( $print_payload['repeats'] ) ); ?></span><?php endif; ?></div>
					<div class="dhikra-print-pages" data-dhikra-print-area>
						<?php foreach ( array_chunk( $print_payload['post_ids'], 4 ) as $page_index => $page_ids ) : ?><section class="dhikra-print-page" aria-label="<?php echo esc_attr( sprintf( __( 'صفحه چاپ %d', 'dhikra' ), $page_index + 1 ) ); ?>"><?php foreach ( $page_ids as $post_id ) : ?><div class="dhikra-print-cell"><?php echo Dhikra_Print::render_print_card( absint( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endforeach; ?></section><?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
			<?php else : ?>
				<section class="dhikra-panel dhikra-personal-shell" data-dhikra-personal-page="print-preview" data-dhikra-print-token="<?php echo esc_attr( isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '' ); ?>">
					<p data-dhikra-personal-status>در حال بررسی هویت ایتا…</p>
					<div data-dhikra-personal-content></div>
					<a class="dhikra-submit" data-dhikra-web-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'print-preview' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>">ورود با شماره موبایل</a>
				</section>
			<?php endif; ?>
		<?php elseif ( 'favorites' === $route ) : ?>
			<?php
			$saved_is_web = is_user_logged_in();
			$saved_page   = $saved_is_web ? Dhikra_Helpers::page_number() : 1;
			$saved_rows   = $saved_is_web ? Dhikra_Data::favorite_rows( get_current_user_id(), $saved_page, 10 ) : array();
			$saved_total  = $saved_is_web ? Dhikra_Data::favorite_count( get_current_user_id() ) : 0;
			?>
			<section class="dhikra-panel dhikra-saved-page" data-dhikra-saved-page data-dhikra-saved-mode="<?php echo $saved_is_web ? 'web' : 'eitaa'; ?>">
				<header class="dhikra-saved-hero">
					<div class="dhikra-saved-hero__mark" aria-hidden="true">♥</div>
					<div class="dhikra-saved-hero__copy"><p class="dhikra-kicker"><?php esc_html_e( 'مجموعه شخصی من', 'dhikra' ); ?></p><h1><?php esc_html_e( 'ذخیره‌شده‌ها', 'dhikra' ); ?></h1><p><?php esc_html_e( 'تلنگرهایی که می‌خواهید دوباره به آن‌ها برگردید، یک‌جا و مرتب.', 'dhikra' ); ?></p></div>
					<a class="dhikra-saved-back" href="<?php echo esc_url( Dhikra_Helpers::app_url( 'home' ) ); ?>"><span aria-hidden="true">←</span><span><?php esc_html_e( 'تلنگرها', 'dhikra' ); ?></span></a>
				</header>
				<div class="dhikra-saved-toolbar">
					<label class="dhikra-saved-search"><span aria-hidden="true">⌕</span><input type="search" data-dhikra-saved-search placeholder="<?php esc_attr_e( 'جستجو در عنوان، متن یا منبع…', 'dhikra' ); ?>" autocomplete="off"></label>
					<label class="dhikra-saved-sort"><span><?php esc_html_e( 'ترتیب', 'dhikra' ); ?></span><select data-dhikra-saved-sort><option value="newest"><?php esc_html_e( 'جدیدترین', 'dhikra' ); ?></option><option value="oldest"><?php esc_html_e( 'قدیمی‌ترین', 'dhikra' ); ?></option></select></label>
				</div>
				<div class="dhikra-saved-filters" data-dhikra-saved-filters aria-label="<?php esc_attr_e( 'فیلتر موضوع', 'dhikra' ); ?>"><button type="button" class="is-active" data-topic=""><?php esc_html_e( 'همه', 'dhikra' ); ?></button></div>
				<div class="dhikra-saved-summary"><strong data-dhikra-saved-count><?php echo esc_html( (string) $saved_total ); ?></strong><span data-dhikra-saved-status><?php echo $saved_is_web ? esc_html__( 'تلنگر ذخیره‌شده', 'dhikra' ) : esc_html__( 'در حال دریافت ذخیره‌شده‌ها…', 'dhikra' ); ?></span></div>
				<div class="dhikra-saved-grid" data-dhikra-saved-list>
					<?php if ( $saved_is_web ) : foreach ( $saved_rows as $saved_row ) : echo dhikra_render_saved_item( $saved_row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; endif; ?>
				</div>
				<div class="dhikra-saved-empty" data-dhikra-saved-empty <?php echo ( $saved_is_web && 0 === $saved_total ) ? '' : 'hidden'; ?>><span aria-hidden="true">♡</span><h2><?php esc_html_e( 'هنوز چیزی اینجا نیست', 'dhikra' ); ?></h2><p><?php esc_html_e( 'روی «ذخیره» هر تلنگر بزنید تا برای مراجعه بعدی اینجا بماند.', 'dhikra' ); ?></p><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'home' ) ); ?>"><?php esc_html_e( 'دیدن تلنگرها', 'dhikra' ); ?></a></div>
				<div class="dhikra-saved-no-results" data-dhikra-saved-no-results hidden><span aria-hidden="true">⌕</span><p><?php esc_html_e( 'چیزی با این جستجو یا فیلتر پیدا نشد.', 'dhikra' ); ?></p></div>
				<?php if ( $saved_is_web ) : ?><div class="dhikra-saved-pagination" data-dhikra-saved-pagination><?php echo wp_kses_post( dhikra_render_pagination( $saved_page, (int) ceil( $saved_total / 10 ), Dhikra_Helpers::app_url( 'favorites' ) ) ); ?></div><?php endif; ?>
				<a class="dhikra-submit" data-dhikra-web-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'favorites' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>"><?php esc_html_e( 'ورود به نسخه وب', 'dhikra' ); ?></a>
				<div class="dhikra-saved-undo" data-dhikra-saved-undo hidden role="status" aria-live="polite"><span><?php esc_html_e( 'از ذخیره‌شده‌ها حذف شد.', 'dhikra' ); ?></span><button type="button" data-dhikra-saved-undo-button><?php esc_html_e( 'برگردان', 'dhikra' ); ?></button></div>
			</section>
		<?php elseif ( 'history' === $route ) : ?>
			<?php if ( is_user_logged_in() ) : ?>
			<?php $page = Dhikra_Helpers::page_number(); $user_id = get_current_user_id(); $ids = Dhikra_Data::history_ids( $user_id, $page, 10 ); $total = Dhikra_Data::history_count( $user_id ); ?>
			<section class="dhikra-panel"><div class="dhikra-panel-heading"><div><p class="dhikra-kicker"><?php esc_html_e( 'حساب من', 'dhikra' ); ?></p><h1><?php esc_html_e( 'تلنگرهای اخیر', 'dhikra' ); ?></h1></div><a class="dhikra-text-link" href="<?php echo esc_url( Dhikra_Helpers::app_url( 'account' ) ); ?>"><?php esc_html_e( 'بازگشت به حساب', 'dhikra' ); ?></a></div><ol class="dhikra-history-list"><?php foreach ( $ids as $post_id ) : ?><li><a href="<?php echo esc_url( Dhikra_Helpers::telangor_url( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></li><?php endforeach; ?></ol><?php if ( empty( $ids ) ) : ?><p class="dhikra-empty-list"><?php esc_html_e( 'تاریخچه‌ای وجود ندارد.', 'dhikra' ); ?></p><?php endif; ?><?php echo wp_kses_post( dhikra_render_pagination( $page, (int) ceil( $total / 10 ), Dhikra_Helpers::app_url( 'history' ) ) ); ?></section>
			<?php else : ?>
				<section class="dhikra-panel dhikra-personal-shell" data-dhikra-personal-page="history" >
					<p data-dhikra-personal-status>در حال بررسی هویت ایتا…</p>
					<div data-dhikra-personal-content></div>
					<a class="dhikra-submit" data-dhikra-web-login hidden href="<?php echo esc_url( add_query_arg( 'return_to', Dhikra_Helpers::app_url( 'history' ), Dhikra_Helpers::app_url( 'login' ) ) ); ?>">ورود با شماره موبایل</a>
				</section>
			<?php endif; ?>
		<?php else : ?>
			<section class="dhikra-panel"><h1><?php esc_html_e( 'صفحه پیدا نشد', 'dhikra' ); ?></h1><p><a href="<?php echo esc_url( Dhikra_Helpers::app_url( 'home' ) ); ?>"><?php esc_html_e( 'بازگشت به تلنگرها', 'dhikra' ); ?></a></p></section>
		<?php endif; ?>
	</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
