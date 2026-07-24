<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\PublicMetaAccess;

final class PublicMetaAccessTest extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['tazkar_test_post_status']   = 'publish';
        $GLOBALS['tazkar_test_review_status'] = 'approved';
    }

    public function test_only_published_and_approved_meta_is_publicly_readable(): void {
        $GLOBALS['tazkar_test_post_status']   = 'publish';
        $GLOBALS['tazkar_test_review_status'] = 'approved';

        self::assertTrue(
            PublicMetaAccess::authorize(
                false,
                'tazkar_arabic_text',
                42,
                0,
                'read_post_meta',
                array()
            )
        );

        $GLOBALS['tazkar_test_review_status'] = 'needs_recheck';
        self::assertFalse(
            PublicMetaAccess::authorize(
                false,
                'tazkar_arabic_text',
                42,
                0,
                'read_post_meta',
                array()
            )
        );

        $GLOBALS['tazkar_test_review_status'] = 'approved';
        $GLOBALS['tazkar_test_post_status']   = 'draft';
        self::assertFalse(
            PublicMetaAccess::authorize(
                false,
                'tazkar_arabic_text',
                42,
                0,
                'read_post_meta',
                array()
            )
        );
    }

    public function test_public_read_rule_does_not_weaken_write_checks(): void {
        self::assertFalse(
            PublicMetaAccess::authorize(
                false,
                'tazkar_arabic_text',
                42,
                0,
                'edit_post_meta',
                array()
            )
        );
        self::assertTrue(
            PublicMetaAccess::authorize(
                true,
                'tazkar_arabic_text',
                42,
                1,
                'edit_post_meta',
                array( 'edit_tazkar_item' )
            )
        );
    }
}
