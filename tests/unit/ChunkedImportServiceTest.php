<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\ChunkedImportRepository;
use Tazkar\Import\ChunkedImportService;
use Tazkar\Import\CsvReader;
use Tazkar\Import\RollbackAwareContentWriter;
use Tazkar\Validation\BatchValidator;
use Tazkar\Validation\ContractSchema;

final class ChunkedImportServiceTest extends TestCase {
	/** @var string[] */
	private array $temporary_files = array();

	protected function tearDown(): void {
		foreach ( $this->temporary_files as $file ) {
			@unlink( $file );
		}
	}

	public function test_staging_and_bounded_chunks_preserve_arabic_and_remove_payloads(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 3 ), 'chunked.csv', 7, 2 );

		self::assertSame( 'staged', $progress->status() );
		self::assertSame( 3, $progress->count( 'pending' ) );
		self::assertStringContainsString( 'تَركُ الغَيبَةِ أَحَبُّ إلَى اللّهِ', (string) $repository->rows[1]['staged_payload'] );

		$progress = $service->process( $progress->batch_uuid(), 7 );
		self::assertSame( 2, $progress->count( 'imported' ) );
		self::assertSame( 1, $progress->count( 'pending' ) );
		self::assertCount( 2, $writer->created );

		$progress = $service->resume( $progress->batch_uuid(), 7 );
		self::assertSame( 'completed', $progress->status() );
		self::assertSame( 3, $progress->count( 'processed' ) );
		self::assertSame( 0, $progress->count( 'pending' ) );
		foreach ( $repository->rows as $row ) {
			self::assertNull( $row['staged_payload'] );
		}
	}

	public function test_interrupted_processing_row_is_recovered_without_duplicate_post(): void {
		$repository                           = new TazkarTestChunkedRepository();
		$writer                               = new TazkarTestRollbackWriter();
		$service                              = $this->service( $repository, $writer );
		$progress                             = $service->stage( $this->csv_with_rows( 1 ), 'resume.csv', 8, 1 );
		$repository->rows[1]['status']        = 'processing';
		$repository->rows[1]['attempt_count'] = 1;

		$progress = $service->resume( $progress->batch_uuid(), 8 );
		self::assertSame( 'completed', $progress->status() );
		self::assertCount( 1, $writer->created );
		self::assertSame( 2, $repository->rows[1]['attempt_count'] );

		$service->process( $progress->batch_uuid(), 8 );
		self::assertCount( 1, $writer->created );
	}

	public function test_invalid_rows_are_terminal_and_never_retain_payload(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 1, true ), 'invalid.csv', 9 );

		self::assertSame( 'completed_with_errors', $progress->status() );
		self::assertSame( 1, $progress->count( 'invalid' ) );
		self::assertNull( $repository->rows[1]['staged_payload'] );
		self::assertCount( 0, $writer->created );
	}

	public function test_html_payload_is_rejected_before_staging(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$path       = $this->csv_with_rows(
			1,
			false,
			static function ( array $row, int $index ): array {
				unset( $index );
				$row['generated_title'] = '<strong>Unsafe title</strong>';
				return $row;
			}
		);
		$progress   = $service->stage( $path, 'html.csv', 9 );

		self::assertSame( 'completed_with_errors', $progress->status() );
		self::assertSame( 1, $progress->count( 'invalid' ) );
		self::assertNull( $repository->rows[1]['staged_payload'] );
	}

	public function test_hard_chunk_limit_caps_untrusted_configuration(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 101 ), 'bounded.csv', 9, 1000 );
		$progress   = $service->process( $progress->batch_uuid(), 9 );

		self::assertSame( ChunkedImportService::MAX_CHUNK_SIZE, $progress->count( 'imported' ) );
		self::assertSame( 1, $progress->count( 'pending' ) );
	}

	public function test_batch_cannot_be_processed_by_another_user(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 1 ), 'owned.csv', 9 );

		$this->expectException( RuntimeException::class );
		$service->process( $progress->batch_uuid(), 10 );
	}


	public function test_second_active_batch_for_same_user_is_rejected(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$service->stage( $this->csv_with_rows( 1 ), 'first.csv', 9 );

		$this->expectException( RuntimeException::class );
		$service->stage( $this->csv_with_rows( 1 ), 'second.csv', 9 );
	}

	public function test_process_rechecks_batch_state_after_lock(): void {
		$repository                    = new TazkarTestChunkedRepository();
		$writer                        = new TazkarTestRollbackWriter();
		$service                       = $this->service( $repository, $writer );
		$progress                      = $service->stage( $this->csv_with_rows( 1 ), 'race.csv', 9 );
		$repository->status_after_lock = 'rollback_processing';

		$progress = $service->process( $progress->batch_uuid(), 9 );

		self::assertSame( 'rollback_processing', $progress->status() );
		self::assertSame( 'pending', $repository->rows[1]['status'] );
		self::assertCount( 0, $writer->created );
	}


	public function test_process_waits_when_shared_deduplication_lock_is_unavailable(): void {
		$repository                               = new TazkarTestChunkedRepository();
		$writer                                   = new TazkarTestRollbackWriter();
		$service                                  = $this->service( $repository, $writer );
		$progress                                 = $service->stage( $this->csv_with_rows( 1 ), 'dedup-lock.csv', 9 );
		$repository->deduplication_lock_available = false;

		$progress = $service->process( $progress->batch_uuid(), 9 );

		self::assertSame( 1, $progress->count( 'pending' ) );
		self::assertSame( 0, $progress->count( 'imported' ) );
		self::assertCount( 0, $writer->created );
	}

	public function test_rollback_is_rejected_when_wordpress_trash_is_disabled(): void {
		$repository            = new TazkarTestChunkedRepository();
		$writer                = new TazkarTestRollbackWriter();
		$service               = $this->service( $repository, $writer );
		$progress              = $service->stage( $this->csv_with_rows( 1 ), 'trash-disabled.csv', 9 );
		$progress              = $service->process( $progress->batch_uuid(), 9 );
		$writer->trash_enabled = false;

		$preview = $service->rollback_preview( $progress->batch_uuid(), 9 );
		self::assertSame( 1, $preview['trash_disabled'] );

		try {
			$service->rollback( $progress->batch_uuid(), 9 );
			self::fail( 'Rollback should be rejected when Trash is disabled.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'Trash is disabled', $exception->getMessage() );
		}

		self::assertSame( 'imported', $repository->rows[1]['status'] );
		self::assertCount( 0, $writer->rollback_calls );
	}

	public function test_rollback_preview_is_non_mutating_and_reports_classifications(): void {
		$repository                       = new TazkarTestChunkedRepository();
		$writer                           = new TazkarTestRollbackWriter();
		$service                          = $this->service( $repository, $writer );
		$progress                         = $service->stage( $this->csv_with_rows( 2 ), 'preview-rollback.csv', 10, 2 );
		$progress                         = $service->process( $progress->batch_uuid(), 10 );
		$writer->inspection_outcomes[101] = RollbackAwareContentWriter::ROLLBACK_ELIGIBLE;
		$writer->inspection_outcomes[102] = RollbackAwareContentWriter::ROLLBACK_PUBLISHED;
		$writer->interaction_counts[102]  = 3;

		$preview = $service->rollback_preview( $progress->batch_uuid(), 10 );

		self::assertSame( 1, $preview['eligible_drafts'] );
		self::assertSame( 1, $preview['published'] );
		self::assertSame( 3, $preview['interactions'] );
		self::assertSame( 'imported', $repository->rows[1]['status'] );
		self::assertSame( 'imported', $repository->rows[2]['status'] );
		self::assertCount( 0, $writer->rollback_calls );
	}

	public function test_rollback_cancels_pending_rows_and_removes_payload(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 2 ), 'cancel.csv', 9, 1 );
		$progress   = $service->process( $progress->batch_uuid(), 9 );

		self::assertSame( 1, $progress->count( 'pending' ) );
		$progress = $service->rollback( $progress->batch_uuid(), 9 );
		self::assertSame( 'rollback_processing', $progress->status() );
		$progress = $service->rollback( $progress->batch_uuid(), 9 );

		self::assertSame( 'rolled_back', $progress->status() );
		self::assertSame( 'cancelled', $repository->rows[2]['status'] );
		self::assertNull( $repository->rows[2]['staged_payload'] );
		self::assertCount( 1, $writer->rollback_calls );
	}

	public function test_rollback_is_batch_owned_conflict_safe_and_idempotent(): void {
		$repository = new TazkarTestChunkedRepository();
		$writer     = new TazkarTestRollbackWriter();
		$service    = $this->service( $repository, $writer );
		$progress   = $service->stage( $this->csv_with_rows( 2 ), 'rollback.csv', 10, 2 );
		$progress   = $service->process( $progress->batch_uuid(), 10 );
		self::assertSame( 'completed', $progress->status() );

		$writer->rollback_outcomes[101] = RollbackAwareContentWriter::ROLLBACK_TRASHED;
		$writer->rollback_outcomes[102] = RollbackAwareContentWriter::ROLLBACK_CONFLICT;
		$progress                       = $service->rollback( $progress->batch_uuid(), 10 );

		self::assertSame( 'rollback_with_conflicts', $progress->status() );
		self::assertSame( 1, $progress->count( 'rollback_conflicts' ) );
		self::assertSame( 'rolled_back', $repository->rows[1]['status'] );
		self::assertSame( 'imported', $repository->rows[2]['status'] );
		self::assertCount( 2, $writer->rollback_calls );

		$service->rollback( $progress->batch_uuid(), 10 );
		self::assertCount( 2, $writer->rollback_calls );
	}

	private function service(
		TazkarTestChunkedRepository $repository,
		TazkarTestRollbackWriter $writer
	): ChunkedImportService {
		return new ChunkedImportService(
			new CsvReader(),
			new BatchValidator(),
			$repository,
			$writer
		);
	}

	/** @param (\Closure(array<string, mixed>, int): array<string, mixed>)|null $mutator */
	private function csv_with_rows( int $count, bool $invalid = false, ?\Closure $mutator = null ): string {
		$fixture = file_get_contents( dirname( __DIR__ ) . '/fixtures/validation/valid-hadith.json' );
		self::assertIsString( $fixture );
		$row = json_decode( $fixture, true );
		self::assertIsArray( $row );
		$path = tempnam( sys_get_temp_dir(), 'tazkar-chunked-' );
		self::assertIsString( $path );
		$this->temporary_files[] = $path;
		$handle                  = fopen( $path, 'wb' );
		self::assertIsResource( $handle );
		$headers = ContractSchema::columns();
		fputcsv( $handle, $headers, ',', '"', '' );

		for ( $index = 0; $index < $count; ++$index ) {
			$current              = $row;
			$current['record_id'] = (string) $current['record_id'] . '-chunk-' . $index;
			if ( $invalid ) {
				$current['arabic_text'] = '';
			}
			if ( null !== $mutator ) {
				$current = $mutator( $current, $index );
			}
			fputcsv(
				$handle,
				array_map(
					static fn ( string $header ): string => (string) ( $current[ $header ] ?? '' ),
					$headers
				),
				',',
				'"',
				''
			);
		}
		fclose( $handle );

		return $path;
	}
}

