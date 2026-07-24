<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
	}

	public function boot(): void {
		require_once EASY2FA_DIR . 'includes/class-crypto.php';
		require_once EASY2FA_DIR . 'includes/class-store.php';
		require_once EASY2FA_DIR . 'includes/class-schema.php';
		require_once EASY2FA_DIR . 'includes/interface-provider.php';
		require_once EASY2FA_DIR . 'includes/class-providers.php';
		require_once EASY2FA_DIR . 'includes/class-policy.php';

		Providers::instance();
	}
}
