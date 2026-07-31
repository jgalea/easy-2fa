<?php

declare( strict_types=1 );

class Test_Policy extends WP_UnitTestCase {
	public function test_disabled_policy_requires_nobody(): void {
		\Sigil\Policy::update( [ 'enabled' => false, 'roles' => [ 'administrator' => true ] ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertFalse( \Sigil\Policy::required_for( $uid ) );
	}

	public function test_role_targeting(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 7 ] );
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$sub   = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->assertTrue( \Sigil\Policy::required_for( $admin ) );
		$this->assertFalse( \Sigil\Policy::required_for( $sub ) );
	}

	public function test_deadline_is_stamped_once_and_is_stable(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 7 ] );
		$uid   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$first = \Sigil\Policy::deadline_for( $uid );
		$this->assertSame( $first, \Sigil\Policy::deadline_for( $uid ) );
		$this->assertGreaterThan( time(), $first );
	}

	public function test_grace_zero_requires_immediately(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 0 ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertTrue( \Sigil\Policy::must_enrol_now( $uid ) );
	}

	public function test_enrolled_user_never_forced(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'administrator' => true ], 'grace_days' => 0 ] );
		$uid = self::factory()->user->create( [ 'role' => 'administrator' ] );
		\Sigil\Store::set_method( $uid, 'totp', [ 'secret' => 'x' ] );
		$this->assertFalse( \Sigil\Policy::must_enrol_now( $uid ) );
	}

	public function test_multi_role_user_matches_any_targeted_role(): void {
		\Sigil\Policy::update( [ 'enabled' => true, 'roles' => [ 'editor' => true ], 'grace_days' => 7 ] );
		$uid  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$user = get_user_by( 'id', $uid );
		$user->add_role( 'editor' );
		$this->assertTrue( \Sigil\Policy::required_for( $uid ) );
	}
}
