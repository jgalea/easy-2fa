<?php

declare( strict_types=1 );

class Test_Store extends WP_UnitTestCase {
	public function test_set_and_read_method(): void {
		$uid = self::factory()->user->create();
		\Sigil\Store::set_method( $uid, 'totp', [ 'secret' => 'abc' ] );
		$methods = \Sigil\Store::methods( $uid );
		$this->assertArrayHasKey( 'totp', $methods );
		$this->assertSame( 'abc', $methods['totp']['secret'] );
		$this->assertTrue( \Sigil\Store::has_any( $uid ) );
	}

	public function test_remove_method(): void {
		$uid = self::factory()->user->create();
		\Sigil\Store::set_method( $uid, 'totp', [ 'secret' => 'abc' ] );
		\Sigil\Store::remove_method( $uid, 'totp' );
		$this->assertFalse( \Sigil\Store::has_any( $uid ) );
	}

	public function test_unknown_user_has_nothing(): void {
		$this->assertSame( [], \Sigil\Store::methods( 999999 ) );
	}
}
