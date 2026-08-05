<?php

declare( strict_types=1 );

/**
 * One guess per token.
 *
 * The attempt counters are a read, an increment and a write, so parallel guesses
 * all see the same count and overwrite each other: the real ceiling becomes the
 * attacker's concurrency. Consuming the token before validating makes the limit
 * structural, and stops two simultaneous completions both issuing a session.
 */
class Test_Token_Claim extends WP_UnitTestCase {

	use Clears_Attempts;

	private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 of "12345678901234567890"

	/**
	 * Enrol one step back, so the code for the current step is still ahead of
	 * the replay floor and can be spent by a test.
	 */
	private function enrolled_user(): \WP_User {
		$user = self::factory()->user->create_and_get();
		$totp = new \Sigil\Providers\Totp();

		$enrolled = $totp->handle_enrol(
			$user->ID,
			array(
				'secret' => self::SECRET,
				'code'   => \Sigil\Providers\Totp::code_at( self::SECRET, time() - 30 ),
			)
		);
		$this->assertTrue( $enrolled );

		return $user;
	}

	public function test_a_wrong_guess_consumes_the_token(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, false, '' );

		$first = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$this->assertWPError( $first );

		$replay = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$this->assertWPError( $replay );
		$this->assertSame( 'sigil_invalid_token', $replay->get_error_code() );
	}

	public function test_the_failure_hands_back_a_usable_token(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, true, '' );

		$error = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$next  = \Sigil\Challenge::next_token( $error, '' );

		$this->assertNotSame( '', $next );
		$this->assertNotSame( $token, $next );
		$this->assertInstanceOf( \WP_User::class, \Sigil\Challenge::user_for( $next ) );
		$this->assertSame( $user->ID, \Sigil\Challenge::user_for( $next )->ID );
	}

	// The replacement has to carry what the user chose at the password step,
	// or a retry silently drops "remember me" and the redirect they came in with.
	public function test_the_replacement_keeps_the_password_step_choices(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, true, admin_url( 'edit.php' ) );

		$error = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$next  = \Sigil\Challenge::next_token( $error, '' );

		$context = \Sigil\Challenge::context_for( $next );
		$this->assertIsArray( $context );
		$this->assertTrue( $context['remember'] );
		$this->assertSame( admin_url( 'edit.php' ), $context['redirect_to'] );
	}

	public function test_a_retry_on_the_replacement_can_still_succeed(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, false, '' );

		$error = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$next  = \Sigil\Challenge::next_token( $error, '' );

		$code = \Sigil\Providers\Totp::code_at( self::SECRET, time() );

		$this->assertTrue( \Sigil\Challenge::complete( $next, 'totp', array( 'code' => $code ) ) );
	}

	// Being throttled is not a guess, so the token the caller holds stays live.
	public function test_a_throttled_attempt_keeps_the_same_token(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, false, '' );

		for ( $i = 0; $i < 6; $i++ ) {
			\Sigil\Rate_Limit::reserve( 'u:' . $user->ID );
		}

		$error = \Sigil\Challenge::complete( $token, 'totp', array( 'code' => '000000' ) );
		$this->assertWPError( $error );
		$this->assertSame( 'sigil_rate_limited', $error->get_error_code() );
		$this->assertSame( $token, \Sigil\Challenge::next_token( $error, '' ) );

		\Sigil\Rate_Limit::clear( 'u:' . $user->ID );
	}

	public function test_a_wrong_method_also_costs_the_token(): void {
		$user  = $this->enrolled_user();
		$token = \Sigil\Challenge::start( $user, false, '' );

		$error = \Sigil\Challenge::complete( $token, 'email', array( 'code' => '000000' ) );
		$this->assertWPError( $error );
		$this->assertNull( \Sigil\Challenge::context_for( $token ) );
		$this->assertNotSame( '', \Sigil\Challenge::next_token( $error, '' ) );
	}
}
