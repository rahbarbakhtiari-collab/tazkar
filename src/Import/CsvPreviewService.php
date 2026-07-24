<?php

namespace Tazkar\Import;

use Tazkar\Validation\BatchValidator;

final class CsvPreviewService {
	public function __construct(
		private readonly CsvReader $reader = new CsvReader(),
		private readonly BatchValidator $validator = new BatchValidator()
	) {}

	public function preview( string $path, string $filename ): CsvPreviewResult {
		$document = $this->reader->read( $path );
		$rows     = array_map(
			static fn ( CsvParsedRow $row ): array => $row->mapped(),
			$document->rows()
		);
		$result   = $this->validator->validate( $document->headers(), $rows );

		foreach ( $document->rows() as $index => $parsed_row ) {
			if ( ! isset( $result->rows()[ $index ] ) ) {
				continue;
			}
			foreach ( $parsed_row->issues() as $issue ) {
				$result->rows()[ $index ]->add( $issue );
			}
		}

		return new CsvPreviewResult( $this->safe_filename( $filename ), $result );
	}

	private function safe_filename( string $filename ): string {
		$filename = basename( str_replace( "\0", '', $filename ) );
		$filename = preg_replace( '/[\x00-\x1F\x7F]+/u', '', $filename );

		return is_string( $filename ) && '' !== $filename ? $filename : 'preview.csv';
	}
}
