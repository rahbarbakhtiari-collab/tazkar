<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Validation\BatchValidator;
use Tazkar\Validation\ContractSchema;
use Tazkar\Validation\IssueCode;

final class BatchValidatorTest extends TestCase {
    public function test_duplicate_record_ids_are_attached_to_every_duplicate_row(): void {
        $first                = $this->fixture( 'valid-quran.json' );
        $second               = $this->fixture( 'valid-hadith.json' );
        $second['record_id']  = $first['record_id'];
        $second['batch_id']   = $first['batch_id'];
        $result               = ( new BatchValidator() )->validate( ContractSchema::columns(), array( $first, $second ) );
        $row_results          = $result->rows();

        self::assertFalse( $result->isValid() );
        foreach ( $row_results as $row_result ) {
            $codes = array_map( static fn ( $issue ): string => $issue->code(), $row_result->errors() );
            self::assertContains( IssueCode::DUPLICATE_RECORD_ID, $codes );
        }
        self::assertSame( 2, $row_results[0]->rowNumber() );
        self::assertSame( 3, $row_results[1]->rowNumber() );
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture( string $name ): array {
        $json = file_get_contents( dirname( __DIR__ ) . '/fixtures/validation/' . $name );
        self::assertNotFalse( $json );

        $decoded = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
        self::assertIsArray( $decoded );

        return $decoded;
    }
}
