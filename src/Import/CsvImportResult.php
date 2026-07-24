<?php

namespace Tazkar\Import;

final class CsvImportResult {
	/** @param CsvImportRowResult[] $rows Persisted row results. */
	public function __construct(
		private readonly string $batch_uuid,
		private readonly bool $repeated_source,
		private readonly string $status,
		private readonly array $rows
	) {}

	public function batch_uuid(): string {
		return $this->batch_uuid;
	}

	public function repeated_source(): bool {
		return $this->repeated_source;
	}

	public function status(): string {
		return $this->status;
	}

	/** @return CsvImportRowResult[] */
	public function rows(): array {
		return $this->rows;
	}

	public function total_rows(): int {
		return count( $this->rows );
	}

	public function imported_rows(): int {
		return $this->count_status( 'imported' );
	}

	public function skipped_rows(): int {
		return $this->count_status( 'skipped' );
	}

	public function invalid_rows(): int {
		return $this->count_status( 'invalid' );
	}

	public function error_rows(): int {
		return $this->count_status( 'error' );
	}

	private function count_status( string $status ): int {
		return count(
			array_filter(
				$this->rows,
				static fn ( CsvImportRowResult $row ): bool => $status === $row->status()
			)
		);
	}
}
