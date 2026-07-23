<?php

declare( strict_types=1 );

class Test_Store extends WP_UnitTestCase {
	public function test_set_and_read_method(): void {
		$uid = self::factory()->user->create();
		\Easy2FA\Store::set_method( $uid, 'totp', [ 'secret' => 'abc' ] );
		$methods = \Easy2FA\Store::methods( $uid );
		$this->assertArrayHasKey( 'totp', $methods );
		$this->assertSame( 'abc', $methods['totp']['secret'] );
		$this->assertTrue( \Easy2FA\Store::has_any( $uid ) );
	}

	public function test_remove_method(): void {
		$uid = self::factory()->user->create();
		\Easy2FA\Store::set_method( $uid, 'totp', [ 'secret' => 'abc' ] );
		\Easy2FA\Store::remove_method( $uid, 'totp' );
		$this->assertFalse( \Easy2FA\Store::has_any( $uid ) );
	}

	public function test_unknown_user_has_nothing(): void {
		$this->assertSame( [], \Easy2FA\Store::methods( 999999 ) );
	}
}
