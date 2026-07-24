<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Validation\ContractSchema;
use Tazkar\Validation\HeaderValidator;
use Tazkar\Validation\IssueCode;

final class ContractSchemaTest extends TestCase {
    public function test_contract_defines_exactly_thirty_five_known_columns(): void {
        self::assertCount( 35, ContractSchema::columns() );
        self::assertSame( ContractSchema::columns(), array_values( array_unique( ContractSchema::columns() ) ) );
    }

    public function test_header_order_does_not_matter(): void {
        $headers = array_reverse( ContractSchema::columns() );
        $result  = ( new HeaderValidator() )->validate( $headers );

        self::assertTrue( $result->isValid() );
        self::assertSame( array(), $result->warnings() );
    }

    public function test_missing_duplicate_and_unknown_headers_are_reported(): void {
        $headers   = ContractSchema::columns();
        $headers   = array_values( array_filter( $headers, static fn ( string $field ): bool => 'arabic_text' !== $field ) );
        $headers[] = 'record_id';
        $headers[] = 'unexpected_column';
        $result    = ( new HeaderValidator() )->validate( $headers );
        $codes     = array_map( static fn ( $issue ): string => $issue->code(), $result->issues() );

        self::assertContains( IssueCode::HEADER_MISSING_REQUIRED, $codes );
        self::assertContains( IssueCode::HEADER_DUPLICATE, $codes );
        self::assertContains( IssueCode::HEADER_UNKNOWN, $codes );
        self::assertFalse( $result->isValid() );
    }
}
