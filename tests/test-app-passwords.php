<?php

declare( strict_types=1 );

class Test_App_Passwords extends WP_UnitTestCase {
	public function test_blocked_role_cannot_use_application_passwords(): void {
		\Easy2FA\Policy::update( [ 'enabled' => true, 'block_app_passwords' => [ 'administrator' => true ] ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertFalse( wp_is_application_passwords_available_for_user( get_user_by( 'id', $uid ) ) );
	}

	public function test_unblocked_role_is_untouched(): void {
		\Easy2FA\Policy::update( [ 'enabled' => true, 'block_app_passwords' => [] ] );
		$uid = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->assertTrue( wp_is_application_passwords_available_for_user( get_user_by( 'id', $uid ) ) );
	}
}
