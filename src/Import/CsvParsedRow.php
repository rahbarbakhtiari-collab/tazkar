<?php

namespace Tazkar\Import;

use Tazkar\Validation\ValidationIssue;

final class CsvParsedRow {
	/**
	 * @param string[]             $cells  Exact parsed cell values.
	 * @param array<string, string> $mapped Header-mapped row values.
	 * @param ValidationIssue[]    $issues Parse-level row issues.
	 */
	public function __construct(
		private readonly int $row_number,
		private readonly array $cells,
		private readonly array $mapped,
		private readonly array $issues = array()
	) {}

	public function row_number(): int {
		return $this->row_number;
	}

	/**
	 * @return string[]
	 */
	public function cells(): array {
		return $this->cells;
	}

	/**
	 * @return array<string, string>
	 */
	public function mapped(): array {
		return $this->mapped;
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function issues(): array {
		return $this->issues;
	}
}
