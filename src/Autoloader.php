<?php

namespace Tazkar;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Autoloader {
    private const PREFIX = 'Tazkar\\';

    /** @var string */
    private static $base_directory = '';

    public static function register( string $base_directory ): void {
        self::$base_directory = rtrim( $base_directory, '/\\' ) . DIRECTORY_SEPARATOR;
        spl_autoload_register( array( self::class, 'autoload' ) );
    }

    public static function autoload( string $class_name ): void {
        if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
            return;
        }

        $relative_class = substr( $class_name, strlen( self::PREFIX ) );
        $relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
        $file_path      = self::$base_directory . $relative_path;

        if ( is_readable( $file_path ) ) {
            require_once $file_path;
        }
    }
}
