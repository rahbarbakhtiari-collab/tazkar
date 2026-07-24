<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'tazkar_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete_data ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'tazkar_import_rows',
	$wpdb->prefix . 'tazkar_import_batches',
	$wpdb->prefix . 'tazkar_feedback',
	$wpdb->prefix . 'tazkar_favorites',
);

foreach ( $tables as $table_name ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'tazkar_db_version',
	'tazkar_version',
	'tazkar_installed_at',
	'tazkar_caps_version',
	'tazkar_delete_data_on_uninstall',
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
}

$administrator = get_role( 'administrator' );

if ( $administrator ) {
	$capabilities = array(
		'manage_tazkar',
		'import_tazkar_items',
		'review_tazkar_items',
		'publish_tazkar_items',
		'manage_tazkar_settings',
		'view_tazkar_feedback',
		'delete_tazkar_data',
	);

	foreach ( $capabilities as $capability ) {
		$administrator->remove_cap( $capability );
	}
}
