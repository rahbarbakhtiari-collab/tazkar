<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\CsvIssueCode;
use Tazkar\Import\CsvPreviewOptions;
use Tazkar\Import\CsvReadException;
use Tazkar\Import\CsvReader;

final class CsvReaderTest extends TestCase {
	/** @var string[] */
	private array $temporary_files = array();

	protected function tearDown(): void {
		foreach ( $this->temporary_files as $file ) {
			@unlink( $file );
		}
	}

	public function test_bom_quoted_comma_newline_and_arabic_are_preserved(): void {
		$path    = $this->temporary_file();
		$arabic  = "  إِنَّ النَّفْسَ لَأَمَّارَةٌ بِالسُّوءِ\n";
		$headers = array( 'record_id', 'translation_original', 'arabic_text' );
		$handle  = fopen( $path, 'wb' );
		self::assertIsResource( $handle );
		fwrite( $handle, "\xEF\xBB\xBF" );
		fputcsv( $handle, $headers, ',', '"', '' );
		fputcsv( $handle, array( 'id-1', "بخش نخست، بخش دوم\nادامه", $arabic ), ',', '"', '' );
		fclose( $handle );

		$document = ( new CsvReader() )->read( $path );
		$row      = $document->rows()[0];

		self::assertSame( $headers, $document->headers() );
		self::assertSame( "بخش نخست، بخش دوم\nادامه", $row->mapped()['translation_original'] );
		self::assertSame( $arabic, $row->mapped()['arabic_text'] );
		self::assertSame( $arabic, $row->cells()[2] );
	}

	public function test_formula_like_cell_is_preserved_and_warned(): void {
		$path = $this->csv_file(
			array( 'record_id', 'generated_title' ),
			array( array( 'id-1', '=SUM(1,2)' ) )
		);
		$row  = ( new CsvReader() )->read( $path )->rows()[0];

		self::assertSame( '=SUM(1,2)', $row->mapped()['generated_title'] );
		self::assertSame( CsvIssueCode::FORMULA_LIKE_TEXT, $row->issues()[0]->code() );
		self::assertSame( 'warning', $row->issues()[0]->severity()->value );
	}

	public function test_invalid_utf8_is_rejected(): void {
		$path = $this->temporary_file();
		file_put_contents( $path, "record_id,arabic_text\nid-1,\xC3\x28\n" );

		try {
			( new CsvReader() )->read( $path );
			self::fail( 'Invalid UTF-8 must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( CsvIssueCode::INVALID_UTF8, $exception->issue_code() );
		}
	}

	public function test_empty_file_and_resource_limits_are_rejected(): void {
		$empty = $this->temporary_file();
		file_put_contents( $empty, '' );

		try {
			( new CsvReader() )->read( $empty );
			self::fail( 'Empty CSV must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( CsvIssueCode::EMPTY_FILE, $exception->issue_code() );
		}

		$columns = $this->csv_file( array( 'a', 'b', 'c' ), array() );
		try {
			( new CsvReader( new CsvPreviewOptions( 1024, 10, 2 ) ) )->read( $columns );
			self::fail( 'Column limit must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( CsvIssueCode::TOO_MANY_COLUMNS, $exception->issue_code() );
		}

		$rows = $this->csv_file( array( 'record_id' ), array( array( '1' ), array( '2' ) ) );
		try {
			( new CsvReader( new CsvPreviewOptions( 1024, 1, 10 ) ) )->read( $rows );
			self::fail( 'Row limit must throw.' );
		} catch ( CsvReadException $exception ) {
			self::assertSame( CsvIssueCode::TOO_MANY_ROWS, $exception->issue_code() );
		}
	}

	public function test_row_width_mismatch_is_attached_to_the_row(): void {
		$path = $this->csv_file( array( 'a', 'b' ), array( array( 'only-one' ) ) );
		$row  = ( new CsvReader() )->read( $path )->rows()[0];

		self::assertSame( CsvIssueCode::ROW_WIDTH_MISMATCH, $row->issues()[0]->code() );
		self::assertSame( 'error', $row->issues()[0]->severity()->value );
		self::assertSame( array( 'a' => 'only-one', 'b' => '' ), $row->mapped() );
	}

	/**
	 * @param string[]   $headers CSV headers.
	 * @param string[][] $rows    CSV rows.
	 */
	private function csv_file( array $headers, array $rows ): string {
		$path   = $this->temporary_file();
		$handle = fopen( $path, 'wb' );
		self::assertIsResource( $handle );
		fputcsv( $handle, $headers, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		fclose( $handle );

		return $path;
	}

	private function temporary_file(): string {
		$path = tempnam( sys_get_temp_dir(), 'tazkar-csv-' );
		self::assertIsString( $path );
		$this->temporary_files[] = $path;

		return $path;
	}
}
