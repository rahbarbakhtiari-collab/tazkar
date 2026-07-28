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
                imported_rows int(10) unsigned NOT NULL DEFAULT 0,
                skipped_rows int(10) unsigned NOT NULL DEFAULT 0,
                invalid_rows int(10) unsigned NOT NULL DEFAULT 0,
                error_rows int(10) unsigned NOT NULL DEFAULT 0,
                pending_rows int(10) unsigned NOT NULL DEFAULT 0,
                rollback_conflicts int(10) unsigned NOT NULL DEFAULT 0,
                next_row_number int(10) unsigned NOT NULL DEFAULT 1,
                chunk_size smallint(5) unsigned NOT NULL DEFAULT 25,
                lock_token char(64) NOT NULL DEFAULT '',
                lock_expires_at datetime NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                completed_at datetime NULL,
                rollback_started_at datetime NULL,
                rolled_back_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_uuid (batch_uuid),
                KEY source_checksum (source_checksum),
                KEY status (status),
                KEY created_by_status (created_by,status),
                KEY lock_expires_at (lock_expires_at)
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
                staged_payload longtext NULL,
                attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
                claimed_at datetime NULL,
                terminal_at datetime NULL,
                payload_removed_at datetime NULL,
                rollback_status varchar(32) NOT NULL DEFAULT '',
                rollback_error_code varchar(100) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_row (batch_id,`row_number`),
                KEY batch_status (batch_id,status),
                KEY record_id (record_id),
                KEY status (status),
                KEY wp_post_id (wp_post_id),
                KEY rollback_status (rollback_status)
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
