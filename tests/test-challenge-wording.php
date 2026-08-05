<?php

declare( strict_types=1 );

/**
 * The challenge screen carries wording an administrator can set, and it names
 * the person signing in. Formatting that text rather than substituting into it
 * put an admin-supplied string into sprintf, where a percent sign is a format
 * specifier: "50% off" threw, and an uncaught throw here is the login screen
 * gone for everybody holding a second factor.
 */
class Test_Challenge_Wording extends WP_UnitTestCase {

	private function render_intro( string $intro ): string {
		add_filter(
			'sigil_challenge_text',
			static function ( array $text ) use ( $intro ): array {
				$text['intro'] = $intro;
				return $text;
			}
		);

		$user      = self::factory()->user->create_and_get( array( 'user_login' => 'ada' ) );
		$provider  = \Sigil\Providers::instance()->get( 'email' );
		$this->assertNotNull( $provider, 'the email provider has to be registered for this to render' );

		$challenge_token     = 'token';
		$challenge_user      = $user;
		$challenge_providers = array( $provider );
		$challenge_active    = $provider;
		$challenge_error     = null;
		$challenge_remember  = false;
		$challenge_redirect  = '';

		ob_start();
		require SIGIL_DIR . 'templates/challenge.php';

		return (string) ob_get_clean();
	}

	public function test_a_percent_sign_in_the_intro_does_not_break_the_screen(): void {
		$html = $this->render_intro( '50% off. Signing in as {{login}}.' );

		$this->assertStringContainsString( '50% off', $html );
		$this->assertStringContainsString( 'ada', $html );
	}

	public function test_a_trailing_percent_does_not_break_the_screen(): void {
		$this->assertStringContainsString( 'Sign in — 100%', $this->render_intro( 'Sign in — 100%' ) );
	}

	public function test_a_second_placeholder_does_not_break_the_screen(): void {
		$html = $this->render_intro( 'Hi %s, %s' );

		$this->assertStringContainsString( 'Hi ada, ada', $html );
	}

	// The wording anyone already typed, and the default string, keep working.
	public function test_the_legacy_placeholder_still_names_the_user(): void {
		$this->assertStringContainsString( 'Signing in as ada.', $this->render_intro( 'Signing in as %s.' ) );
	}
}
