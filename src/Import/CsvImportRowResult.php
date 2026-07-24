<?php

namespace Tazkar\Import;

final class CsvImportRowResult {
	public function __construct(
		private readonly int $row_number,
		private readonly string $record_id,
		private readonly string $status,
		private readonly string $issue_code,
		private readonly int $post_id,
		private readonly string $payload_hash
	) {}

	public function row_number(): int {
		return $this->row_number;
	}

	public function record_id(): string {
		return $this->record_id;
	}

	public function status(): string {
		return $this->status;
	}

	public function issue_code(): string {
		return $this->issue_code;
	}

	public function post_id(): int {
		return $this->post_id;
	}

	public function payload_hash(): string {
		return $this->payload_hash;
	}
}
