<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Core\Requirements;

final class RequirementsTest extends TestCase {
    public function test_supported_versions_have_no_errors(): void {
        self::assertSame( array(), Requirements::unmet( '8.1.0', '6.8' ) );
    }

    public function test_unsupported_versions_return_two_errors(): void {
        self::assertCount( 2, Requirements::unmet( '8.0.30', '6.7.2' ) );
    }
}
