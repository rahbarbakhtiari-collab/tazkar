<?php

namespace Tazkar\Validation;

final class BatchValidationResult {
	/**
	 * @param ValidationResult[] $rows Row results indexed by input position.
	 */
	public function __construct(
		private readonly ValidationResult $headers,
		private readonly array $rows
	) {}

	public function isValid(): bool {
		if ( ! $this->headers->isValid() ) {
			return false;
		}

		foreach ( $this->rows as $row ) {
			if ( ! $row->isValid() ) {
				return false;
			}
		}

		return true;
	}

	public function headers(): ValidationResult {
		return $this->headers;
	}

	/**
	 * @return ValidationResult[]
	 */
	public function rows(): array {
		return $this->rows;
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function errors(): array {
		$issues = $this->headers->errors();

		foreach ( $this->rows as $row ) {
			$issues = array_merge( $issues, $row->errors() );
		}

		return $issues;
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function warnings(): array {
		$issues = $this->headers->warnings();

		foreach ( $this->rows as $row ) {
			$issues = array_merge( $issues, $row->warnings() );
		}

		return $issues;
	}
}
