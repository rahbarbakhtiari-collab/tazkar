<?php

namespace Tazkar\Core;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class Capabilities {
	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			'manage_tazkar',
			'import_tazkar_items',
			'review_tazkar_items',
			'publish_tazkar_items',
			'manage_tazkar_settings',
			'view_tazkar_feedback',
			'delete_tazkar_data',
		);
	}

	public static function maybe_sync(): void {
		if ( TAZKAR_VERSION === (string) get_option( 'tazkar_caps_version', '' ) ) {
			return;
		}

		self::sync();
	}

	public static function sync(): void {
		self::grant_to_administrator();
		update_option( 'tazkar_caps_version', TAZKAR_VERSION, false );
	}

	public static function grant_to_administrator(): void {
		$administrator = get_role( 'administrator' );

		if ( ! $administrator ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$administrator->add_cap( $capability );
		}
	}

	public static function revoke_from_administrator(): void {
		$administrator = get_role( 'administrator' );

		if ( ! $administrator ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$administrator->remove_cap( $capability );
		}
	}
}
