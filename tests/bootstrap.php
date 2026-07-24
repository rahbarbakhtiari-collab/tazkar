<?php

define( 'TAZKAR_TESTING', true );
define( 'TAZKAR_MIN_WP_VERSION', '6.8' );
define( 'TAZKAR_MIN_PHP_VERSION', '8.1' );
define( 'TAZKAR_DB_VERSION', '1' );

require_once dirname( __DIR__ ) . '/src/Autoloader.php';

Tazkar\Autoloader::register( dirname( __DIR__ ) . '/src/' );
