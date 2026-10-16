<?php
/**
 * Authentication actions owned by Dhikra.
 *
 * Login and registration are delegated exclusively to Manahel IPPanel OTP.
 * Dhikra only owns the nonce-protected logout route.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Auth {
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
	}

	public function handle(): void {
		if ( ! Dhikra_Helpers::is_app_request() || 'logout' !== Dhikra_Helpers::request_route() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'dhikra_logout' ) ) {
				wp_safe_redirect( Dhikra_Helpers::app_url( 'account' ) );
				exit;
			}
			wp_logout();
		}

		wp_safe_redirect( Dhikra_Helpers::app_url( 'home' ) );
		exit;
	}
}
