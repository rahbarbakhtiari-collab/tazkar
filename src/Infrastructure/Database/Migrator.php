<?php

namespace Tazkar\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Migrator {
    public static function maybe_migrate(): void {
        $installed_version = (string) get_option( 'tazkar_db_version', '0' );

        if ( version_compare( $installed_version, TAZKAR_DB_VERSION, '<' ) ) {
            self::migrate();
        }
    }

    public static function migrate(): void {
        global $wpdb;
        /** @var \wpdb $wpdb */

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $statements = Schema::create_statements( $wpdb->prefix, $wpdb->get_charset_collate() );

        foreach ( $statements as $statement ) {
            dbDelta( $statement );
        }

        update_option( 'tazkar_db_version', TAZKAR_DB_VERSION, false );
    }

    /**
     * @return array<string,bool>
     */
    public static function table_status(): array {
        global $wpdb;
        /** @var \wpdb $wpdb */

        $status = array();

        foreach ( Schema::table_names( $wpdb->prefix ) as $key => $table_name ) {
            $table_pattern  = $wpdb->esc_like( $table_name );
            $found          = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_pattern ) );
            $status[ $key ] = $table_name === $found;
        }

        return $status;
    }
}
