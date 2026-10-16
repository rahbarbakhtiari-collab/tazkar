<?php
/**
 * Shared helper methods.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Helpers {
	public const PRODUCT_NAME = 'ذِكْرَىٰ';
	public const EITAA_APP_URL = 'https://eitaa.com/talanghor_app';
	public const SHORT_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
	public const RESERVED_ROUTES = array(
		'login',
		'register',
		'lost-password',
		'reset-password',
		'account',
		'favorites',
		'history',
		'print',
		'print-preview',
		'logout',
	);

	public static function defaults(): array {
		return array(
			'base_slug'                    => 'zekra',
			'root_url_mode'                => 0,
			'button_label'                 => __( 'تلنگر جدید', 'dhikra' ),
			'card_style'                   => 'royal',
			'delete_data_on_uninstall'     => 0,
			'display_title'                => 1,
			'display_original'             => 1,
			'display_translation'          => 1,
			'display_reflection'           => 1,
			'display_speaker'              => 1,
			'display_source'               => 1,
			'display_topic'                => 0,
			'display_brand'                => 0,
			'share_template_general'       => "[title]\n\n[translation]\n\n[source]\n[short_url]",
			'share_template_eitaa'         => "[title]\n\n[translation]\n\n[source]\n[app_url]",
			'share_template_bale'          => '',
			'share_template_telegram'      => '',
			'near_repeat_window'           => 12,
			'api_public_enabled'           => 1,
			'print_max_cards'              => 500,
			'eitaa_bot_token'               => '',
			'eitaa_init_max_age'            => DAY_IN_SECONDS,
			'daily_send_time'              => '08:00',
		);
	}

	public static function settings(): array {
		static $settings = null;
		if ( is_array( $settings ) ) {
			return $settings;
		}
		$stored = get_option( 'dhikra_options', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, self::defaults() );
		return $settings;
	}

	public static function setting( string $key, mixed $fallback = null ): mixed {
		$settings = self::settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Return the local font declarations used by public and administrative UI.
	 *
	 * Font files are intentionally loaded from this site's uploads directory so
	 * the same installation works when WordPress or uploads use a custom URL.
	 */
	public static function font_face_css(): string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}

		$base    = trailingslashit( (string) $uploads['baseurl'] ) . 'fonts/';
		$persian = $base . 'IRANSansX/';
		$arabic  = $base . 'Quran/';
		$faces   = array(
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-regular.woff2', 400 ),
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-medium.woff2', 500 ),
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-demiBold.woff2', 600 ),
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-bold.woff2', 700 ),
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-extrabold.woff2', 800 ),
			array( 'Dhikra IRANSansX', $persian . 'IRANSansXFaNum-black.woff2', 900 ),
			array( 'Dhikra Scheherazade', $arabic . 'ScheherazadeNew-Regular.woff2', 400 ),
			array( 'Dhikra Scheherazade', $arabic . 'ScheherazadeNew-Medium.woff2', 500 ),
			array( 'Dhikra Scheherazade', $arabic . 'ScheherazadeNew-SemiBold.woff2', 600 ),
			array( 'Dhikra Scheherazade', $arabic . 'ScheherazadeNew-Bold.woff2', 700 ),
		);

		$css = '';
		foreach ( $faces as $face ) {
			$css .= "@font-face{font-family:'" . $face[0] . "';src:url('" . esc_url_raw( $face[1] ) . "') format('woff2');font-style:normal;font-weight:" . $face[2] . ';font-display:swap;}';
		}

		return $css;
	}

	public static function add_font_faces( string $style_handle ): void {
		$css = self::font_face_css();
		if ( '' !== $css ) {
			wp_add_inline_style( $style_handle, $css );
		}
	}

	public static function base_slug(): string {
		$slug = sanitize_title( (string) self::setting( 'base_slug', 'zekra' ) );
		return '' !== $slug ? $slug : 'zekra';
	}

	public static function root_mode(): bool {
		return ! empty( self::setting( 'root_url_mode', 0 ) );
	}

	public static function app_url( string $route = 'home', array $args = array() ): string {
		$pretty = '' !== (string) get_option( 'permalink_structure', '' );
		$route  = sanitize_key( $route );

		if ( ! $pretty ) {
			$url = add_query_arg( 'dhikra_route', $route, home_url( '/' ) );
			return empty( $args ) ? $url : add_query_arg( $args, $url );
		}

		if ( 'home' === $route ) {
			$url = self::root_mode() ? home_url( '/' ) : home_url( '/' . self::base_slug() . '/' );
		} elseif ( in_array( $route, self::RESERVED_ROUTES, true ) ) {
			$base = self::root_mode() ? '/' : '/' . self::base_slug() . '/';
			$url  = home_url( $base . $route . '/' );
		} else {
			$url = self::app_url( 'home' );
		}

		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	public static function telangor_url( int $post_id ): string {
		$code = Dhikra_Data::get_short_code( $post_id );
		if ( '' === $code ) {
			return self::app_url( 'home' );
		}

		$pretty = '' !== (string) get_option( 'permalink_structure', '' );
		if ( ! $pretty ) {
			return add_query_arg(
				array(
					'dhikra_route' => 'telangor',
					'dhikra_code'  => $code,
				),
				home_url( '/' )
			);
		}

		return self::root_mode() ? home_url( '/' . rawurlencode( $code ) . '/' ) : home_url( '/z/' . rawurlencode( $code ) . '/' );
	}

	public static function is_app_request(): bool {
		$route = get_query_var( 'dhikra_route' );
		if ( is_string( $route ) && '' !== $route ) {
			return true;
		}

		if ( self::root_mode() && is_front_page() ) {
			return true;
		}

		return false;
	}

	public static function request_route(): string {
		$route = sanitize_key( (string) get_query_var( 'dhikra_route' ) );
		if ( '' !== $route ) {
			return $route;
		}

		return self::root_mode() && is_front_page() ? 'home' : '';
	}

	public static function sanitize_short_code( string $code ): string {
		$code = strtoupper( preg_replace( '/\s+/', '', $code ) ?? '' );
		$code = preg_replace( '/[^23456789ABCDEFGHJKLMNPQRSTUVWXYZ]/', '', $code ) ?? '';
		return 5 === strlen( $code ) ? $code : '';
	}

	public static function random_short_code(): string {
		$alphabet = self::SHORT_CODE_ALPHABET;
		$max      = strlen( $alphabet ) - 1;
		$code     = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$code .= $alphabet[ random_int( 0, $max ) ];
		}
		return $code;
	}

	public static function unicode_chars( string $value ): array {
		if ( '' === $value ) {
			return array();
		}
		$chars = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $chars ) ? $chars : str_split( $value );
	}

	public static function unicode_length( string $value ): int {
		return count( self::unicode_chars( $value ) );
	}

	public static function unicode_substr( string $value, int $start, ?int $length = null ): string {
		$chars = self::unicode_chars( $value );
		$slice = null === $length ? array_slice( $chars, $start ) : array_slice( $chars, $start, $length );
		return implode( '', $slice );
	}

	public static function normalize_text( string $value ): string {
		$value = wp_strip_all_tags( $value, false );
		$value = preg_replace( "/\r\n?|\n/u", "\n", $value ) ?? $value;
		$value = preg_replace( '/[\t ]+/u', ' ', $value ) ?? $value;
		$value = preg_replace( '/[ \t]*\n[ \t]*/u', "\n", $value ) ?? $value;
		$value = preg_replace( '/\n{3,}/u', "\n\n", $value ) ?? $value;
		return trim( $value );
	}

	public static function text_direction( string $value ): string {
		return preg_match( '/[\x{0600}-\x{06FF}]/u', $value ) ? 'rtl' : 'ltr';
	}

	public static function render_share_template( string $template, array $data ): string {
		$replacements = array(
			'[title]'       => (string) ( $data['title'] ?? '' ),
			'[arabic]'      => (string) ( $data['original'] ?? '' ),
			'[original]'    => (string) ( $data['original'] ?? '' ),
			'[translation]' => (string) ( $data['translation'] ?? '' ),
			'[reflection]'  => (string) ( $data['reflection'] ?? '' ),
			'[speaker]'     => (string) ( $data['speaker'] ?? '' ),
			'[source]'      => (string) ( $data['source'] ?? '' ),
			'[topic]'       => (string) ( $data['main_topic_name'] ?? '' ),
			'[short_url]'   => (string) ( $data['url'] ?? '' ),
			'[app_url]'     => self::EITAA_APP_URL,
			'[site_name]'   => (string) get_bloginfo( 'name' ),
		);

		$text = strtr( $template, $replacements );
		$text = preg_replace( '/\n[ \t]+/u', "\n", $text ) ?? $text;
		$text = preg_replace( '/\n{3,}/u', "\n\n", $text ) ?? $text;
		return trim( $text );
	}

	public static function share_template( string $platform = 'general' ): string {
		$platform = sanitize_key( $platform );
		$key      = 'share_template_' . $platform;
		$template = (string) self::setting( $key, '' );
		if ( '' === trim( $template ) && 'eitaa' === $platform ) {
			$template = self::defaults()['share_template_eitaa'];
		} elseif ( '' === trim( $template ) ) {
			$template = (string) self::setting( 'share_template_general', self::defaults()['share_template_general'] );
		}
		return $template;
	}

	public static function valid_share_placeholders(): array {
		return array( '[title]', '[arabic]', '[original]', '[translation]', '[reflection]', '[speaker]', '[source]', '[topic]', '[short_url]', '[app_url]', '[site_name]' );
	}

	public static function hex_color( string $value, string $fallback ): string {
		$value = sanitize_hex_color( $value );
		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	public static function hex_to_rgb( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '31,99,195';
		}
		return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
	}

	public static function sanitize_status( string $status ): string {
		return in_array( $status, array( 'draft', 'publish', 'future', 'pending' ), true ) ? $status : 'draft';
	}

	public static function notice( string $message, string $type = 'info' ): string {
		$type = in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info';
		return '<div class="dhikra-notice dhikra-notice--' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
	}

	public static function safe_return_url( string $url, string $fallback = '' ): string {
		$fallback = '' !== $fallback ? $fallback : self::app_url( 'home' );
		$url      = wp_validate_redirect( $url, $fallback );
		$home     = wp_parse_url( home_url( '/' ) );
		$target   = wp_parse_url( $url );
		if ( empty( $home['host'] ) || empty( $target['host'] ) || strtolower( (string) $home['host'] ) !== strtolower( (string) $target['host'] ) ) {
			return $fallback;
		}
		return $url;
	}

	public static function rate_limit( string $action, int $limit, int $window, int $user_id = 0 ): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'dhikra_rl_' . md5( $action . '|' . $user_id . '|' . $ip );
		$row = get_transient( $key );
		if ( ! is_array( $row ) ) {
			set_transient( $key, array( 'count' => 1 ), $window );
			return true;
		}
		$count = absint( $row['count'] ?? 0 );
		if ( $count >= $limit ) {
			return false;
		}
		$row['count'] = $count + 1;
		set_transient( $key, $row, $window );
		return true;
	}

	public static function page_number(): int {
		$page = isset( $_GET['dhikra_page'] ) ? absint( wp_unslash( $_GET['dhikra_page'] ) ) : 1;
		return max( 1, $page );
	}

	public static function xml_escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
