<?php

use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Import\ChunkedImportService;
use Tazkar\Infrastructure\Database\Schema;
use Tazkar\Validation\ContractSchema;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$fixture_json = file_get_contents( __DIR__ . '/fixtures/validation/valid-hadith.json' );
if ( false === $fixture_json ) {
	$fail( 'Chunked import fixture could not be read.' );
}
$fixture = json_decode( $fixture_json, true );
if ( ! is_array( $fixture ) ) {
	$fail( 'Chunked import fixture is invalid.' );
}

$run_id  = strtolower( substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 10 ) );
$rows    = array();
$headers = ContractSchema::columns();
for ( $index = 0; $index < 60; ++$index ) {
	$row                     = $fixture;
	$row['record_id']        = sprintf( 'TZK-CHUNK-%s-%03d', $run_id, $index );
	$row['batch_id']         = 'TZK-CHUNK-' . $run_id;
	$row['generated_title']  = 'ورود قطعه‌ای تذکار ' . $index;
	$row['suggested_slug']   = 'tazkar-chunk-' . $run_id . '-' . $index;
	$row['review_status']    = 'extracted';
	$row['import_status']    = 'not_imported';
	$row['wp_post_id']       = '';
	$rows[]                  = $row;
}
$rows[] = $rows[0];
$invalid                    = $fixture;
$invalid['record_id']       = 'TZK-CHUNK-' . $run_id . '-INVALID';
$invalid['batch_id']        = 'TZK-CHUNK-' . $run_id;
$invalid['generated_title'] = '';
$rows[]                     = $invalid;
$approved                    = $fixture;
$approved['record_id']       = 'TZK-CHUNK-' . $run_id . '-APPROVED';
$approved['batch_id']        = 'TZK-CHUNK-' . $run_id;
$approved['generated_title'] = 'ردیف تأییدشده منبع';
$approved['review_status']   = 'approved';
$rows[]                      = $approved;

$path = tempnam( sys_get_temp_dir(), 'tazkar-wp-chunked-' );
if ( false === $path ) {
	$fail( 'Chunked import CSV could not be created.' );
}
$handle = fopen( $path, 'wb' );
if ( false === $handle ) {
	$fail( 'Chunked import CSV could not be opened.' );
}
fputcsv( $handle, $headers, ',', '"', '' );
foreach ( $rows as $row ) {
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
$tables       = Schema::table_names( $wpdb->prefix );
$posts_before = (int) wp_count_posts( ContentType::POST_TYPE )->draft;
$service      = new ChunkedImportService();

try {
	$progress = $service->stage( $path, '../chunked-import.csv', 1, 25 );
} finally {
	@unlink( $path );
}
if ( is_file( $path ) ) {
	$fail( 'Original upload fixture was retained after staging.' );
}
if ( 'staged' !== $progress->status() || 63 !== $progress->count( 'total' ) ) {
	$fail( 'Chunked import staging state is incorrect.' );
}
if ( 60 !== $progress->count( 'pending' ) || 2 !== $progress->count( 'skipped' ) || 1 !== $progress->count( 'invalid' ) ) {
	$fail( 'Chunked import staging classifications are incorrect.' );
}

$batch_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT id FROM %i WHERE batch_uuid = %s',
		$tables['import_batches'],
		$progress->batch_uuid()
	)
);
if ( $batch_id < 1 ) {
	$fail( 'Chunked import batch was not persisted.' );
}
if ( 60 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE batch_id = %d AND staged_payload IS NOT NULL', $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Only pending allowlisted payloads must be staged.' );
}

$progress = $service->process( $progress->batch_uuid(), 1 );
if ( 25 !== $progress->count( 'imported' ) || 35 !== $progress->count( 'pending' ) ) {
	$fail( 'First bounded chunk did not process exactly 25 pending rows.' );
}

$interrupted_row_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM %i WHERE batch_id = %d AND status = 'pending' ORDER BY row_number ASC LIMIT 1",
		$tables['import_rows'],
		$batch_id
	)
);
if ( $interrupted_row_id < 1 ) {
	$fail( 'No row was available for interruption recovery.' );
}
$wpdb->query(
	$wpdb->prepare(
		"UPDATE %i SET status = 'processing', attempt_count = attempt_count + 1, claimed_at = %s WHERE id = %d",
		$tables['import_rows'],
		current_time( 'mysql', true ),
		$interrupted_row_id
	)
);

$iterations = 0;
while ( $progress->count( 'pending' ) > 0 && $iterations < 10 ) {
	$progress = $service->resume( $progress->batch_uuid(), 1 );
	++$iterations;
}
if ( 'completed_with_errors' !== $progress->status() ) {
	$fail( 'Chunked import did not reach completed_with_errors.' );
}
if ( 60 !== $progress->count( 'imported' ) || 2 !== $progress->count( 'skipped' ) || 1 !== $progress->count( 'invalid' ) || 0 !== $progress->count( 'pending' ) ) {
	$fail( 'Chunked import terminal counts are incoherent.' );
}
if ( 63 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE batch_id = %d', $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Chunked import did not preserve one outcome per source row.' );
}
if ( 63 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE batch_id = %d AND staged_payload IS NULL AND payload_removed_at IS NOT NULL', $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Terminal rows retained staged payload.' );
}
if ( 2 > (int) $wpdb->get_var( $wpdb->prepare( 'SELECT attempt_count FROM %i WHERE id = %d', $tables['import_rows'], $interrupted_row_id ) ) ) {
	$fail( 'Interrupted processing row was not safely recovered and retried.' );
}
if ( $posts_before + 60 !== (int) wp_count_posts( ContentType::POST_TYPE )->draft ) {
	$fail( 'Chunked import did not create exactly 60 drafts.' );
}

