<?php

define( 'TAZKAR_TESTING', true );
define( 'TAZKAR_MIN_WP_VERSION', '6.8' );
define( 'TAZKAR_MIN_PHP_VERSION', '8.1' );
define( 'TAZKAR_DB_VERSION', '1' );

require_once dirname( __DIR__ ) . '/src/Autoloader.php';

Tazkar\Autoloader::register( dirname( __DIR__ ) . '/src/' );

$GLOBALS['tazkar_test_post_status']      = 'publish';
$GLOBALS['tazkar_test_review_status']    = 'approved';
$GLOBALS['tazkar_test_current_user_can'] = true;
$GLOBALS['tazkar_test_nonce_action']     = null;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		unset( $capability, $args );
		return (bool) $GLOBALS['tazkar_test_current_user_can'];
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$GLOBALS['tazkar_test_nonce_action'] = array( $action, $query_arg );
		return 1;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		unset( $title, $args );
		throw new RuntimeException( (string) $message );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		unset( $action, $name, $referer, $display );
		return '';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null ) {
		echo '<button type="submit">' . esc_html( (string) $text ) . '</button>';
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post_id ) {
		unset( $post_id );
		return $GLOBALS['tazkar_test_post_status'];
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $post_id, $single );

		if ( 'tazkar_review_status' === $key ) {
			return $GLOBALS['tazkar_test_review_status'];
		}

		return '';
	}
}
