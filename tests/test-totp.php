<?php

declare( strict_types=1 );

class Test_TOTP extends WP_UnitTestCase {
	private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 of "12345678901234567890"

	public function test_rfc6238_vectors(): void {
		$totp = new \Easy2FA\Providers\TOTP();
		$this->assertSame( '287082', $totp::code_at( self::SECRET, 59 ) );
		$this->assertSame( '081804', $totp::code_at( self::SECRET, 1111111109 ) );
		$this->assertSame( '005924', $totp::code_at( self::SECRET, 1234567890 ) );
	}

	public function test_accepts_previous_window(): void {
		$uid  = self::factory()->user->create();
		$totp = new \Easy2FA\Providers\TOTP();
		$totp->handle_enrol( $uid, [ 'secret' => self::SECRET, 'code' => $totp::code_at( self::SECRET, time() ) ] );
		$prev = $totp::code_at( self::SECRET, time() - 30 );
		$this->assertTrue( $totp->validate( $uid, [ 'code' => $prev ] ) );
	}

	public function test_rejects_replay_of_used_code(): void {
		$uid  = self::factory()->user->create();
		$totp = new \Easy2FA\Providers\TOTP();
		$code = $totp::code_at( self::SECRET, time() );
		$totp->handle_enrol( $uid, [ 'secret' => self::SECRET, 'code' => $code ] );
		$this->assertTrue( $totp->validate( $uid, [ 'code' => $code ] ) );
		$this->assertFalse( $totp->validate( $uid, [ 'code' => $code ] ) );
	}

	public function test_rejects_wrong_code(): void {
		$uid  = self::factory()->user->create();
		$totp = new \Easy2FA\Providers\TOTP();
		$totp->handle_enrol( $uid, [ 'secret' => self::SECRET, 'code' => $totp::code_at( self::SECRET, time() ) ] );
		$this->assertFalse( $totp->validate( $uid, [ 'code' => '000000' ] ) );
	}

	public function test_degenerate_secret_cannot_validate(): void {
		$uid  = self::factory()->user->create();
		$totp = new \Easy2FA\Providers\TOTP();
		$this->assertWPError( $totp->handle_enrol( $uid, [ 'secret' => 'A', 'code' => '000000' ] ) );
		\Easy2FA\Store::set_method( $uid, 'totp', [ 'secret' => \Easy2FA\Crypto::encrypt( 'A' ) ] );
		$this->assertFalse( $totp->validate( $uid, [ 'code' => '000000' ] ) );
	}
}
