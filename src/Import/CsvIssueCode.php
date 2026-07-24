<?php

namespace Tazkar\Import;

final class CsvIssueCode {
	public const FILE_NOT_READABLE  = 'csv.file_not_readable';
	public const FILE_TOO_LARGE     = 'csv.file_too_large';
	public const EMPTY_FILE         = 'csv.empty_file';
	public const INVALID_UTF8       = 'csv.invalid_utf8';
	public const TOO_MANY_COLUMNS   = 'csv.too_many_columns';
	public const TOO_MANY_ROWS      = 'csv.too_many_rows';
	public const ROW_WIDTH_MISMATCH = 'csv.row_width_mismatch';
	public const FORMULA_LIKE_TEXT  = 'csv.formula_like_text';
	public const UPLOAD_ERROR       = 'csv.upload_error';
	public const NOT_UPLOADED       = 'csv.not_uploaded';
	public const INVALID_EXTENSION  = 'csv.invalid_extension';
	public const INVALID_MIME       = 'csv.invalid_mime';

	private function __construct() {}
}
