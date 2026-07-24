<?php

namespace Tazkar\Import;

use Tazkar\Validation\BatchValidator;
use Tazkar\Validation\IssueCode;
use Tazkar\Validation\ValidationIssue;
use Tazkar\Validation\ValidationResult;

final class CsvImportService {
	public function __construct(
		private readonly CsvReader $reader = new CsvReader(),
		private readonly BatchValidator $validator = new BatchValidator(),
		private readonly ImportRepository $repository = new WordPressImportRepository(),
		private readonly ContentWriter $content_writer = new WordPressContentWriter()
	) {}

	public function import( string $path, string $filename, int $user_id ): CsvImportResult {
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
		$batch          = $this->repository->create_batch(
			$this->safe_filename( $filename ),
			$checksum,
			$schema_version,
			count( $mapped ),
			$user_id
		);

		$results       = array();
		$seen_records  = array();
		$seen_payloads = array();
		$headers_valid = $validation->headers()->is_valid();

		foreach ( $validation->rows() as $index => $row_result ) {
			$row          = $row_result->row();
			$row_number   = $row_result->row_number() ?? $index + 2;
			$record_id    = is_scalar( $row['record_id'] ?? null ) ? (string) $row['record_id'] : '';
			$payload_hash = $this->payload_hash( $row );

			if ( '' !== $record_id && isset( $seen_records[ $record_id ] ) ) {
				$results[] = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'skipped',
					CsvImportIssueCode::DUPLICATE_BATCH_RECORD,
					0,
					$payload_hash
				);
				continue;
			}
			if ( isset( $seen_payloads[ $payload_hash ] ) ) {
				$results[] = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'skipped',
					CsvImportIssueCode::DUPLICATE_BATCH_PAYLOAD,
					0,
					$payload_hash
				);
				continue;
			}
			if ( '' !== $record_id ) {
				$seen_records[ $record_id ] = true;
			}
			$seen_payloads[ $payload_hash ] = true;

			if ( ! $headers_valid || $this->has_blocking_errors( $row_result ) ) {
				$issue_code = $headers_valid
					? $this->first_error_code( $row_result )
					: CsvImportIssueCode::HEADER_INVALID;
				$results[]  = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'invalid',
					$issue_code,
					0,
					$payload_hash
				);
				continue;
			}

			if ( 'approved' === (string) ( $row['review_status'] ?? '' ) ) {
				$results[] = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'skipped',
					CsvImportIssueCode::SOURCE_APPROVED,
					0,
					$payload_hash
				);
				continue;
			}

			$prior_post = $this->repository->find_prior_post( $record_id, $payload_hash );
			if ( $prior_post > 0 ) {
				$results[] = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'skipped',
					CsvImportIssueCode::DUPLICATE_PRIOR,
					$prior_post,
					$payload_hash
				);
				continue;
			}

			$post_id = 0;
			try {
				$this->repository->begin_row();
				$post_id = $this->content_writer->create_draft( $row, $user_id );
				$result  = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'imported',
					'',
					$post_id,
					$payload_hash
				);
				$this->repository->commit_row();
				$results[] = $result;
			} catch ( \Throwable $exception ) {
				unset( $exception );
				$this->repository->rollback_row();
				$this->content_writer->delete( $post_id );
				$results[] = $this->persist_result(
					$batch['id'],
					$row_number,
					$record_id,
					'error',
					CsvImportIssueCode::ROW_WRITE_FAILED,
					0,
					$payload_hash
				);
			}
		}

		$status = $this->batch_status( $results );
		$this->repository->finish_batch( $batch['id'], $status, count( $results ) );

		return new CsvImportResult(
			$batch['uuid'],
			$batch['repeated_source'],
			$status,
			$results
		);
	}

	private function persist_result(
		int $batch_id,
		int $row_number,
		string $record_id,
		string $status,
		string $issue_code,
		int $post_id,
		string $payload_hash
	): CsvImportRowResult {
		$this->repository->record_row(
			$batch_id,
			$row_number,
			$record_id,
			$status,
			$issue_code,
			$post_id,
			$payload_hash
		);

		return new CsvImportRowResult(
			$row_number,
			$record_id,
			$status,
			$issue_code,
			$post_id,
			$payload_hash
		);
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
			if (
				$issue instanceof ValidationIssue
				&& IssueCode::DUPLICATE_RECORD_ID !== $issue->code()
			) {
				return $issue->code();
			}
		}

		return CsvImportIssueCode::ROW_INVALID;
	}

	/** @param CsvImportRowResult[] $results Persisted row results. */
	private function batch_status( array $results ): string {
		if ( array() === $results ) {
			return 'failed';
		}

		$errors  = count(
			array_filter(
				$results,
				static fn ( CsvImportRowResult $row ): bool => 'error' === $row->status()
			)
		);
		$invalid = count(
			array_filter(
				$results,
				static fn ( CsvImportRowResult $row ): bool => 'invalid' === $row->status()
			)
		);
		$success = count(
			array_filter(
				$results,
				static fn ( CsvImportRowResult $row ): bool => in_array(
					$row->status(),
					array( 'imported', 'skipped' ),
					true
				)
			)
		);

		if ( $errors === count( $results ) && 0 === $success ) {
			return 'failed';
		}

		return 0 < $errors || 0 < $invalid ? 'completed_with_errors' : 'completed';
	}

	private function safe_filename( string $filename ): string {
		$filename = str_replace( array( "\0", '\\' ), array( '', '/' ), $filename );
		$filename = basename( $filename );
		$filename = preg_replace( '/[\x00-\x1F\x7F]+/u', '', $filename );

		return is_string( $filename ) && '' !== $filename ? $filename : 'import.csv';
	}
}
