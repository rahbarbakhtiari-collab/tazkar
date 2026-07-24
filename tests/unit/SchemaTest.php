<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Infrastructure\Database\Schema;

final class SchemaTest extends TestCase {
    public function test_table_names_respect_custom_prefix(): void {
        self::assertSame(
            'custom_tazkar_import_batches',
            Schema::table_names( 'custom_' )['import_batches']
        );
    }

    public function test_schema_contains_four_tables(): void {
        self::assertCount( 4, Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' ) );
    }
}
