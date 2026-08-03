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

	// A wrong code re-renders the screen. The code the user is reading out of their
	// inbox has to survive that, however long they took to type it.
	public function test_rerender_keeps_the_live_code(): void {
		$uid = self::factory()->user->create();
		$p   = new \Sigil\Providers\Email();
		$p->send_code( $uid );
		$code = get_user_meta( $uid, '_sigil_email_debug_code', true );

		delete_transient( 'sigil_email_cd_' . $uid ); // cooldown elapsed, code has not
		ob_start();
		$p->render_challenge( $uid );
		ob_end_clean();

		$this->assertTrue( $p->validate( $uid, [ 'code' => $code ] ) );
	}

	// Signing in again right after a successful email login must send a new code,
	// not show a screen asking for one that was just consumed.
	public function test_challenge_after_use_sends_a_fresh_code(): void {
		$uid = self::factory()->user->create();
		$p   = new \Sigil\Providers\Email();
		$p->send_code( $uid );
		$p->validate( $uid, [ 'code' => get_user_meta( $uid, '_sigil_email_debug_code', true ) ] );

		ob_start();
		$p->render_challenge( $uid ); // still inside the old cooldown window
		ob_end_clean();

		$fresh = get_user_meta( $uid, '_sigil_email_debug_code', true );
		$this->assertNotEmpty( $fresh );
		$this->assertTrue( $p->validate( $uid, [ 'code' => $fresh ] ) );
	}
}
