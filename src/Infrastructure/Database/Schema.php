<?php

namespace Tazkar\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class Schema {
	/**
	 * @return array<string,string>
	 */
	public static function table_names( string $prefix ): array {
		return array(
			'import_batches' => $prefix . 'tazkar_import_batches',
			'import_rows'    => $prefix . 'tazkar_import_rows',
			'feedback'       => $prefix . 'tazkar_feedback',
			'favorites'      => $prefix . 'tazkar_favorites',
		);
	}

	/**
	 * @return string[]
	 */
	public static function create_statements( string $prefix, string $charset_collate ): array {
		$tables = self::table_names( $prefix );

		return array(
			"CREATE TABLE {$tables['import_batches']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                batch_uuid varchar(64) NOT NULL,
                source_name varchar(255) NOT NULL DEFAULT '',
                source_checksum char(64) NOT NULL DEFAULT '',
                schema_version varchar(20) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'created',
                total_rows int(10) unsigned NOT NULL DEFAULT 0,
                processed_rows int(10) unsigned NOT NULL DEFAULT 0,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_uuid (batch_uuid),
                KEY source_checksum (source_checksum),
                KEY status (status)
            ) {$charset_collate};",
			"CREATE TABLE {$tables['import_rows']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                batch_id bigint(20) unsigned NOT NULL,
                `row_number` int(10) unsigned NOT NULL,
                record_id varchar(100) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'pending',
                error_code varchar(100) NOT NULL DEFAULT '',
                wp_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
                payload_hash char(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_row (batch_id,`row_number`),
                KEY record_id (record_id),
                KEY status (status),
                KEY wp_post_id (wp_post_id)
            ) {$charset_collate};",
			"CREATE TABLE {$tables['feedback']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                item_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                feedback_type varchar(50) NOT NULL,
                message text NULL,
                status varchar(32) NOT NULL DEFAULT 'open',
                created_at datetime NOT NULL,
                resolved_at datetime NULL,
                PRIMARY KEY  (id),
                KEY item_id (item_id),
                KEY user_id (user_id),
                KEY feedback_type (feedback_type),
                KEY status (status)
            ) {$charset_collate};",
			"CREATE TABLE {$tables['favorites']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                item_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_item (user_id,item_id),
                KEY item_id (item_id)
            ) {$charset_collate};",
		);
	}
}
