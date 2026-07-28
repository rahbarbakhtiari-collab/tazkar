<?php

namespace Tazkar\Import;

interface RollbackAwareContentWriter extends ContentWriter {
	public const ROLLBACK_TRASHED         = 'trashed';
	public const ROLLBACK_ALREADY_TRASHED = 'already_trashed';
	public const ROLLBACK_CONFLICT        = 'conflict';
	public const ROLLBACK_MISSING         = 'missing';

	public function mark_import_ownership( int $post_id, string $batch_uuid, int $row_id ): void;

	public function rollback_owned_draft( int $post_id, string $batch_uuid, int $row_id ): string;
}
