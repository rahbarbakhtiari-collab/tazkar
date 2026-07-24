<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class ContentType {
	public const POST_TYPE = 'tazkar_item';

	/**
	 * @return array<string, mixed>
	 */
	public static function arguments(): array {
		return array(
			'labels'              => array(
				'name'          => __( 'تلنگرها', 'tazkar' ),
				'singular_name' => __( 'تلنگر', 'tazkar' ),
				'add_new_item'  => __( 'افزودن تلنگر', 'tazkar' ),
				'edit_item'     => __( 'ویرایش تلنگر', 'tazkar' ),
				'view_item'     => __( 'مشاهده تلنگر', 'tazkar' ),
				'search_items'  => __( 'جست‌وجوی تلنگرها', 'tazkar' ),
				'not_found'     => __( 'تلنگری پیدا نشد.', 'tazkar' ),
			),
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'tazkar',
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'reminders' ),
			'supports'            => array( 'title', 'author', 'revisions', 'custom-fields' ),
			'exclude_from_search' => false,
			'capability_type'     => array( 'tazkar_item', 'tazkar_items' ),
			'map_meta_cap'        => true,
			'capabilities'        => self::capabilities(),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		return array(
			'edit_post'              => 'edit_tazkar_item',
			'read_post'              => 'read_tazkar_item',
			'delete_post'            => 'delete_tazkar_item',
			'edit_posts'             => 'manage_tazkar',
			'edit_others_posts'      => 'manage_tazkar',
			'publish_posts'          => 'publish_tazkar_items',
			'read_private_posts'     => 'review_tazkar_items',
			'delete_posts'           => 'manage_tazkar',
			'delete_private_posts'   => 'manage_tazkar',
			'delete_published_posts' => 'manage_tazkar',
			'delete_others_posts'    => 'manage_tazkar',
			'edit_private_posts'     => 'review_tazkar_items',
			'edit_published_posts'   => 'manage_tazkar',
			'create_posts'           => 'manage_tazkar',
		);
	}

	public static function register(): void {
		register_post_type( self::POST_TYPE, self::arguments() );
	}
}
