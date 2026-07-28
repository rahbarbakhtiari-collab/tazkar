<?php

namespace Tazkar\Import;

use JsonException;
use RuntimeException;
use Tazkar\Validation\BatchValidator;
use Tazkar\Validation\ContractSchema;
use Tazkar\Validation\IssueCode;
use Tazkar\Validation\ValidationIssue;
use Tazkar\Validation\ValidationResult;

final class ChunkedImportService {
	public const DEFAULT_CHUNK_SIZE = 25;
	public const MAX_CHUNK_SIZE     = 100;
	private const LOCK_SECONDS      = 120;
	private const MAX_CHUNK_SECONDS = 20.0;

	public function __construct(
		private readonly CsvReader $reader = new CsvReader(),
		private readonly BatchValidator $validator = new BatchValidator(),
		private readonly ChunkedImportRepository $repository = new WordPressChunkedImportRepository(),
		private readonly RollbackAwareContentWriter $content_writer = new WordPressContentWriter()
	) {}

	public function stage(
		string $path,
		string $filename,
		int $user_id,
		int $chunk_size = self::DEFAULT_CHUNK_SIZE
	): ChunkedImportProgress {
		$document   = $this->reader->read( $path );
		$mapped     = array_map(
			static fn ( CsvParsedRow $row ): array => $row->mapped(),
			$document->rows()
		);
		$validation = $this->validator->validate( $document->headers(), $mapped );

		foreach ( $document->rows() as $index => $parsed_row ) {
			if ( ! isset( $validation->rows()[ $index ] ) ) {
				continue;
			}
			foreach ( $parsed_row->issues() as $issue ) {
				$validation->rows()[ $index ]->add( $issue );
			}
		}

		$checksum = hash_file( 'sha256', $path );
		if ( false === $checksum ) {
			throw new CsvReadException(
				CsvIssueCode::FILE_NOT_READABLE,
				'CSV checksum could not be calculated.'
			);
		}

		$schema_version = isset( $mapped[0]['schema_version'] ) ? (string) $mapped[0]['schema_version'] : '';
		$batch          = array();
		$seen_records   = array();
		$seen_payloads  = array();
		$headers_valid  = $validation->headers()->is_valid();

		if ( ! $this->repository->acquire_user_import_lock( $user_id ) ) {
			throw new RuntimeException( 'Another import is currently being prepared for this user.' );
		}

		try {
			$this->repository->begin_row();
			try {
				if ( $this->repository->has_active_batch( $user_id ) ) {
					throw new RuntimeException( 'Finish or roll back the active import before staging another file.' );
				}

				$batch = $this->repository->create_staged_batch(
					$this->safe_filename( $filename ),
					$checksum,
					$schema_version,
					count( $mapped ),
					$user_id,
					$this->bounded_chunk_size( $chunk_size )
				);

				foreach ( $validation->rows() as $index => $row_result ) {
					$row          = $row_result->row();
					$row_number   = $row_result->row_number() ?? $index + 2;
					$record_id    = is_scalar( $row['record_id'] ?? null ) ? (string) $row['record_id'] : '';
					$payload_hash = $this->payload_hash( $row );
					$status       = 'pending';
					$error_code   = '';
					$post_id      = 0;
					$payload      = null;

					if ( '' !== $record_id && isset( $seen_records[ $record_id ] ) ) {
						$status     = 'skipped';
						$error_code = CsvImportIssueCode::DUPLICATE_BATCH_RECORD;
					} elseif ( isset( $seen_payloads[ $payload_hash ] ) ) {
						$status     = 'skipped';
						$error_code = CsvImportIssueCode::DUPLICATE_BATCH_PAYLOAD;
					} else {
						if ( '' !== $record_id ) {
							$seen_records[ $record_id ] = true;
						}
						$seen_payloads[ $payload_hash ] = true;

						if ( ! $headers_valid || $this->has_blocking_errors( $row_result ) ) {
							$status     = 'invalid';
							$error_code = $headers_valid
							? $this->first_error_code( $row_result )
							: CsvImportIssueCode::HEADER_INVALID;
						} elseif ( $this->contains_html( $row ) ) {
							$status     = 'invalid';
							$error_code = CsvImportIssueCode::ROW_INVALID;
						} elseif ( 'approved' === (string) ( $row['review_status'] ?? '' ) ) {
							$status     = 'skipped';
							$error_code = CsvImportIssueCode::SOURCE_APPROVED;
						} else {
							$post_id = $this->repository->find_prior_post( $record_id, $payload_hash );
							if ( $post_id > 0 ) {
								$status     = 'skipped';
								$error_code = CsvImportIssueCode::DUPLICATE_PRIOR;
							} else {
								$payload = $this->encode_staged_payload( $row );
							}
						}
					}

					$this->repository->stage_row(
						(int) $batch['id'],
						$row_number,
						$record_id,
						$status,
						$error_code,
						$post_id,
						$payload_hash,
						$payload
					);
				}

				$this->synchronize_batch( (int) $batch['id'], 'staged' );
				$this->repository->commit_row();
			} catch ( \Throwable $exception ) {
				$this->repository->rollback_row();
				throw $exception;
			}
		} finally {
			$this->repository->release_user_import_lock( $user_id );
		}

		return $this->progress( (string) $batch['uuid'], $user_id );
	}

