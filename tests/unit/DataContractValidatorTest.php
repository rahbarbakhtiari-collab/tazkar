<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Validation\DataContractValidator;
use Tazkar\Validation\IssueCode;

final class DataContractValidatorTest extends TestCase {
    private DataContractValidator $validator;

    protected function setUp(): void {
        $this->validator = new DataContractValidator();
    }

    public function test_valid_quran_and_hadith_rows_pass_without_mutation(): void {
        foreach ( array( 'valid-quran.json', 'valid-hadith.json' ) as $fixture ) {
            $row      = $this->fixture( $fixture );
            $original = $row;
            $result   = $this->validator->validate( $row, 2 );

            self::assertTrue( $result->is_valid(), $fixture );
            self::assertSame( $original, $result->row() );
            self::assertSame( $original['arabic_text'], $result->row()['arabic_text'] );
        }
    }

    public function test_invalid_fixture_covers_blocking_rule_families(): void {
        $result = $this->validator->validate( $this->fixture( 'invalid-row.json' ), 4 );
        $codes  = $this->codes( $result->errors() );

        foreach (
            array(
                IssueCode::FIELD_REQUIRED,
                IssueCode::FIELD_INVALID_TYPE,
                IssueCode::FIELD_INVALID_ENUM,
                IssueCode::SCHEMA_UNSUPPORTED,
                IssueCode::TRANSLATION_LANGUAGE_INVALID,
                IssueCode::QURAN_SPEAKER_NOT_EMPTY,
                IssueCode::QURAN_SURAH_NAME_REQUIRED,
                IssueCode::QURAN_SURAH_NUMBER_RANGE,
                IssueCode::QURAN_AYAH_START_POSITIVE,
                IssueCode::QURAN_AYAH_END_BEFORE_START,
                IssueCode::TITLE_HTML,
                IssueCode::TITLE_SYMBOLS,
                IssueCode::REFLECTION_SENTENCE_COUNT,
                IssueCode::SECONDARY_TOPICS_COUNT,
                IssueCode::TAGS_COUNT,
                IssueCode::SLUG_INVALID,
                IssueCode::SOURCE_NOT_FROM_ORIGINAL,
                IssueCode::WP_POST_ID_NOT_EMPTY,
            ) as $code
        ) {
            self::assertContains( $code, $codes, $code );
        }
    }

    public function test_hadith_requires_speaker_and_rejects_quran_fields(): void {
        $row                 = $this->fixture( 'valid-hadith.json' );
        $row['speaker']      = '';
        $row['surah_name']   = 'حمد';
        $row['surah_number'] = '1';
        $row['ayah_start']   = '1';
        $result              = $this->validator->validate( $row );
        $codes               = $this->codes( $result->errors() );

        self::assertContains( IssueCode::HADITH_SPEAKER_REQUIRED, $codes );
        self::assertContains( IssueCode::HADITH_SURAH_DATA_NOT_EMPTY, $codes );
    }

    public function test_maximum_lengths_and_text_restrictions_block_import(): void {
        $row                       = $this->fixture( 'valid-quran.json' );
        $row['record_id']          = str_repeat( 'a', 65 );
        $row['generated_title']    = str_repeat( 'ا', 81 );
        $row['motivational_text']  = str_repeat( 'ب', 281 );
        $row['reviewer_notes']     = str_repeat( 'پ', 1001 );
        $row['generated_title']   .= "\n";
        $row['motivational_text'] .= "\n#";
        $result                    = $this->validator->validate( $row );
        $codes                     = $this->codes( $result->errors() );

        self::assertContains( IssueCode::FIELD_MAX_LENGTH, $codes );
        self::assertContains( IssueCode::TITLE_NEWLINE, $codes );
        self::assertContains( IssueCode::REFLECTION_NEWLINE, $codes );
        self::assertContains( IssueCode::REFLECTION_SYMBOLS, $codes );
    }

    public function test_all_decision_warning_families_are_emitted_without_blocking(): void {
        $row                        = $this->fixture( 'valid-hadith.json' );
        $row['generated_title']     = str_repeat( 'ت', 61 );
        $row['motivational_text']   = 'متن کوتاه برای هشدار طول.';
        $row['source_confidence']   = 'medium';
        $row['excerpt_scope']       = 'partial';
        $row['suggested_slug']      = '';
        $row['critical_issue']      = 'yes';
        $result                     = $this->validator->validate( $row );
        $codes                      = $this->codes( $result->warnings() );

        self::assertTrue( $result->is_valid() );
        self::assertContains( IssueCode::TITLE_LENGTH_WARNING, $codes );
        self::assertContains( IssueCode::REFLECTION_LENGTH_WARNING, $codes );
        self::assertContains( IssueCode::SOURCE_CONFIDENCE_MEDIUM, $codes );
        self::assertContains( IssueCode::PARTIAL_EXCERPT, $codes );
        self::assertContains( IssueCode::SLUG_EMPTY, $codes );
        self::assertContains( IssueCode::CRITICAL_ISSUE, $codes );
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

    /**
     * @param array<int, object> $issues Validation issues.
     * @return string[]
     */
    private function codes( array $issues ): array {
        return array_map( static fn ( $issue ): string => $issue->code(), $issues );
    }
}
