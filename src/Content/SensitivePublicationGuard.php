<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class SensitivePublicationGuard {
	public static function register(): void {
		add_action( 'added_post_meta', array( self::class, 'maybe_unpublish' ), 20, 4 );
		add_action( 'updated_post_meta', array( self::class, 'maybe_unpublish' ), 20, 4 );
		add_action( 'deleted_post_meta', array( self::class, 'maybe_unpublish' ), 20, 4 );
	}

	/**
	 * Remove an item from public view after an approved sensitive value becomes stale.
	 *
	 * @param mixed  $meta_id    Metadata row ID or deleted meta IDs.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Changed meta key.
	 * @param mixed  $meta_value Changed value.
	 */
	public static function maybe_unpublish( $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		$post_type     = (string) get_post_type( $object_id );
		$review_status = (string) get_post_meta(
			$object_id,
			MetaRegistry::key( 'review_status' ),
			true
		);
		$post_status   = (string) get_post_status( $object_id );

		if ( ! self::should_unpublish( $post_type, $review_status, $post_status, $meta_key ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $object_id,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Decide whether a sensitive metadata change must remove a post from public view.
	 */
	public static function should_unpublish(
		string $post_type,
		string $review_status,
		string $post_status,
		string $meta_key
	): bool {
		return ContentType::POST_TYPE === $post_type
			&& 'needs_recheck' === $review_status
			&& 'publish' === $post_status
			&& in_array( $meta_key, self::sensitive_meta_keys(), true );
	}

	/**
	 * @return string[]
	 */
	private static function sensitive_meta_keys(): array {
		return array_map( array( MetaRegistry::class, 'key' ), SensitiveChangeGuard::sensitive_fields() );
	}
}
