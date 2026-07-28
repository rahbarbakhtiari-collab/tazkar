<?php

namespace Tazkar\Import;

use RuntimeException;
use Tazkar\Infrastructure\Database\Schema;

final class WordPressChunkedImportRepository implements ChunkedImportRepository {
	/** @return array{id:int,uuid:string,repeated_source:bool} */
	public function create_staged_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by,
		int $chunk_size
	): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table    = Schema::table_names( $wpdb->prefix )['import_batches'];
		$repeated = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE source_checksum = %s AND status IN ('completed','completed_with_errors') LIMIT 1",
				$table,
				$checksum
			)
		);
		$uuid     = wp_generate_uuid4();
		$now      = current_time( 'mysql', true );
		$ok       = $wpdb->insert(
			$table,
			array(
				'batch_uuid'      => $uuid,
				'source_name'     => $filename,
				'source_checksum' => $checksum,
				'schema_version'  => $schema_version,
				'status'          => 'staged',
				'total_rows'      => $total_rows,
				'pending_rows'    => $total_rows,
				'chunk_size'      => $chunk_size,
				'created_by'      => $created_by,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import batch could not be staged.' );
		}

		return array(
			'id'              => (int) $wpdb->insert_id,
			'uuid'            => $uuid,
			'repeated_source' => $repeated,
		);
	}

	public function stage_row(
		int $batch_id,
		int $row_number,
		string $record_id,
		string $status,
		string $error_code,
		int $post_id,
		string $payload_hash,
		?string $staged_payload
	): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table    = Schema::table_names( $wpdb->prefix )['import_rows'];
		$now      = current_time( 'mysql', true );
		$terminal = 'pending' === $status ? null : $now;
		$removed  = null === $staged_payload ? $now : null;
		$ok       = $wpdb->insert(
			$table,
			array(
				'batch_id'          => $batch_id,
				'row_number'        => $row_number,
				'record_id'         => $record_id,
				'status'            => $status,
				'error_code'        => $error_code,
				'wp_post_id'        => $post_id,
				'payload_hash'      => $payload_hash,
				'staged_payload'    => $staged_payload,
				'terminal_at'       => $terminal,
				'payload_removed_at' => $removed,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Staged import row could not be stored.' );
		}
	}

	/** @return array<string, mixed>|null */
	public function get_owned_batch( string $batch_uuid, int $user_id ): ?array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_batches'];
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT current_batch.*,
				EXISTS(
					SELECT 1 FROM %i prior_batch
					WHERE prior_batch.source_checksum = current_batch.source_checksum
					AND prior_batch.id <> current_batch.id
					AND prior_batch.status IN ('completed','completed_with_errors')
				) AS repeated_source
				FROM %i current_batch
				WHERE current_batch.batch_uuid = %s AND current_batch.created_by = %d
				LIMIT 1",
				$table,
				$table,
				$batch_uuid,
				$user_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public function acquire_lock( int $batch_id, string $token, string $expires_at ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_batches'];
		$now   = current_time( 'mysql', true );
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET lock_token = %s, lock_expires_at = %s, updated_at = %s
				WHERE id = %d
				AND status NOT IN ('rolled_back','rollback_with_conflicts')
				AND (lock_token = '' OR lock_expires_at IS NULL OR lock_expires_at < %s)",
				$table,
				$token,
				$expires_at,
				$now,
				$batch_id,
				$now
			)
		);

		return 1 === $count;
	}

	public function release_lock( int $batch_id, string $token ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_batches'];
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET lock_token = '', lock_expires_at = NULL, updated_at = %s WHERE id = %d AND lock_token = %s",
				$table,
				current_time( 'mysql', true ),
				$batch_id,
				$token
			)
		);
	}

	public function recover_processing_rows( int $batch_id ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'pending', claimed_at = NULL, updated_at = %s WHERE batch_id = %d AND status = 'processing'",
				$table,
				current_time( 'mysql', true ),
				$batch_id
			)
		);
	}

	/** @return array<int, array<string, mixed>> */
	public function pending_rows( int $batch_id, int $limit ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, batch_id, row_number, record_id, status, payload_hash, staged_payload, wp_post_id
				FROM %i WHERE batch_id = %d AND status = 'pending'
				ORDER BY row_number ASC LIMIT %d",
				$table,
				$batch_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	public function mark_row_processing( int $row_id, string $claimed_at ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = 'processing', attempt_count = attempt_count + 1, claimed_at = %s, updated_at = %s
				WHERE id = %d AND status = 'pending'",
				$table,
				$claimed_at,
				$claimed_at,
				$row_id
			)
		);

		return 1 === $count;
	}

	public function find_prior_post( string $record_id, string $payload_hash ): int {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		if ( '' === $record_id ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT wp_post_id FROM %i WHERE status = 'imported' AND wp_post_id > 0 AND payload_hash = %s ORDER BY id DESC LIMIT 1",
					$table,
					$payload_hash
				)
			);
		} else {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT wp_post_id FROM %i WHERE status = 'imported' AND wp_post_id > 0 AND (record_id = %s OR payload_hash = %s) ORDER BY id DESC LIMIT 1",
					$table,
					$record_id,
					$payload_hash
				)
			);
		}

		return is_numeric( $value ) ? (int) $value : 0;
	}

	public function begin_row(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$wpdb->query( 'START TRANSACTION' );
	}

	public function commit_row(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$wpdb->query( 'COMMIT' );
	}

	public function rollback_row(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$wpdb->query( 'ROLLBACK' );
	}

	public function finish_row(
		int $row_id,
		string $status,
		string $error_code,
		int $post_id,
		string $terminal_at
	): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = %s, error_code = %s, wp_post_id = %d,
				staged_payload = NULL, payload_removed_at = %s, terminal_at = %s,
				claimed_at = NULL, updated_at = %s
				WHERE id = %d AND status IN ('pending','processing')",
				$table,
				$status,
				$error_code,
				$post_id,
				$terminal_at,
				$terminal_at,
				$terminal_at,
				$row_id
			)
		);

		return 1 === $count;
	}

	/** @return array<string, int> */
	public function refresh_batch_counts( int $batch_id ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables = Schema::table_names( $wpdb->prefix );
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN status NOT IN ('pending','processing') THEN 1 ELSE 0 END) AS processed,
				SUM(CASE WHEN status = 'imported' THEN 1 ELSE 0 END) AS imported,
				SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped,
				SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) AS invalid,
				SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS error,
				SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN rollback_status = 'conflict' THEN 1 ELSE 0 END) AS rollback_conflicts,
				COALESCE(MIN(CASE WHEN status IN ('pending','processing') THEN row_number END), 0) AS next_row
				FROM %i WHERE batch_id = %d",
				$tables['import_rows'],
				$batch_id
			),
			ARRAY_A
		);
		$counts = array(
			'total'              => (int) ( $row['total'] ?? 0 ),
			'processed'          => (int) ( $row['processed'] ?? 0 ),
			'imported'           => (int) ( $row['imported'] ?? 0 ),
			'skipped'            => (int) ( $row['skipped'] ?? 0 ),
			'invalid'            => (int) ( $row['invalid'] ?? 0 ),
			'error'              => (int) ( $row['error'] ?? 0 ),
			'pending'            => (int) ( $row['pending'] ?? 0 ),
			'rollback_conflicts' => (int) ( $row['rollback_conflicts'] ?? 0 ),
			'next_row'           => (int) ( $row['next_row'] ?? 0 ),
		);
		$next   = $counts['next_row'] > 0 ? $counts['next_row'] : $counts['total'] + 1;
		$ok     = $wpdb->update(
			$tables['import_batches'],
			array(
				'total_rows'         => $counts['total'],
				'processed_rows'     => $counts['processed'],
				'imported_rows'      => $counts['imported'],
				'skipped_rows'       => $counts['skipped'],
				'invalid_rows'       => $counts['invalid'],
				'error_rows'         => $counts['error'],
				'pending_rows'       => $counts['pending'],
				'rollback_conflicts' => $counts['rollback_conflicts'],
				'next_row_number'    => $next,
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => $batch_id ),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import batch counters could not be refreshed.' );
		}

		return $counts;
	}

	public function set_batch_state( int $batch_id, string $status, ?string $completed_at = null ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table   = Schema::table_names( $wpdb->prefix )['import_batches'];
		$data    = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s', '%s' );
		if ( null !== $completed_at ) {
			$data['completed_at'] = $completed_at;
			$formats[]            = '%s';
		}
		if ( false === $wpdb->update( $table, $data, array( 'id' => $batch_id ), $formats, array( '%d' ) ) ) {
			throw new RuntimeException( 'Import batch state could not be updated.' );
		}
	}

	/** @return array<int, array<string, mixed>> */
	public function rows( int $batch_id ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, row_number, record_id, status, error_code, wp_post_id, payload_hash,
				attempt_count, rollback_status, rollback_error_code, terminal_at, payload_removed_at
				FROM %i WHERE batch_id = %d ORDER BY row_number ASC",
				$table,
				$batch_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public function rollback_candidates( int $batch_id, int $limit ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, row_number, record_id, status, wp_post_id, rollback_status
				FROM %i
				WHERE batch_id = %d AND status IN ('pending','processing','imported') AND rollback_status = ''
				ORDER BY row_number ASC LIMIT %d",
				$table,
				$batch_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	public function cancel_pending_row( int $row_id, string $terminal_at ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = 'cancelled', staged_payload = NULL, payload_removed_at = %s,
				terminal_at = %s, rollback_status = 'cancelled', updated_at = %s
				WHERE id = %d AND status IN ('pending','processing') AND rollback_status = ''",
				$table,
				$terminal_at,
				$terminal_at,
				$terminal_at,
				$row_id
			)
		);

		return 1 === $count;
	}

	public function finish_rollback_row(
		int $row_id,
		string $row_status,
		string $rollback_status,
		string $rollback_error_code,
		string $terminal_at
	): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_rows'];
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = %s, rollback_status = %s, rollback_error_code = %s,
				staged_payload = NULL, payload_removed_at = COALESCE(payload_removed_at, %s),
				terminal_at = COALESCE(terminal_at, %s), updated_at = %s
				WHERE id = %d AND status = 'imported' AND rollback_status = ''",
				$table,
				$row_status,
				$rollback_status,
				$rollback_error_code,
				$terminal_at,
				$terminal_at,
				$terminal_at,
				$row_id
			)
		);

		return 1 === $count;
	}

	public function start_rollback( int $batch_id, string $started_at ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_batches'];
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'rollback_processing',
				rollback_started_at = COALESCE(rollback_started_at, %s), updated_at = %s
				WHERE id = %d AND status NOT IN ('rolled_back','rollback_with_conflicts')",
				$table,
				$started_at,
				$started_at,
				$batch_id
			)
		);
		if ( false === $count ) {
			throw new RuntimeException( 'Import rollback could not be started.' );
		}
	}

	public function finish_rollback( int $batch_id, string $status, string $finished_at ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Schema::table_names( $wpdb->prefix )['import_batches'];
		$ok    = $wpdb->update(
			$table,
			array(
				'status'         => $status,
				'rolled_back_at' => $finished_at,
				'updated_at'     => $finished_at,
			),
			array( 'id' => $batch_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import rollback could not be finalized.' );
		}
	}
}
