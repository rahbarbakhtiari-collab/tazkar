<?php

use Tazkar\Infrastructure\Database\Migrator;
use Tazkar\Infrastructure\Database\Schema;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

global $wpdb;
$tables          = Schema::table_names( $wpdb->prefix );
$charset_collate = $wpdb->get_charset_collate();

$wpdb->query( 'DROP TABLE IF EXISTS ' . $tables['import_rows'] );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $tables['import_batches'] );

$batch_created = $wpdb->query(
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
		PRIMARY KEY (id),
		UNIQUE KEY batch_uuid (batch_uuid),
		KEY source_checksum (source_checksum),
		KEY status (status)
	) {$charset_collate}"
);
$row_created = $wpdb->query(
	"CREATE TABLE {$tables['import_rows']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_id bigint(20) unsigned NOT NULL,
		row_number int(10) unsigned NOT NULL,
		record_id varchar(100) NOT NULL DEFAULT '',
		status varchar(32) NOT NULL DEFAULT 'pending',
		error_code varchar(100) NOT NULL DEFAULT '',
		wp_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		payload_hash char(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY batch_row (batch_id,row_number),
		KEY record_id (record_id),
		KEY status (status),
		KEY wp_post_id (wp_post_id)
	) {$charset_collate}"
);
if ( false === $batch_created || false === $row_created ) {
	$fail( 'Version 1 import tables could not be created.' );
}

$now      = current_time( 'mysql', true );
$checksum = hash( 'sha256', 'tazkar-v1-migration-smoke' );
$inserted = $wpdb->insert(
	$tables['import_batches'],
	array(
		'batch_uuid'      => '22222222-2222-4222-8222-222222222222',
		'source_name'     => 'migration-v1.csv',
		'source_checksum' => $checksum,
		'schema_version'  => '1.1',
		'status'          => 'completed',
		'total_rows'      => 1,
		'processed_rows'  => 1,
		'created_by'      => 1,
		'created_at'      => $now,
		'updated_at'      => $now,
	),
	array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
);
if ( false === $inserted ) {
	$fail( 'Version 1 batch fixture could not be inserted.' );
}
$batch_id = (int) $wpdb->insert_id;
$inserted = $wpdb->insert(
	$tables['import_rows'],
	array(
		'batch_id'     => $batch_id,
		'row_number'   => 2,
		'record_id'    => 'migration-preserved-record',
		'status'       => 'imported',
		'error_code'   => '',
		'wp_post_id'   => 0,
		'payload_hash' => hash( 'sha256', 'migration-preserved-payload' ),
		'created_at'   => $now,
		'updated_at'   => $now,
	),
	array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
);
if ( false === $inserted ) {
	$fail( 'Version 1 row fixture could not be inserted.' );
}

update_option( 'tazkar_db_version', '1', false );
Migrator::maybe_migrate();
Migrator::maybe_migrate();

if ( '2' !== (string) get_option( 'tazkar_db_version' ) ) {
	$fail( 'Database version was not upgraded to 2.' );
}
if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE id = %d', $tables['import_batches'], $batch_id ) ) ) {
	$fail( 'Version 1 batch data was not preserved.' );
}
if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE batch_id = %d AND record_id = %s', $tables['import_rows'], $batch_id, 'migration-preserved-record' ) ) ) {
	$fail( 'Version 1 row data was not preserved.' );
}

foreach ( array( 'pending_rows', 'chunk_size', 'lock_token', 'rollback_conflicts' ) as $column ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $tables['import_batches'], $column ) );
	if ( $column !== $found ) {
		$fail( 'Batch migration column is missing: ' . $column );
	}
}
foreach ( array( 'staged_payload', 'attempt_count', 'payload_removed_at', 'rollback_status' ) as $column ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $tables['import_rows'], $column ) );
	if ( $column !== $found ) {
		$fail( 'Row migration column is missing: ' . $column );
	}
}

if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE batch_id = %d', $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Repeated migration changed the preserved row count.' );
}

echo "Tazkar schema v1-to-v2 migration smoke passed.\n";
