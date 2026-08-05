<?php

declare( strict_types=1 );

/**
 * An address is not a person. Everybody behind one office router, school or
 * mobile network arrives from the same address, and holding all of them to one
 * person's allowance lets a few colleagues mistyping stop everyone else
 * signing in.
 */
class Test_Shared_Address extends WP_UnitTestCase {

	use Clears_Attempts;

	public function tear_down(): void {
		remove_all_filters( 'sigil_client_ip' );
		parent::tear_down();
	}

	public function test_the_address_bucket_outlasts_one_persons_allowance(): void {
		$this->assertGreaterThan(
			\Sigil\Rate_Limit::max_attempts(),
			\Sigil\Rate_Limit::max_ip_attempts(),
			'a shared address has to count further than a single account'
		);
	}

	// Several colleagues failing does not lock the next one out.
	public function test_colleagues_sharing_an_address_do_not_lock_each_other_out(): void {
		$shared = 'ip:' . hash( 'sha256', '203.0.113.10' );

		for ( $i = 0; $i < \Sigil\Rate_Limit::max_attempts() + 1; $i++ ) {
			\Sigil\Rate_Limit::reserve( $shared );
		}

		$this->assertLessThanOrEqual(
			\Sigil\Rate_Limit::max_ip_attempts(),
			\Sigil\Rate_Limit::reserve( $shared ),
			'the next colleague still gets a turn'
		);
	}

	// But one address cannot spray indefinitely across different accounts.
	public function test_one_address_still_stops_eventually(): void {
		$shared = 'ip:' . hash( 'sha256', '203.0.113.11' );

		for ( $i = 0; $i < \Sigil\Rate_Limit::max_ip_attempts(); $i++ ) {
			\Sigil\Rate_Limit::reserve( $shared );
		}

		$this->assertGreaterThan( \Sigil\Rate_Limit::max_ip_attempts(), \Sigil\Rate_Limit::reserve( $shared ) );
	}

	public function test_a_site_behind_a_proxy_can_supply_the_real_address(): void {
		add_filter( 'sigil_client_ip', static fn(): string => '198.51.100.7' );

		$this->assertSame( '198.51.100.7', \Sigil\Request::client_ip() );
	}

	// A filter returning nonsense must not switch address counting off, which
	// is what returning nothing would do.
	public function test_a_broken_filter_falls_back_to_the_server(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		add_filter( 'sigil_client_ip', static fn(): string => 'not-an-address' );

		$this->assertSame( '203.0.113.20', \Sigil\Request::client_ip() );
	}

	public function test_an_address_the_server_cannot_report_is_no_address(): void {
		$previous = $_SERVER['REMOTE_ADDR'] ?? null;
		unset( $_SERVER['REMOTE_ADDR'] );

		$ip = \Sigil\Request::client_ip();

		if ( null !== $previous ) {
			$_SERVER['REMOTE_ADDR'] = $previous;
		}

		$this->assertSame( '', $ip );
	}
}