final class TazkarTestChunkedRepository implements ChunkedImportRepository {
	/** @var array<int, array<string, mixed>> */
	public array $rows = array();
	/** @var array<string, mixed> */
	public array $batch                       = array();
	private bool $locked                      = false;
	private bool $user_locked                 = false;
	private bool $deduplication_locked        = false;
	public bool $deduplication_lock_available = true;
	public ?string $status_after_lock         = null;

	public function create_staged_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by,
		int $chunk_size
	): array {
		unset( $filename, $checksum, $schema_version );
		$this->batch = array(
			'id'              => 1,
			'batch_uuid'      => '11111111-1111-4111-8111-111111111111',
			'created_by'      => $created_by,
			'status'          => 'staged',
			'chunk_size'      => $chunk_size,
			'total_rows'      => $total_rows,
			'repeated_source' => false,
		);

		return array(
			'id'              => 1,
			'uuid'            => (string) $this->batch['batch_uuid'],
			'repeated_source' => false,
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
		$id                = count( $this->rows ) + 1;
		$this->rows[ $id ] = array(
			'id'                  => $id,
			'batch_id'            => $batch_id,
			'row_number'          => $row_number,
			'record_id'           => $record_id,
			'status'              => $status,
			'error_code'          => $error_code,
			'wp_post_id'          => $post_id,
			'payload_hash'        => $payload_hash,
			'staged_payload'      => $staged_payload,
			'attempt_count'       => 0,
			'rollback_status'     => '',
			'rollback_error_code' => '',
		);
	}

	public function get_owned_batch( string $batch_uuid, int $user_id ): ?array {
		return $batch_uuid === ( $this->batch['batch_uuid'] ?? '' )
			&& $user_id === ( $this->batch['created_by'] ?? 0 )
			? $this->batch
			: null;
	}

	public function acquire_user_import_lock( int $user_id ): bool {
		unset( $user_id );
		if ( $this->user_locked ) {
			return false;
		}
		$this->user_locked = true;

		return true;
	}

	public function release_user_import_lock( int $user_id ): void {
		unset( $user_id );
		$this->user_locked = false;
	}

	public function has_active_batch( int $user_id ): bool {
		return $user_id === ( $this->batch['created_by'] ?? 0 )
			&& in_array( (string) ( $this->batch['status'] ?? '' ), array( 'staged', 'processing', 'rollback_processing' ), true );
	}

	public function acquire_deduplication_lock(): bool {
		if ( ! $this->deduplication_lock_available || $this->deduplication_locked ) {
			return false;
		}
		$this->deduplication_locked = true;

		return true;
	}

	public function release_deduplication_lock(): void {
		$this->deduplication_locked = false;
	}

	public function acquire_lock( int $batch_id, string $token, string $expires_at ): bool {
		unset( $batch_id, $token, $expires_at );
		if ( $this->locked ) {
			return false;
		}
		$this->locked = true;
		if ( null !== $this->status_after_lock ) {
			$this->batch['status']   = $this->status_after_lock;
			$this->status_after_lock = null;
		}
		return true;
	}

	public function release_lock( int $batch_id, string $token ): void {
		unset( $batch_id, $token );
		$this->locked = false;
	}

	public function recover_processing_rows( int $batch_id ): void {
		foreach ( $this->rows as &$row ) {
			if ( $batch_id === $row['batch_id'] && 'processing' === $row['status'] ) {
				$row['status'] = 'pending';
			}
		}
		unset( $row );
	}

	public function pending_rows( int $batch_id, int $limit ): array {
		return array_slice(
			array_values(
				array_filter(
					$this->rows,
					static fn ( array $row ): bool => $batch_id === $row['batch_id'] && 'pending' === $row['status']
				)
			),
			0,
			$limit
		);
	}

	public function mark_row_processing( int $row_id, string $claimed_at ): bool {
		unset( $claimed_at );
		if ( ! isset( $this->rows[ $row_id ] ) || 'pending' !== $this->rows[ $row_id ]['status'] ) {
			return false;
		}
		$this->rows[ $row_id ]['status'] = 'processing';
		++$this->rows[ $row_id ]['attempt_count'];
		return true;
	}

	public function find_prior_post( string $record_id, string $payload_hash ): int {
		foreach ( $this->rows as $row ) {
			if ( 'imported' !== $row['status'] ) {
				continue;
			}
			if ( ( '' !== $record_id && $record_id === $row['record_id'] ) || $payload_hash === $row['payload_hash'] ) {
				return (int) $row['wp_post_id'];
			}
		}
		return 0;
	}

	public function begin_row(): void {}
	public function commit_row(): void {}
	public function rollback_row(): void {}

	public function finish_row(
		int $row_id,
		string $status,
		string $error_code,
		int $post_id,
		string $terminal_at
	): bool {
		unset( $terminal_at );
		if ( ! isset( $this->rows[ $row_id ] ) || ! in_array( $this->rows[ $row_id ]['status'], array( 'pending', 'processing' ), true ) ) {
			return false;
		}
		$this->rows[ $row_id ]['status']         = $status;
		$this->rows[ $row_id ]['error_code']     = $error_code;
		$this->rows[ $row_id ]['wp_post_id']     = $post_id;
		$this->rows[ $row_id ]['staged_payload'] = null;
		return true;
	}

	public function refresh_batch_counts( int $batch_id ): array {
		unset( $batch_id );
		$counts = array(
			'total'              => count( $this->rows ),
			'processed'          => 0,
			'imported'           => 0,
			'skipped'            => 0,
			'invalid'            => 0,
			'error'              => 0,
			'pending'            => 0,
			'rollback_conflicts' => 0,
			'next_row'           => 0,
		);
		foreach ( $this->rows as $row ) {
			$status = (string) $row['status'];
			if ( in_array( $status, array( 'pending', 'processing' ), true ) ) {
				++$counts['pending'];
				if ( 0 === $counts['next_row'] ) {
					$counts['next_row'] = (int) $row['row_number'];
				}
			} else {
				++$counts['processed'];
			}
			if ( isset( $counts[ $status ] ) && ! in_array( $status, array( 'pending', 'processing' ), true ) ) {
				++$counts[ $status ];
			}
			if ( 'conflict' === $row['rollback_status'] ) {
				++$counts['rollback_conflicts'];
			}
		}
		return $counts;
	}

	public function set_batch_state( int $batch_id, string $status, ?string $completed_at = null ): void {
		unset( $batch_id, $completed_at );
		$this->batch['status'] = $status;
	}

	public function rows( int $batch_id ): array {
		unset( $batch_id );
		return array_values( $this->rows );
	}

	public function rollback_candidates( int $batch_id, int $limit ): array {
		return array_slice(
			array_values(
				array_filter(
					$this->rows,
					static fn ( array $row ): bool => $batch_id === $row['batch_id']
						&& in_array( $row['status'], array( 'pending', 'processing', 'imported' ), true )
						&& '' === $row['rollback_status']
				)
			),
			0,
			$limit
		);
	}

	public function cancel_pending_row( int $row_id, string $terminal_at ): bool {
		unset( $terminal_at );
		if ( ! isset( $this->rows[ $row_id ] ) ) {
			return false;
		}
		$this->rows[ $row_id ]['status']          = 'cancelled';
		$this->rows[ $row_id ]['rollback_status'] = 'cancelled';
		$this->rows[ $row_id ]['staged_payload']  = null;
		return true;
	}

	public function finish_rollback_row(
		int $row_id,
		string $row_status,
		string $rollback_status,
		string $rollback_error_code,
		string $terminal_at
	): bool {
		unset( $terminal_at );
		if ( ! isset( $this->rows[ $row_id ] ) || '' !== $this->rows[ $row_id ]['rollback_status'] ) {
			return false;
		}
		$this->rows[ $row_id ]['status']              = $row_status;
		$this->rows[ $row_id ]['rollback_status']     = $rollback_status;
		$this->rows[ $row_id ]['rollback_error_code'] = $rollback_error_code;
		return true;
	}

	public function start_rollback( int $batch_id, string $started_at ): void {
		unset( $batch_id, $started_at );
		$this->batch['status'] = 'rollback_processing';
	}

	public function finish_rollback( int $batch_id, string $status, string $finished_at ): void {
		unset( $batch_id, $finished_at );
		$this->batch['status'] = $status;
	}
}

