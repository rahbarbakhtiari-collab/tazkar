<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Admin\CsvPreviewPage;

final class CsvPreviewPageImportSecurityTest extends TestCase {
	protected function tearDown(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_POST                     = array();
		$_FILES                    = array();
		$GLOBALS['tazkar_test_nonce_action'] = null;
	}

	public function test_import_request_checks_a_separate_nonce(): void {
		$_SERVER['REQUEST_METHOD']     = 'POST';
		$_POST['tazkar_csv_action']    = 'import';
		$_FILES                       = array();

		ob_start();
		( new CsvPreviewPage() )->render();
		ob_end_clean();

		self::assertSame(
			array( 'tazkar_csv_import', 'tazkar_csv_import_nonce' ),
			$GLOBALS['tazkar_test_nonce_action']
		);
	}
}
