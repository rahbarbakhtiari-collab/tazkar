<?php

namespace Tazkar\Import;

use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;

final class DraftItemPayloadBuilder {
	/**
	 * Build a server-owned draft payload. CSV system fields never control publication,
	 * authorship, dates, post IDs, approval, or import status.
	 *
	 * @param array<string, mixed> $row Validated source row.
	 */
	public function build( array $row, int $author_id ): DraftItemPayload {
		$title = is_scalar( $row['generated_title'] ?? null ) ? (string) $row['generated_title'] : '';
		$slug  = is_scalar( $row['suggested_slug'] ?? null ) ? (string) $row['suggested_slug'] : '';

		$post_data = array(
			'post_type'    => ContentType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => '',
			'post_author'  => max( 0, $author_id ),
		);
		if ( '' !== trim( $slug ) ) {
			$post_data['post_name'] = $slug;
		}

		$meta = array();
		foreach ( array_keys( MetaRegistry::definitions() ) as $field ) {
			if ( in_array( $field, array( 'review_status', 'import_status' ), true ) ) {
				continue;
			}
			if ( array_key_exists( $field, $row ) && is_scalar( $row[ $field ] ) ) {
				$meta[ MetaRegistry::key( $field ) ] = $row[ $field ];
			}
		}

		$meta[ MetaRegistry::key( 'review_status' ) ] = $this->safe_review_status( $row['review_status'] ?? null );
		$meta[ MetaRegistry::key( 'import_status' ) ] = 'imported';
		$arabic = is_string( $row['arabic_text'] ?? null ) ? $row['arabic_text'] : '';
		$meta[ MetaRegistry::ARABIC_HASH_KEY ] = hash( 'sha256', $arabic );

		$topics = $this->unique_terms(
			array_merge(
				$this->split_terms( $row['primary_topic'] ?? null ),
				$this->split_terms( $row['secondary_topics'] ?? null )
			)
		);
		$tags = $this->unique_terms( $this->split_terms( $row['tags'] ?? null ) );

		return new DraftItemPayload( $post_data, $meta, $topics, $tags );
	}

	/** @param mixed $value Raw review status. */
	private function safe_review_status( $value ): string {
		$status = is_scalar( $value ) ? (string) $value : '';
		if ( in_array( $status, array( 'rejected', 'needs_recheck', 'needs_review' ), true ) ) {
			return $status;
		}

		return 'needs_review';
	}

	/**
	 * @param mixed $value Raw pipe-separated value.
	 * @return string[]
	 */
	private function split_terms( $value ): array {
		if ( ! is_scalar( $value ) ) {
			return array();
		}

		$terms = array_map( 'trim', explode( '|', (string) $value ) );

		return array_values(
			array_filter(
				$terms,
				static fn ( string $term ): bool => '' !== $term
			)
		);
	}

	/**
	 * @param string[] $terms Term names.
	 * @return string[]
	 */
	private function unique_terms( array $terms ): array {
		$unique = array();
		foreach ( $terms as $term ) {
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			if ( ! array_key_exists( $key, $unique ) ) {
				$unique[ $key ] = $term;
			}
		}

		return array_values( $unique );
	}
}
