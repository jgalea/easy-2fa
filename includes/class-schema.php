<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Schema {

	private static ?Schema $instance = null;

	public static function instance(): Schema {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'admin_init', array( self::$instance, 'maybe_install' ) );
		}
		return self::$instance;
	}

	// The activation hook is not enough on its own: a half-failed activation, a
	// dropped table, or a site that never fired the hook (some CLI installs)
	// would leave the credentials table missing forever, and every passkey read
	// would throw. A passkey credential can only be created during admin
	// enrolment, so checking here guarantees the table exists before one can.
	public function maybe_install(): void {
		if ( get_option( 'easy2fa_db_version' ) !== EASY2FA_VERSION ) {
			self::install();
		}
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'easy2fa_credentials';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			credential_id VARBINARY(255) NOT NULL,
			public_key TEXT NOT NULL,
			sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			transports VARCHAR(120) NOT NULL DEFAULT '',
			label VARCHAR(190) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			last_used_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY credential_id (credential_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'easy2fa_db_version', EASY2FA_VERSION, false );
	}
}
