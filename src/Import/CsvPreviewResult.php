<?php

namespace Tazkar\Import;

use Tazkar\Validation\BatchValidationResult;
use Tazkar\Validation\ValidationResult;

final class CsvPreviewResult {
	public function __construct(
		private readonly string $filename,
		private readonly BatchValidationResult $validation
	) {}

	public function filename(): string {
		return $this->filename;
	}

	public function validation(): BatchValidationResult {
		return $this->validation;
	}

	public function total_rows(): int {
		return count( $this->validation->rows() );
	}

	public function valid_rows(): int {
		return count(
			array_filter(
				$this->validation->rows(),
				static fn ( ValidationResult $result ): bool => $result->is_valid()
			)
		);
	}

	public function invalid_rows(): int {
		return $this->total_rows() - $this->valid_rows();
	}

	public function warning_rows(): int {
		return count(
			array_filter(
				$this->validation->rows(),
				static fn ( ValidationResult $result ): bool => array() !== $result->warnings()
			)
		);
	}

	public function is_valid(): bool {
		return $this->validation->is_valid();
	}
}
