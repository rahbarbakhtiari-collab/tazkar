<?php
/**
 * Safe schema and data upgrades.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Upgrader {
	public static function maybe_upgrade(): void {
		self::cleanup_removed_image_share();
		$installed = (string) get_option( 'dhikra_db_version', '0.0.0' );
		if ( version_compare( $installed, '1.6.7', '<' ) ) {
			wp_clear_scheduled_hook( 'dhikra_daily_telangor_dispatch' );
		}
		if ( version_compare( $installed, DHIKRA_DB_VERSION, '<' ) ) {
			Dhikra_Data::install_schema();
			Dhikra_Rewards::install_capabilities();
			$defaults = Dhikra_Helpers::defaults();
			$options  = array_intersect_key( wp_parse_args( get_option( 'dhikra_options', array() ), $defaults ), $defaults );
			update_option( 'dhikra_options', $options, false );
			// Do not record a successful migration if the coupon table failed to install.
			if ( Dhikra_Coupons::storage_ready( false ) && Dhikra_Analytics::storage_ready() && Dhikra_Coupons::backfill_legacy_assignments() ) {
				update_option( 'dhikra_db_version', DHIKRA_DB_VERSION, false );
				update_option( 'dhikra_migration_pending', 1, false );
				update_option( 'dhikra_flush_rewrite', 1, false );
			}
		}

		Dhikra_Rewards::install_capabilities();
		add_action( 'admin_init', array( self::class, 'process_migration_batch' ) );
		add_action( 'admin_init', array( self::class, 'maybe_flush_rewrite' ), 30 );
	}

	/**
	 * One-time cleanup after removal of the image-sharing feature in 1.6.21.
	 */
	private static function cleanup_removed_image_share(): void {
		if ( get_option( 'dhikra_image_share_removed_1_6_21', false ) ) {
			return;
		}
		wp_clear_scheduled_hook( 'dhikra_cleanup_temp_image' );
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) ) {
			$dir = trailingslashit( (string) $uploads['basedir'] ) . 'dhikra-temp';
			foreach ( glob( trailingslashit( $dir ) . 'dhikra-*.png' ) ?: array() as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file );
				}
			}
			$index = trailingslashit( $dir ) . 'index.php';
			if ( is_file( $index ) ) { @unlink( $index ); }
			if ( is_dir( $dir ) ) { @rmdir( $dir ); }
		}
		update_option( 'dhikra_image_share_removed_1_6_21', 1, false );
	}

	public static function process_migration_batch(): void {
		if ( ! get_option( 'dhikra_migration_pending', false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$offset = absint( get_option( 'dhikra_migration_offset', 0 ) );
		$ids    = get_posts(
			array(
				'post_type'      => 'dhikra_telangor',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 40,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		if ( empty( $ids ) ) {
			delete_option( 'dhikra_migration_pending' );
			delete_option( 'dhikra_migration_offset' );
			return;
		}
		foreach ( $ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( '' === (string) get_post_meta( $post_id, '_dhikra_original_text', true ) ) {
				$old = (string) get_post_meta( $post_id, '_dhikra_arabic_text', true );
				if ( '' !== $old ) {
					update_post_meta( $post_id, '_dhikra_original_text', $old );
				}
			}
			if ( '' === (string) get_post_meta( $post_id, '_dhikra_type', true ) ) {
				update_post_meta( $post_id, '_dhikra_type', 'quran' );
			}
			$main_topic = absint( get_post_meta( $post_id, '_dhikra_main_topic_id', true ) );
			if ( $main_topic <= 0 ) {
				$terms = wp_get_object_terms( $post_id, 'dhikra_topic', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					update_post_meta( $post_id, '_dhikra_main_topic_id', absint( $terms[0] ) );
				}
			}
			if ( '' === Dhikra_Data::get_short_code( $post_id ) ) {
				Dhikra_Data::assign_short_code( $post_id );
			}
		}
		update_option( 'dhikra_migration_offset', $offset + count( $ids ), false );
	}

	public static function maybe_flush_rewrite(): void {
		if ( ! get_option( 'dhikra_flush_rewrite', false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		( new Dhikra_Router() )->rewrite_rules();
		flush_rewrite_rules( false );
		delete_option( 'dhikra_flush_rewrite' );
	}
}
