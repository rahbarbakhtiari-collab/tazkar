<?php
/**
 * Main plugin coordinator.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Plugin {
	private static ?Dhikra_Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): Dhikra_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( 'Dhikra_Upgrader', 'maybe_upgrade' ), 20 );

		// Services required on both public and administrative requests.
		( new Dhikra_Content() )->register();
		( new Dhikra_Audience() )->register();
		( new Dhikra_Broadcast() )->register();
		( new Dhikra_Rewards() )->register();
		( new Dhikra_Random() )->register();
		( new Dhikra_REST() )->register();
		( new Dhikra_Mobile_Auth() )->register();
		( new Dhikra_Block() )->register();
		( new Dhikra_Security() )->register();

		if ( is_admin() ) {
			// Admin controllers are intentionally not parsed or instantiated on the
			// mini-app frontend. admin-post.php is also covered by is_admin().
			( new Dhikra_Settings() )->register();
			( new Dhikra_Audience_Admin() )->register();
			( new Dhikra_Analytics_Admin() )->register();
			( new Dhikra_Reward_Admin() )->register();
			( new Dhikra_Coupon_Admin() )->register();
			( new Dhikra_Importer() )->register();
		}

		if ( ! is_admin() ) {
			( new Dhikra_Accounts() )->register();
			( new Dhikra_Print() )->register();
			( new Dhikra_Auth() )->register();
			( new Dhikra_Router() )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'dhikra', false, dirname( DHIKRA_BASENAME ) . '/languages' );
	}
}
