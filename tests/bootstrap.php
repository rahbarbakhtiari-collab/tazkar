<?php

define( 'TAZKAR_TESTING', true );
define( 'TAZKAR_MIN_WP_VERSION', '6.8' );
define( 'TAZKAR_MIN_PHP_VERSION', '8.1' );
define( 'TAZKAR_DB_VERSION', '1' );

require_once dirname( __DIR__ ) . '/src/Autoloader.php';

Tazkar\Autoloader::register( dirname( __DIR__ ) . '/src/' );

$GLOBALS['tazkar_test_post_status'] = 'publish';

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
        return true;
    }
}

if ( ! function_exists( 'get_post_status' ) ) {
    function get_post_status( $post_id ) {
        unset( $post_id );
        return $GLOBALS['tazkar_test_post_status'];
    }
}
