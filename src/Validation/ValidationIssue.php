<?php

namespace Tazkar\Validation;

final class ValidationIssue {
	/**
	 * @param array<string, scalar|null> $context Additional machine-readable data.
	 */
	public function __construct(
		private readonly string $code,
		private readonly Severity $severity,
		private readonly ?string $field,
		private readonly string $message,
		private readonly ?int $row_number = null,
		private readonly array $context = array()
	) {}

	public function code(): string {
		return $this->code;
	}

	public function severity(): Severity {
		return $this->severity;
	}

	public function field(): ?string {
		return $this->field;
	}

	public function message(): string {
		return $this->message;
	}

	public function rowNumber(): ?int {
		return $this->row_number;
	}

	/**
	 * @return array<string, scalar|null>
	 */
	public function context(): array {
		return $this->context;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'code'       => $this->code,
			'severity'   => $this->severity->value,
			'field'      => $this->field,
			'message'    => $this->message,
			'row_number' => $this->row_number,
			'context'    => $this->context,
		);
	}
}
