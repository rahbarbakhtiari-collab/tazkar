<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\PublicMetaAccess;

final class PublicMetaAccessTest extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['tazkar_test_post_status'] = 'publish';
    }

    public function test_published_meta_is_readable_without_weakening_write_checks(): void {
        $GLOBALS['tazkar_test_post_status'] = 'publish';

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
    }

    public function test_draft_meta_keeps_existing_authorization_result(): void {
        $GLOBALS['tazkar_test_post_status'] = 'draft';

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
        self::assertTrue(
            PublicMetaAccess::authorize(
                true,
                'tazkar_arabic_text',
                42,
                1,
                'read_post_meta',
                array( 'edit_tazkar_item' )
            )
        );
    }
}
