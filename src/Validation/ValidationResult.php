<?php

namespace Tazkar\Validation;

final class ValidationResult {
	/** @var ValidationIssue[] */
	private array $issues = array();

	/**
	 * @param array<string, mixed> $row Exact unmodified input row.
	 */
	public function __construct(
		private readonly array $row,
		private readonly ?int $row_number = null
	) {}

	public function add( ValidationIssue $issue ): void {
		$this->issues[] = $issue;
	}

	public function error(
		string $code,
		?string $field,
		string $message,
		array $context = array()
	): void {
		$this->add(
			new ValidationIssue(
				$code,
				Severity::ERROR,
				$field,
				$message,
				$this->row_number,
				$context
			)
		);
	}

	public function warning(
		string $code,
		?string $field,
		string $message,
		array $context = array()
	): void {
		$this->add(
			new ValidationIssue(
				$code,
				Severity::WARNING,
				$field,
				$message,
				$this->row_number,
				$context
			)
		);
	}

	public function is_valid(): bool {
		return array() === $this->errors();
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function errors(): array {
		return array_values(
			array_filter(
				$this->issues,
				static fn ( ValidationIssue $issue ): bool => Severity::ERROR === $issue->severity()
			)
		);
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function warnings(): array {
		return array_values(
			array_filter(
				$this->issues,
				static fn ( ValidationIssue $issue ): bool => Severity::WARNING === $issue->severity()
			)
		);
	}

	/**
	 * @return ValidationIssue[]
	 */
	public function issues(): array {
		return $this->issues;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function row(): array {
		return $this->row;
	}

	public function row_number(): ?int {
		return $this->row_number;
	}
}
