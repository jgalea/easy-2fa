<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Schema {

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
