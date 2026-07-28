<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Admin\CsvPreviewPage;

final class CsvPreviewPageImportSecurityTest extends TestCase {
	protected function tearDown(): void {
		$_POST                                   = array();
		$_FILES                                  = array();
		$GLOBALS['tazkar_test_nonce_action']     = null;
		$GLOBALS['tazkar_test_current_user_can'] = true;
		$GLOBALS['tazkar_test_actions']          = array();
		$GLOBALS['tazkar_test_options']          = array();
	}

	public function test_all_state_changing_admin_actions_are_registered(): void {
		( new CsvPreviewPage() )->register();

		self::assertArrayHasKey( 'admin_post_tazkar_import_start', $GLOBALS['tazkar_test_actions'] );
		self::assertArrayHasKey( 'admin_post_tazkar_import_process', $GLOBALS['tazkar_test_actions'] );
		self::assertArrayHasKey( 'admin_post_tazkar_import_resume', $GLOBALS['tazkar_test_actions'] );
		self::assertArrayHasKey( 'admin_post_tazkar_import_rollback', $GLOBALS['tazkar_test_actions'] );
	}

	public function test_start_action_checks_dedicated_nonce_before_upload_validation(): void {
		try {
			( new CsvPreviewPage() )->start_import();
			self::fail( 'The missing upload must stop the request.' );
		} catch ( RuntimeException $exception ) {
			unset( $exception );
		}

		self::assertSame(
			array( 'tazkar_import_start', 'tazkar_import_start_nonce' ),
			$GLOBALS['tazkar_test_nonce_action']
		);
	}

	public function test_process_action_uses_batch_bound_nonce(): void {
		$_POST['batch_uuid'] = '11111111-1111-4111-8111-111111111111';

		try {
			( new CsvPreviewPage() )->process_import();
			self::fail( 'The absent WordPress database must stop the request.' );
		} catch ( RuntimeException $exception ) {
			unset( $exception );
		}

		self::assertSame(
			array(
				'tazkar_import_process_11111111-1111-4111-8111-111111111111',
				'tazkar_import_nonce',
			),
			$GLOBALS['tazkar_test_nonce_action']
		);
	}

	public function test_import_actions_require_capability(): void {
		$GLOBALS['tazkar_test_current_user_can'] = false;

		$this->expectException( RuntimeException::class );
		( new CsvPreviewPage() )->start_import();
	}
}
