<?php

declare( strict_types=1 );

/**
 * A server clock that has drifted rejects every authenticator code from every
 * user, and looks exactly like everyone suddenly mistyping.
 */
class Test_Clock_Drift extends WP_UnitTestCase {

	private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	public function set_up(): void {
		parent::set_up();
		delete_transient( 'sigil_totp_drift' );
	}

	public function tear_down(): void {
		delete_transient( 'sigil_totp_drift' );
		parent::tear_down();
	}

	private function enrolled(): int {
		$user = self::factory()->user->create();
		$totp = new \Sigil\Providers\Totp();
		$totp->handle_enrol(
			$user,
			array( 'secret' => self::SECRET, 'code' => \Sigil\Providers\Totp::code_at( self::SECRET, time() - 30 ) )
		);

		return (int) $user;
	}

	public function test_a_correct_code_reports_no_drift(): void {
		$user = $this->enrolled();
		$totp = new \Sigil\Providers\Totp();

		$this->assertTrue( $totp->validate( $user, array( 'code' => \Sigil\Providers\Totp::code_at( self::SECRET, time() ) ) ) );
		$this->assertNull( \Sigil\Providers\Totp::clock_drift() );
	}

	// A code from well outside the window that still matches the shared secret
	// is the clock being wrong, not the person.
	public function test_a_code_from_far_outside_the_window_is_read_as_drift(): void {
		$user = $this->enrolled();
		$totp = new \Sigil\Providers\Totp();

		$far = \Sigil\Providers\Totp::code_at( self::SECRET, time() + ( 10 * 60 ) );

		$this->assertFalse( $totp->validate( $user, array( 'code' => $far ) ), 'it must still be refused' );

		$drift = \Sigil\Providers\Totp::clock_drift();
		$this->assertNotNull( $drift );
		$this->assertGreaterThan( 300, abs( (int) $drift ) );
	}

	// Someone simply typing it wrong must not be reported as a clock problem.
	public function test_a_genuinely_wrong_code_is_not_read_as_drift(): void {
		$user = $this->enrolled();
		$totp = new \Sigil\Providers\Totp();

		$this->assertFalse( $totp->validate( $user, array( 'code' => '000000' ) ) );
		$this->assertNull( \Sigil\Providers\Totp::clock_drift() );
	}

	public function test_a_later_correct_code_clears_the_warning(): void {
		$user = $this->enrolled();
		$totp = new \Sigil\Providers\Totp();

		$totp->validate( $user, array( 'code' => \Sigil\Providers\Totp::code_at( self::SECRET, time() + ( 10 * 60 ) ) ) );
		$this->assertNotNull( \Sigil\Providers\Totp::clock_drift() );

		$this->assertTrue( $totp->validate( $user, array( 'code' => \Sigil\Providers\Totp::code_at( self::SECRET, time() ) ) ) );
		$this->assertNull( \Sigil\Providers\Totp::clock_drift(), 'a working code proves the clock is fine' );
	}

	// Drift is only ever read to warn someone. It must never let a code through.
	public function test_drift_never_authenticates_anyone(): void {
		$user = $this->enrolled();
		$totp = new \Sigil\Providers\Totp();
		$far  = \Sigil\Providers\Totp::code_at( self::SECRET, time() + ( 10 * 60 ) );

		$this->assertFalse( $totp->validate( $user, array( 'code' => $far ) ) );
		$this->assertFalse( $totp->validate( $user, array( 'code' => $far ) ), 'still refused once drift is known' );
	}
}
