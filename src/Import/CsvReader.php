<?php

namespace Tazkar\Import;

use Tazkar\Validation\IssueCode;
use Tazkar\Validation\Severity;
use Tazkar\Validation\ValidationIssue;

final class CsvReader {
	public function __construct(
		private readonly CsvPreviewOptions $options = new CsvPreviewOptions()
	) {}

	public function read( string $path ): CsvDocument {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new CsvReadException( CsvIssueCode::FILE_NOT_READABLE, 'CSV file is not readable.' );
		}

		$size = filesize( $path );
		if ( false === $size ) {
			throw new CsvReadException( CsvIssueCode::FILE_NOT_READABLE, 'CSV file size could not be read.' );
		}
		if ( $size > $this->options->max_bytes() ) {
			throw new CsvReadException(
				CsvIssueCode::FILE_TOO_LARGE,
				'CSV file exceeds the configured size limit.',
				array(
					'bytes'     => $size,
					'max_bytes' => $this->options->max_bytes(),
				)
			);
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new CsvReadException( CsvIssueCode::FILE_NOT_READABLE, 'CSV file could not be opened.' );
		}

		try {
			$header_record = fgetcsv( $handle, null, ',', '"', '' );
			if ( false === $header_record || array( null ) === $header_record ) {
				throw new CsvReadException( CsvIssueCode::EMPTY_FILE, 'CSV file has no header row.' );
			}

			$headers = $this->string_cells( $header_record, 1 );
			if ( isset( $headers[0] ) && str_starts_with( $headers[0], "\xEF\xBB\xBF" ) ) {
				$headers[0] = substr( $headers[0], 3 );
			}
			if ( array() === $headers || array() === array_filter( $headers, static fn ( string $value ): bool => '' !== $value ) ) {
				throw new CsvReadException( CsvIssueCode::EMPTY_FILE, 'CSV header row is empty.' );
			}
			if ( count( $headers ) > $this->options->max_columns() ) {
				throw new CsvReadException(
					CsvIssueCode::TOO_MANY_COLUMNS,
					'CSV file exceeds the column limit.',
					array(
						'columns'     => count( $headers ),
						'max_columns' => $this->options->max_columns(),
					)
				);
			}

			$rows       = array();
			$row_number = 1;
			while ( true ) {
				$record = fgetcsv( $handle, null, ',', '"', '' );
				if ( false === $record ) {
					break;
				}

				++$row_number;
				if ( count( $rows ) >= $this->options->max_rows() ) {
					throw new CsvReadException(
						CsvIssueCode::TOO_MANY_ROWS,
						'CSV file exceeds the row limit.',
						array( 'max_rows' => $this->options->max_rows() )
					);
				}

				$cells  = $this->string_cells( $record, $row_number );
				$issues = array();
				if ( count( $cells ) !== count( $headers ) ) {
					$issues[] = new ValidationIssue(
						CsvIssueCode::ROW_WIDTH_MISMATCH,
						Severity::ERROR,
						null,
						'CSV row has a different number of cells than the header.',
						$row_number,
						array(
							'cells'   => count( $cells ),
							'headers' => count( $headers ),
						)
					);
				}

				$mapped = array();
				foreach ( $headers as $index => $header ) {
					$cell              = $cells[ $index ] ?? '';
					$mapped[ $header ] = $cell;
					if ( $this->is_formula_like( $cell ) ) {
						$issues[] = new ValidationIssue(
							CsvIssueCode::FORMULA_LIKE_TEXT,
							Severity::WARNING,
							$header,
							'Formula-like CSV cell was preserved as plain text.',
							$row_number,
							array(
								'column' => $index + 1,
								'prefix' => substr( $cell, 0, 1 ),
							)
						);
					}
				}

				$rows[] = new CsvParsedRow( $row_number, $cells, $mapped, $issues );
			}

			return new CsvDocument( $headers, $rows );
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * @param array<int, string|null> $cells Parsed CSV record.
	 * @return string[]
	 */
	private function string_cells( array $cells, int $row_number ): array {
		$values = array();
		foreach ( $cells as $column => $cell ) {
			$value = null === $cell ? '' : $cell;
			if ( 1 !== preg_match( '//u', $value ) ) {
				throw new CsvReadException(
					CsvIssueCode::INVALID_UTF8,
					'CSV contains invalid UTF-8.',
					array(
						'row'    => $row_number,
						'column' => $column + 1,
					)
				);
			}
			$values[] = $value;
		}

		return $values;
	}

	private function is_formula_like( string $value ): bool {
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true );
	}
}
