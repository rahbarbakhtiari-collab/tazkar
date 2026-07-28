<?php

namespace Tazkar\Import;

interface ChunkedImportRepository {
	/**
	 * @return array{id:int,uuid:string,repeated_source:bool}
	 */
	public function create_staged_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by,
		int $chunk_size
	): array;

	public function stage_row(
		int $batch_id,
		int $row_number,
		string $record_id,
		string $status,
		string $error_code,
		int $post_id,
		string $payload_hash,
		?string $staged_payload
	): void;

	/** @return array<string, mixed>|null */
	public function get_owned_batch( string $batch_uuid, int $user_id ): ?array;

	public function acquire_user_import_lock( int $user_id ): bool;

	public function release_user_import_lock( int $user_id ): void;

	public function has_active_batch( int $user_id ): bool;

	public function acquire_lock( int $batch_id, string $token, string $expires_at ): bool;

	public function release_lock( int $batch_id, string $token ): void;

	public function recover_processing_rows( int $batch_id ): void;

	/** @return array<int, array<string, mixed>> */
	public function pending_rows( int $batch_id, int $limit ): array;

	public function mark_row_processing( int $row_id, string $claimed_at ): bool;

	public function find_prior_post( string $record_id, string $payload_hash ): int;

	public function begin_row(): void;

	public function commit_row(): void;

	public function rollback_row(): void;

	public function finish_row(
		int $row_id,
		string $status,
		string $error_code,
		int $post_id,
		string $terminal_at
	): bool;

	/** @return array<string, int> */
	public function refresh_batch_counts( int $batch_id ): array;

	public function set_batch_state( int $batch_id, string $status, ?string $completed_at = null ): void;

	/** @return array<int, array<string, mixed>> */
	public function rows( int $batch_id ): array;

	/** @return array<int, array<string, mixed>> */
	public function rollback_candidates( int $batch_id, int $limit ): array;

	public function cancel_pending_row( int $row_id, string $terminal_at ): bool;

	public function finish_rollback_row(
		int $row_id,
		string $row_status,
		string $rollback_status,
		string $rollback_error_code,
		string $terminal_at
	): bool;

	public function start_rollback( int $batch_id, string $started_at ): void;

	public function finish_rollback( int $batch_id, string $status, string $finished_at ): void;
}
