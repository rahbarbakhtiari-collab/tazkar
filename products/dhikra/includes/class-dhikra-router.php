<?php
/**
 * Standalone public routing.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Router {
	public function register(): void {
		add_action( 'init', array( $this, 'rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_app_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'isolate_app_assets' ), 9999 );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical' ), 10, 2 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
	}

	public function rewrite_rules(): void {
		$routes = implode( '|', array_map( 'preg_quote', Dhikra_Helpers::RESERVED_ROUTES ) );
		if ( Dhikra_Helpers::root_mode() ) {
			add_rewrite_rule( '^(' . $routes . ')/?$', 'index.php?dhikra_route=$matches[1]', 'top' );
			add_rewrite_rule( '^([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})/?$', 'index.php?dhikra_route=telangor&dhikra_code=$matches[1]', 'top' );
		} else {
			$base = preg_quote( Dhikra_Helpers::base_slug(), '#' );
			add_rewrite_rule( '^' . $base . '/?$', 'index.php?dhikra_route=home', 'top' );
			add_rewrite_rule( '^' . $base . '/(' . $routes . ')/?$', 'index.php?dhikra_route=$matches[1]', 'top' );
			add_rewrite_rule( '^z/([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})/?$', 'index.php?dhikra_route=telangor&dhikra_code=$matches[1]', 'top' );
			add_rewrite_rule( '^dhikra/?$', 'index.php?dhikra_route=legacy', 'top' );
		}
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'dhikra_route';
		$vars[] = 'dhikra_code';
		return $vars;
	}

	public function template_include( string $template ): string {
		return Dhikra_Helpers::is_app_request() ? DHIKRA_DIR . 'templates/app.php' : $template;
	}

	public function template_redirect(): void {
		if ( ! Dhikra_Helpers::is_app_request() ) {
			return;
		}
		$route = Dhikra_Helpers::request_route();
		// Card routes are safe to keep briefly in the user's WebView cache. This
		// avoids a white/blank reload when Eitaa recreates or resumes the view.
		if ( in_array( $route, array( 'home', 'telangor' ), true ) ) {
			header( 'Cache-Control: private, max-age=60, stale-while-revalidate=300' );
		} else {
			nocache_headers();
		}
		if ( in_array( $route, array( 'login', 'register' ), true ) && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( 'legacy' === $route ) {
			wp_safe_redirect( Dhikra_Helpers::app_url( 'home' ), 301 );
			exit;
		}
		if ( in_array( $route, array( 'lost-password', 'reset-password' ), true ) ) {
			$return_to = isset( $_GET['return_to'] ) ? Dhikra_Helpers::safe_return_url( esc_url_raw( wp_unslash( $_GET['return_to'] ) ), Dhikra_Helpers::app_url( 'account' ) ) : Dhikra_Helpers::app_url( 'account' );
			wp_safe_redirect( add_query_arg( 'return_to', $return_to, Dhikra_Helpers::app_url( 'login' ) ) );
			exit;
		}
		if ( in_array( $route, array( 'login', 'register' ), true ) && is_user_logged_in() ) {
			$return_to = isset( $_GET['return_to'] ) ? Dhikra_Helpers::safe_return_url( esc_url_raw( wp_unslash( $_GET['return_to'] ) ), Dhikra_Helpers::app_url( 'account' ) ) : Dhikra_Helpers::app_url( 'account' );
			wp_safe_redirect( $return_to );
			exit;
		}
		if ( 'telangor' === $route ) {
			$code = Dhikra_Helpers::sanitize_short_code( (string) get_query_var( 'dhikra_code' ) );
			if ( '' === $code || Dhikra_Data::find_post_by_code( $code, true ) <= 0 ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	}

	public function enqueue_app_assets(): void {
		if ( ! Dhikra_Helpers::is_app_request() ) {
			return;
		}
		wp_enqueue_style( 'dhikra-frontend', DHIKRA_URL . 'assets/css/frontend.css', array(), DHIKRA_VERSION );
		Dhikra_Helpers::add_font_faces( 'dhikra-frontend' );
		if ( in_array( Dhikra_Helpers::request_route(), array( 'print', 'print-preview' ), true ) ) {
			wp_enqueue_style( 'dhikra-print', DHIKRA_URL . 'assets/css/print.css', array( 'dhikra-frontend' ), DHIKRA_VERSION );
		}

		// Eitaa requires its current SDK to be loaded directly from the official
		// endpoint and before the mini-app's own scripts.
		// Both scripts are deferred in the document head. The SDK still precedes
		// Dhikra, as required by Eitaa, while HTML/CSS can paint immediately
		// instead of waiting on a blocking remote SDK request.
		wp_enqueue_script( 'dhikra-eitaa-web-app', 'https://developer.eitaa.com/eitaa-web-app.js', array(), null, array( 'strategy' => 'defer', 'in_footer' => false ) );
		wp_enqueue_script( 'dhikra-frontend', DHIKRA_URL . 'assets/js/frontend.js', array( 'dhikra-eitaa-web-app' ), DHIKRA_VERSION, array( 'strategy' => 'defer', 'in_footer' => false ) );
		wp_localize_script(
			'dhikra-frontend',
			'DhikraApp',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'dhikra/v1/' ) ),
				'nonce'        => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'loginUrl'     => Dhikra_Helpers::app_url( 'login' ),
				'homeUrl'      => Dhikra_Helpers::app_url( 'home' ),
				'route'        => Dhikra_Helpers::request_route(),
				'eitaaAppUrl'  => Dhikra_Helpers::EITAA_APP_URL,
				'buttonLabel'  => (string) Dhikra_Helpers::setting( 'button_label', __( 'تلنگر جدید', 'dhikra' ) ),
				'isLoggedIn'   => is_user_logged_in(),
				'repeatWindow' => absint( Dhikra_Helpers::setting( 'near_repeat_window', 12 ) ),
				'messages'     => array(
					'loading'      => __( 'در حال آوردن تلنگر تازه…', 'dhikra' ),
					'error'        => __( 'دریافت تلنگر تازه انجام نشد.', 'dhikra' ),
					'shared'       => __( 'اشتراک‌گذاری باز شد.', 'dhikra' ),
					'copied'       => __( 'متن کارت کپی شد.', 'dhikra' ),
					'favorite'     => __( 'به ذخیره‌شده‌ها افزوده شد.', 'dhikra' ),
					'unfavorite'   => __( 'از ذخیره‌شده‌ها حذف شد.', 'dhikra' ),
					'fitError'     => __( 'این محتوا برای نمایش بدون اسکرول بیش‌ازحد بلند است.', 'dhikra' ),
				),
			)
		);
	}

	public function isolate_app_assets(): void {
		if ( ! Dhikra_Helpers::is_app_request() ) {
			return;
		}
		global $wp_styles, $wp_scripts;
		$allowed_styles  = array( 'dhikra-frontend' );
		if ( in_array( Dhikra_Helpers::request_route(), array( 'print', 'print-preview' ), true ) ) {
			$allowed_styles[] = 'dhikra-print';
		}
		$allowed_scripts = array( 'dhikra-eitaa-web-app', 'dhikra-frontend' );
		if ( in_array( Dhikra_Helpers::request_route(), array( 'login', 'register' ), true ) && Dhikra_Mobile_Auth::provider_available() ) {
			$allowed_styles[]  = 'mio-frontend';
			$allowed_scripts[] = 'mio-frontend';
		}
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				if ( ! in_array( $handle, $allowed_styles, true ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( ! in_array( $handle, $allowed_scripts, true ) ) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	public function disable_canonical( mixed $redirect_url, mixed $requested_url ): mixed {
		unset( $requested_url );
		return Dhikra_Helpers::is_app_request() ? false : $redirect_url;
	}

	public function document_title( string $title ): string {
		if ( ! Dhikra_Helpers::is_app_request() ) {
			return $title;
		}
		$route = Dhikra_Helpers::request_route();
		if ( 'telangor' === $route ) {
			$post_id = Dhikra_Data::find_post_by_code( (string) get_query_var( 'dhikra_code' ), true );
			return $post_id > 0 ? get_the_title( $post_id ) . ' — ' . Dhikra_Helpers::PRODUCT_NAME : Dhikra_Helpers::PRODUCT_NAME;
		}
		$labels = array(
			'login'         => __( 'ورود', 'dhikra' ),
			'register'      => __( 'ثبت‌نام', 'dhikra' ),
			'lost-password' => __( 'بازیابی رمز عبور', 'dhikra' ),
			'reset-password'=> __( 'تعیین رمز تازه', 'dhikra' ),
			'account'       => __( 'حساب من', 'dhikra' ),
			'favorites'     => __( 'علاقه‌مندی‌ها', 'dhikra' ),
			'history'       => __( 'تاریخچه', 'dhikra' ),
			'print'         => __( 'چاپ کارت‌ها', 'dhikra' ),
			'print-preview' => __( 'پیش‌نمایش چاپ', 'dhikra' ),
		);
		return isset( $labels[ $route ] ) ? $labels[ $route ] . ' — ' . Dhikra_Helpers::PRODUCT_NAME : Dhikra_Helpers::PRODUCT_NAME;
	}

	public function resource_hints( array $urls, string $relation_type ): array {
		if ( Dhikra_Helpers::is_app_request() && 'preconnect' === $relation_type ) {
			$urls[] = array( 'href' => 'https://developer.eitaa.com', 'crossorigin' => 'anonymous' );
		}
		return $urls;
	}

	public function hide_admin_bar( bool $show ): bool {
		return Dhikra_Helpers::is_app_request() ? false : $show;
	}
}
