<?php
/**
 * Plugin Name:       ذِكْرَىٰ
 * Description:       تجربهٔ کارت‌محور فارسی برای تلنگرهای دینی و حکمی، با ورود و ثبت‌نام OTP موبایل.
 * Version:           1.6.29
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            رهبر بختیاری
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dhikra
 * Domain Path:       /languages
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DHIKRA_VERSION', '1.6.29' );
define( 'DHIKRA_DB_VERSION', '1.6.29' );
define( 'DHIKRA_FILE', __FILE__ );
define( 'DHIKRA_DIR', plugin_dir_path( __FILE__ ) );
define( 'DHIKRA_URL', plugin_dir_url( __FILE__ ) );
define( 'DHIKRA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Dhikra classes on demand instead of parsing every admin and frontend
 * component on every WordPress request.
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( ! str_starts_with( $class, 'Dhikra_' ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', substr( $class, 7 ) ) );
		$file = DHIKRA_DIR . 'includes/class-dhikra-' . $slug . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( DHIKRA_FILE, array( 'Dhikra_Activator', 'activate' ) );
register_deactivation_hook( DHIKRA_FILE, array( 'Dhikra_Activator', 'deactivate' ) );

Dhikra_Plugin::instance()->run();
