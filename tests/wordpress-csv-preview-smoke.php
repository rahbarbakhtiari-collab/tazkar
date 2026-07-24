<?php

use Tazkar\Content\ContentType;
use Tazkar\Import\CsvPreviewService;
use Tazkar\Infrastructure\Database\Schema;
use Tazkar\Validation\ContractSchema;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$fixture_json = file_get_contents( __DIR__ . '/fixtures/validation/valid-quran.json' );
if ( false === $fixture_json ) {
	$fail( 'Validation fixture could not be read.' );
}

$row = json_decode( $fixture_json, true );
if ( ! is_array( $row ) ) {
	$fail( 'Validation fixture is invalid.' );
}

$headers = array_reverse( ContractSchema::columns() );
$values  = array_map( static fn ( string $header ) => $row[ $header ] ?? '', $headers );
$path    = tempnam( sys_get_temp_dir(), 'tazkar-wp-csv-' );
if ( false === $path ) {
	$fail( 'Temporary CSV could not be created.' );
}

$handle = fopen( $path, 'wb' );
if ( false === $handle ) {
	$fail( 'Temporary CSV could not be opened.' );
}
fwrite( $handle, "\xEF\xBB\xBF" );
fputcsv( $handle, $headers, ',', '"', '' );
fputcsv( $handle, $values, ',', '"', '' );
fclose( $handle );

global $wpdb;
$table_names = Schema::table_names( $wpdb->prefix );
$post_before = (int) wp_count_posts( ContentType::POST_TYPE )->draft
	+ (int) wp_count_posts( ContentType::POST_TYPE )->publish;
$batch_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table_names['import_batches'] );
$row_before   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table_names['import_rows'] );

try {
	$result = ( new CsvPreviewService() )->preview( $path, '../unsafe-preview.csv' );
} finally {
	@unlink( $path );
}

if ( ! $result->is_valid() || 1 !== $result->total_rows() || 1 !== $result->valid_rows() ) {
	$fail( 'Valid CSV preview did not pass.' );
}
if ( $row['arabic_text'] !== $result->validation()->rows()[0]->row()['arabic_text'] ) {
	$fail( 'CSV preview mutated Arabic text.' );
}
if ( 'unsafe-preview.csv' !== $result->filename() ) {
	$fail( 'CSV filename was not safely reduced to a basename.' );
}

$post_after = (int) wp_count_posts( ContentType::POST_TYPE )->draft
	+ (int) wp_count_posts( ContentType::POST_TYPE )->publish;
$batch_after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table_names['import_batches'] );
$row_after   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table_names['import_rows'] );

if ( $post_before !== $post_after || $batch_before !== $batch_after || $row_before !== $row_after ) {
	$fail( 'CSV preview persisted posts or import records.' );
}

echo "Tazkar Sprint 3 WordPress CSV preview smoke passed.\n";
