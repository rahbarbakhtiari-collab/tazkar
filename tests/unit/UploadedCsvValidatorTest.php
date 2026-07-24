<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\CsvIssueCode;
use Tazkar\Import\CsvPreviewOptions;
use Tazkar\Import\CsvReadException;
use Tazkar\Import\UploadedCsvValidator;

final class UploadedCsvValidatorTest extends TestCase {
	private ?string $temporary_file = null;

	protected function setUp(): void {
		$path = tempnam( sys_get_temp_dir(), 'tazkar-upload-' );
		self::assertIsString( $path );
		$this->temporary_file = $path;
		file_put_contents( $path, "record_id\nid-1\n" );
	}

	protected function tearDown(): void {
		if ( null !== $this->temporary_file ) {
			@unlink( $this->temporary_file );
		}
	}

	public function test_valid_csv_metadata_is_accepted(): void {
		$upload = ( new UploadedCsvValidator() )->validate( $this->metadata( 'sample.csv' ), true, 'text/plain' );

		self::assertSame( $this->temporary_file, $upload->path() );
		self::assertSame( 'sample.csv', $upload->name() );
		self::assertSame( 'text/plain', $upload->mime() );
	}

	/**
	 * @dataProvider invalid_upload_provider
	 */
	public function test_invalid_upload_metadata_is_rejected(
		string $name,
		bool $is_uploaded,
		string $mime,
		int $error,
		string $expected_code
	): void {
		$file          = $this->metadata( $name );
		$file['error'] = $error;

		try {
			( new UploadedCsvValidator() )->validate( $file, $is_uploaded, $mime );
			self::fail( 'Invalid upload must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( $expected_code, $exception->issue_code() );
		}
	}

	/**
	 * @return array<string, array{string, bool, string, int, string}>
	 */
	public static function invalid_upload_provider(): array {
		return array(
			'upload error'      => array( 'sample.csv', true, 'text/csv', UPLOAD_ERR_INI_SIZE, CsvIssueCode::UPLOAD_ERROR ),
			'not HTTP upload'   => array( 'sample.csv', false, 'text/csv', UPLOAD_ERR_OK, CsvIssueCode::NOT_UPLOADED ),
			'wrong extension'   => array( 'sample.xlsx', true, 'text/csv', UPLOAD_ERR_OK, CsvIssueCode::INVALID_EXTENSION ),
			'wrong MIME'        => array( 'sample.csv', true, 'application/zip', UPLOAD_ERR_OK, CsvIssueCode::INVALID_MIME ),
		);
	}

	public function test_actual_size_limit_is_enforced(): void {
		file_put_contents( $this->temporary_file, str_repeat( 'x', 20 ) );

		try {
			( new UploadedCsvValidator( new CsvPreviewOptions( 10, 10, 10 ) ) )->validate(
				$this->metadata( 'sample.csv' ),
				true,
				'text/plain'
			);
			self::fail( 'Oversized upload must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( CsvIssueCode::FILE_TOO_LARGE, $exception->issue_code() );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function metadata( string $name ): array {
		return array(
			'name'     => $name,
			'tmp_name' => $this->temporary_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $this->temporary_file ),
		);
	}
}
