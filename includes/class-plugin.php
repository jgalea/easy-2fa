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
		Schema::instance();
		require_once EASY2FA_DIR . 'includes/interface-provider.php';
		require_once EASY2FA_DIR . 'includes/class-providers.php';
		require_once EASY2FA_DIR . 'includes/class-policy.php';

		Providers::instance();

		require_once EASY2FA_DIR . 'includes/class-rate-limit.php';
		require_once EASY2FA_DIR . 'includes/class-challenge.php';
		Challenge::instance();

		require_once EASY2FA_DIR . 'includes/class-recovery.php';
		require_once EASY2FA_DIR . 'includes/class-cli.php';
		Recovery::instance();
		CLI::instance();

		require_once EASY2FA_DIR . 'includes/class-settings.php';
		require_once EASY2FA_DIR . 'includes/class-users-column.php';
		require_once EASY2FA_DIR . 'includes/class-app-passwords.php';

		Settings::instance();
		Users_Column::instance();
		App_Passwords::instance();

		require_once EASY2FA_DIR . 'includes/class-enrolment.php';
		Enrolment::instance();

		require_once EASY2FA_DIR . 'includes/class-enforcement.php';
		Enforcement::instance();
	}
}
