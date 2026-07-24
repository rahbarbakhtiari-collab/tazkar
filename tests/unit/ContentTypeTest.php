<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\ContentType;
use Tazkar\Content\Taxonomies;

final class ContentTypeTest extends TestCase {
    public function test_content_type_matches_architecture_contract(): void {
        $arguments = ContentType::arguments();

        self::assertTrue( $arguments['public'] );
        self::assertTrue( $arguments['show_in_rest'] );
        self::assertTrue( $arguments['has_archive'] );
        self::assertSame( array( 'title', 'author', 'revisions' ), $arguments['supports'] );
        self::assertNotContains( 'editor', $arguments['supports'] );
        self::assertSame( 'reminders', $arguments['rewrite']['slug'] );
    }

    public function test_taxonomy_definitions_are_scoped_and_rest_visible(): void {
        $definitions = Taxonomies::definitions();

        self::assertArrayHasKey( Taxonomies::TOPIC, $definitions );
        self::assertArrayHasKey( Taxonomies::TAG, $definitions );
        self::assertTrue( $definitions[ Taxonomies::TOPIC ]['hierarchical'] );
        self::assertFalse( $definitions[ Taxonomies::TAG ]['hierarchical'] );
        self::assertTrue( $definitions[ Taxonomies::TOPIC ]['show_in_rest'] );
        self::assertTrue( $definitions[ Taxonomies::TAG ]['show_in_rest'] );
    }
}
