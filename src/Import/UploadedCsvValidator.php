<?php

namespace Tazkar\Import;

final class UploadedCsvValidator {
	/** @var string[] */
	private const ALLOWED_MIME_TYPES = array(
		'text/csv',
		'text/plain',
		'application/csv',
		'application/vnd.ms-excel',
	);

	public function __construct(
		private readonly CsvPreviewOptions $options = new CsvPreviewOptions()
	) {}

	/**
	 * @param array<string, mixed> $file Normalized upload metadata.
	 */
	public function validate( array $file, bool $is_uploaded, string $detected_mime ): UploadedCsv {
		$error = $file['error'] ?? null;
		if ( UPLOAD_ERR_OK !== $error ) {
			throw new CsvReadException( CsvIssueCode::UPLOAD_ERROR, 'CSV upload failed.' );
		}
		if ( ! $is_uploaded ) {
			throw new CsvReadException( CsvIssueCode::NOT_UPLOADED, 'CSV temporary file is not an HTTP upload.' );
		}

		$name = is_string( $file['name'] ?? null ) ? $file['name'] : '';
		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			throw new CsvReadException( CsvIssueCode::INVALID_EXTENSION, 'Only .csv files are accepted.' );
		}
		if ( ! in_array( strtolower( $detected_mime ), self::ALLOWED_MIME_TYPES, true ) ) {
			throw new CsvReadException(
				CsvIssueCode::INVALID_MIME,
				'CSV MIME type is not allowed.',
				array( 'mime' => $detected_mime )
			);
		}

		$path = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$size = filesize( $path );
		if ( false === $size ) {
			throw new CsvReadException( CsvIssueCode::FILE_NOT_READABLE, 'Uploaded CSV size could not be read.' );
		}
		if ( $size > $this->options->max_bytes() ) {
			throw new CsvReadException( CsvIssueCode::FILE_TOO_LARGE, 'Uploaded CSV exceeds the size limit.' );
		}

		return new UploadedCsv( $path, $name, $size, strtolower( $detected_mime ) );
	}
}
