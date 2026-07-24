<?php

namespace Tazkar\Import;

final class CsvReadException extends \RuntimeException {
	/**
	 * @param array<string, scalar|null> $context Additional machine-readable data.
	 */
	public function __construct(
		private readonly string $issue_code,
		string $message,
		private readonly array $context = array()
	) {
		parent::__construct( $message );
	}

	public function issue_code(): string {
		return $this->issue_code;
	}

	/**
	 * @return array<string, scalar|null>
	 */
	public function context(): array {
		return $this->context;
	}
}
