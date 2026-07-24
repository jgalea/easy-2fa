<?php

declare( strict_types=1 );

class Test_Credentials extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\Easy2FA\Schema::install();
	}

	public function test_delete_refuses_other_users_credential(): void {
		$a  = self::factory()->user->create();
		$b  = self::factory()->user->create();
		$id = \Easy2FA\Credentials::add( $a, 'cred-a', 'pk', 0, 'Phone', 'internal' );
		$this->assertFalse( \Easy2FA\Credentials::delete( $id, $b ) );
		$this->assertNotNull( \Easy2FA\Credentials::by_credential_id( 'cred-a' ) );
	}

	public function test_sign_count_regression_is_detectable(): void {
		$a  = self::factory()->user->create();
		$id = \Easy2FA\Credentials::add( $a, 'cred-b', 'pk', 5, 'Key', 'usb' );
		\Easy2FA\Credentials::touch( $id, 9 );
		$this->assertSame( 9, (int) \Easy2FA\Credentials::by_credential_id( 'cred-b' )->sign_count );
	}

	public function test_for_user_returns_only_own_credentials(): void {
		$a = self::factory()->user->create();
		$b = self::factory()->user->create();
		\Easy2FA\Credentials::add( $a, 'cred-a2', 'pk-a', 0, 'A', 'usb' );
		\Easy2FA\Credentials::add( $b, 'cred-b2', 'pk-b', 0, 'B', 'nfc' );

		$for_a = \Easy2FA\Credentials::for_user( $a );
		$this->assertCount( 1, $for_a );
		$this->assertSame( 'cred-a2', $for_a[0]->credential_id );
	}

	public function test_add_returns_positive_id(): void {
		$a  = self::factory()->user->create();
		$id = \Easy2FA\Credentials::add( $a, 'cred-c', 'pk', 0, 'Label', 'ble' );
		$this->assertGreaterThan( 0, $id );
	}
}
