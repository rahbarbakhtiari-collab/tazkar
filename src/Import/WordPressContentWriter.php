<?php

namespace Tazkar\Import;

use RuntimeException;
use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Content\Taxonomies;

final class WordPressContentWriter implements RollbackAwareContentWriter {
	private const BATCH_OWNER_META = '_tazkar_import_batch_uuid';
	private const ROW_OWNER_META   = '_tazkar_import_row_id';

	public function __construct(
		private readonly DraftItemPayloadBuilder $payload_builder = new DraftItemPayloadBuilder()
	) {}

	/** @param array<string, mixed> $row Validated source row. */
	public function create_draft( array $row, int $author_id ): int {
		$payload   = $this->payload_builder->build( $row, $author_id );
		$post_data = $payload->post_data();
		if ( isset( $post_data['post_name'] ) ) {
			$post_data['post_name'] = sanitize_title( (string) $post_data['post_name'] );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}
		$post_id = (int) $post_id;

		try {
			foreach ( $payload->meta() as $key => $value ) {
				$this->store_meta( $post_id, $key, $value );
			}
			$this->assign_terms( $post_id, Taxonomies::TOPIC, $payload->topics() );
			$this->assign_terms( $post_id, Taxonomies::TAG, $payload->tags() );
		} catch ( \Throwable $exception ) {
			wp_delete_post( $post_id, true );
			throw $exception;
		}

		return $post_id;
	}

	public function delete( int $post_id ): void {
		if ( $post_id > 0 ) {
			wp_delete_post( $post_id, true );
		}
	}

	public function mark_import_ownership( int $post_id, string $batch_uuid, int $row_id ): void {
		$this->store_meta( $post_id, self::BATCH_OWNER_META, $batch_uuid );
		$this->store_meta( $post_id, self::ROW_OWNER_META, $row_id );
	}

	public function rollback_owned_draft( int $post_id, string $batch_uuid, int $row_id ): string {
		if ( $post_id < 1 ) {
			return self::ROLLBACK_MISSING;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return self::ROLLBACK_MISSING;
		}
		if ( ContentType::POST_TYPE !== $post->post_type ) {
			return self::ROLLBACK_CONFLICT;
		}

		$stored_batch = (string) get_post_meta( $post_id, self::BATCH_OWNER_META, true );
		$stored_row   = (int) get_post_meta( $post_id, self::ROW_OWNER_META, true );
		if ( $stored_batch !== $batch_uuid || $stored_row !== $row_id ) {
			return self::ROLLBACK_CONFLICT;
		}
		if ( 'trash' === $post->post_status ) {
			return self::ROLLBACK_ALREADY_TRASHED;
		}

		$review_status = (string) get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true );
		if ( 'draft' !== $post->post_status || 'approved' === $review_status ) {
			return self::ROLLBACK_CONFLICT;
		}

		$trashed = wp_trash_post( $post_id );

		return false === $trashed ? self::ROLLBACK_CONFLICT : self::ROLLBACK_TRASHED;
	}

	/** @param mixed $value Exact desired metadata value. */
	private function store_meta( int $post_id, string $key, $value ): void {
		$result = update_post_meta( $post_id, $key, $value );
		if ( false !== $result ) {
			return;
		}

		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			throw new RuntimeException( 'Post meta could not be stored.' );
		}

		$stored = get_post_meta( $post_id, $key, true );
		if ( is_scalar( $stored ) && is_scalar( $value ) && (string) $stored === (string) $value ) {
			return;
		}

		throw new RuntimeException( 'Post meta could not be stored.' );
	}

	/** @param string[] $terms Term names. */
	private function assign_terms( int $post_id, string $taxonomy, array $terms ): void {
		$sanitized = array_values(
			array_filter(
				array_map( 'sanitize_text_field', $terms ),
				static fn ( string $term ): bool => '' !== $term
			)
		);
		$result    = wp_set_object_terms(
			$post_id,
			array_values( array_unique( $sanitized ) ),
			$taxonomy,
			false
		);
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
	}
}
