<?php

declare(strict_types=1);

$stub_root = sys_get_temp_dir() . '/tazkar-wp-stub-' . getmypid() . '/';
@mkdir( $stub_root . 'wp-admin/includes', 0777, true );
file_put_contents(
    $stub_root . 'wp-admin/includes/upgrade.php',
    <<<'PHP'
<?php
function dbDelta( $statement ) {
    $GLOBALS['tazkar_test_sql'][] = $statement;
}
PHP
);

define( 'ABSPATH', $stub_root );

$GLOBALS['wp_version']        = '7.0.2';
$GLOBALS['tazkar_test_sql']   = array();
$GLOBALS['tazkar_options']    = array();
$GLOBALS['tazkar_actions']    = array();
$GLOBALS['tazkar_caps']       = array();
$GLOBALS['activation_hook']   = null;
$GLOBALS['deactivation_hook'] = null;

class Tazkar_Test_Role {
    public function add_cap( string $capability ): void {
        $GLOBALS['tazkar_caps'][ $capability ] = true;
    }

    public function remove_cap( string $capability ): void {
        unset( $GLOBALS['tazkar_caps'][ $capability ] );
    }
}

class Tazkar_Test_WPDB {
    public $prefix = 'custom_';

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

$GLOBALS['wpdb'] = new Tazkar_Test_WPDB();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['tazkar_actions'][ $hook ][] = $callback; }
function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) { if ( ! array_key_exists( $name, $GLOBALS['tazkar_options'] ) ) { $GLOBALS['tazkar_options'][ $name ] = $value; } }
function deactivate_plugins( $plugin ) {}
function delete_transient( $name ) { $GLOBALS['deleted_transient'] = $name; }
function esc_html( $text ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function get_option( $name, $default = false ) { return $GLOBALS['tazkar_options'][ $name ] ?? $default; }
function get_role( $role ) { return 'administrator' === $role ? new Tazkar_Test_Role() : null; }
function is_multisite() { return false; }
function plugin_basename( $file ) { return basename( $file ); }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.invalid/'; }
function register_activation_hook( $file, $callback ) { $GLOBALS['activation_hook'] = $callback; }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['deactivation_hook'] = $callback; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['tazkar_options'][ $name ] = $value; }
function wp_die( $message = '', $title = '', $args = array() ) { throw new RuntimeException( (string) $message ); }
function __( $text, $domain = 'default' ) { return $text; }

require dirname( __DIR__ ) . '/tazkar.php';

if ( ! is_callable( $GLOBALS['activation_hook'] ) ) {
    fwrite( STDERR, "Activation hook was not registered.\n" );
    exit( 1 );
}

call_user_func( $GLOBALS['activation_hook'], false );
call_user_func( $GLOBALS['activation_hook'], false );

$checks = array(
    8 === count( $GLOBALS['tazkar_test_sql'] ),
    '1' === (string) ( $GLOBALS['tazkar_options']['tazkar_db_version'] ?? '' ),
    '0.3.0-alpha.1' === (string) ( $GLOBALS['tazkar_options']['tazkar_version'] ?? '' ),
    7 === count( $GLOBALS['tazkar_caps'] ),
    false !== strpos( $GLOBALS['tazkar_test_sql'][0] ?? '', 'custom_tazkar_import_batches' ),
    false !== strpos( $GLOBALS['tazkar_test_sql'][3] ?? '', 'custom_tazkar_favorites' ),
);

if ( in_array( false, $checks, true ) ) {
    fwrite( STDERR, "Activation smoke test failed.\n" );
    exit( 1 );
}

call_user_func( $GLOBALS['deactivation_hook'] );

if ( 7 !== count( $GLOBALS['tazkar_caps'] ) ) {
    fwrite( STDERR, "Deactivation must preserve capabilities.\n" );
    exit( 1 );
}

echo "Tazkar activation smoke test passed.\n";
