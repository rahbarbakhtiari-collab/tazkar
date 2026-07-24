<?php

namespace Tazkar\Import;

final class UploadedCsv {
	public function __construct(
		private readonly string $path,
		private readonly string $name,
		private readonly int $size,
		private readonly string $mime
	) {}

	public function path(): string {
		return $this->path;
	}

	public function name(): string {
		return $this->name;
	}

	public function size(): int {
		return $this->size;
	}

	public function mime(): string {
		return $this->mime;
	}
}
