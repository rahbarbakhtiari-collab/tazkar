<?php

namespace Tazkar\Admin;

use Tazkar\Core\Requirements;
use Tazkar\Infrastructure\Database\Migrator;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class StatusPage {
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'وضعیت تذکار', 'tazkar' ),
            __( 'تذکار', 'tazkar' ),
            'manage_tazkar',
            'tazkar',
            array( $this, 'render' ),
            'dashicons-format-quote',
            58
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_tazkar' ) ) {
            wp_die( esc_html__( 'شما اجازه مشاهده این صفحه را ندارید.', 'tazkar' ) );
        }

        global $wp_version;

        $requirements = Requirements::unmet( PHP_VERSION, (string) $wp_version );
        $tables       = Migrator::table_status();
        ?>
        <div class="wrap tazkar-status-page">
            <h1><?php echo esc_html__( 'وضعیت افزونه تذکار', 'tazkar' ); ?></h1>
            <p><?php echo esc_html__( 'این صفحه فقط وضعیت زیرساخت Sprint صفر را نمایش می‌دهد.', 'tazkar' ); ?></p>

            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'نسخه افزونه', 'tazkar' ); ?></th>
                        <td><code><?php echo esc_html( TAZKAR_VERSION ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'نسخه ساختار داده', 'tazkar' ); ?></th>
                        <td><code><?php echo esc_html( (string) get_option( 'tazkar_db_version', '0' ) ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'WordPress', 'tazkar' ); ?></th>
                        <td><?php echo esc_html( (string) $wp_version ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'PHP', 'tazkar' ); ?></th>
                        <td><?php echo esc_html( PHP_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'نیازمندی‌ها', 'tazkar' ); ?></th>
                        <td>
                            <?php if ( array() === $requirements ) : ?>
                                <strong style="color:#006b3c"><?php echo esc_html__( 'سازگار', 'tazkar' ); ?></strong>
                            <?php else : ?>
                                <strong style="color:#b32d2e"><?php echo esc_html__( 'ناسازگار', 'tazkar' ); ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__( 'جدول‌های عملیاتی', 'tazkar' ); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'جدول', 'tazkar' ); ?></th>
                        <th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tables as $table_key => $exists ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $table_key ); ?></code></td>
                            <td>
                                <?php echo $exists ? esc_html__( 'موجود', 'tazkar' ) : esc_html__( 'ایجاد نشده', 'tazkar' ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
