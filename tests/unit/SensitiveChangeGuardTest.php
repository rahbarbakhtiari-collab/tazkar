<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Content\SensitiveChangeGuard;

final class SensitiveChangeGuardTest extends TestCase {
    public function test_approved_sensitive_change_requires_recheck(): void {
        self::assertTrue(
            SensitiveChangeGuard::should_mark_for_recheck(
                ContentType::POST_TYPE,
                'approved',
                MetaRegistry::key( 'arabic_text' ),
                'الف',
                'ب'
            )
        );
    }

    public function test_same_value_does_not_require_recheck(): void {
        self::assertFalse(
            SensitiveChangeGuard::should_mark_for_recheck(
                ContentType::POST_TYPE,
                'approved',
                MetaRegistry::key( 'surah_number' ),
                '12',
                12
            )
        );
    }

    public function test_non_sensitive_or_unapproved_change_does_not_require_recheck(): void {
        self::assertFalse(
            SensitiveChangeGuard::should_mark_for_recheck(
                ContentType::POST_TYPE,
                'approved',
                MetaRegistry::key( 'motivational_text' ),
                'الف',
                'ب'
            )
        );
        self::assertFalse(
            SensitiveChangeGuard::should_mark_for_recheck(
                ContentType::POST_TYPE,
                'needs_review',
                MetaRegistry::key( 'arabic_text' ),
                'الف',
                'ب'
            )
        );
    }

    public function test_arabic_hash_uses_exact_bytes(): void {
        $first  = 'آیه';
        $second = ' آیه';

        self::assertSame( hash( 'sha256', $first ), SensitiveChangeGuard::hash_arabic( $first ) );
        self::assertNotSame( SensitiveChangeGuard::hash_arabic( $first ), SensitiveChangeGuard::hash_arabic( $second ) );
    }
}
