<?php

namespace Tazkar\Import;

interface ContentWriter {
	/** @param array<string, mixed> $row Validated source row. */
	public function create_draft( array $row, int $author_id ): int;

	public function delete( int $post_id ): void;
}
