<?php

declare( strict_types=1 );

class Test_Enforcement extends WP_UnitTestCase {
	public function test_redirect_target_for_overdue_user(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 0 ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertNotEmpty( \Sigil\Enforcement::instance()->redirect_target( $uid ) );
	}

	public function test_no_redirect_within_grace(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 7 ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertSame( '', \Sigil\Enforcement::instance()->redirect_target( $uid ) );
	}

	public function test_setup_page_itself_is_never_redirected(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 0 ] );
		$uid            = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$_GET['page']   = 'sigil-2fa-setup';
		$this->assertSame( '', \Sigil\Enforcement::instance()->redirect_target( $uid ) );
		unset( $_GET['page'] );
	}
}