final class TazkarTestRollbackWriter implements RollbackAwareContentWriter {
	/** @var array<int, array<string, mixed>> */
	public array $created = array();
	/** @var array<int, string> */
	public array $rollback_outcomes = array();
	/** @var array<int, string> */
	public array $inspection_outcomes = array();
	/** @var array<int, int> */
	public array $interaction_counts = array();
	/** @var int[] */
	public array $rollback_calls = array();
	public bool $trash_enabled   = true;
	private int $next_post_id    = 101;

	public function create_draft( array $row, int $author_id ): int {
		$post_id                   = $this->next_post_id++;
		$this->created[ $post_id ] = array(
			'row'       => $row,
			'author_id' => $author_id,
		);
		return $post_id;
	}

	public function delete( int $post_id ): void {
		unset( $this->created[ $post_id ] );
	}

	public function mark_import_ownership( int $post_id, string $batch_uuid, int $row_id ): void {
		$this->created[ $post_id ]['batch_uuid'] = $batch_uuid;
		$this->created[ $post_id ]['row_id']     = $row_id;
	}

	public function trash_available(): bool {
		return $this->trash_enabled;
	}

	public function inspect_rollback( int $post_id, string $batch_uuid, int $row_id ): array {
		unset( $batch_uuid, $row_id );

		return array(
			'outcome'      => $this->trash_enabled
				? ( $this->inspection_outcomes[ $post_id ] ?? self::ROLLBACK_ELIGIBLE )
				: self::ROLLBACK_TRASH_DISABLED,
			'interactions' => $this->interaction_counts[ $post_id ] ?? 0,
		);
	}

	public function rollback_owned_draft( int $post_id, string $batch_uuid, int $row_id ): string {
		unset( $batch_uuid, $row_id );
		if ( ! $this->trash_enabled ) {
			return self::ROLLBACK_CONFLICT;
		}
		$this->rollback_calls[] = $post_id;
		return $this->rollback_outcomes[ $post_id ] ?? self::ROLLBACK_TRASHED;
	}
}
