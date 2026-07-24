<?php

namespace Tazkar\Import;

use RuntimeException;
use Tazkar\Infrastructure\Database\Schema;

final class WordPressImportRepository implements ImportRepository {
	/** @return array{id:int,uuid:string,repeated_source:bool} */
	public function create_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by
	): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables   = Schema::table_names( $wpdb->prefix );
		$repeated = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE source_checksum = %s AND status IN ('completed','completed_with_errors') LIMIT 1",
				$tables['import_batches'],
				$checksum
			)
		);
		$uuid     = wp_generate_uuid4();
		$now      = current_time( 'mysql', true );
		$ok       = $wpdb->insert(
			$tables['import_batches'],
			array(
				'batch_uuid'      => $uuid,
				'source_name'     => $filename,
				'source_checksum' => $checksum,
				'schema_version'  => $schema_version,
				'status'          => 'processing',
				'total_rows'      => $total_rows,
				'processed_rows'  => 0,
				'created_by'      => $created_by,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import batch could not be created.' );
		}

		return array(
			'id'              => (int) $wpdb->insert_id,
			'uuid'            => $uuid,
			'repeated_source' => $repeated,
		);
	}

	public function find_prior_post( string $record_id, string $payload_hash ): int {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables = Schema::table_names( $wpdb->prefix );
		$value  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT wp_post_id FROM %i WHERE status = 'imported' AND wp_post_id > 0 AND (record_id = %s OR payload_hash = %s) ORDER BY id DESC LIMIT 1",
				$tables['import_rows'],
				$record_id,
				$payload_hash
			)
		);

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

	public function record_row(
		int $batch_id,
		int $row_number,
		string $record_id,
		string $status,
		string $error_code,
		int $post_id,
		string $payload_hash
	): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables = Schema::table_names( $wpdb->prefix );
		$now    = current_time( 'mysql', true );
		$ok     = $wpdb->insert(
			$tables['import_rows'],
			array(
				'batch_id'     => $batch_id,
				'row_number'   => $row_number,
				'record_id'    => $record_id,
				'status'       => $status,
				'error_code'   => $error_code,
				'wp_post_id'   => $post_id,
				'payload_hash' => $payload_hash,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import row could not be recorded.' );
		}
	}

	public function finish_batch( int $batch_id, string $status, int $processed_rows ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$tables = Schema::table_names( $wpdb->prefix );
		$ok     = $wpdb->update(
			$tables['import_batches'],
			array(
				'status'         => $status,
				'processed_rows' => $processed_rows,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => $batch_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			throw new RuntimeException( 'Import batch could not be finalized.' );
		}
	}
}
