<?php

namespace Tazkar\Import;

final class CsvPreviewOptions {
	public const DEFAULT_MAX_BYTES   = 10 * 1024 * 1024;
	public const DEFAULT_MAX_ROWS    = 500;
	public const DEFAULT_MAX_COLUMNS = 60;

	public function __construct(
		private readonly int $max_bytes = self::DEFAULT_MAX_BYTES,
		private readonly int $max_rows = self::DEFAULT_MAX_ROWS,
		private readonly int $max_columns = self::DEFAULT_MAX_COLUMNS
	) {
		if ( $max_bytes < 1 || $max_rows < 1 || $max_columns < 1 ) {
			throw new \InvalidArgumentException( 'CSV preview limits must be positive.' );
		}
	}

	public function max_bytes(): int {
		return $this->max_bytes;
	}

	public function max_rows(): int {
		return $this->max_rows;
	}

	public function max_columns(): int {
		return $this->max_columns;
	}
}
