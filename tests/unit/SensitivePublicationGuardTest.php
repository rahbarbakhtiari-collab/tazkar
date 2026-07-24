<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Content\SensitivePublicationGuard;

final class SensitivePublicationGuardTest extends TestCase {
    public function test_published_stale_sensitive_content_must_be_unpublished(): void {
        self::assertTrue(
            SensitivePublicationGuard::should_unpublish(
                ContentType::POST_TYPE,
                'needs_recheck',
                'publish',
                MetaRegistry::key( 'arabic_text' )
            )
        );
    }

    public function test_other_states_or_fields_remain_unchanged(): void {
        self::assertFalse(
            SensitivePublicationGuard::should_unpublish(
                ContentType::POST_TYPE,
                'approved',
                'publish',
                MetaRegistry::key( 'arabic_text' )
            )
        );
        self::assertFalse(
            SensitivePublicationGuard::should_unpublish(
                ContentType::POST_TYPE,
                'needs_recheck',
                'draft',
                MetaRegistry::key( 'arabic_text' )
            )
        );
        self::assertFalse(
            SensitivePublicationGuard::should_unpublish(
                ContentType::POST_TYPE,
                'needs_recheck',
                'publish',
                MetaRegistry::key( 'motivational_text' )
            )
        );
    }
}
