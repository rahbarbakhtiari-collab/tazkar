<?php

namespace Tazkar\Import;

final class CsvDocument {
	/**
	 * @param string[]       $headers Exact header names after BOM removal.
	 * @param CsvParsedRow[] $rows    Parsed data records.
	 */
	public function __construct(
		private readonly array $headers,
		private readonly array $rows
	) {}

	/**
	 * @return string[]
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * @return CsvParsedRow[]
	 */
	public function rows(): array {
		return $this->rows;
	}
}
