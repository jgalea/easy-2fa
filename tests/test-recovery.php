<?php

declare( strict_types=1 );

class Test_Recovery extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\Sigil\Schema::install();
	}

	public function test_admin_can_reset_another_user(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user  = self::factory()->user->create( [ 'role' => 'editor' ] );
		\Sigil\Store::set_method( $user, 'totp', [ 'secret' => 'x' ] );
		$this->assertTrue( \Sigil\Recovery::reset_user( $user, $admin ) );
		$this->assertFalse( \Sigil\Store::has_any( $user ) );
	}

	public function test_editor_cannot_reset_another_user(): void {
		$a = self::factory()->user->create( [ 'role' => 'editor' ] );
		$b = self::factory()->user->create( [ 'role' => 'editor' ] );
		\Sigil\Store::set_method( $b, 'totp', [ 'secret' => 'x' ] );
		$this->assertWPError( \Sigil\Recovery::reset_user( $b, $a ) );
		$this->assertTrue( \Sigil\Store::has_any( $b ) );
	}

	public function test_reset_clears_passkey_credentials_too(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user  = self::factory()->user->create();
		\Sigil\Credentials::add( $user, 'cred-x', 'pk', 0, 'Phone', 'internal' );
		\Sigil\Recovery::reset_user( $user, $admin );
		$this->assertSame( [], \Sigil\Credentials::for_user( $user ) );
	}

	public function test_reset_is_logged(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user  = self::factory()->user->create();
		\Sigil\Store::set_method( $user, 'totp', [ 'secret' => 'x' ] );
		\Sigil\Recovery::reset_user( $user, $admin );
		$log = get_user_meta( $user, '_sigil_reset_log', true );
		$this->assertSame( $admin, (int) $log[0]['actor'] );
	}
}
