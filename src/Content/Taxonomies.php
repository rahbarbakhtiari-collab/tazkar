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
		$capabilities = self::capabilities();

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
				'capabilities'      => $capabilities,
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
				'capabilities'      => $capabilities,
				'rewrite'           => array( 'slug' => 'reminder-tag' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		return array(
			'manage_terms' => 'manage_tazkar',
			'edit_terms'   => 'manage_tazkar',
			'delete_terms' => 'manage_tazkar',
			'assign_terms' => 'manage_tazkar',
		);
	}

	public static function register(): void {
		foreach ( self::definitions() as $taxonomy => $arguments ) {
			register_taxonomy( $taxonomy, array( ContentType::POST_TYPE ), $arguments );
		}
	}
}
