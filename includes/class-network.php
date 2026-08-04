<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

/**
 * Multisite awareness in one place.
 *
 * WordPress users are network-wide, so their second factors are too: one
 * credentials table, one policy, one rate-limit counter for the whole network.
 * Storing any of that per site would let a user enrol on one site and be
 * unprotected on the next, and would let an attacker reset a rate limit by
 * moving to a sibling site.
 */
final class Network {

	public static function is_network(): bool {
		return is_multisite();
	}

	/**
	 * @param mixed $default_value
	 * @return mixed
	 */
	public static function get_option( string $key, $default_value = false ) {
		return self::is_network()
			? get_site_option( $key, $default_value )
			: get_option( $key, $default_value );
	}

	/**
	 * @param mixed $value
	 */
	public static function update_option( string $key, $value ): void {
		if ( self::is_network() ) {
			update_site_option( $key, $value );
			return;
		}

		update_option( $key, $value, false );
	}

	public static function delete_option( string $key ): void {
		if ( self::is_network() ) {
			delete_site_option( $key );
			return;
		}

		delete_option( $key );
	}

	/**
	 * @return mixed
	 */
	public static function get_transient( string $key ) {
		return self::is_network() ? get_site_transient( $key ) : get_transient( $key );
	}

	/**
	 * @param mixed $value
	 */
	public static function set_transient( string $key, $value, int $ttl ): void {
		if ( self::is_network() ) {
			set_site_transient( $key, $value, $ttl );
			return;
		}

		set_transient( $key, $value, $ttl );
	}

	public static function delete_transient( string $key ): void {
		if ( self::is_network() ) {
			delete_site_transient( $key );
			return;
		}

		delete_transient( $key );
	}

	public static function table_prefix(): string {
		global $wpdb;
		return self::is_network() ? $wpdb->base_prefix : $wpdb->prefix;
	}

	/**
	 * The capability that governs the plugin's settings. On a network the policy
	 * is network-wide, so a single site administrator must not be able to weaken
	 * it for everyone.
	 */
	public static function manage_capability(): string {
		$capability = self::is_network() ? 'manage_network_options' : 'manage_options';

		/**
		 * Filter the capability required to manage the plugin's settings.
		 *
		 * Narrowing this is the point; widening it hands the policy to more
		 * people than WordPress trusts with the site, so an empty or non-string
		 * value is ignored.
		 *
		 * @param string $capability Default for this install type.
		 */
		$filtered = apply_filters( 'sigil_manage_capability', $capability );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : $capability;
	}

	/**
	 * The network's own domain, lowercased. Empty on a single site.
	 */
	public static function primary_host(): string {
		if ( ! self::is_network() ) {
			return '';
		}

		$network = function_exists( 'get_network' ) ? get_network() : null;
		$domain  = $network instanceof \WP_Network ? (string) $network->domain : '';

		return strtolower( ltrim( $domain, '.' ) );
	}

	public static function settings_url(): string {
		return self::is_network()
			? network_admin_url( 'settings.php?page=sigil-2fa' )
			: admin_url( 'options-general.php?page=sigil-2fa' );
	}
}
