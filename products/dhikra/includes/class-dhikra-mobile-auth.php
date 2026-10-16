<?php
/**
 * Manahel IPPanel OTP integration for the standalone application.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Mobile_Auth {
	private string $form_html = '';
	private string $prepared_return_to = '';

	public function register(): void {
		add_filter( 'dhikra_sms_provider_available', array( $this, 'declare_provider' ) );
		add_action( 'dhikra_render_mobile_auth', array( $this, 'render' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'prepare_assets' ), 5 );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	public static function provider_available(): bool {
		return defined( 'MIO_VERSION' )
			&& version_compare( (string) MIO_VERSION, '1.1.3', '>=' )
			&& class_exists( 'MIO_Settings' )
			&& shortcode_exists( 'manahel_otp_login' );
	}

	public function declare_provider( bool $available ): bool {
		return $available || self::provider_available();
	}

	public static function provider_enabled(): bool {
		if ( ! self::provider_available() ) {
			return false;
		}

		$settings = MIO_Settings::get();
		return ! empty( $settings['enabled'] );
	}

	public static function provider_ready(): bool {
		if ( ! self::provider_enabled() ) {
			return false;
		}

		$settings = MIO_Settings::get();
		return '' !== MIO_Settings::api_key()
			&& preg_match( '/^\+[1-9]\d{4,15}$/', trim( (string) $settings['sender'] ) )
			&& '' !== trim( (string) $settings['pattern_code'] );
	}

	/**
	 * Prepare the shortcode during the enqueue phase before styles and scripts print.
	 */
	public function prepare_assets(): void {
		if ( ! Dhikra_Helpers::is_app_request() || is_user_logged_in() ) {
			return;
		}

		$route = Dhikra_Helpers::request_route();
		if ( ! in_array( $route, array( 'login', 'register' ), true ) ) {
			return;
		}

		$this->prepare_form( $this->request_return_to() );
	}

	public function render( string $return_to = '' ): void {
		$return_to = Dhikra_Helpers::safe_return_url( $return_to, Dhikra_Helpers::app_url( 'account' ) );

		if ( ! self::provider_available() ) {
			echo wp_kses_post( Dhikra_Helpers::notice( __( 'برای ورود و ثبت‌نام، نسخه ۱.۱.۳ یا جدیدتر افزونه «ورود پیامکی مناهل با IPPanel» باید نصب و فعال باشد.', 'dhikra' ), 'error' ) );
			return;
		}

		$this->prepare_form( $return_to );
		if ( '' === $this->form_html ) {
			echo wp_kses_post( Dhikra_Helpers::notice( __( 'فرم ورود پیامکی در دسترس نیست. تنظیمات افزونه پیامکی را بررسی کنید.', 'dhikra' ), 'error' ) );
			return;
		}

		echo $this->form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted output from the required Manahel OTP shortcode.
	}

	public function dependency_notice(): void {
		if ( self::provider_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'ذِكْرَىٰ:', 'dhikra' ); ?></strong> <?php esc_html_e( 'ورود و ثبت‌نام این نسخه فقط با OTP موبایل انجام می‌شود. نسخه ۱.۱.۳ یا جدیدتر افزونه «ورود پیامکی مناهل با IPPanel» را نصب و فعال کنید.', 'dhikra' ); ?></p>
		</div>
		<?php
	}

	private function request_return_to(): string {
		$raw = isset( $_GET['return_to'] ) ? esc_url_raw( wp_unslash( $_GET['return_to'] ) ) : '';
		return Dhikra_Helpers::safe_return_url( $raw, Dhikra_Helpers::app_url( 'account' ) );
	}

	private function prepare_form( string $return_to ): void {
		if ( ! self::provider_ready() || ( '' !== $this->form_html && $return_to === $this->prepared_return_to ) ) {
			return;
		}

		$had_redirect = array_key_exists( 'redirect_to', $_GET );
		$old_redirect = $had_redirect ? $_GET['redirect_to'] : null;
		$_GET['redirect_to'] = $return_to;

		$this->form_html          = (string) do_shortcode( '[manahel_otp_login]' );
		$this->prepared_return_to = $return_to;

		if ( $had_redirect ) {
			$_GET['redirect_to'] = $old_redirect;
		} else {
			unset( $_GET['redirect_to'] );
		}
	}
}
