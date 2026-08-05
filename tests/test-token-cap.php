<?php

declare( strict_types=1 );

/**
 * A token is claimed on use, so one token buys one guess. Nothing bounded how
 * many could be minted, and each fresh one is another guess.
 */
class Test_Token_Cap extends WP_UnitTestCase {

	use Clears_Attempts;

	public function test_an_account_cannot_hold_an_unbounded_pile_of_tokens(): void {
		$user = self::factory()->user->create_and_get();

		$tokens = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$tokens[] = \Sigil\Challenge::start( $user, false, '' );
		}

		$live = 0;
		foreach ( $tokens as $token ) {
			if ( \Sigil\Challenge::user_for( $token ) instanceof \WP_User ) {
				$live++;
			}
		}

		$this->assertGreaterThan( 0, $live, 'some have to survive or nobody could sign in' );
		$this->assertLessThan( count( $tokens ), $live, 'and minting more must not keep them all alive' );
	}

	// The one being used is the newest, so a retry is never the token evicted.
	public function test_the_newest_token_survives(): void {
		$user = self::factory()->user->create_and_get();

		$newest = '';
		for ( $i = 0; $i < 12; $i++ ) {
			$newest = \Sigil\Challenge::start( $user, false, '' );
		}

		$this->assertInstanceOf( \WP_User::class, \Sigil\Challenge::user_for( $newest ) );
	}

	// One account filling its own list must not touch anybody else's.
	public function test_one_account_does_not_evict_another(): void {
		$a = self::factory()->user->create_and_get();
		$b = self::factory()->user->create_and_get();

		$theirs = \Sigil\Challenge::start( $b, false, '' );

		for ( $i = 0; $i < 12; $i++ ) {
			\Sigil\Challenge::start( $a, false, '' );
		}

		$this->assertInstanceOf( \WP_User::class, \Sigil\Challenge::user_for( $theirs ) );
	}
}
