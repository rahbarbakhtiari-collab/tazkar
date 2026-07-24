<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Admin\CsvPreviewPage;
use Tazkar\Import\CsvPreviewService;
use Tazkar\Import\UploadedCsvValidator;

final class CsvPreviewPageSecurityTest extends TestCase {
	protected function tearDown(): void {
		$GLOBALS['tazkar_test_current_user_can'] = true;
		$GLOBALS['tazkar_test_nonce_action']     = null;
		$_SERVER['REQUEST_METHOD']               = 'GET';
		$_FILES                                  = array();
	}

	public function test_page_requires_import_capability(): void {
		$GLOBALS['tazkar_test_current_user_can'] = false;
		$_SERVER['REQUEST_METHOD']               = 'GET';

		$this->expectException( RuntimeException::class );
		( new CsvPreviewPage() )->render();
	}

	public function test_post_request_checks_dedicated_nonce_before_upload_processing(): void {
		$GLOBALS['tazkar_test_current_user_can'] = true;
		$_SERVER['REQUEST_METHOD']               = 'POST';
		$_FILES                                  = array();

		ob_start();
		( new CsvPreviewPage() )->render();
		ob_end_clean();

		self::assertSame(
			array( 'tazkar_csv_preview', 'tazkar_csv_preview_nonce' ),
			$GLOBALS['tazkar_test_nonce_action']
		);
	}

	public function test_mime_is_not_detected_before_http_upload_origin_is_proven(): void {
		$path = tempnam( sys_get_temp_dir(), 'tazkar-not-uploaded-' );
		self::assertIsString( $path );
		file_put_contents( $path, "schema_version,record_id\n1.1,test\n" );

		$mime_detector_called = false;
		$page                 = new CsvPreviewPage(
			new CsvPreviewService(),
			new UploadedCsvValidator(),
			static function ( string $candidate_path ) use ( &$mime_detector_called ): string {
				unset( $candidate_path );
				$mime_detector_called = true;

				return 'text/csv';
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_FILES                    = array(
			'tazkar_csv_file' => array(
				'name'     => 'test.csv',
				'tmp_name' => $path,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $path ),
				'type'     => 'text/csv',
			),
		);

		ob_start();
		$page->render();
		ob_end_clean();
		@unlink( $path );

		self::assertFalse( $mime_detector_called );
	}
}
