<?php

declare( strict_types=1 );

/**
 * The ceiling has to be the configured number, not however many requests an
 * attacker can make at the same moment.
 */
class Test_Rate_Limit extends WP_UnitTestCase {

	use Clears_Attempts;

	public function test_each_reservation_returns_its_own_number(): void {
		$seen = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$seen[] = \Sigil\Rate_Limit::reserve( 'probe' );
		}

		$this->assertSame( array( 1, 2, 3, 4, 5 ), $seen, 'no two attempts may claim the same number' );
	}

	public function test_the_ceiling_holds(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			\Sigil\Rate_Limit::reserve( 'probe' );
		}

		$this->assertTrue( \Sigil\Rate_Limit::blocked( 'probe' ) );
		$this->assertGreaterThan( \Sigil\Rate_Limit::max_attempts(), \Sigil\Rate_Limit::reserve( 'probe' ) );
	}

	public function test_a_finished_window_starts_again(): void {
		global $wpdb;

		\Sigil\Rate_Limit::reserve( 'probe' );
		\Sigil\Rate_Limit::reserve( 'probe' );

		// Age the window rather than waiting fifteen minutes for it.
		$wpdb->query(
			$wpdb->prepare( 'UPDATE ' . \Sigil\Rate_Limit::table() . ' SET expires = %d', time() - 1 )
		);

		$this->assertSame( 0, \Sigil\Rate_Limit::count( 'probe' ), 'an expired window counts as nothing' );
		$this->assertSame( 1, \Sigil\Rate_Limit::reserve( 'probe' ), 'and the next attempt is the first of a new one' );
	}

	public function test_clearing_resets_it(): void {
		\Sigil\Rate_Limit::reserve( 'probe' );
		\Sigil\Rate_Limit::reserve( 'probe' );
		\Sigil\Rate_Limit::clear( 'probe' );

		$this->assertSame( 0, \Sigil\Rate_Limit::count( 'probe' ) );
	}

	public function test_separate_keys_are_counted_separately(): void {
		\Sigil\Rate_Limit::reserve( 'probe' );
		\Sigil\Rate_Limit::reserve( 'probe' );

		$this->assertSame( 1, \Sigil\Rate_Limit::reserve( 'probe-other' ) );
		\Sigil\Rate_Limit::clear( 'probe-other' );
	}

	// Refusing everything is the safe reading for a limiter and a site-wide
	// lockout at the same time, so a counter that has gone missing is put back
	// rather than left to stop everyone signing in.
	public function test_a_missing_counter_is_rebuilt(): void {
		$this->drop_table();
		self::set_repair_spent( false );

		$this->assertFalse( self::table_exists(), 'the table has to be gone for this to mean anything' );

		$this->assertSame( 1, \Sigil\Rate_Limit::reserve( 'probe' ), 'the attempt is counted against a rebuilt table' );
		$this->assertTrue( self::table_exists(), 'and the table is there afterwards' );
	}

	// When it cannot be put back there is no counter to trust, and an uncounted
	// guess is worse than a refused one.
	public function test_a_counter_that_cannot_be_rebuilt_fails_closed(): void {
		$this->drop_table();

		// Standing in for a database where the table cannot be created at all:
		// the repair has been tried and did not help.
		self::set_repair_spent( true );

		$this->assertFalse( self::table_exists(), 'the table has to be gone for this to mean anything' );
		$this->assertTrue( self::repair_is_spent(), 'and the repair has to be spent' );

		$reserved = \Sigil\Rate_Limit::reserve( 'probe' );

		$this->assertGreaterThan( \Sigil\Rate_Limit::max_attempts(), $reserved );

		\Sigil\Schema::install();
	}

	// Five guesses is five guesses however many tokens are in play, which is the
	// path that got around the one-guess-per-token claim.
	public function test_many_tokens_do_not_buy_more_guesses(): void {
		$user = self::factory()->user->create_and_get();
		\Sigil\Store::set_method( (int) $user->ID, 'email', array( 'enrolled_at' => 1 ) );

		$refused = 0;
		for ( $i = 0; $i < 8; $i++ ) {
			$token  = \Sigil\Challenge::start( $user, false, '' );
			$result = \Sigil\Challenge::complete( $token, 'email', array( 'code' => '000000' ) );

			if ( is_wp_error( $result ) && 'sigil_rate_limited' === $result->get_error_code() ) {
				$refused++;
			}
		}

		$this->assertGreaterThan( 0, $refused, 'a fresh token must not buy a fresh allowance' );

		\Sigil\Rate_Limit::clear( 'u:' . $user->ID );
	}
}
