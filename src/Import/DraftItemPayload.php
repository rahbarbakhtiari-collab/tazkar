<?php

namespace Tazkar\Import;

final class DraftItemPayload {
	/**
	 * @param array<string, mixed> $post_data WordPress post fields owned by the server.
	 * @param array<string, mixed> $meta      Registered Tazkar meta values.
	 * @param string[]             $topics    Topic term names.
	 * @param string[]             $tags      Tag term names.
	 */
	public function __construct(
		private readonly array $post_data,
		private readonly array $meta,
		private readonly array $topics,
		private readonly array $tags
	) {}

	/** @return array<string, mixed> */
	public function post_data(): array {
		return $this->post_data;
	}

	/** @return array<string, mixed> */
	public function meta(): array {
		return $this->meta;
	}

	/** @return string[] */
	public function topics(): array {
		return $this->topics;
	}

	/** @return string[] */
	public function tags(): array {
		return $this->tags;
	}
}
