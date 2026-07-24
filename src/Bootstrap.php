<?php

namespace Tazkar;

use Tazkar\Admin\StatusPage;
use Tazkar\Core\Capabilities;
use Tazkar\Infrastructure\Database\Migrator;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class Bootstrap {
	/** @var self|null */
	private static $instance;

	/** @var bool */
	private $registered = false;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		load_plugin_textdomain( 'tazkar', false, dirname( plugin_basename( TAZKAR_PLUGIN_FILE ) ) . '/languages' );

		add_action( 'admin_init', array( Migrator::class, 'maybe_migrate' ) );
		add_action( 'admin_init', array( Capabilities::class, 'maybe_sync' ) );

		if ( is_admin() ) {
			( new StatusPage() )->register();
		}
	}
}
