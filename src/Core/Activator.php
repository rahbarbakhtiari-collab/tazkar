<?php

namespace Tazkar\Core;

use Tazkar\Infrastructure\Database\Migrator;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Activator {
    public static function activate( bool $network_wide = false ): void {
        if ( $network_wide && is_multisite() ) {
            self::abort_activation(
                __( 'فعال‌سازی شبکه‌ای تذکار در نسخه فعلی پشتیبانی نمی‌شود. افزونه را برای هر سایت جداگانه فعال کنید.', 'tazkar' )
            );
        }

        if ( ! Requirements::is_met() ) {
            self::abort_activation(
                __( 'نسخه PHP یا WordPress با نیازمندی‌های تذکار سازگار نیست.', 'tazkar' )
            );
        }

        Capabilities::sync();
        Migrator::migrate();

        if ( false === get_option( 'tazkar_installed_at', false ) ) {
            add_option( 'tazkar_installed_at', gmdate( 'Y-m-d H:i:s' ), '', false );
        }

        update_option( 'tazkar_version', TAZKAR_VERSION, false );
    }

    private static function abort_activation( string $message ): void {
        if ( function_exists( 'deactivate_plugins' ) ) {
            deactivate_plugins( plugin_basename( TAZKAR_PLUGIN_FILE ) );
        }

        wp_die(
            esc_html( $message ),
            esc_html__( 'خطای فعال‌سازی تذکار', 'tazkar' ),
            array( 'back_link' => true )
        );
    }
}
