<?php

namespace Tazkar\Import;

final class CsvImportIssueCode {
	public const HEADER_INVALID          = 'import.header_invalid';
	public const ROW_INVALID             = 'import.row_invalid';
	public const SOURCE_APPROVED         = 'import.source_approved_forbidden';
	public const DUPLICATE_BATCH_RECORD  = 'import.duplicate_batch_record_id';
	public const DUPLICATE_BATCH_PAYLOAD = 'import.duplicate_batch_payload';
	public const DUPLICATE_PRIOR         = 'import.duplicate_prior';
	public const ROW_WRITE_FAILED        = 'import.row_write_failed';

	private function __construct() {}
}
