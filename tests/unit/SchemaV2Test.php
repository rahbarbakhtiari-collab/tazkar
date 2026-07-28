<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Infrastructure\Database\Schema;

final class SchemaV2Test extends TestCase {
	public function test_schema_uses_custom_prefix_and_resumable_batch_columns(): void {
		$statements = Schema::create_statements( 'custom_', 'DEFAULT CHARACTER SET utf8mb4' );
		$batch_sql  = $statements[0];

		self::assertStringContainsString( 'custom_tazkar_import_batches', $batch_sql );
		self::assertStringContainsString( 'processed_rows', $batch_sql );
		self::assertStringContainsString( 'pending_rows', $batch_sql );
		self::assertStringContainsString( 'next_row_number', $batch_sql );
		self::assertStringContainsString( 'chunk_size', $batch_sql );
		self::assertStringContainsString( 'lock_token', $batch_sql );
		self::assertStringContainsString( 'rollback_conflicts', $batch_sql );
	}

	public function test_schema_stores_only_staged_row_payload_and_terminal_cleanup_state(): void {
		$statements = Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' );
		$row_sql    = $statements[1];

		self::assertStringContainsString( 'staged_payload longtext NULL', $row_sql );
		self::assertStringContainsString( 'attempt_count', $row_sql );
		self::assertStringContainsString( 'payload_removed_at', $row_sql );
		self::assertStringContainsString( 'rollback_status', $row_sql );
		self::assertStringContainsString( 'UNIQUE KEY batch_row', $row_sql );
	}

	public function test_schema_statements_are_deterministic_for_repeated_migration(): void {
		$first  = Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' );
		$second = Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' );

		self::assertSame( $first, $second );
		self::assertCount( 4, $first );
	}
}
