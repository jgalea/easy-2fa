<?php

declare( strict_types=1 );

class Test_Challenge extends WP_UnitTestCase {
	public function test_token_resolves_to_user(): void {
		$user = self::factory()->user->create_and_get();
		$tok  = \Easy2FA\Challenge::start( $user, false );
		$this->assertSame( $user->ID, \Easy2FA\Challenge::user_for( $tok )->ID );
	}

	public function test_unknown_token_resolves_to_null(): void {
		$this->assertNull( \Easy2FA\Challenge::user_for( 'not-a-token' ) );
	}

	public function test_wrong_code_returns_error_and_counts_against_limit(): void {
		$user = self::factory()->user->create_and_get();
		\Easy2FA\Store::set_method( $user->ID, 'backup', [ 'hashes' => [] ] );
		$tok = \Easy2FA\Challenge::start( $user, false );
		$this->assertWPError( \Easy2FA\Challenge::complete( $tok, 'backup', [ 'code' => 'wrong' ] ) );
	}

	public function test_token_is_single_use(): void {
		$user  = self::factory()->user->create_and_get();
		$p     = new \Easy2FA\Providers\Backup_Codes();
		$codes = $p->generate( $user->ID );
		$tok   = \Easy2FA\Challenge::start( $user, false );
		$this->assertTrue( \Easy2FA\Challenge::complete( $tok, 'backup', [ 'code' => $codes[0] ] ) );
		$this->assertNull( \Easy2FA\Challenge::user_for( $tok ) );
	}

	public function test_blocked_user_cannot_complete_even_with_right_code(): void {
		$user  = self::factory()->user->create_and_get();
		$p     = new \Easy2FA\Providers\Backup_Codes();
		$codes = $p->generate( $user->ID );
		$tok   = \Easy2FA\Challenge::start( $user, false );
		for ( $i = 0; $i < 5; $i++ ) {
			\Easy2FA\Challenge::complete( $tok, 'backup', [ 'code' => 'wrong' ] );
		}
		$this->assertWPError( \Easy2FA\Challenge::complete( $tok, 'backup', [ 'code' => $codes[0] ] ) );
	}
}
