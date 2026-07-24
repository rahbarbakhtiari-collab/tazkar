<?php

namespace Tazkar\Validation;

final class HeaderValidator {
	/**
	 * @param string[] $headers File headers in any order.
	 */
	public function validate( array $headers ): ValidationResult {
		$result = new ValidationResult( array( 'headers' => $headers ) );
		$counts = array_count_values( $headers );

		foreach ( $counts as $header => $count ) {
			if ( $count > 1 ) {
				$result->error(
					IssueCode::HEADER_DUPLICATE,
					$header,
					'Duplicate header.',
					array( 'count' => $count )
				);
			}
		}

		foreach ( ContractSchema::requiredHeaders() as $required ) {
			if ( ! in_array( $required, $headers, true ) ) {
				$result->error(
					IssueCode::HEADER_MISSING_REQUIRED,
					$required,
					'Required header is missing.'
				);
			}
		}

		foreach ( array_unique( $headers ) as $header ) {
			if ( ! in_array( $header, ContractSchema::columns(), true ) ) {
				$result->warning(
					IssueCode::HEADER_UNKNOWN,
					$header,
					'Unknown header will not be mapped.'
				);
			}
		}

		return $result;
	}
}
