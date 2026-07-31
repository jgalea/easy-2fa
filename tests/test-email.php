<?php

declare( strict_types=1 );

class Test_Email_Codes extends WP_UnitTestCase {
	public function test_code_validates_then_expires_after_use(): void {
		$uid = self::factory()->user->create();
		$p   = new \Sigil\Providers\Email();
		$p->send_code( $uid );
		$code = get_user_meta( $uid, '_sigil_email_debug_code', true ); // test-only mirror, set when WP_DEBUG
		$this->assertTrue( $p->validate( $uid, [ 'code' => $code ] ) );
		$this->assertFalse( $p->validate( $uid, [ 'code' => $code ] ) );
	}

	public function test_expired_code_is_rejected(): void {
		$uid = self::factory()->user->create();
		$p   = new \Sigil\Providers\Email();
		$p->send_code( $uid );
		$code = get_user_meta( $uid, '_sigil_email_debug_code', true );
		set_transient( 'sigil_email_' . $uid, false ); // simulate TTL elapse
		$this->assertFalse( $p->validate( $uid, [ 'code' => $code ] ) );
	}

	public function test_rate_limits_resend(): void {
		$uid = self::factory()->user->create();
		$p   = new \Sigil\Providers\Email();
		$this->assertTrue( $p->send_code( $uid ) );
		$this->assertFalse( $p->send_code( $uid ) ); // within cooldown
	}
}
