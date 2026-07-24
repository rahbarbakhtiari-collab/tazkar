<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class SensitiveChangeGuard {
	/** @var bool */
	private static $updating_status = false;

	/**
	 * @return string[]
	 */
	public static function sensitive_fields(): array {
		return array(
			'content_type',
			'source_file',
			'book_title',
			'source_location',
			'speaker',
			'arabic_text',
			'translation_original',
			'translation_corrected',
			'source_original',
			'source_corrected',
			'surah_name',
			'surah_number',
			'ayah_start',
			'ayah_end',
		);
	}

	public static function register(): void {
		add_filter( 'add_post_metadata', array( self::class, 'before_add' ), 10, 5 );
		add_filter( 'update_post_metadata', array( self::class, 'before_update' ), 10, 5 );
		add_filter( 'delete_post_metadata', array( self::class, 'before_delete' ), 10, 5 );
		add_action( 'added_post_meta', array( self::class, 'refresh_arabic_hash' ), 10, 4 );
		add_action( 'updated_post_meta', array( self::class, 'refresh_arabic_hash' ), 10, 4 );
		add_action( 'deleted_post_meta', array( self::class, 'after_delete' ), 10, 4 );
	}

	/**
	 * Mark an approved item before a sensitive meta value is added.
	 *
	 * @param mixed  $check      Short-circuit value.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @param mixed  $unique     Unique flag.
	 * @return mixed
	 */
	public static function before_add( $check, int $object_id, string $meta_key, $meta_value, $unique ) {
		unset( $unique );

		if ( null !== $check ) {
			return $check;
		}

		self::mark_if_needed( $object_id, $meta_key, null, $meta_value );

		return $check;
	}

	/**
	 * Mark an approved item before a sensitive meta value is updated.
	 *
	 * @param mixed  $check      Short-circuit value.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @param mixed  $prev_value Previous value supplied to WordPress.
	 * @return mixed
	 */
	public static function before_update( $check, int $object_id, string $meta_key, $meta_value, $prev_value ) {
		unset( $prev_value );

		if ( null !== $check ) {
			return $check;
		}

		$old_value = get_post_meta( $object_id, $meta_key, true );
		self::mark_if_needed( $object_id, $meta_key, $old_value, $meta_value );

		return $check;
	}

	/**
	 * Mark an approved item before sensitive meta is deleted.
	 *
	 * @param mixed  $check      Short-circuit value.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Value constraint.
	 * @param mixed  $delete_all Delete-all flag.
	 * @return mixed
	 */
	public static function before_delete( $check, int $object_id, string $meta_key, $meta_value, $delete_all ) {
		unset( $meta_value, $delete_all );

		if ( null !== $check ) {
			return $check;
		}

		$old_value = get_post_meta( $object_id, $meta_key, true );
		self::mark_if_needed( $object_id, $meta_key, $old_value, null );

		return $check;
	}

	/**
	 * Refresh the exact Arabic SHA-256 after metadata changes.
	 *
	 * @param int    $meta_id    Metadata row ID.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Stored value reported by WordPress.
	 */
	public static function refresh_arabic_hash( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		if (
			MetaRegistry::key( 'arabic_text' ) !== $meta_key
			|| ContentType::POST_TYPE !== get_post_type( $object_id )
		) {
			return;
		}

		$arabic = (string) get_post_meta( $object_id, MetaRegistry::key( 'arabic_text' ), true );
		update_post_meta( $object_id, MetaRegistry::ARABIC_HASH_KEY, self::hash_arabic( $arabic ) );
	}

	/**
	 * Remove the Arabic hash after Arabic metadata is deleted.
	 *
	 * @param int[]  $meta_ids   Deleted meta IDs.
	 * @param int    $object_id  Target post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Deleted value.
	 */
	public static function after_delete( array $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		unset( $meta_ids, $meta_value );

		if (
			MetaRegistry::key( 'arabic_text' ) === $meta_key
			&& ContentType::POST_TYPE === get_post_type( $object_id )
		) {
			delete_post_meta( $object_id, MetaRegistry::ARABIC_HASH_KEY );
		}
	}

	/**
	 * Hash the exact stored Arabic bytes.
	 *
	 * @param string $arabic Exact Arabic value.
	 */
	public static function hash_arabic( string $arabic ): string {
		return hash( 'sha256', $arabic );
	}

	/**
	 * Decide whether a metadata transition invalidates approval.
	 *
	 * @param string $post_type     Post type.
	 * @param string $review_status Current review status.
	 * @param string $meta_key      Changed meta key.
	 * @param mixed  $old_value     Previous value.
	 * @param mixed  $new_value     New value.
	 */
	public static function should_mark_for_recheck(
		string $post_type,
		string $review_status,
		string $meta_key,
		$old_value,
		$new_value
	): bool {
		return ContentType::POST_TYPE === $post_type
			&& 'approved' === $review_status
			&& in_array( $meta_key, self::sensitive_meta_keys(), true )
			&& self::comparable_value( $old_value ) !== self::comparable_value( $new_value );
	}

	/**
	 * Apply the needs_recheck transition when required.
	 *
	 * @param int    $object_id Target post ID.
	 * @param string $meta_key  Changed meta key.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 */
	private static function mark_if_needed( int $object_id, string $meta_key, $old_value, $new_value ): void {
		if ( self::$updating_status ) {
			return;
		}

		$review_key    = MetaRegistry::key( 'review_status' );
		$review_status = (string) get_post_meta( $object_id, $review_key, true );
		$post_type     = (string) get_post_type( $object_id );

		if ( ! self::should_mark_for_recheck( $post_type, $review_status, $meta_key, $old_value, $new_value ) ) {
			return;
		}

		self::$updating_status = true;
		update_post_meta( $object_id, $review_key, 'needs_recheck' );
		self::$updating_status = false;
	}

	/**
	 * @return string[]
	 */
	private static function sensitive_meta_keys(): array {
		return array_map( array( MetaRegistry::class, 'key' ), self::sensitive_fields() );
	}

	/**
	 * @param mixed $value Value to compare.
	 */
	private static function comparable_value( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
