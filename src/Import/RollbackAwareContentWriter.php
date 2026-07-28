<?php

namespace Tazkar\Import;

interface RollbackAwareContentWriter extends ContentWriter {
	public const ROLLBACK_ELIGIBLE        = 'eligible';
	public const ROLLBACK_TRASHED         = 'trashed';
	public const ROLLBACK_ALREADY_TRASHED = 'already_trashed';
	public const ROLLBACK_PUBLISHED       = 'published';
	public const ROLLBACK_APPROVED        = 'approved';
	public const ROLLBACK_EDITED          = 'edited';
	public const ROLLBACK_FOREIGN         = 'foreign';
	public const ROLLBACK_CONFLICT        = 'conflict';
	public const ROLLBACK_MISSING         = 'missing';

	public function mark_import_ownership( int $post_id, string $batch_uuid, int $row_id ): void;

	/** @return array{outcome:string,interactions:int} */
	public function inspect_rollback( int $post_id, string $batch_uuid, int $row_id ): array;

	public function rollback_owned_draft( int $post_id, string $batch_uuid, int $row_id ): string;
}
