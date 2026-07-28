<?php

namespace Tazkar\Import;

use RuntimeException;
use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Content\Taxonomies;
use Tazkar\Infrastructure\Database\Schema;

final class WordPressContentWriter implements RollbackAwareContentWriter {
	private const BATCH_OWNER_META = '_tazkar_import_batch_uuid';
	private const ROW_OWNER_META   = '_tazkar_import_row_id';
	private const SNAPSHOT_META    = '_tazkar_import_snapshot_hash';

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
		$this->store_meta( $post_id, self::SNAPSHOT_META, $this->snapshot_hash( $post_id ) );
	}

	/** @return array{outcome:string,interactions:int} */
	public function inspect_rollback( int $post_id, string $batch_uuid, int $row_id ): array {
		if ( $post_id < 1 ) {
			return array(
				'outcome'      => self::ROLLBACK_MISSING,
				'interactions' => 0,
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'outcome'      => self::ROLLBACK_MISSING,
				'interactions' => 0,
			);
		}

		$interactions = $this->interaction_count( $post_id );
		if ( ContentType::POST_TYPE !== $post->post_type ) {
			return array(
				'outcome'      => self::ROLLBACK_FOREIGN,
				'interactions' => $interactions,
			);
		}

		$stored_batch = (string) get_post_meta( $post_id, self::BATCH_OWNER_META, true );
		$stored_row   = (int) get_post_meta( $post_id, self::ROW_OWNER_META, true );
		if ( $stored_batch !== $batch_uuid || $stored_row !== $row_id ) {
			return array(
				'outcome'      => self::ROLLBACK_FOREIGN,
				'interactions' => $interactions,
			);
		}
		if ( 'trash' === $post->post_status ) {
			return array(
				'outcome'      => self::ROLLBACK_ALREADY_TRASHED,
				'interactions' => $interactions,
			);
		}
		if ( 'publish' === $post->post_status ) {
			return array(
				'outcome'      => self::ROLLBACK_PUBLISHED,
				'interactions' => $interactions,
			);
		}
		if ( 'draft' !== $post->post_status ) {
			return array(
				'outcome'      => self::ROLLBACK_CONFLICT,
				'interactions' => $interactions,
			);
		}

		$review_status = (string) get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true );
		if ( 'approved' === $review_status ) {
			return array(
				'outcome'      => self::ROLLBACK_APPROVED,
				'interactions' => $interactions,
			);
		}

		$stored_snapshot = (string) get_post_meta( $post_id, self::SNAPSHOT_META, true );
		if ( '' === $stored_snapshot || ! hash_equals( $stored_snapshot, $this->snapshot_hash( $post_id ) ) ) {
			return array(
				'outcome'      => self::ROLLBACK_EDITED,
				'interactions' => $interactions,
			);
		}

		return array(
			'outcome'      => self::ROLLBACK_ELIGIBLE,
			'interactions' => $interactions,
		);
	}

	public function rollback_owned_draft( int $post_id, string $batch_uuid, int $row_id ): string {
		$inspection = $this->inspect_rollback( $post_id, $batch_uuid, $row_id );
		$outcome    = $inspection['outcome'];
		if ( self::ROLLBACK_ALREADY_TRASHED === $outcome ) {
			return self::ROLLBACK_ALREADY_TRASHED;
		}
		if ( self::ROLLBACK_MISSING === $outcome ) {
			return self::ROLLBACK_MISSING;
		}
		if ( self::ROLLBACK_ELIGIBLE !== $outcome ) {
			return self::ROLLBACK_CONFLICT;
		}

		$trashed = wp_trash_post( $post_id );

		return false === $trashed ? self::ROLLBACK_CONFLICT : self::ROLLBACK_TRASHED;
	}


	private function interaction_count( int $post_id ): int {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables    = Schema::table_names( $wpdb->prefix );
		$feedback  = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE item_id = %d', $tables['feedback'], $post_id )
		);
		$favorites = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE item_id = %d', $tables['favorites'], $post_id )
		);

		return max( 0, (int) get_comments_number( $post_id ) )
			+ max( 0, (int) $feedback )
			+ max( 0, (int) $favorites );
	}

	private function snapshot_hash( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$meta = get_post_meta( $post_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		unset( $meta[ self::SNAPSHOT_META ] );
		ksort( $meta );

		$topics = wp_get_object_terms( $post_id, Taxonomies::TOPIC, array( 'fields' => 'names' ) );
		$tags   = wp_get_object_terms( $post_id, Taxonomies::TAG, array( 'fields' => 'names' ) );
		if ( is_wp_error( $topics ) || is_wp_error( $tags ) ) {
			return '';
		}
		$topics = array_map( 'strval', is_array( $topics ) ? $topics : array() );
		$tags   = array_map( 'strval', is_array( $tags ) ? $tags : array() );
		sort( $topics );
		sort( $tags );

		$state = array(
			'post'   => array(
				'author'       => (int) $post->post_author,
				'content'      => (string) $post->post_content,
				'excerpt'      => (string) $post->post_excerpt,
				'modified_gmt' => (string) $post->post_modified_gmt,
				'name'         => (string) $post->post_name,
				'status'       => (string) $post->post_status,
				'title'        => (string) $post->post_title,
				'type'         => (string) $post->post_type,
			),
			'meta'   => $meta,
			'topics' => $topics,
			'tags'   => $tags,
		);
		$json  = wp_json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return hash( 'sha256', is_string( $json ) ? $json : serialize( $state ) );
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