	public function process( string $batch_uuid, int $user_id ): ChunkedImportProgress {
		$batch = $this->require_batch( $batch_uuid, $user_id );
		if ( ! in_array( (string) $batch['status'], array( 'staged', 'processing' ), true ) ) {
			return $this->progress( $batch_uuid, $user_id );
		}

		$token = bin2hex( random_bytes( 32 ) );
		if ( ! $this->repository->acquire_lock( (int) $batch['id'], $token, $this->future_time( self::LOCK_SECONDS ) ) ) {
			return $this->progress( $batch_uuid, $user_id );
		}

		try {
			$batch = $this->require_batch( $batch_uuid, $user_id );
			if ( ! in_array( (string) $batch['status'], array( 'staged', 'processing' ), true ) ) {
				return $this->progress( $batch_uuid, $user_id );
			}

			$this->repository->recover_processing_rows( (int) $batch['id'] );
			$rows = $this->repository->pending_rows(
				(int) $batch['id'],
				$this->bounded_chunk_size( (int) ( $batch['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE ) )
			);

			$started_at = microtime( true );
			$processed  = 0;
			foreach ( $rows as $row ) {
				if ( $processed > 0 && microtime( true ) - $started_at >= self::MAX_CHUNK_SECONDS ) {
					break;
				}
				$this->process_row( $batch_uuid, $row, $user_id );
				++$processed;
			}

			$this->synchronize_batch( (int) $batch['id'], 'processing' );
		} finally {
			$this->repository->release_lock( (int) $batch['id'], $token );
		}

		return $this->progress( $batch_uuid, $user_id );
	}

	public function resume( string $batch_uuid, int $user_id ): ChunkedImportProgress {
		return $this->process( $batch_uuid, $user_id );
	}


	/** @return array<string, int> */
	public function rollback_preview( string $batch_uuid, int $user_id ): array {
		$batch  = $this->require_batch( $batch_uuid, $user_id );
		$counts = array(
			'total_candidates' => 0,
			'pending'          => 0,
			'eligible_drafts'  => 0,
			'published'        => 0,
			'approved'         => 0,
			'edited'           => 0,
			'missing'          => 0,
			'foreign'          => 0,
			'other_conflicts'  => 0,
			'already_trashed'  => 0,
			'interactions'     => 0,
		);

		foreach ( $this->repository->rows( (int) $batch['id'] ) as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( in_array( $status, array( 'pending', 'processing' ), true ) ) {
				++$counts['total_candidates'];
				++$counts['pending'];
				continue;
			}
			if ( 'imported' !== $status ) {
				continue;
			}

			++$counts['total_candidates'];
			$inspection              = $this->content_writer->inspect_rollback(
				(int) ( $row['wp_post_id'] ?? 0 ),
				$batch_uuid,
				(int) ( $row['id'] ?? 0 )
			);
			$counts['interactions'] += max( 0, (int) ( $inspection['interactions'] ?? 0 ) );

			switch ( (string) ( $inspection['outcome'] ?? '' ) ) {
				case RollbackAwareContentWriter::ROLLBACK_ELIGIBLE:
					++$counts['eligible_drafts'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_PUBLISHED:
					++$counts['published'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_APPROVED:
					++$counts['approved'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_EDITED:
					++$counts['edited'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_MISSING:
					++$counts['missing'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_FOREIGN:
					++$counts['foreign'];
					break;
				case RollbackAwareContentWriter::ROLLBACK_ALREADY_TRASHED:
					++$counts['already_trashed'];
					break;
				default:
					++$counts['other_conflicts'];
					break;
			}
		}

		return $counts;
	}

	public function rollback( string $batch_uuid, int $user_id ): ChunkedImportProgress {
		$batch = $this->require_batch( $batch_uuid, $user_id );
		if ( in_array( (string) $batch['status'], array( 'rolled_back', 'rollback_with_conflicts' ), true ) ) {
			return $this->progress( $batch_uuid, $user_id );
		}

		$token = bin2hex( random_bytes( 32 ) );
		if ( ! $this->repository->acquire_lock( (int) $batch['id'], $token, $this->future_time( self::LOCK_SECONDS ) ) ) {
			return $this->progress( $batch_uuid, $user_id );
		}

		try {
			$now = $this->now();
			$this->repository->recover_processing_rows( (int) $batch['id'] );
			$this->repository->start_rollback( (int) $batch['id'], $now );
			$rows = $this->repository->rollback_candidates(
				(int) $batch['id'],
				$this->bounded_chunk_size( (int) ( $batch['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE ) )
			);

			$started_at = microtime( true );
			$processed  = 0;
			foreach ( $rows as $row ) {
				if ( $processed > 0 && microtime( true ) - $started_at >= self::MAX_CHUNK_SECONDS ) {
					break;
				}
				$this->rollback_row( $batch_uuid, $row );
				++$processed;
			}

			$remaining = $this->repository->rollback_candidates( (int) $batch['id'], 1 );
			$counts    = $this->repository->refresh_batch_counts( (int) $batch['id'] );
			if ( array() !== $remaining ) {
				$this->repository->set_batch_state( (int) $batch['id'], 'rollback_processing' );
			} else {
				$status = ( $counts['rollback_conflicts'] ?? 0 ) > 0
					? 'rollback_with_conflicts'
					: 'rolled_back';
				$this->repository->finish_rollback( (int) $batch['id'], $status, $this->now() );
			}
		} finally {
			$this->repository->release_lock( (int) $batch['id'], $token );
		}

		return $this->progress( $batch_uuid, $user_id );
	}

	public function progress( string $batch_uuid, int $user_id ): ChunkedImportProgress {
		$batch  = $this->require_batch( $batch_uuid, $user_id );
		$counts = $this->repository->refresh_batch_counts( (int) $batch['id'] );
		$batch  = $this->require_batch( $batch_uuid, $user_id );

		return new ChunkedImportProgress(
			(string) $batch['batch_uuid'],
			(bool) ( $batch['repeated_source'] ?? false ),
			(string) $batch['status'],
			$counts,
			$this->repository->rows( (int) $batch['id'] )
		);
	}

	/** @param array<string, mixed> $row Persisted row state. */
	private function process_row( string $batch_uuid, array $row, int $user_id ): void {
		$row_id = (int) ( $row['id'] ?? 0 );
		if ( $row_id < 1 || ! $this->repository->mark_row_processing( $row_id, $this->now() ) ) {
			return;
		}

		try {
			$payload = $this->decode_staged_payload( (string) ( $row['staged_payload'] ?? '' ) );
		} catch ( JsonException $exception ) {
			unset( $exception );
			$this->repository->finish_row(
				$row_id,
				'error',
				CsvImportIssueCode::ROW_WRITE_FAILED,
				0,
				$this->now()
			);
			return;
		}

		$record_id    = (string) ( $row['record_id'] ?? '' );
		$payload_hash = (string) ( $row['payload_hash'] ?? '' );
		$prior_post   = $this->repository->find_prior_post( $record_id, $payload_hash );
		if ( $prior_post > 0 ) {
			$this->repository->finish_row(
				$row_id,
				'skipped',
				CsvImportIssueCode::DUPLICATE_PRIOR,
				$prior_post,
				$this->now()
			);
			return;
		}

		$post_id = 0;
		try {
			$this->repository->begin_row();
			$post_id = $this->content_writer->create_draft( $payload, $user_id );
			$this->content_writer->mark_import_ownership( $post_id, $batch_uuid, $row_id );
			if ( ! $this->repository->finish_row( $row_id, 'imported', '', $post_id, $this->now() ) ) {
				throw new RuntimeException( 'Import row could not be finalized.' );
			}
			$this->repository->commit_row();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			$this->repository->rollback_row();
			$this->content_writer->delete( $post_id );
			$this->repository->finish_row(
				$row_id,
				'error',
				CsvImportIssueCode::ROW_WRITE_FAILED,
				0,
				$this->now()
			);
		}
	}

	/** @param array<string, mixed> $row Persisted row state. */
	private function rollback_row( string $batch_uuid, array $row ): void {
		$row_id = (int) ( $row['id'] ?? 0 );
		$status = (string) ( $row['status'] ?? '' );
		if ( $row_id < 1 ) {
			return;
		}

		if ( in_array( $status, array( 'pending', 'processing' ), true ) ) {
			$this->repository->cancel_pending_row( $row_id, $this->now() );
			return;
		}
		if ( 'imported' !== $status ) {
			return;
		}

		$outcome = $this->content_writer->rollback_owned_draft(
			(int) ( $row['wp_post_id'] ?? 0 ),
			$batch_uuid,
			$row_id
		);
		if ( in_array(
			$outcome,
			array(
				RollbackAwareContentWriter::ROLLBACK_TRASHED,
				RollbackAwareContentWriter::ROLLBACK_ALREADY_TRASHED,
			),
			true
		) ) {
			$this->repository->finish_rollback_row( $row_id, 'rolled_back', 'trashed', '', $this->now() );
			return;
		}

		$error_code = RollbackAwareContentWriter::ROLLBACK_MISSING === $outcome
			? 'rollback_post_missing'
			: 'rollback_post_conflict';
		$this->repository->finish_rollback_row( $row_id, 'imported', 'conflict', $error_code, $this->now() );
	}

	private function synchronize_batch( int $batch_id, string $active_status ): void {
		$counts = $this->repository->refresh_batch_counts( $batch_id );
		if ( 0 === ( $counts['total'] ?? 0 ) ) {
			$this->repository->set_batch_state( $batch_id, 'failed', $this->now() );
			return;
		}
		if ( ( $counts['pending'] ?? 0 ) > 0 ) {
			$this->repository->set_batch_state( $batch_id, $active_status );
			return;
		}

		$status = ( $counts['error'] ?? 0 ) > 0 || ( $counts['invalid'] ?? 0 ) > 0
			? 'completed_with_errors'
			: 'completed';
		$this->repository->set_batch_state( $batch_id, $status, $this->now() );
	}

	/** @return array<string, mixed> */
	private function require_batch( string $batch_uuid, int $user_id ): array {
		$batch = $this->repository->get_owned_batch( $batch_uuid, $user_id );
		if ( null === $batch ) {
			throw new RuntimeException( 'Import batch was not found or is not owned by the current user.' );
		}

		return $batch;
	}

	/** @param array<string, mixed> $row Validated source row. */
	private function encode_staged_payload( array $row ): string {
		$allowed = array_fill_keys( ContractSchema::columns(), true );
		$payload = array_intersect_key( $row, $allowed );
		unset( $payload['wp_post_id'], $payload['import_status'], $payload['import_error'] );

		return json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);
	}

	/** @return array<string, mixed> */
	private function decode_staged_payload( string $payload ): array {
		$decoded = json_decode( $payload, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $decoded ) ) {
			throw new JsonException( 'Staged import payload is invalid.' );
		}

		return $decoded;
	}

	/** @param array<string, mixed> $row Exact validated row. */
	private function payload_hash( array $row ): string {
		ksort( $row );
		$json = json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return hash( 'sha256', is_string( $json ) ? $json : serialize( $row ) );
	}

	private function has_blocking_errors( ValidationResult $result ): bool {
		foreach ( $result->errors() as $issue ) {
			if ( IssueCode::DUPLICATE_RECORD_ID !== $issue->code() ) {
				return true;
			}
		}

		return false;
	}

	private function first_error_code( ValidationResult $result ): string {
		foreach ( $result->errors() as $issue ) {
			if ( $issue instanceof ValidationIssue && IssueCode::DUPLICATE_RECORD_ID !== $issue->code() ) {
				return $issue->code();
			}
		}

		return CsvImportIssueCode::ROW_INVALID;
	}

	/** @param array<string, mixed> $row Validated source row. */
	private function contains_html( array $row ): bool {
		foreach ( $row as $value ) {
			if ( is_scalar( $value ) && 1 === preg_match( '/<[^>]+>/u', (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	private function bounded_chunk_size( int $chunk_size ): int {
		return max( 1, min( self::MAX_CHUNK_SIZE, $chunk_size ) );
	}

	private function safe_filename( string $filename ): string {
		$filename = str_replace( array( "\0", '\\' ), array( '', '/' ), $filename );
		$filename = basename( $filename );
		$filename = preg_replace( '/[\x00-\x1F\x7F]+/u', '', $filename );

		return is_string( $filename ) && '' !== $filename ? $filename : 'import.csv';
	}

	private function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	private function future_time( int $seconds ): string {
		return gmdate( 'Y-m-d H:i:s', time() + $seconds );
	}
}
