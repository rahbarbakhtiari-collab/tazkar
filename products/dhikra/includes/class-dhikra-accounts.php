<?php
/**
 * User preferences, favorites and external identity contracts.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Accounts {
	public static function resolve_eitaa_user( string $init_data ): array|WP_Error {
		$identity = Dhikra_Eitaa::validate_init_data( $init_data );
		if ( is_wp_error( $identity ) ) {
			$data = $identity->get_error_data();
			if ( ! is_array( $data ) || empty( $data['status'] ) ) {
				$identity->add_data( array( 'status' => 403 ) );
			}
			return $identity;
		}
		$display_name = trim( (string) $identity['first_name'] . ' ' . (string) $identity['last_name'] );
		$user_id = self::ensure_external_user( 'eitaa', (string) $identity['eitaa_user_id'], $display_name );
		return is_wp_error( $user_id ) ? $user_id : array( 'identity' => $identity, 'user_id' => absint( $user_id ) );
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_account_actions' ), 8 );
	}

	public function handle_account_actions(): void {
		if ( ! Dhikra_Helpers::is_app_request() || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) || ! is_user_logged_in() ) {
			return;
		}
		$route = Dhikra_Helpers::request_route();
		if ( 'account' === $route && isset( $_POST['dhikra_account_action'] ) && 'save_topics' === sanitize_key( wp_unslash( $_POST['dhikra_account_action'] ) ) ) {
			$this->save_topics();
		}
		if ( 'favorites' === $route && isset( $_POST['dhikra_account_action'] ) && 'remove_favorite' === sanitize_key( wp_unslash( $_POST['dhikra_account_action'] ) ) ) {
			$this->remove_favorite();
		}
	}

	private function save_topics(): never {
		check_admin_referer( 'dhikra_save_topics', 'dhikra_nonce' );
		$active   = Dhikra_Data::active_topic_ids();
		$selected = isset( $_POST['dhikra_topics'] ) && is_array( $_POST['dhikra_topics'] ) ? array_map( 'absint', wp_unslash( $_POST['dhikra_topics'] ) ) : array();
		$selected = array_values( array_intersect( $active, $selected ) );
		if ( empty( $selected ) ) {
			wp_safe_redirect( add_query_arg( 'dhikra_error', __( 'دست‌کم یک موضوع را انتخاب کنید.', 'dhikra' ), Dhikra_Helpers::app_url( 'account' ) ) );
			exit;
		}
		Dhikra_Data::save_excluded_topic_ids( get_current_user_id(), array_diff( $active, $selected ) );
		wp_safe_redirect( add_query_arg( 'dhikra_notice', __( 'موضوعات دلخواه ذخیره شد.', 'dhikra' ), Dhikra_Helpers::app_url( 'account' ) ) );
		exit;
	}

	private function remove_favorite(): never {
		check_admin_referer( 'dhikra_remove_favorite', 'dhikra_nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		Dhikra_Data::set_favorite( get_current_user_id(), $post_id, false );
		wp_safe_redirect( Dhikra_Helpers::app_url( 'favorites' ) );
		exit;
	}

	public static function ensure_external_user( string $provider, string $external_user_id, string $display_name = '' ): int|WP_Error {
		$provider         = sanitize_key( $provider );
		$external_user_id = sanitize_text_field( $external_user_id );
		if ( '' === $provider || '' === $external_user_id || strlen( $external_user_id ) > 191 ) {
			return new WP_Error( 'dhikra_invalid_identity', __( 'هویت بیرونی معتبر نیست.', 'dhikra' ) );
		}
		global $wpdb;
		$table            = Dhikra_Data::table( 'identities' );
		$existing_user_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE provider = %s AND external_user_id = %s LIMIT 1", $provider, $external_user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing_user_id > 0 && get_user_by( 'id', $existing_user_id ) ) {
			return $existing_user_id;
		}

		$hash      = substr( hash( 'sha256', $provider . '|' . $external_user_id ), 0, 24 );
		$user_login = 'dhikra_' . substr( $provider, 0, 10 ) . '_' . substr( $hash, 0, 12 );
		$email      = $hash . '@accounts.invalid';
		$created    = wp_insert_user(
			array(
				'user_login'   => $user_login,
				'user_pass'    => wp_generate_password( 40, true, true ),
				'user_email'   => $email,
				'display_name' => '' !== $display_name ? sanitize_text_field( $display_name ) : __( 'کاربر ذِكْرَىٰ', 'dhikra' ),
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$user_id = absint( $created );
		update_user_meta( $user_id, '_dhikra_external_only', '1' );
		if ( $existing_user_id > 0 ) {
			// Repair an identity row whose temporary WordPress user was removed independently.
			$result = $wpdb->update(
				$table,
				array( 'user_id' => $user_id, 'created_at' => current_time( 'mysql', true ) ),
				array( 'provider' => $provider, 'external_user_id' => $external_user_id ),
				array( '%d', '%s' ),
				array( '%s', '%s' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'provider'         => $provider,
					'external_user_id' => $external_user_id,
					'user_id'          => $user_id,
					'created_at'       => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
		}
		if ( false === $result ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return new WP_Error( 'dhikra_identity_store', __( 'ذخیرهٔ هویت بیرونی انجام نشد.', 'dhikra' ) );
		}
		do_action( 'dhikra_external_user_created', $user_id, $provider, $external_user_id );
		return $user_id;
	}

	public static function link_external_identity( int $target_user_id, string $provider, string $external_user_id ): bool|WP_Error {
		$provider         = sanitize_key( $provider );
		$external_user_id = sanitize_text_field( $external_user_id );
		if ( $target_user_id <= 0 || ! get_user_by( 'id', $target_user_id ) || '' === $provider || '' === $external_user_id ) {
			return new WP_Error( 'dhikra_invalid_identity_link', __( 'اطلاعات اتصال حساب معتبر نیست.', 'dhikra' ) );
		}
		global $wpdb;
		$table     = Dhikra_Data::table( 'identities' );
		$source_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE provider = %s AND external_user_id = %s LIMIT 1", $provider, $external_user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$source_is_external_only = $source_id > 0 && '1' === (string) get_user_meta( $source_id, '_dhikra_external_only', true );
		if ( $source_id > 0 && $source_id !== $target_user_id && ! $source_is_external_only ) {
			return new WP_Error( 'dhikra_identity_already_linked', __( 'این هویت بیرونی قبلاً به یک حساب عادی دیگر متصل شده است.', 'dhikra' ) );
		}
		if ( $source_id > 0 && $source_id !== $target_user_id ) {
			Dhikra_Data::merge_user_data( $source_id, $target_user_id );
		}
		if ( $source_id > 0 ) {
			$result = $wpdb->update( $table, array( 'user_id' => $target_user_id ), array( 'provider' => $provider, 'external_user_id' => $external_user_id ), array( '%d' ), array( '%s', '%s' ) );
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'provider'         => $provider,
					'external_user_id' => $external_user_id,
					'user_id'          => $target_user_id,
					'created_at'       => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
		}
		if ( false === $result ) {
			return new WP_Error( 'dhikra_identity_link_failed', __( 'اتصال حساب انجام نشد.', 'dhikra' ) );
		}

		if ( $source_id > 0 && $source_id !== $target_user_id && $source_is_external_only ) {
			// Preserve any additional messenger identities already attached to the temporary account.
			$wpdb->update( $table, array( 'user_id' => $target_user_id ), array( 'user_id' => $source_id ), array( '%d' ), array( '%d' ) );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $source_id );
		}

		$target_user = get_user_by( 'id', $target_user_id );
		if ( $target_user instanceof WP_User && ! str_ends_with( strtolower( $target_user->user_email ), '@accounts.invalid' ) ) {
			delete_user_meta( $target_user_id, '_dhikra_external_only' );
		}
		do_action( 'dhikra_external_identity_linked', $target_user_id, $provider, $external_user_id, $source_id );
		return true;
	}
}
