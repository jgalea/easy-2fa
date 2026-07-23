<?php

declare( strict_types=1 );

namespace Easy2FA\Providers;

use Easy2FA\Provider;
use Easy2FA\Store;

defined( 'ABSPATH' ) || exit;

final class Email implements Provider {

	private const CODE_TTL       = 600;
	private const RESEND_COOLDOWN = 60;
	private const TRANSIENT_KEY  = 'easy2fa_email_';
	private const COOLDOWN_KEY   = 'easy2fa_email_cd_';
	// Test-only plaintext mirror key. See send_code() for write conditions.
	private const DEBUG_META     = '_easy2fa_email_debug_code';

	public function id(): string {
		return 'email';
	}

	public function label(): string {
		return __( 'Email code', 'easy-2fa' );
	}

	public function priority(): int {
		return 30;
	}

	public function is_available(): bool {
		return true;
	}

	public function is_enrolled( int $user_id ): bool {
		$methods = Store::methods( $user_id );
		return isset( $methods[ $this->id() ] );
	}

	public function render_enrol( int $user_id ): void {
		$user = get_userdata( $user_id );
		$email = $user instanceof \WP_User ? $user->user_email : '';
		echo '<p>' . esc_html__( 'We will send a six-digit code to your account email when you sign in.', 'easy-2fa' ) . '</p>';
		if ( '' !== $email ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: user email address */
					__( 'Codes go to: %s', 'easy-2fa' ),
					$email
				)
			) . '</p>';
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function handle_enrol( int $user_id, array $input ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
			return new \WP_Error(
				'easy2fa_email_missing',
				__( 'Your account needs a valid email address to use email codes.', 'easy-2fa' )
			);
		}

		Store::set_method(
			$user_id,
			$this->id(),
			[
				'enrolled_at' => time(),
			]
		);

		return true;
	}

	public function render_challenge( int $user_id ): void {
		echo '<p>' . esc_html__( 'Enter the six-digit code we sent to your email.', 'easy-2fa' ) . '</p>';
		echo '<p><label for="easy2fa-email-code">' . esc_html__( 'Email code', 'easy-2fa' ) . '</label> ';
		echo '<input type="text" name="code" id="easy2fa-email-code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required /></p>';
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function validate( int $user_id, array $input ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$raw = isset( $input['code'] ) ? $input['code'] : '';
		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return false;
		}

		$code = preg_replace( '/\D/', '', (string) $raw );
		if ( ! is_string( $code ) || 6 !== strlen( $code ) ) {
			return false;
		}

		$stored = get_transient( self::TRANSIENT_KEY . $user_id );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return false;
		}

		$expected = $this->hash_code( $code );
		if ( ! hash_equals( $stored, $expected ) ) {
			return false;
		}

		delete_transient( self::TRANSIENT_KEY . $user_id );
		delete_user_meta( $user_id, self::DEBUG_META );

		return true;
	}

	public function unenrol( int $user_id ): void {
		Store::remove_method( $user_id, $this->id() );
		delete_transient( self::TRANSIENT_KEY . $user_id );
		delete_transient( self::COOLDOWN_KEY . $user_id );
		delete_user_meta( $user_id, self::DEBUG_META );
	}

	public function send_code( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( false !== get_transient( self::COOLDOWN_KEY . $user_id ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
			return false;
		}

		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		set_transient( self::TRANSIENT_KEY . $user_id, $this->hash_code( $code ), self::CODE_TTL );
		set_transient( self::COOLDOWN_KEY . $user_id, 1, self::RESEND_COOLDOWN );

		// Test-only plaintext mirror. WP_DEBUG on production debug installs, or WP unit tests
		// (wp-env's wp-tests-config.php sets WP_DEBUG false; WP_TESTS_DOMAIN is test-suite-only).
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || defined( 'WP_TESTS_DOMAIN' ) ) {
			update_user_meta( $user_id, self::DEBUG_META, $code );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		if ( '' === $site_name ) {
			$site_name = home_url();
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your login code for %s', 'easy-2fa' ),
			$site_name
		);

		$message = sprintf(
			/* translators: 1: site name, 2: six-digit code */
			__(
				"Someone is trying to sign in to %1\$s.\n\nYour one-time code is: %2\$s\n\nThis code expires in ten minutes and can only be used once.\n\nNobody from %1\$s will ever ask you for this code. If you did not try to sign in, you can ignore this email.",
				'easy-2fa'
			),
			$site_name,
			$code
		);

		$from = get_option( 'admin_email' );
		if ( ! is_string( $from ) || ! is_email( $from ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			$from = is_string( $host ) && '' !== $host ? 'wordpress@' . $host : 'wordpress@example.com';
		}

		$from_filter = static function () use ( $from ): string {
			return $from;
		};
		$from_name_filter = static function () use ( $site_name ): string {
			return $site_name;
		};

		add_filter( 'wp_mail_from', $from_filter, 999 );
		add_filter( 'wp_mail_from_name', $from_name_filter, 999 );
		$sent = (bool) wp_mail( $user->user_email, $subject, $message );
		remove_filter( 'wp_mail_from', $from_filter, 999 );
		remove_filter( 'wp_mail_from_name', $from_name_filter, 999 );

		return $sent;
	}

	private function hash_code( string $code ): string {
		return hash_hmac( 'sha256', $code, wp_salt( 'auth' ) );
	}
}
