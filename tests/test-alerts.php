<?php

declare( strict_types=1 );

/**
 * Adding a factor is how someone who already has the password makes their access
 * permanent, and nothing about the account looks different afterwards. The owner
 * is the only one who can notice, and only if told.
 */
class Test_Alerts extends WP_UnitTestCase {

	/** @var list<array<string, mixed>> */
	private array $sent = array();

	public function set_up(): void {
		parent::set_up();
		\Sigil\Schema::install();
		\Sigil\Alerts::instance();

		$this->sent = array();
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->sent[] = $args;
				return $args;
			}
		);
	}

	private function change( int $user_id, callable $mutate ): void {
		do_action( 'sigil_before_methods_change', $user_id );
		$mutate();
		do_action( 'sigil_methods_changed', $user_id );
	}

	public function test_adding_a_method_tells_the_account_holder(): void {
		$user = self::factory()->user->create_and_get();

		$this->change(
			(int) $user->ID,
			function () use ( $user ) {
				\Sigil\Store::set_method( (int) $user->ID, 'totp', array( 'secret' => 'x' ) );
			}
		);

		$this->assertCount( 1, $this->sent );
		$this->assertSame( $user->user_email, $this->sent[0]['to'] );
		$this->assertStringContainsString( 'Added', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'Authenticator', $this->sent[0]['message'] );
	}

	public function test_removing_a_method_says_so(): void {
		$user = self::factory()->user->create_and_get();
		\Sigil\Store::set_method( (int) $user->ID, 'totp', array( 'secret' => 'x' ) );

		$this->change(
			(int) $user->ID,
			function () use ( $user ) {
				\Sigil\Store::remove_method( (int) $user->ID, 'totp' );
			}
		);

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Removed', $this->sent[0]['message'] );
	}

	public function test_a_change_that_changed_nothing_sends_nothing(): void {
		$user = self::factory()->user->create_and_get();
		\Sigil\Store::set_method( (int) $user->ID, 'totp', array( 'secret' => 'x' ) );

		$this->change( (int) $user->ID, static function () {} );

		$this->assertSame( array(), $this->sent );
	}

	// A reset sends its own notice, so the generic alert must stay out of the way
	// rather than mail twice about one event.
	public function test_a_reset_does_not_send_two_emails(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}

		$user = self::factory()->user->create_and_get();
		\Sigil\Store::set_method( (int) $user->ID, 'totp', array( 'secret' => 'x' ) );

		\Sigil\Recovery::reset_user( (int) $user->ID, $admin );

		$this->assertCount( 1, $this->sent, 'exactly one mail about one event' );
		$this->assertStringContainsString( 'reset', strtolower( (string) $this->sent[0]['subject'] ) );
	}

	public function test_the_alert_can_be_turned_off(): void {
		add_filter( 'sigil_send_change_alert', '__return_false' );
		$user = self::factory()->user->create_and_get();

		$this->change(
			(int) $user->ID,
			function () use ( $user ) {
				\Sigil\Store::set_method( (int) $user->ID, 'totp', array( 'secret' => 'x' ) );
			}
		);

		$this->assertSame( array(), $this->sent );
	}

	// Behind a proxy REMOTE_ADDR is the proxy, so the line hedges rather than
	// asserting where someone was.
	public function test_the_origin_line_does_not_overstate_what_it_knows(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		$line                   = \Sigil\Alerts::origin_line();

		$this->assertStringContainsString( '203.0.113.10', $line );
		$this->assertStringContainsString( 'proxy', $line );

		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertStringNotContainsString( '203.0.113.10', \Sigil\Alerts::origin_line() );
	}

	public function test_a_bogus_address_is_not_repeated_into_the_email(): void {
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

		$this->assertSame( '', \Sigil\Alerts::request_ip() );
		$this->assertStringNotContainsString( 'not-an-ip', \Sigil\Alerts::origin_line() );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_the_login_code_email_says_where_the_request_came_from(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

		$user  = self::factory()->user->create_and_get();
		$email = new \Sigil\Providers\Email();
		$email->send_code( (int) $user->ID );

		$this->assertNotEmpty( $this->sent );
		$this->assertStringContainsString( '203.0.113.10', $this->sent[0]['message'] );

		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
