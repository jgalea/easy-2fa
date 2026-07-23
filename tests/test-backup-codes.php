<?php

declare( strict_types=1 );

class Test_Backup_Codes extends WP_UnitTestCase {
	public function test_generates_ten_codes(): void {
		$uid = self::factory()->user->create();
		$p   = new \Easy2FA\Providers\Backup_Codes();
		$this->assertCount( 10, $p->generate( $uid ) );
		$this->assertSame( 10, $p->remaining( $uid ) );
	}

	public function test_codes_are_hashed_at_rest(): void {
		$uid   = self::factory()->user->create();
		$p     = new \Easy2FA\Providers\Backup_Codes();
		$codes = $p->generate( $uid );
		$raw   = wp_json_encode( \Easy2FA\Store::methods( $uid )['backup'] );
		$this->assertStringNotContainsString( $codes[0], $raw );
	}

	public function test_code_is_single_use(): void {
		$uid   = self::factory()->user->create();
		$p     = new \Easy2FA\Providers\Backup_Codes();
		$codes = $p->generate( $uid );
		$this->assertTrue( $p->validate( $uid, [ 'code' => $codes[3] ] ) );
		$this->assertFalse( $p->validate( $uid, [ 'code' => $codes[3] ] ) );
		$this->assertSame( 9, $p->remaining( $uid ) );
	}

	public function test_regenerating_invalidates_old_codes(): void {
		$uid   = self::factory()->user->create();
		$p     = new \Easy2FA\Providers\Backup_Codes();
		$old   = $p->generate( $uid );
		$p->generate( $uid );
		$this->assertFalse( $p->validate( $uid, [ 'code' => $old[0] ] ) );
	}
}
