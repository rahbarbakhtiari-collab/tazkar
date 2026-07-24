<?php

namespace Tazkar\Core;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class Deactivator {
    public static function deactivate(): void {
        // Permanent data and capabilities are intentionally preserved.
        delete_transient( 'tazkar_admin_status' );
    }
}
