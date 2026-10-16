<?php
/**
 * Eitaa Mini App identity validation and outbound messaging bridge.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Eitaa {
	public static function validate_init_data( string $init_data ): array|WP_Error {
		$init_data = trim( $init_data );
		if ( '' === $init_data || strlen( $init_data ) > 20000 ) {
			return new WP_Error( 'dhikra_eitaa_missing_init', __( 'اطلاعات ورود ایتا دریافت نشد.', 'dhikra' ) );
		}

		$token = trim( (string) Dhikra_Helpers::setting( 'eitaa_bot_token', '' ) );
		$filtered = apply_filters( 'dhikra_eitaa_validate_init_data', null, $init_data, $token );
		if ( is_array( $filtered ) ) {
			return self::normalize_validated_data( $filtered );
		}
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}
		if ( '' === $token ) {
			return new WP_Error( 'dhikra_eitaa_token_missing', __( 'توکن برنامک ایتا در تنظیمات ذکری ثبت نشده است.', 'dhikra' ) );
		}

		$parts = array();
		parse_str( $init_data, $parts );
		if ( ! is_array( $parts ) || empty( $parts['hash'] ) ) {
			return new WP_Error( 'dhikra_eitaa_invalid_init', __( 'دادهٔ ورود ایتا معتبر نیست.', 'dhikra' ) );
		}
		$received_hash = strtolower( sanitize_text_field( (string) $parts['hash'] ) );
		unset( $parts['hash'] );
		ksort( $parts, SORT_STRING );
		$lines = array();
		foreach ( $parts as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$lines[] = $key . '=' . (string) $value;
			}
		}
		$data_check = implode( "\n", $lines );

		// Eitaa Mini Apps expose a Telegram-compatible initData shape. The
		// validator is kept filterable so a provider-specific signer can be
		// substituted without changing the audience/reward modules.
		$secret   = hash_hmac( 'sha256', $token, 'WebAppData', true );
		$computed = hash_hmac( 'sha256', $data_check, $secret );
		if ( ! hash_equals( $computed, $received_hash ) ) {
			return new WP_Error( 'dhikra_eitaa_bad_hash', __( 'اعتبارسنجی ورود ایتا ناموفق بود.', 'dhikra' ) );
		}

		$max_age = max( 300, absint( Dhikra_Helpers::setting( 'eitaa_init_max_age', DAY_IN_SECONDS ) ) );
		$auth_date = isset( $parts['auth_date'] ) ? absint( $parts['auth_date'] ) : 0;
		if ( $auth_date <= 0 || abs( time() - $auth_date ) > $max_age ) {
			return new WP_Error( 'dhikra_eitaa_expired_init', __( 'اطلاعات ورود ایتا منقضی شده است. برنامک را دوباره باز کنید.', 'dhikra' ) );
		}

		return self::normalize_validated_data( $parts );
	}

	private static function normalize_validated_data( array $parts ): array|WP_Error {
		$user = $parts['user'] ?? array();
		if ( is_string( $user ) ) {
			$decoded = json_decode( $user, true );
			$user = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $user ) || empty( $user['id'] ) ) {
			return new WP_Error( 'dhikra_eitaa_user_missing', __( 'شناسهٔ کاربر ایتا در دادهٔ معتبر وجود ندارد.', 'dhikra' ) );
		}
		$eitaa_id = preg_replace( '/\D+/', '', (string) $user['id'] );
		if ( '' === $eitaa_id ) {
			return new WP_Error( 'dhikra_eitaa_user_invalid', __( 'شناسهٔ کاربر ایتا نامعتبر است.', 'dhikra' ) );
		}
		return array(
			'eitaa_user_id'      => $eitaa_id,
			'first_name'         => sanitize_text_field( (string) ( $user['first_name'] ?? '' ) ),
			'last_name'          => sanitize_text_field( (string) ( $user['last_name'] ?? '' ) ),
			'language_code'      => sanitize_text_field( (string) ( $user['language_code'] ?? '' ) ),
			'allows_write_to_pm' => ! empty( $user['allows_write_to_pm'] ),
			'auth_date'          => absint( $parts['auth_date'] ?? 0 ),
			'device_id'          => sanitize_text_field( (string) ( $parts['device_id'] ?? '' ) ),
			'start_param'        => sanitize_text_field( (string) ( $parts['start_param'] ?? '' ) ),
		);
	}

	public static function send_message( string $eitaa_user_id, string $text ): true|WP_Error {
		$eitaa_user_id = preg_replace( '/\D+/', '', $eitaa_user_id );
		$text = trim( wp_strip_all_tags( $text ) );
		if ( '' === $eitaa_user_id || '' === $text ) {
			return new WP_Error( 'dhikra_eitaa_message_invalid', __( 'گیرنده یا متن پیام معتبر نیست.', 'dhikra' ) );
		}

		$filtered = apply_filters( 'dhikra_eitaa_send_message', null, $eitaa_user_id, $text );
		if ( true === $filtered ) {
			return true;
		}
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}

		$token = trim( (string) Dhikra_Helpers::setting( 'eitaa_bot_token', '' ) );
		if ( '' === $token ) {
			return new WP_Error( 'dhikra_eitaa_sender_missing', __( 'توکن برنامه ایتا تنظیم نشده است.', 'dhikra' ) );
		}

		// Mini App outbound messages use the dedicated application endpoint.
		// The application token is part of the request body; it must not be
		// embedded in the URL (that URL shape belongs to a different Eitaayar API).
		$url = 'https://eitaayar.ir/api/app/sendMessage';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'token'   => $token,
					'chat_id' => $eitaa_user_id,
					'text'    => $text,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = absint( wp_remote_retrieve_response_code( $response ) );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );
		$success  = $code >= 200
			&& $code < 300
			&& is_array( $body )
			&& true === ( $body['ok'] ?? false )
			&& 'success' === strtolower( trim( (string) ( $body['result'] ?? '' ) ) );

		if ( ! $success ) {
			$detail = self::response_error_detail( $code, $body, $raw_body );
			return new WP_Error(
				'dhikra_eitaa_send_failed',
				sprintf( __( 'ارسال پیام در ایتا انجام نشد: %s', 'dhikra' ), $detail ),
				array(
					'http_code' => $code,
					'response'  => is_array( $body ) ? $body : null,
					'raw_body'  => substr( wp_strip_all_tags( $raw_body ), 0, 2000 ),
				)
			);
		}
		return true;
	}

	private static function response_error_detail( int $code, mixed $body, string $raw_body ): string {
		$parts = array();
		if ( $code > 0 ) {
			$parts[] = 'HTTP ' . $code;
		}
		if ( is_array( $body ) ) {
			foreach ( array( 'description', 'message', 'error', 'result' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
					$value = trim( sanitize_text_field( (string) $body[ $key ] ) );
					if ( '' !== $value && 'success' !== strtolower( $value ) ) {
						$parts[] = $value;
						break;
					}
				}
			}
		} elseif ( '' !== trim( $raw_body ) ) {
			$parts[] = substr( trim( sanitize_text_field( wp_strip_all_tags( $raw_body ) ) ), 0, 300 );
		}
		return ! empty( $parts ) ? implode( ' — ', array_unique( $parts ) ) : __( 'پاسخ موفق معتبر از API دریافت نشد.', 'dhikra' );
	}
}
