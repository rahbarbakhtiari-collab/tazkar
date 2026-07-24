<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Admin\CsvPreviewPage;

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
}
