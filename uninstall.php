<?php

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function easy2fa_uninstall(): void {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy2fa_credentials" );

	foreach ( array( 'easy2fa_db_version', 'easy2fa_policy' ) as $option ) {
		delete_option( $option );
	}

	foreach ( array( '_easy2fa_methods', '_easy2fa_deadline', '_easy2fa_reset_log', '_easy2fa_email_debug_code' ) as $meta_key ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key ) );
	}

	// Sweep any leftover plugin transients. Rate-limit, challenge, email and
	// backup-display transients all carry the easy2fa_ prefix.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_easy2fa\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_easy2fa\\_%'"
	);
}

easy2fa_uninstall();
