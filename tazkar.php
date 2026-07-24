<?php
/**
 * Plugin Name:       Tazkar – Quran & Hadith Reflections
 * Description:       زیرساخت افزونه تذکار برای مدیریت تلنگرهای قرآن و حدیث.
 * Version:           0.1.0-alpha.1
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Tazkar Project
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tazkar
 * Domain Path:       /languages
 * Network:           false
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    add_action(
        'admin_notices',
        static function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'افزونه تذکار به PHP 8.1 یا بالاتر نیاز دارد.', 'tazkar' );
            echo '</p></div>';
        }
    );
    return;
}

define( 'TAZKAR_VERSION', '0.1.0-alpha.1' );
define( 'TAZKAR_DB_VERSION', '1' );
define( 'TAZKAR_MIN_WP_VERSION', '6.8' );
define( 'TAZKAR_MIN_PHP_VERSION', '8.1' );
define( 'TAZKAR_PLUGIN_FILE', __FILE__ );
define( 'TAZKAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAZKAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAZKAR_PLUGIN_DIR . 'src/Autoloader.php';

Tazkar\Autoloader::register( TAZKAR_PLUGIN_DIR . 'src/' );

register_activation_hook( __FILE__, array( Tazkar\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Tazkar\Core\Deactivator::class, 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function () {
        if ( ! Tazkar\Core\Requirements::is_met() ) {
            Tazkar\Core\Requirements::register_admin_notice();
            return;
        }

        Tazkar\Bootstrap::instance()->register();
    }
);
