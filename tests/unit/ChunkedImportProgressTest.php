<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Import\ChunkedImportProgress;

final class ChunkedImportProgressTest extends TestCase {
	public function test_staged_batch_exposes_resume_and_rollback_actions(): void {
		$progress = new ChunkedImportProgress(
			'11111111-1111-4111-8111-111111111111',
			false,
			'staged',
			array( 'pending' => 2, 'imported' => 0 ),
			array()
		);

		self::assertTrue( $progress->can_resume() );
		self::assertTrue( $progress->can_rollback() );
	}

	public function test_completed_batch_cannot_resume_but_can_rollback_imports(): void {
		$progress = new ChunkedImportProgress(
			'11111111-1111-4111-8111-111111111111',
			false,
			'completed',
			array( 'pending' => 0, 'imported' => 3 ),
			array()
		);

		self::assertFalse( $progress->can_resume() );
		self::assertTrue( $progress->can_rollback() );
	}

	public function test_terminal_rollback_exposes_no_state_changing_action(): void {
		$progress = new ChunkedImportProgress(
			'11111111-1111-4111-8111-111111111111',
			false,
			'rollback_with_conflicts',
			array( 'pending' => 0, 'imported' => 2 ),
			array()
		);

		self::assertFalse( $progress->can_resume() );
		self::assertFalse( $progress->can_rollback() );
	}
}
