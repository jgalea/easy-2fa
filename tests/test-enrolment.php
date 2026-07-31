<?php

declare( strict_types=1 );

class Test_Enrolment extends WP_UnitTestCase {
	public function test_first_method_forces_backup_code_generation(): void {
		$uid = self::factory()->user->create();
		wp_set_current_user( $uid );
		\Sigil\Enrolment::instance()->complete_method( $uid, 'totp', [ 'secret' => 'x' ] );
		$this->assertArrayHasKey( 'backup', \Sigil\Store::methods( $uid ) );
	}

	public function test_cannot_remove_last_method_while_required(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ] ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $uid );
		\Sigil\Store::set_method( $uid, 'totp', [ 'secret' => 'x' ] );
		$this->assertWPError( \Sigil\Enrolment::instance()->remove_method( $uid, 'totp' ) );
	}

	public function test_user_cannot_edit_another_users_methods(): void {
		$a = self::factory()->user->create( [ 'role' => 'editor' ] );
		$b = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $a );
		$this->assertWPError( \Sigil\Enrolment::instance()->remove_method( $b, 'totp' ) );
	}
}
