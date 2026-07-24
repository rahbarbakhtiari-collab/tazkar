<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\CsvIssueCode;
use Tazkar\Import\CsvPreviewService;
use Tazkar\Validation\ContractSchema;

final class CsvPreviewServiceTest extends TestCase {
	private ?string $temporary_file = null;

	protected function tearDown(): void {
		if ( null !== $this->temporary_file ) {
			@unlink( $this->temporary_file );
		}
	}

	public function test_reordered_headers_are_mapped_and_validated_without_mutation(): void {
		$row      = $this->fixture( 'valid-quran.json' );
		$headers  = array_reverse( ContractSchema::columns() );
		$values   = array_map( static fn ( string $header ) => $row[ $header ] ?? '', $headers );
		$path     = $this->write_csv( $headers, array( $values ) );
		$result   = ( new CsvPreviewService() )->preview( $path, "../unsafe\0-name.csv" );
		$validated = $result->validation()->rows()[0];

		self::assertTrue( $result->is_valid() );
		self::assertSame( 1, $result->total_rows() );
		self::assertSame( 1, $result->valid_rows() );
		self::assertSame( $row['arabic_text'], $validated->row()['arabic_text'] );
		self::assertSame( 'unsafe-name.csv', $result->filename() );
	}

	public function test_parse_issues_are_merged_with_contract_issues(): void {
		$row                       = $this->fixture( 'valid-hadith.json' );
		$row['generated_title']    = '@formula-like';
		$headers                   = ContractSchema::columns();
		$values                    = array_map( static fn ( string $header ) => $row[ $header ] ?? '', $headers );
		$values[]                  = 'extra-cell';
		$result                    = ( new CsvPreviewService() )->preview( $this->write_csv( $headers, array( $values ) ), 'test.csv' );
		$codes                     = array_map( static fn ( $issue ): string => $issue->code(), $result->validation()->rows()[0]->issues() );

		self::assertFalse( $result->is_valid() );
		self::assertContains( CsvIssueCode::ROW_WIDTH_MISMATCH, $codes );
		self::assertContains( CsvIssueCode::FORMULA_LIKE_TEXT, $codes );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fixture( string $name ): array {
		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/validation/' . $name );
		self::assertNotFalse( $json );
		$decoded = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		self::assertIsArray( $decoded );

		return $decoded;
	}

	/**
	 * @param string[] $headers CSV headers.
	 * @param array<int, array<int, scalar|null>> $rows CSV rows.
	 */
	private function write_csv( array $headers, array $rows ): string {
		$path = tempnam( sys_get_temp_dir(), 'tazkar-preview-' );
		self::assertIsString( $path );
		$this->temporary_file = $path;
		$handle = fopen( $path, 'wb' );
		self::assertIsResource( $handle );
		fputcsv( $handle, $headers, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		fclose( $handle );

		return $path;
	}
}
