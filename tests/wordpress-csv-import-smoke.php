<?php

use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Import\CsvImportService;
use Tazkar\Infrastructure\Database\Schema;
use Tazkar\Validation\ContractSchema;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$fixture_json = file_get_contents( __DIR__ . '/fixtures/validation/valid-hadith.json' );
if ( false === $fixture_json ) {
	$fail( 'Validation fixture could not be read.' );
}
$valid = json_decode( $fixture_json, true );
if ( ! is_array( $valid ) ) {
	$fail( 'Validation fixture is invalid.' );
}

$duplicate = $valid;
$invalid   = $valid;
$invalid['record_id']       = 'wp-import-invalid';
$invalid['generated_title'] = '';
$approved = $valid;
$approved['record_id']     = 'wp-import-approved';
$approved['review_status'] = 'approved';

$headers = ContractSchema::columns();
$path    = tempnam( sys_get_temp_dir(), 'tazkar-wp-import-' );
if ( false === $path ) {
	$fail( 'Temporary CSV could not be created.' );
}
$handle = fopen( $path, 'wb' );
if ( false === $handle ) {
	$fail( 'Temporary CSV could not be opened.' );
}
fputcsv( $handle, $headers, ',', '"', '' );
foreach ( array( $valid, $duplicate, $invalid, $approved ) as $row ) {
	fputcsv(
		$handle,
		array_map(
			static fn ( string $header ) => $row[ $header ] ?? '',
			$headers
		),
		',',
		'"',
		''
	);
}
fclose( $handle );

global $wpdb;
$tables          = Schema::table_names( $wpdb->prefix );
$posts_before    = (int) wp_count_posts( ContentType::POST_TYPE )->draft;
$batches_before  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $tables['import_batches'] );
$rows_before     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $tables['import_rows'] );

try {
	$service = new CsvImportService();
	$first   = $service->import( $path, '../mixed-import.csv', 1 );
	$second  = $service->import( $path, '..\\mixed-import.csv', 1 );
} finally {
	@unlink( $path );
}

if ( 1 !== $first->imported_rows() || 2 !== $first->skipped_rows() || 1 !== $first->invalid_rows() ) {
	$fail( 'Mixed CSV import counts are incorrect.' );
}
if ( ! $second->repeated_source() || 0 !== $second->imported_rows() ) {
	$fail( 'Repeated CSV did not suppress duplicate posts.' );
}

$posts_after = (int) wp_count_posts( ContentType::POST_TYPE )->draft;
if ( $posts_before + 1 !== $posts_after ) {
	$fail( 'CSV import did not create exactly one draft.' );
}
if ( $batches_before + 2 !== (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $tables['import_batches'] ) ) {
	$fail( 'CSV import batches were not recorded.' );
}
if ( $rows_before + 8 !== (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $tables['import_rows'] ) ) {
	$fail( 'CSV import row results were not recorded for every row.' );
}

$post_id = $first->rows()[0]->post_id();
if ( $post_id <= 0 || 'draft' !== get_post_status( $post_id ) ) {
	$fail( 'Imported item is not a draft.' );
}
if ( 'needs_review' !== get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true ) ) {
	$fail( 'Imported item was not forced to needs_review.' );
}
if ( 'imported' !== get_post_meta( $post_id, MetaRegistry::key( 'import_status' ), true ) ) {
	$fail( 'Imported item status was not server-owned.' );
}
if ( $valid['arabic_text'] !== get_post_meta( $post_id, MetaRegistry::key( 'arabic_text' ), true ) ) {
	$fail( 'CSV import mutated Arabic text.' );
}
if ( 1 !== (int) get_post_field( 'post_author', $post_id ) ) {
	$fail( 'CSV import did not use the authenticated server author.' );
}
if ( 'publish' === get_post_status( $post_id ) ) {
	$fail( 'CSV import published an item.' );
}

$imported_rows = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . $tables['import_rows'] . ' WHERE status = %s AND wp_post_id = %d',
		'imported',
		$post_id
	)
);
if ( 1 !== $imported_rows ) {
	$fail( 'Imported row result is incoherent.' );
}

echo "Tazkar Sprint 4 WordPress CSV import smoke passed.\n";
