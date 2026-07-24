<?php

namespace Tazkar\Import;

interface ImportRepository {
	/**
	 * @return array{id:int,uuid:string,repeated_source:bool}
	 */
	public function create_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by
	): array;

	public function find_prior_post( string $record_id, string $payload_hash ): int;

	public function begin_row(): void;

	public function commit_row(): void;

	public function rollback_row(): void;

	public function record_row(
		int $batch_id,
		int $row_number,
		string $record_id,
		string $status,
		string $error_code,
		int $post_id,
		string $payload_hash
	): void;

	public function finish_batch( int $batch_id, string $status, int $processed_rows ): void;
}
