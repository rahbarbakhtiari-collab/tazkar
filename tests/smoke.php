<?php

declare(strict_types=1);

define( 'TAZKAR_TESTING', true );
define( 'TAZKAR_MIN_WP_VERSION', '6.8' );
define( 'TAZKAR_MIN_PHP_VERSION', '8.1' );
define( 'TAZKAR_DB_VERSION', '1' );

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
Tazkar\Autoloader::register( dirname( __DIR__ ) . '/src/' );

$failures = array();

$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

$assert( array() === Tazkar\Core\Requirements::unmet( '8.1.0', '6.8' ), 'Supported versions should pass.' );
$assert( 2 === count( Tazkar\Core\Requirements::unmet( '8.0.0', '6.7' ) ), 'Unsupported versions should report two errors.' );
$assert( 7 === count( Tazkar\Core\Capabilities::all() ), 'Seven capabilities are expected.' );
$assert(
    'site42_tazkar_feedback' === Tazkar\Infrastructure\Database\Schema::table_names( 'site42_' )['feedback'],
    'Custom database prefixes must be respected.'
);
$assert(
    4 === count( Tazkar\Infrastructure\Database\Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' ) ),
    'Four schema statements are expected.'
);

if ( array() !== $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo "Tazkar Sprint 0 smoke tests passed.\n";
