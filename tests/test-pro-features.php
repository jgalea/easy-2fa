<?php

declare( strict_types=1 );

/**
 * Pro features, exercised through the seams the free plugin exposes.
 *
 * Lives in the free repo's suite because that is where the WordPress test
 * harness is; it skips entirely when the add-on is not present.
 */
class Test_Pro_Features extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'SIGIL_PRO' ) || ! SIGIL_PRO ) {
			$this->markTestSkipped( 'pro add-on not installed' );
		}

		foreach ( array( 'class-trusted-devices', 'class-method-policy', 'class-branding', 'class-portability' ) as $file ) {
			require_once SIGIL_DIR . 'pro/' . $file . '.php';
		}
	}

	public function tear_down(): void {
		delete_option( \Easy2FA\Pro\Method_Policy::OPTION );
		delete_option( \Easy2FA\Pro\Branding::OPTION );
		remove_all_filters( 'sigil_providers' );
		parent::tear_down();
	}

	public function test_a_disallowed_method_is_refused_for_that_role(): void {
		\Easy2FA\Pro\Method_Policy::update(
			array( 'subscriber' => array( 'email' => false, 'totp' => true ) )
		);

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$editor     = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertFalse( \Easy2FA\Pro\Method_Policy::allowed_for( $subscriber, 'email' ) );
		$this->assertTrue( \Easy2FA\Pro\Method_Policy::allowed_for( $subscriber, 'totp' ) );
		$this->assertTrue( \Easy2FA\Pro\Method_Policy::allowed_for( $editor, 'email' ), 'a role with no rule keeps everything' );
	}

	// Holding two roles must widen what someone may use, never narrow it, or
	// adding a role would quietly take a method away.
	public function test_the_more_permissive_role_wins(): void {
		\Easy2FA\Pro\Method_Policy::update(
			array(
				'subscriber' => array( 'email' => false ),
				'editor'     => array( 'email' => true ),
			)
		);

		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $user )->add_role( 'editor' );

		$this->assertTrue( \Easy2FA\Pro\Method_Policy::allowed_for( $user, 'email' ) );
	}

	public function test_the_registry_drops_a_disallowed_method(): void {
		\Easy2FA\Pro\Method_Policy::update(
			array( 'subscriber' => array( 'email' => false, 'totp' => true, 'backup' => true, 'passkey' => true ) )
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		\Easy2FA\Pro\Method_Policy::instance();

		$ids = array_map(
			static function ( \Sigil\Provider $provider ): string {
				return $provider->id();
			},
			\Sigil\Providers::instance()->all()
		);
		$this->assertNotContains( 'email', $ids );
		$this->assertContains( 'totp', $ids );
	}

	// A policy that allows nothing would leave an account unable to authenticate
	// at all, so it is treated as a misconfiguration rather than obeyed.
	public function test_a_policy_that_allows_nothing_is_ignored(): void {
		\Easy2FA\Pro\Method_Policy::update(
			array( 'subscriber' => array( 'email' => false, 'totp' => false, 'backup' => false, 'passkey' => false ) )
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		\Easy2FA\Pro\Method_Policy::instance();

		$this->assertNotEmpty( \Sigil\Providers::instance()->all() );
	}

	public function test_a_custom_email_replaces_the_default(): void {
		\Easy2FA\Pro\Branding::update(
			array(
				'subject'   => 'Your {{site}} code',
				'body'      => 'Hello {{user}}, your code is {{code}}.',
				'from'      => 'security@example.com',
				'from_name' => 'Example Security',
			)
		);
		\Easy2FA\Pro\Branding::instance();

		$user = self::factory()->user->create_and_get( array( 'display_name' => 'Ada' ) );

		$this->assertSame( 'Your Example code', apply_filters( 'sigil_email_subject', 'default', $user, 'Example' ) );
		$this->assertSame(
			'Hello Ada, your code is 123456.',
			apply_filters( 'sigil_email_message', 'default', $user, '123456', 'Example' )
		);
		$this->assertSame( 'security@example.com', apply_filters( 'sigil_email_from', 'wordpress@example.com', $user ) );
		$this->assertSame( 'Example Security', apply_filters( 'sigil_email_from_name', 'Example', $user ) );
	}

	// A template that lost its placeholder would mail a code-less code email.
	public function test_a_template_without_the_code_falls_back(): void {
		\Easy2FA\Pro\Branding::update( array( 'body' => 'Hello, please sign in.' ) );
		\Easy2FA\Pro\Branding::instance();

		$user = self::factory()->user->create_and_get();

		$this->assertSame(
			'the default body',
			apply_filters( 'sigil_email_message', 'the default body', $user, '123456', 'Example' )
		);
	}

	public function test_export_carries_settings_and_no_secrets(): void {
		\Sigil\Policy::update( array( 'enabled' => true, 'grace_days' => 4 ) );
		\Easy2FA\Pro\Method_Policy::update( array( 'editor' => array( 'email' => false ) ) );

		$payload = \Easy2FA\Pro\Portability::export();
		$json    = wp_json_encode( $payload );

		$this->assertSame( 'sigil-2fa', $payload['plugin'] );
		$this->assertSame( 4, $payload['policy']['grace_days'] );
		$this->assertFalse( $payload['methods']['editor']['email'] );

		// block_app_passwords is a setting name, not a secret, so the check is
		// for the things that would actually be a leak.
		foreach ( array( 'secret', 'credential', 'public_key', 'sign_count' ) as $forbidden ) {
			$this->assertStringNotContainsStringIgnoringCase( $forbidden, (string) $json );
		}
	}

	public function test_import_restores_settings(): void {
		$json = wp_json_encode(
			array(
				'format'   => 1,
				'plugin'   => 'sigil-2fa',
				'policy'   => array( 'grace_days' => 9 ),
				'methods'  => array( 'author' => array( 'email' => false ) ),
				'branding' => array( 'subject' => 'Imported subject' ),
			)
		);

		$this->assertTrue( \Easy2FA\Pro\Portability::import( (string) $json ) );
		$this->assertSame( 9, \Sigil\Policy::get()['grace_days'] );
		$this->assertFalse( \Easy2FA\Pro\Method_Policy::get()['author']['email'] );
		$this->assertSame( 'Imported subject', \Easy2FA\Pro\Branding::get()['subject'] );
	}

	public function test_import_refuses_a_foreign_or_unreadable_file(): void {
		$this->assertWPError( \Easy2FA\Pro\Portability::import( 'not json' ) );
		$this->assertWPError( \Easy2FA\Pro\Portability::import( '{"plugin":"something-else","format":1}' ) );
		$this->assertWPError( \Easy2FA\Pro\Portability::import( '{"plugin":"sigil-2fa","format":99}' ) );
	}

	// Turning 2FA off is not what an administrator expects from "import".
	public function test_import_never_disables_an_enabled_policy(): void {
		\Sigil\Policy::update( array( 'enabled' => true ) );

		$json = wp_json_encode(
			array( 'format' => 1, 'plugin' => 'sigil-2fa', 'policy' => array( 'enabled' => false, 'grace_days' => 2 ) )
		);
		\Easy2FA\Pro\Portability::import( (string) $json );

		$this->assertTrue( \Sigil\Policy::get()['enabled'] );
		$this->assertSame( 2, \Sigil\Policy::get()['grace_days'] );
	}
}
