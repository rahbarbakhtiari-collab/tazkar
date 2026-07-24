<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\ContentWriter;
use Tazkar\Import\CsvImportIssueCode;
use Tazkar\Import\CsvImportService;
use Tazkar\Import\CsvReader;
use Tazkar\Import\ImportRepository;
use Tazkar\Validation\BatchValidator;
use Tazkar\Validation\ContractSchema;

final class CsvImportServiceTest extends TestCase {
	/** @var string[] */
	private array $temporary_files = array();

	protected function tearDown(): void {
		foreach ( $this->temporary_files as $file ) {
			@unlink( $file );
		}
	}

	public function test_valid_rows_are_imported_as_drafts_and_recorded(): void {
		$repository = new TazkarTestImportRepository();
		$writer     = new TazkarTestContentWriter();
		$service    = new CsvImportService(
			new CsvReader(),
			new BatchValidator(),
			$repository,
			$writer
		);

		$result = $service->import( $this->csv_with_rows( 2, false ), 'valid.csv', 77 );

		self::assertSame( 2, $result->imported_rows() );
		self::assertSame( 'completed', $result->status() );
		self::assertCount( 2, $repository->rows );
		self::assertSame( 77, $writer->authors[0] );
		self::assertSame(
			'تَركُ الغَيبَةِ أَحَبُّ إلَى اللّهِ',
			$writer->rows[0]['arabic_text']
		);
	}

	public function test_duplicate_in_batch_imports_first_and_skips_second(): void {
		$path       = $this->csv_with_rows( 2, true );
		$repository = new TazkarTestImportRepository();
		$writer     = new TazkarTestContentWriter();
		$result     = ( new CsvImportService(
			new CsvReader(),
			new BatchValidator(),
			$repository,
			$writer
		) )->import( $path, 'duplicate.csv', 1 );

		self::assertSame( 1, $result->imported_rows() );
		self::assertSame( 1, $result->skipped_rows() );
		self::assertSame(
			CsvImportIssueCode::DUPLICATE_BATCH_RECORD,
			$result->rows()[1]->issue_code()
		);
	}

	public function test_prior_duplicate_creates_no_new_post(): void {
		$path       = $this->csv_with_rows( 1, false );
		$repository = new TazkarTestImportRepository();
		$repository->prior_post = 900;
		$writer = new TazkarTestContentWriter();
		$result = ( new CsvImportService(
			new CsvReader(),
			new BatchValidator(),
			$repository,
			$writer
		) )->import( $path, 'prior.csv', 1 );

		self::assertSame( 1, $result->skipped_rows() );
		self::assertSame( 900, $result->rows()[0]->post_id() );
		self::assertCount( 0, $writer->rows );
	}

	public function test_row_failure_is_isolated_and_recorded(): void {
		$path       = $this->csv_with_rows( 1, false );
		$repository = new TazkarTestImportRepository();
		$writer     = new TazkarTestContentWriter();
		$writer->fail = true;
		$result = ( new CsvImportService(
			new CsvReader(),
			new BatchValidator(),
			$repository,
			$writer
		) )->import( $path, 'failure.csv', 1 );

		self::assertSame( 'failed', $result->status() );
		self::assertSame( 1, $result->error_rows() );
		self::assertSame( 1, $repository->rollbacks );
		self::assertSame(
			CsvImportIssueCode::ROW_WRITE_FAILED,
			$result->rows()[0]->issue_code()
		);
	}

	private function csv_with_rows( int $count, bool $same_record_id ): string {
		$fixture = file_get_contents(
			dirname( __DIR__ ) . '/fixtures/validation/valid-hadith.json'
		);
		self::assertIsString( $fixture );
		$row = json_decode( $fixture, true );
		self::assertIsArray( $row );
		$headers = ContractSchema::columns();
		$path    = tempnam( sys_get_temp_dir(), 'tazkar-import-' );
		self::assertIsString( $path );
		$this->temporary_files[] = $path;
		$handle = fopen( $path, 'wb' );
		self::assertIsResource( $handle );
		fputcsv( $handle, $headers, ',', '"', '' );

		for ( $index = 0; $index < $count; ++$index ) {
			$current = $row;
			if ( ! $same_record_id ) {
				$current['record_id'] .= '-' . $index;
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

final class TazkarTestImportRepository implements ImportRepository {
	/** @var array<int, array<string, mixed>> */
	public array $rows = array();
	public int $prior_post = 0;
	public int $rollbacks = 0;
	public string $batch_status = '';

	public function create_batch(
		string $filename,
		string $checksum,
		string $schema_version,
		int $total_rows,
		int $created_by
	): array {
		unset( $filename, $checksum, $schema_version, $total_rows, $created_by );

		return array( 'id' => 5, 'uuid' => 'batch-test', 'repeated_source' => false );
	}

	public function find_prior_post( string $record_id, string $payload_hash ): int {
		unset( $record_id, $payload_hash );

		return $this->prior_post;
	}

	public function begin_row(): void {}
	public function commit_row(): void {}

	public function rollback_row(): void {
		++$this->rollbacks;
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
		$this->rows[] = compact(
			'batch_id',
			'row_number',
			'record_id',
			'status',
			'error_code',
			'post_id',
			'payload_hash'
		);
	}

	public function finish_batch( int $batch_id, string $status, int $processed_rows ): void {
		unset( $batch_id, $processed_rows );
		$this->batch_status = $status;
	}
}

final class TazkarTestContentWriter implements ContentWriter {
	/** @var array<int, array<string, mixed>> */
	public array $rows = array();
	/** @var int[] */
	public array $authors = array();
	public bool $fail = false;

	public function create_draft( array $row, int $author_id ): int {
		if ( $this->fail ) {
			throw new RuntimeException( 'Simulated row failure.' );
		}
		$this->rows[]    = $row;
		$this->authors[] = $author_id;

		return 100 + count( $this->rows );
	}

	public function delete( int $post_id ): void {
		unset( $post_id );
	}
}
