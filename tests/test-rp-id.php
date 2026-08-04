<?php

declare( strict_types=1 );

/**
 * The relying-party ID decides which hostnames a passkey will sign for. Widening
 * it too far hands credentials to a domain that should not have them; too narrow
 * and a network's sites each need their own passkey.
 */
class Test_Rp_Id extends WP_UnitTestCase {

	public function test_single_site_uses_its_own_host(): void {
		$this->assertSame( 'example.com', \Sigil\Providers\Passkey::resolve_rp_id( 'example.com', '' ) );
		$this->assertSame( 'shop.example.com', \Sigil\Providers\Passkey::resolve_rp_id( 'shop.example.com', '' ) );
	}

	public function test_a_site_under_the_network_domain_anchors_to_it(): void {
		$this->assertSame( 'example.com', \Sigil\Providers\Passkey::resolve_rp_id( 'example.com', 'example.com' ) );
		$this->assertSame( 'example.com', \Sigil\Providers\Passkey::resolve_rp_id( 'sub.example.com', 'example.com' ) );
		$this->assertSame( 'example.com', \Sigil\Providers\Passkey::resolve_rp_id( 'deep.sub.example.com', 'example.com' ) );
	}

	// A mapped domain is a different registrable suffix. Claiming the network
	// domain there would produce assertions the browser refuses to make.
	public function test_a_mapped_domain_keeps_its_own_host(): void {
		$this->assertSame( 'othersite.org', \Sigil\Providers\Passkey::resolve_rp_id( 'othersite.org', 'example.com' ) );
	}

	// The suffix test must respect the dot boundary, or an attacker-registered
	// lookalike would be treated as part of the network.
	public function test_a_lookalike_host_is_not_treated_as_a_subdomain(): void {
		$this->assertSame( 'notexample.com', \Sigil\Providers\Passkey::resolve_rp_id( 'notexample.com', 'example.com' ) );
		$this->assertSame( 'example.com.evil.test', \Sigil\Providers\Passkey::resolve_rp_id( 'example.com.evil.test', 'example.com' ) );
	}

	public function test_the_filter_still_wins(): void {
		$override = static function (): string {
			return 'forced.example';
		};

		add_filter( 'sigil_rp_id', $override );
		$this->assertSame( 'forced.example', \Sigil\Providers\Passkey::rp_id() );
		remove_filter( 'sigil_rp_id', $override );
	}
}
