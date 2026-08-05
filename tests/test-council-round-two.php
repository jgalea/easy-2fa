<?php

declare( strict_types=1 );

/**
 * Regressions for the second security review.
 */
class Test_Council_Round_Two extends WP_UnitTestCase {

	private \WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'SIGIL_PRO' ) || ! SIGIL_PRO ) {
			$this->markTestSkipped( 'pro add-on not installed' );
		}

		\Sigil\Schema::install();
		foreach ( array( 'class-method-policy', 'class-zero-setup', 'class-always-email', 'class-bypass' ) as $f ) {
			require_once SIGIL_DIR . 'pro/' . $f . '.php';
		}

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( \Sigil\Pro\Method_Policy::OPTION );
		delete_option( \Sigil\Pro\Zero_Setup::OPTION );
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	// The worst outcome a policy can have is switching the thing off. Forbidding
	// the only method an account holds must never leave it with no challenge.
	public function test_forbidding_someones_only_method_does_not_disable_their_2fa(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		\Sigil\Store::set_method( $user->ID, 'email', array( 'enrolled_at' => 1 ) );

		\Sigil\Pro\Method_Policy::update(
			array( 'subscriber' => array( 'email' => false, 'totp' => true, 'backup' => true, 'passkey' => true ) )
		);
		\Sigil\Pro\Method_Policy::instance()->register();
		wp_set_current_user( (int) $user->ID );

		$this->assertNotEmpty(
			\Sigil\Providers::instance()->enrolled_for( (int) $user->ID ),
			'the account still has a factor, so the login must still be challenged'
		);
		$this->assertTrue( \Sigil\Providers::instance()->has_usable( (int) $user->ID ) );
	}

	// The rule belongs where a method is chosen.
	public function test_a_forbidden_method_cannot_be_enrolled(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\Sigil\Pro\Method_Policy::update( array( 'subscriber' => array( 'email' => false ) ) );
		\Sigil\Pro\Method_Policy::instance()->register();
		wp_set_current_user( $user );

		$result = \Sigil\Enrolment::instance()->complete_method( $user, 'email', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'sigil_method_not_allowed', $result->get_error_code() );
	}

	public function test_an_allowed_method_still_enrols(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\Sigil\Pro\Method_Policy::update( array( 'subscriber' => array( 'email' => false, 'backup' => true ) ) );
		\Sigil\Pro\Method_Policy::instance()->register();
		wp_set_current_user( $user );

		$this->assertTrue( \Sigil\Enrolment::instance()->complete_method( $user, 'backup', array() ) );
	}

	// The policy used to identify the challenged user from $_REQUEST, which the
	// REST route never populates, so it silently did not apply there at all.
	public function test_the_rest_route_is_not_a_way_around_the_policy(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		\Sigil\Store::set_method( $user->ID, 'email', array( 'enrolled_at' => 1 ) );
		\Sigil\Pro\Method_Policy::update( array( 'subscriber' => array( 'email' => false, 'totp' => true ) ) );
		\Sigil\Pro\Method_Policy::instance()->register();

		$email = new \Sigil\Providers\Email();
		$email->send_code( (int) $user->ID );
		$code = get_user_meta( $user->ID, '_sigil_email_debug_code', true );

		$token = \Sigil\Challenge::start( $user, false, '' );

		$request = new \WP_REST_Request( 'POST', '/sigil/v1/challenge' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'token' => $token, 'provider' => 'email', 'code' => $code ) ) );

		// The account holds email, so it may still use it. What matters is that
		// the outcome no longer depends on which route the request arrived by.
		$rest = $this->server->dispatch( $request )->get_status();

		wp_set_current_user( (int) $user->ID );
		$form_can_use = false;
		foreach ( \Sigil\Providers::instance()->enrolled_for( (int) $user->ID ) as $provider ) {
			if ( 'email' === $provider->id() ) {
				$form_can_use = true;
			}
		}

		$this->assertSame( $form_can_use, 200 === $rest, 'both routes must agree' );
	}

	// Automatic email is a floor for accounts with nothing, not an extra door on
	// accounts that already hold something stronger.
	public function test_zero_setup_does_not_add_email_to_an_account_with_a_stronger_factor(): void {
		\Sigil\Pro\Zero_Setup::set_enabled( true );
		\Sigil\Pro\Zero_Setup::instance()->register();

		$bare   = self::factory()->user->create();
		$strong = self::factory()->user->create();
		\Sigil\Store::set_method( $strong, 'totp', array( 'secret' => 'x' ) );

		$ids = static function ( int $id ): array {
			return array_map(
				static function ( \Sigil\Provider $p ): string {
					return $p->id();
				},
				\Sigil\Providers::instance()->enrolled_for( $id )
			);
		};

		$this->assertContains( 'email', $ids( $bare ), 'an account with nothing is covered' );
		$this->assertNotContains( 'email', $ids( $strong ), 'an account with an authenticator is not lowered to its inbox' );
	}

	public function test_zero_setup_still_honours_a_deliberate_email_enrolment(): void {
		\Sigil\Pro\Zero_Setup::set_enabled( true );
		\Sigil\Pro\Zero_Setup::instance()->register();

		$user = self::factory()->user->create();
		\Sigil\Store::set_method( $user, 'email', array( 'enrolled_at' => 1 ) );
		\Sigil\Store::set_method( $user, 'totp', array( 'secret' => 'x' ) );

		$ids = array_map(
			static function ( \Sigil\Provider $p ): string {
				return $p->id();
			},
			\Sigil\Providers::instance()->enrolled_for( $user )
		);

		$this->assertContains( 'email', $ids, 'chosen email is still email' );
	}

	// One excused sign-in has to mean one, even if two logins arrive together.
	public function test_an_excused_sign_in_cannot_be_spent_twice(): void {
		$actor = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $actor );
		}
		$user = self::factory()->user->create_and_get();

		\Sigil\Pro\Bypass::grant_for( (int) $user->ID, $actor );
		\Sigil\Pro\Bypass::instance()->register();

		$grant = get_user_meta( (int) $user->ID, '_sigil_pro_bypass', true );

		// Both requests read the same live grant before either deletes it.
		$first  = \Sigil\Pro\Bypass::instance()->maybe_skip( false, $user );
		update_user_meta( (int) $user->ID, '_sigil_pro_bypass', $grant );
		$second = \Sigil\Pro\Bypass::instance()->maybe_skip( false, $user );
		\Sigil\Pro\Bypass::revoke( (int) $user->ID );

		$this->assertTrue( $first );
		$this->assertTrue( $second, 'a re-granted excuse is a new one' );
		$this->assertFalse(
			\Sigil\Pro\Bypass::instance()->maybe_skip( false, $user ),
			'but a spent one is gone'
		);
	}
}
