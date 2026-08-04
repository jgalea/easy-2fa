<?php

declare( strict_types=1 );

/**
 * Boundaries the REST surface must hold, from the 0.2.0 security review.
 */
class Test_REST_Boundaries extends WP_UnitTestCase {

	private \WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\Sigil\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	private function get( string $route ): \WP_REST_Response {
		return $this->server->dispatch( new \WP_REST_Request( 'GET', $route ) );
	}

	// A network policy is set by the network administrator. A site administrator
	// must not be able to shed their own second factor and keep the session.
	public function test_a_site_administrator_cannot_reset_their_own_2fa_on_a_network(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'network-only boundary' );
		}

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\Sigil\Store::set_method( $admin, 'totp', array( 'secret' => 'x' ) );
		wp_set_current_user( $admin );

		$response = $this->server->dispatch(
			new \WP_REST_Request( 'DELETE', "/sigil/v1/users/{$admin}/methods" )
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertTrue( \Sigil\Store::has_any( $admin ) );
	}

	public function test_a_logged_out_caller_cannot_probe_user_zero(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get( '/sigil/v1/users/0' )->get_status() );
	}

	// list_users is a site capability, but accounts are network-wide. Without a
	// membership check a site administrator could inventory which accounts across
	// the whole network have no second factor.
	public function test_a_site_administrator_cannot_read_a_non_member_across_the_network(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'network-only boundary' );
		}

		$outsider = self::factory()->user->create();
		remove_user_from_blog( $outsider, get_current_blog_id() );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$this->assertSame( 403, $this->get( "/sigil/v1/users/{$outsider}" )->get_status() );
	}

	public function test_a_network_administrator_still_can(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'network-only boundary' );
		}

		$outsider = self::factory()->user->create();
		remove_user_from_blog( $outsider, get_current_blog_id() );

		$super = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super );
		wp_set_current_user( $super );

		$this->assertSame( 200, $this->get( "/sigil/v1/users/{$outsider}" )->get_status() );
	}
}
