<?php

declare( strict_types=1 );

/**
 * The wizard exists to stop the most common rollout mistake: enforcing 2FA on
 * everyone before setting up your own, and locking yourself out first.
 */
class Test_Pro_Wizard extends WP_UnitTestCase {

	private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'SIGIL_PRO' ) || ! SIGIL_PRO ) {
			$this->markTestSkipped( 'pro add-on not installed' );
		}

		require_once SIGIL_DIR . 'pro/class-wizard.php';
		\Sigil\Schema::install();
	}

	public function tear_down(): void {
		delete_option( \Sigil\Pro\Wizard::OPTION );
		\Sigil\Network::delete_option( \Sigil\Policy::OPTION_KEY );
		parent::tear_down();
	}

	private function enrol( int $user_id ): void {
		$totp = new \Sigil\Providers\Totp();
		$totp->handle_enrol(
			$user_id,
			array( 'secret' => self::SECRET, 'code' => \Sigil\Providers\Totp::code_at( self::SECRET, time() - 30 ) )
		);
	}

	public function test_it_starts_by_asking_you_to_protect_yourself(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$state = \Sigil\Pro\Wizard::state();
		$this->assertSame( 1, $state['step'] );
		$this->assertFalse( $state['enrolled'] );
	}

	public function test_it_moves_on_once_you_hold_a_factor(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->enrol( $admin );

		$state = \Sigil\Pro\Wizard::state();
		$this->assertTrue( $state['enrolled'] );
		$this->assertSame( 2, $state['step'] );
	}

	// Enabled with no roles chosen protects nobody, so it does not count as done.
	public function test_an_empty_role_list_is_not_a_policy(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->enrol( $admin );

		\Sigil\Policy::update( array( 'enabled' => true, 'roles' => array( 'administrator' => false ) ) );

		$this->assertFalse( \Sigil\Pro\Wizard::state()['policy_set'] );
		$this->assertSame( 2, \Sigil\Pro\Wizard::state()['step'] );
	}

	public function test_it_reaches_the_last_step_once_a_policy_is_real(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->enrol( $admin );

		\Sigil\Policy::update( array( 'enabled' => true, 'roles' => array( 'administrator' => true ) ) );

		$state = \Sigil\Pro\Wizard::state();
		$this->assertTrue( $state['policy_set'] );
		$this->assertSame( 3, $state['step'] );
	}

	public function test_the_prompt_stops_once_it_is_dismissed(): void {
		$this->assertFalse( \Sigil\Pro\Wizard::is_done() );
		\Sigil\Network::update_option( \Sigil\Pro\Wizard::OPTION, true );
		$this->assertTrue( \Sigil\Pro\Wizard::is_done() );
	}
}
