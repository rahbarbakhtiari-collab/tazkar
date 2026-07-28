<?php

namespace Tazkar\Import;

final class ChunkedImportProgress {
	/**
	 * @param array<string, int>               $counts Aggregate row counters.
	 * @param array<int, array<string, mixed>> $rows   Persisted row outcomes.
	 */
	public function __construct(
		private readonly string $batch_uuid,
		private readonly bool $repeated_source,
		private readonly string $status,
		private readonly array $counts,
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

	public function count( string $name ): int {
		return isset( $this->counts[ $name ] ) ? (int) $this->counts[ $name ] : 0;
	}

	/** @return array<int, array<string, mixed>> */
	public function rows(): array {
		return $this->rows;
	}

	public function can_resume(): bool {
		return in_array( $this->status, array( 'staged', 'processing' ), true )
			&& $this->count( 'pending' ) > 0;
	}

	public function can_rollback(): bool {
		return ! in_array( $this->status, array( 'rolled_back', 'rollback_with_conflicts' ), true )
			&& ( $this->count( 'pending' ) > 0 || $this->count( 'imported' ) > 0 );
	}
}
