<?php

declare( strict_types=1 );

/**
 * These assertions are written to hold in both installs, so the same file proves
 * single-site behaviour under phpunit.xml.dist and network behaviour under
 * phpunit-multisite.xml.dist.
 */
class Test_Network extends WP_UnitTestCase {

	public function tear_down(): void {
		\Sigil\Network::delete_option( 'sigil_test_opt' );
		\Sigil\Network::delete_transient( 'sigil_test_tr' );
		parent::tear_down();
	}

	public function test_option_roundtrip(): void {
		\Sigil\Network::update_option( 'sigil_test_opt', array( 'a' => 1 ) );
		$this->assertSame( array( 'a' => 1 ), \Sigil\Network::get_option( 'sigil_test_opt' ) );

		\Sigil\Network::delete_option( 'sigil_test_opt' );
		$this->assertFalse( \Sigil\Network::get_option( 'sigil_test_opt' ) );
	}

	// The point of the helper: a network stores once for everyone, and nothing
	// lands in the current site's own options where a sibling site would miss it.
	public function test_storage_follows_the_install_type(): void {
		\Sigil\Network::update_option( 'sigil_test_opt', 'x' );

		if ( is_multisite() ) {
			$this->assertSame( 'x', get_site_option( 'sigil_test_opt' ) );
			$this->assertFalse( get_option( 'sigil_test_opt' ) );
		} else {
			$this->assertSame( 'x', get_option( 'sigil_test_opt' ) );
		}
	}

	public function test_transient_roundtrip(): void {
		\Sigil\Network::set_transient( 'sigil_test_tr', 7, 60 );
		$this->assertSame( 7, \Sigil\Network::get_transient( 'sigil_test_tr' ) );

		if ( is_multisite() ) {
			$this->assertSame( 7, get_site_transient( 'sigil_test_tr' ) );
		}

		\Sigil\Network::delete_transient( 'sigil_test_tr' );
		$this->assertFalse( \Sigil\Network::get_transient( 'sigil_test_tr' ) );
	}

	// Users are network-wide, so their passkeys must live in one table.
	public function test_credentials_table_is_network_wide(): void {
		global $wpdb;

		$expected = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
		$this->assertSame( $expected . 'sigil_credentials', \Sigil\Schema::table() );
	}

	// A single site administrator must not be able to weaken a network policy.
	public function test_settings_capability(): void {
		$this->assertSame(
			is_multisite() ? 'manage_network_options' : 'manage_options',
			\Sigil\Network::manage_capability()
		);
	}

	public function test_policy_is_shared_across_the_install(): void {
		\Sigil\Policy::update( array( 'grace_days' => 3 ) );

		$stored = is_multisite()
			? get_site_option( \Sigil\Policy::OPTION_KEY )
			: get_option( \Sigil\Policy::OPTION_KEY );

		$this->assertIsArray( $stored );
		$this->assertSame( 3, (int) $stored['grace_days'] );
		$this->assertSame( 3, \Sigil\Policy::get()['grace_days'] );
	}
}
