<?php
/**
 * Gutenberg block and shortcode.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Block {
	private static bool $localized = false;
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'dhikra', array( $this, 'shortcode' ) );
	}

	public function register_block(): void {
		register_block_type(
			DHIKRA_DIR,
			array( 'render_callback' => array( $this, 'render' ) )
		);
		wp_set_script_translations( 'dhikra-telangor-editor-script', 'dhikra', DHIKRA_DIR . 'languages' );
	}

	public function shortcode( array $atts = array() ): string {
		unset( $atts );
		return $this->render();
	}

	public function render(): string {
		wp_enqueue_style( 'dhikra-frontend', DHIKRA_URL . 'assets/css/frontend.css', array(), DHIKRA_VERSION );
		Dhikra_Helpers::add_font_faces( 'dhikra-frontend' );
		wp_enqueue_script( 'dhikra-frontend', DHIKRA_URL . 'assets/js/frontend.js', array(), DHIKRA_VERSION, true );
		if ( ! self::$localized ) {
			self::$localized = true;
			wp_localize_script(
				'dhikra-frontend',
				'DhikraApp',
				array(
					'restUrl'      => esc_url_raw( rest_url( 'dhikra/v1/' ) ),
					'nonce'        => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
					'loginUrl'     => Dhikra_Helpers::app_url( 'login' ),
					'homeUrl'      => Dhikra_Helpers::app_url( 'home' ),
					'buttonLabel'  => (string) Dhikra_Helpers::setting( 'button_label', __( 'تلنگر جدید', 'dhikra' ) ),
					'isLoggedIn'   => is_user_logged_in(),
					'repeatWindow' => absint( Dhikra_Helpers::setting( 'near_repeat_window', 12 ) ),
					'messages'     => array( 'loading' => __( 'در حال آوردن تلنگر تازه…', 'dhikra' ), 'error' => __( 'دریافت تلنگر تازه انجام نشد.', 'dhikra' ), 'shared' => __( 'کارت برای اشتراک‌گذاری آماده شد.', 'dhikra' ), 'copied' => __( 'متن و پیوند کپی شد.', 'dhikra' ), 'favorite' => __( 'به علاقه‌مندی‌ها افزوده شد.', 'dhikra' ), 'unfavorite' => __( 'از علاقه‌مندی‌ها حذف شد.', 'dhikra' ), 'fitError' => __( 'این محتوا برای نمایش بدون اسکرول بیش‌ازحد بلند است.', 'dhikra' ) ),
				)
			);
		}
		$post_id  = Dhikra_Random::next_for_request();
		$favorite = $post_id > 0 && is_user_logged_in() && Dhikra_Data::is_favorite( get_current_user_id(), $post_id );
		ob_start();
		?>
		<div class="dhikra-embed" data-dhikra-widget>
			<div class="dhikra-card-slot" data-dhikra-card-slot><?php echo Dhikra_Random::render_card( $post_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="dhikra-actions">
				<button type="button" class="dhikra-action dhikra-action--primary" data-dhikra-next><?php echo esc_html( (string) Dhikra_Helpers::setting( 'button_label', __( 'تلنگر جدید', 'dhikra' ) ) ); ?></button>
				<button type="button" class="dhikra-action" data-dhikra-favorite aria-pressed="<?php echo $favorite ? 'true' : 'false'; ?>"><?php esc_html_e( 'علاقه‌مندی', 'dhikra' ); ?></button>
				<button type="button" class="dhikra-action" data-dhikra-share><?php esc_html_e( 'اشتراک‌گذاری', 'dhikra' ); ?></button>
			</div>
			<div class="dhikra-live" data-dhikra-live aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
