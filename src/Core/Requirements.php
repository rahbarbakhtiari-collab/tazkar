<?php

namespace Tazkar\Core;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Requirements {
    public static function is_met(): bool {
        global $wp_version;

        return array() === self::unmet(
            PHP_VERSION,
            isset( $wp_version ) ? (string) $wp_version : '0.0'
        );
    }

    /**
     * @return string[]
     */
    public static function unmet( string $php_version, string $wordpress_version ): array {
        $errors = array();

        if ( version_compare( $php_version, TAZKAR_MIN_PHP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                'PHP %1$s or newer is required; detected %2$s.',
                TAZKAR_MIN_PHP_VERSION,
                $php_version
            );
        }

        if ( version_compare( $wordpress_version, TAZKAR_MIN_WP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                'WordPress %1$s or newer is required; detected %2$s.',
                TAZKAR_MIN_WP_VERSION,
                $wordpress_version
            );
        }

        return $errors;
    }

    public static function register_admin_notice(): void {
        add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );
    }

    public static function render_admin_notice(): void {
        global $wp_version;

        $errors = self::unmet(
            PHP_VERSION,
            isset( $wp_version ) ? (string) $wp_version : '0.0'
        );

        if ( array() === $errors ) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>';
        echo esc_html__( 'تذکار فعال نشد، زیرا محیط میزبان با نیازمندی‌های افزونه سازگار نیست.', 'tazkar' );
        echo '</strong></p><ul style="list-style:disc;padding-inline-start:1.5rem">';

        foreach ( $errors as $error ) {
            echo '<li>' . esc_html( $error ) . '</li>';
        }

        echo '</ul></div>';
    }
}
