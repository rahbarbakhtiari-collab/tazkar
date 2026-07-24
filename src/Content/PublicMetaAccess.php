<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class PublicMetaAccess {
	public static function register(): void {
		foreach ( MetaRegistry::public_fields() as $field ) {
			add_filter(
				'auth_post_meta_' . MetaRegistry::key( $field ) . '_for_' . ContentType::POST_TYPE,
				array( self::class, 'authorize' ),
				20,
				6
			);
		}
	}

	/**
	 * Permit public reads only for published and approved items without weakening write checks.
	 *
	 * @param bool     $allowed  Existing authorization result.
	 * @param string   $meta_key Registered meta key.
	 * @param int      $post_id  Target post ID.
	 * @param int      $user_id  Current user ID.
	 * @param string   $cap      Requested meta capability.
	 * @param string[] $caps     Primitive capabilities.
	 */
	public static function authorize(
		bool $allowed,
		string $meta_key,
		int $post_id,
		int $user_id,
		string $cap,
		array $caps
	): bool {
		unset( $meta_key, $user_id, $caps );

		if (
			'read_post_meta' === $cap
			&& 'publish' === get_post_status( $post_id )
			&& 'approved' === get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true )
		) {
			return true;
		}

		return $allowed;
	}
}
