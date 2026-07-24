<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Taxonomies {
    public const TOPIC = 'tazkar_topic';
    public const TAG   = 'tazkar_tag';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array {
        return array(
            self::TOPIC => array(
                'labels'            => array(
                    'name'          => __( 'موضوع‌ها', 'tazkar' ),
                    'singular_name' => __( 'موضوع', 'tazkar' ),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => true,
                'rewrite'           => array( 'slug' => 'reminder-topic' ),
            ),
            self::TAG   => array(
                'labels'            => array(
                    'name'          => __( 'برچسب‌ها', 'tazkar' ),
                    'singular_name' => __( 'برچسب', 'tazkar' ),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => array( 'slug' => 'reminder-tag' ),
            ),
        );
    }

    public static function register(): void {
        foreach ( self::definitions() as $taxonomy => $arguments ) {
            register_taxonomy( $taxonomy, array( ContentType::POST_TYPE ), $arguments );
        }
    }
}
