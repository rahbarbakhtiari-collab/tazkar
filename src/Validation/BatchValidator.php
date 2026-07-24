<?php

namespace Tazkar\Validation;

final class BatchValidator {
	public function __construct(
		private readonly HeaderValidator $header_validator = new HeaderValidator(),
		private readonly DataContractValidator $row_validator = new DataContractValidator()
	) {}

	/**
	 * @param string[]                  $headers File headers in any order.
	 * @param array<int, array<string, mixed>> $rows    Associative input rows.
	 */
	public function validate( array $headers, array $rows ): BatchValidationResult {
		$header_result = $this->header_validator->validate( $headers );
		$row_results   = array();
		$record_rows   = array();

		foreach ( $rows as $index => $row ) {
			$row_number            = $index + 2;
			$row_results[ $index ] = $this->row_validator->validate( $row, $row_number );
			$record_id             = $row['record_id'] ?? null;

			if ( is_string( $record_id ) && '' !== trim( $record_id ) ) {
				$record_rows[ $record_id ][] = $index;
			}
		}

		foreach ( $record_rows as $record_id => $indexes ) {
			if ( count( $indexes ) < 2 ) {
				continue;
			}

			foreach ( $indexes as $index ) {
				$row_results[ $index ]->error(
					IssueCode::DUPLICATE_RECORD_ID,
					'record_id',
					'record_id is duplicated in the batch.',
					array(
						'record_id' => $record_id,
						'count'     => count( $indexes ),
					)
				);
			}
		}

		return new BatchValidationResult( $header_result, $row_results );
	}
}