$imported_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, wp_post_id FROM %i WHERE batch_id = %d AND status = 'imported' ORDER BY row_number ASC",
		$tables['import_rows'],
		$batch_id
	),
	ARRAY_A
);
if ( ! is_array( $imported_rows ) || 60 !== count( $imported_rows ) ) {
	$fail( 'Imported row ownership records are missing.' );
}
$sample_post_id = (int) $imported_rows[0]['wp_post_id'];
if ( 'draft' !== get_post_status( $sample_post_id ) ) {
	$fail( 'Chunked import created a non-draft post.' );
}
if ( 'needs_review' !== get_post_meta( $sample_post_id, MetaRegistry::key( 'review_status' ), true ) ) {
	$fail( 'Chunked import did not force needs_review.' );
}
if ( $fixture['arabic_text'] !== get_post_meta( $sample_post_id, MetaRegistry::key( 'arabic_text' ), true ) ) {
	$fail( 'Chunked import changed exact Arabic text.' );
}
if ( $progress->batch_uuid() !== get_post_meta( $sample_post_id, '_tazkar_import_batch_uuid', true ) ) {
	$fail( 'Chunked import batch ownership meta is missing.' );
}

$posts_after_completion = (int) wp_count_posts( ContentType::POST_TYPE )->draft;
$retried                = $service->process( $progress->batch_uuid(), 1 );
if ( 60 !== $retried->count( 'imported' ) || $posts_after_completion !== (int) wp_count_posts( ContentType::POST_TYPE )->draft ) {
	$fail( 'Terminal process retry was not idempotent.' );
}

$published_post_id = (int) $imported_rows[0]['wp_post_id'];
$approved_post_id  = (int) $imported_rows[1]['wp_post_id'];
$edited_post_id    = (int) $imported_rows[2]['wp_post_id'];
$missing_post_id   = (int) $imported_rows[3]['wp_post_id'];
$foreign_post_id   = (int) $imported_rows[4]['wp_post_id'];
$eligible_post_id  = (int) $imported_rows[5]['wp_post_id'];
update_post_meta( $published_post_id, MetaRegistry::key( 'review_status' ), 'approved' );
wp_update_post(
	array(
		'ID'          => $published_post_id,
		'post_status' => 'publish',
	)
);
update_post_meta( $approved_post_id, MetaRegistry::key( 'review_status' ), 'approved' );
wp_update_post(
	array(
		'ID'         => $edited_post_id,
		'post_title' => 'عنوان ویرایش‌شده پس از ورود',
	)
);
wp_delete_post( $missing_post_id, true );
update_post_meta( $foreign_post_id, '_tazkar_import_row_id', 999999 );

$iterations = 0;
while ( ! in_array( $progress->status(), array( 'rolled_back', 'rollback_with_conflicts' ), true ) && $iterations < 10 ) {
	$progress = $service->rollback( $progress->batch_uuid(), 1 );
	++$iterations;
}
if ( 'rollback_with_conflicts' !== $progress->status() || 5 !== $progress->count( 'rollback_conflicts' ) ) {
	$fail( 'Rollback did not preserve and report published, approved, edited, missing, and foreign conflicts.' );
}
if ( 'publish' !== get_post_status( $published_post_id ) ) {
	$fail( 'Rollback changed a published conflict.' );
}
if ( 'draft' !== get_post_status( $approved_post_id ) || 'approved' !== get_post_meta( $approved_post_id, MetaRegistry::key( 'review_status' ), true ) ) {
	$fail( 'Rollback changed an approved conflict.' );
}
if ( 'عنوان ویرایش‌شده پس از ورود' !== get_the_title( $edited_post_id ) || 'draft' !== get_post_status( $edited_post_id ) ) {
	$fail( 'Rollback changed a manually edited conflict.' );
}
if ( null !== get_post( $missing_post_id ) ) {
	$fail( 'Missing-post conflict setup was not preserved.' );
}
if ( 'draft' !== get_post_status( $foreign_post_id ) || 999999 !== (int) get_post_meta( $foreign_post_id, '_tazkar_import_row_id', true ) ) {
	$fail( 'Rollback changed a foreign-ownership conflict.' );
}
if ( 'trash' !== get_post_status( $eligible_post_id ) ) {
	$fail( 'Rollback did not trash an eligible owned draft.' );
}
if ( 55 !== (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE batch_id = %d AND status = 'rolled_back' AND rollback_status = 'trashed'", $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Rollback row outcomes are incoherent.' );
}

$state_before_retry = array(
	'conflicts' => $progress->count( 'rollback_conflicts' ),
	'trashed'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE batch_id = %d AND rollback_status = 'trashed'", $tables['import_rows'], $batch_id ) ),
);
$progress = $service->rollback( $progress->batch_uuid(), 1 );
if ( $state_before_retry['conflicts'] !== $progress->count( 'rollback_conflicts' ) ) {
	$fail( 'Repeated rollback changed conflict counts.' );
}
if ( $state_before_retry['trashed'] !== (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE batch_id = %d AND rollback_status = 'trashed'", $tables['import_rows'], $batch_id ) ) ) {
	$fail( 'Repeated rollback was not idempotent.' );
}

echo "Tazkar Sprint 5 chunked import and rollback smoke passed.\n";
