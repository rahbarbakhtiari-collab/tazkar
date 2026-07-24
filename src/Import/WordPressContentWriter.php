<?php

namespace Tazkar\Import;

use RuntimeException;
use Tazkar\Content\Taxonomies;

final class WordPressContentWriter implements ContentWriter {
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
