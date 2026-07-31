<?php

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall runs once, at the moment the user deletes the plugin. Dropping a
 * plugin-owned table and sweeping its options has no core API, and caching a
 * result set here would be meaningless since nothing reads it afterwards.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */
function sigil_uninstall(): void {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sigil_credentials" );

	foreach ( array( 'sigil_db_version', 'sigil_policy' ) as $option ) {
		delete_option( $option );
	}

	foreach ( array( '_sigil_methods', '_sigil_deadline', '_sigil_reset_log', '_sigil_email_debug_code' ) as $meta_key ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key ) );
	}

	// Sweep any leftover plugin transients. Rate-limit, challenge, email and
	// backup-display transients all carry the sigil_ prefix.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_sigil\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_sigil\\_%'"
	);
}

sigil_uninstall();
