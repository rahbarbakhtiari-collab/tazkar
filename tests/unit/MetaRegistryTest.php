<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\MetaRegistry;

final class MetaRegistryTest extends TestCase {
    public function test_all_persistent_contract_meta_is_registered(): void {
        $definitions = MetaRegistry::definitions();

        self::assertCount( 29, $definitions );
        self::assertArrayHasKey( 'arabic_text', $definitions );
        self::assertArrayHasKey( 'review_status', $definitions );
        self::assertArrayHasKey( 'import_status', $definitions );
    }

    public function test_public_rest_allowlist_excludes_internal_review_and_batch_fields(): void {
        $public = MetaRegistry::public_fields();

        self::assertContains( 'arabic_text', $public );
        self::assertContains( 'translation_corrected', $public );
        self::assertContains( 'source_corrected', $public );
        self::assertNotContains( 'batch_id', $public );
        self::assertNotContains( 'review_status', $public );
        self::assertNotContains( 'reviewer_notes', $public );
        self::assertNotContains( 'meaning_confidence', $public );
        self::assertNotContains( 'import_status', $public );
    }

    public function test_arabic_sanitizer_preserves_exact_unicode_and_spacing(): void {
        $arabic = "  إِنَّ النَّفْسَ لَأَمَّارَةٌ بِالسُّوءِ\n";

        self::assertSame( $arabic, MetaRegistry::sanitize_arabic( $arabic ) );
    }

    public function test_enum_sanitizers_reject_unknown_values(): void {
        self::assertSame( 'approved', MetaRegistry::sanitize_review_status( 'approved' ) );
        self::assertSame( '', MetaRegistry::sanitize_review_status( 'published' ) );
        self::assertSame( '', MetaRegistry::sanitize_confidence( 'certain' ) );
    }
}
