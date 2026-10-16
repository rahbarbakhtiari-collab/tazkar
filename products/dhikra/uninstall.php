<?php
/**
 * Uninstall cleanup. Data is preserved unless the administrator opted in.
 *
 * @package Dhikra
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'dhikra_options', array() );
if ( empty( $options['delete_data_on_uninstall'] ) ) {
	return;
}

$post_ids = get_posts(
	array(
		'post_type'      => 'dhikra_telangor',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $post_ids as $post_id ) {
	wp_delete_post( absint( $post_id ), true );
}

$terms = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( is_array( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( absint( $term_id ), 'dhikra_topic' );
	}
}

$attachments = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_dhikra_background_owned',
		'meta_value'     => '1',
	)
);
foreach ( $attachments as $attachment_id ) {
	wp_delete_attachment( absint( $attachment_id ), true );
}

$external_users = get_users(
	array(
		'fields'     => 'ids',
		'meta_key'   => '_dhikra_external_only',
		'meta_value' => '1',
	)
);
if ( ! empty( $external_users ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $external_users as $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( $user instanceof WP_User && str_ends_with( strtolower( $user->user_email ), '@accounts.invalid' ) ) {
			wp_delete_user( $user->ID );
		}
	}
}

delete_metadata( 'user', 0, '_dhikra_excluded_topics', '', true );
delete_metadata( 'user', 0, '_dhikra_external_only', '', true );

$option_names = array(
	'dhikra_options',
	'dhikra_db_version',
	'dhikra_samples_created',
	'dhikra_samples_created_110',
	'dhikra_flush_rewrite',
	'dhikra_migration_pending',
	'dhikra_migration_offset',
	'dhikra_pool_version',
);
foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}

global $wpdb;
wp_clear_scheduled_hook( 'dhikra_daily_telangor_dispatch' );

foreach ( array( 'favorites', 'history', 'short_codes', 'identities', 'audience', 'reward_campaigns', 'reward_entries', 'reward_progress', 'reward_coupons', 'broadcasts', 'messages', 'visits' ) as $suffix ) {
	$table = $wpdb->prefix . 'dhikra_' . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
}

$transient_patterns = array( '_transient_dhikra_%', '_transient_timeout_dhikra_%' );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$transient_patterns[0],
		$transient_patterns[1]
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

$uploads = wp_upload_dir();
$base    = trailingslashit( (string) $uploads['basedir'] ) . 'dhikra';
if ( is_dir( $base ) && str_starts_with( wp_normalize_path( $base ), wp_normalize_path( (string) $uploads['basedir'] ) ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		} else {
			@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
	@rmdir( $base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
