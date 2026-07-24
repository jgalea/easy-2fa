<?php

declare( strict_types=1 );

namespace Easy2FA\Providers;

use Easy2FA\Credentials;
use Easy2FA\Provider;
use Easy2FA\Store;

defined( 'ABSPATH' ) || exit;

require_once EASY2FA_DIR . 'includes/class-credentials.php';

final class Passkey implements Provider {

	private const CHALLENGE_TTL = 300;

	private static bool $booted = false;

	public function __construct() {
		self::boot();
		$this->register_hooks();
	}

	/**
	 * Load the WebAuthn library only on PHP 8.0+ so PHP 7.4 never parses it.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( PHP_VERSION_ID < 80000 ) {
			return;
		}

		$lib = EASY2FA_DIR . 'vendor/lbuchs/webauthn/WebAuthn.php';
		if ( is_readable( $lib ) ) {
			require_once $lib;
		}
	}

	public function id(): string {
		return 'passkey';
	}

	public function label(): string {
		return __( 'Passkey', 'easy-2fa' );
	}

	public function priority(): int {
		return 10;
	}

	public function is_available(): bool {
		return PHP_VERSION_ID >= 80000
			&& extension_loaded( 'openssl' )
			&& extension_loaded( 'mbstring' );
	}

	public function is_enrolled( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$methods = Store::methods( $user_id );
		if ( ! isset( $methods[ $this->id() ] ) ) {
			return false;
		}

		return [] !== Credentials::for_user( $user_id );
	}

	public function render_enrol( int $user_id ): void {
		if ( ! $this->is_available() || $user_id <= 0 ) {
			echo '<p>' . esc_html__( 'Passkeys are not available in this environment.', 'easy-2fa' ) . '</p>';
			return;
		}

		$this->enqueue_script( $user_id );

		echo '<div class="easy2fa-passkey-enrol" data-user-id="' . esc_attr( (string) $user_id ) . '">';
		echo '<p>' . esc_html__( 'Register a passkey for this account. You will be prompted by your browser or device.', 'easy-2fa' ) . '</p>';
		echo '<p><label for="easy2fa-passkey-label">' . esc_html__( 'Device label', 'easy-2fa' ) . '</label> ';
		echo '<input type="text" id="easy2fa-passkey-label" name="easy2fa_passkey_label" class="regular-text" value="" maxlength="190" /></p>';
		echo '<p><button type="button" class="button button-primary easy2fa-passkey-register">' . esc_html__( 'Register passkey', 'easy-2fa' ) . '</button></p>';
		echo '<p class="easy2fa-passkey-status" role="status" aria-live="polite"></p>';
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function handle_enrol( int $user_id, array $input ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'easy2fa_passkey_unavailable', __( 'Passkeys are not available in this environment.', 'easy-2fa' ) );
		}

		if ( $user_id <= 0 || ! $this->user_can_manage( $user_id ) ) {
			return new \WP_Error( 'easy2fa_forbidden', __( 'You are not allowed to enrol a passkey for this user.', 'easy-2fa' ) );
		}

		$client_data_json  = $this->decode_binary_field( $input['clientDataJSON'] ?? '' );
		$attestation_object = $this->decode_binary_field( $input['attestationObject'] ?? '' );
		$label             = isset( $input['label'] ) ? sanitize_text_field( (string) $input['label'] ) : '';
		$transports_raw    = $input['transports'] ?? '';
		$transports        = $this->sanitize_transports( $transports_raw );

		if ( '' === $client_data_json || '' === $attestation_object ) {
			return new \WP_Error( 'easy2fa_passkey_invalid', __( 'Invalid passkey registration data.', 'easy-2fa' ) );
		}

		$challenge = $this->get_challenge( $user_id, 'register' );
		if ( null === $challenge ) {
			return new \WP_Error( 'easy2fa_passkey_challenge', __( 'Registration challenge expired. Please try again.', 'easy-2fa' ) );
		}

		try {
			$webauthn = $this->webauthn();
			// failIfRootMismatch=false: browser "none" attestation is common without a CA chain.
			$data = $webauthn->processCreate(
				$client_data_json,
				$attestation_object,
				$challenge,
				false,
				true,
				false
			);
		} catch ( \Throwable $e ) {
			$this->clear_challenge( $user_id, 'register' );
			return new \WP_Error( 'easy2fa_passkey_verify', __( 'Passkey registration failed verification.', 'easy-2fa' ) );
		}

		$this->clear_challenge( $user_id, 'register' );

		$credential_id = is_string( $data->credentialId ) ? $data->credentialId : '';
		$public_key    = is_string( $data->credentialPublicKey ) ? $data->credentialPublicKey : '';
		$sign_count    = isset( $data->signatureCounter ) && is_int( $data->signatureCounter ) ? $data->signatureCounter : 0;

		if ( '' === $credential_id || '' === $public_key ) {
			return new \WP_Error( 'easy2fa_passkey_invalid', __( 'Invalid passkey registration data.', 'easy-2fa' ) );
		}

		if ( null !== Credentials::by_credential_id( $credential_id ) ) {
			return new \WP_Error( 'easy2fa_passkey_duplicate', __( 'This passkey is already registered.', 'easy-2fa' ) );
		}

		if ( '' === $label ) {
			$label = __( 'Passkey', 'easy-2fa' );
		}

		$id = Credentials::add( $user_id, $credential_id, $public_key, $sign_count, $label, $transports );
		if ( $id <= 0 ) {
			return new \WP_Error( 'easy2fa_passkey_store', __( 'Could not store the passkey.', 'easy-2fa' ) );
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
		if ( ! $this->is_available() || $user_id <= 0 ) {
			echo '<p>' . esc_html__( 'Passkeys are not available in this environment.', 'easy-2fa' ) . '</p>';
			return;
		}

		$this->enqueue_script( $user_id );

		echo '<div class="easy2fa-passkey-challenge" data-user-id="' . esc_attr( (string) $user_id ) . '">';
		echo '<p>' . esc_html__( 'Use your passkey to continue.', 'easy-2fa' ) . '</p>';
		echo '<p><button type="button" class="button button-primary easy2fa-passkey-authenticate">' . esc_html__( 'Use passkey', 'easy-2fa' ) . '</button></p>';
		echo '<p class="easy2fa-passkey-status" role="status" aria-live="polite"></p>';
		echo '<input type="hidden" name="easy2fa_passkey_assertion" id="easy2fa-passkey-assertion" value="" />';
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function validate( int $user_id, array $input ): bool {
		if ( ! $this->is_available() || $user_id <= 0 ) {
			return false;
		}

		$client_data_json   = $this->decode_binary_field( $input['clientDataJSON'] ?? '' );
		$authenticator_data = $this->decode_binary_field( $input['authenticatorData'] ?? '' );
		$signature          = $this->decode_binary_field( $input['signature'] ?? '' );
		$raw_id             = $this->decode_binary_field( $input['id'] ?? ( $input['rawId'] ?? '' ) );

		if ( '' === $client_data_json || '' === $authenticator_data || '' === $signature || '' === $raw_id ) {
			return false;
		}

		$cred = Credentials::by_credential_id( $raw_id );
		if ( null === $cred || (int) $cred->user_id !== $user_id ) {
			return false;
		}

		$challenge = $this->get_challenge( $user_id, 'auth' );
		if ( null === $challenge ) {
			return false;
		}

		$prev_count = (int) $cred->sign_count;

		try {
			$webauthn = $this->webauthn();
			$webauthn->processGet(
				$client_data_json,
				$authenticator_data,
				$signature,
				(string) $cred->public_key,
				$challenge,
				$prev_count,
				false,
				true
			);
		} catch ( \Throwable $e ) {
			$this->clear_challenge( $user_id, 'auth' );
			if ( $this->is_sign_count_error( $e ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'Easy 2FA: passkey sign count regression for user %d credential %d',
						$user_id,
						(int) $cred->id
					)
				);
			}
			return false;
		}

		$new_count = $webauthn->getSignatureCounter();
		if ( ! is_int( $new_count ) ) {
			$new_count = $prev_count;
		}

		// Defence in depth: library already checks, but plan requires explicit refuse when stored is non-zero and new is not greater.
		if ( $prev_count > 0 && $new_count <= $prev_count ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'Easy 2FA: passkey sign count regression for user %d credential %d (stored=%d new=%d)',
					$user_id,
					(int) $cred->id,
					$prev_count,
					$new_count
				)
			);
			$this->clear_challenge( $user_id, 'auth' );
			return false;
		}

		Credentials::touch( (int) $cred->id, $new_count > 0 ? $new_count : $prev_count );
		$this->clear_challenge( $user_id, 'auth' );

		return true;
	}

	public function unenrol( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		Credentials::delete_for_user( $user_id );
		Store::remove_method( $user_id, $this->id() );
	}

	private function register_hooks(): void {
		add_action( 'wp_ajax_easy2fa_passkey_register_options', [ $this, 'ajax_register_options' ] );
		add_action( 'wp_ajax_easy2fa_passkey_register', [ $this, 'ajax_register' ] );
		add_action( 'wp_ajax_easy2fa_passkey_auth_options', [ $this, 'ajax_auth_options' ] );
		add_action( 'wp_ajax_easy2fa_passkey_auth', [ $this, 'ajax_auth' ] );
	}

	public function ajax_register_options(): void {
		$this->ajax_guard();

		$user_id = $this->request_user_id();
		if ( null === $user_id || ! $this->user_can_manage( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to enrol a passkey for this user.', 'easy-2fa' ) ], 403 );
		}

		if ( ! $this->is_available() ) {
			wp_send_json_error( [ 'message' => __( 'Passkeys are not available in this environment.', 'easy-2fa' ) ], 400 );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'User not found.', 'easy-2fa' ) ], 404 );
		}

		$exclude = [];
		foreach ( Credentials::for_user( $user_id ) as $row ) {
			$exclude[] = $row->credential_id;
		}

		try {
			$webauthn = $this->webauthn();
			$args     = $webauthn->getCreateArgs(
				(string) $user_id,
				$user->user_login,
				$user->display_name,
				self::CHALLENGE_TTL,
				false,
				'preferred',
				null,
				$exclude
			);
			$challenge = $webauthn->getChallenge()->getBinaryString();
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => __( 'Could not start passkey registration.', 'easy-2fa' ) ], 500 );
		}

		$this->store_challenge( $user_id, 'register', $challenge );

		wp_send_json_success( $args );
	}

	public function ajax_register(): void {
		$this->ajax_guard();

		$user_id = $this->request_user_id();
		if ( null === $user_id || ! $this->user_can_manage( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to enrol a passkey for this user.', 'easy-2fa' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_guard via check_ajax_referer.
		$input = [
			'clientDataJSON'    => isset( $_POST['clientDataJSON'] ) ? wp_unslash( (string) $_POST['clientDataJSON'] ) : '',
			'attestationObject' => isset( $_POST['attestationObject'] ) ? wp_unslash( (string) $_POST['attestationObject'] ) : '',
			'label'             => isset( $_POST['label'] ) ? wp_unslash( (string) $_POST['label'] ) : '',
			'transports'        => isset( $_POST['transports'] ) ? wp_unslash( (string) $_POST['transports'] ) : '',
		];

		$result = $this->handle_enrol( $user_id, $input );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success( [ 'message' => __( 'Passkey registered.', 'easy-2fa' ) ] );
	}

	public function ajax_auth_options(): void {
		$this->ajax_guard();

		$user_id = $this->request_user_id();
		if ( null === $user_id || ! $this->user_can_manage( $user_id ) ) {
			// During login challenge the user may not be fully authenticated; allow if they can read themselves after password step.
			// Still require a logged-in user or matching capability via ajax_guard (is_user_logged_in).
			if ( null === $user_id || get_current_user_id() !== $user_id ) {
				wp_send_json_error( [ 'message' => __( 'You are not allowed to use this passkey challenge.', 'easy-2fa' ) ], 403 );
			}
		}

		if ( ! $this->is_available() ) {
			wp_send_json_error( [ 'message' => __( 'Passkeys are not available in this environment.', 'easy-2fa' ) ], 400 );
		}

		$ids = [];
		foreach ( Credentials::for_user( $user_id ) as $row ) {
			$ids[] = $row->credential_id;
		}

		if ( [] === $ids ) {
			wp_send_json_error( [ 'message' => __( 'No passkeys are registered for this account.', 'easy-2fa' ) ], 400 );
		}

		try {
			$webauthn = $this->webauthn();
			$args     = $webauthn->getGetArgs(
				$ids,
				self::CHALLENGE_TTL,
				true,
				true,
				true,
				true,
				true,
				'preferred'
			);
			$challenge = $webauthn->getChallenge()->getBinaryString();
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => __( 'Could not start passkey authentication.', 'easy-2fa' ) ], 500 );
		}

		$this->store_challenge( $user_id, 'auth', $challenge );

		wp_send_json_success( $args );
	}

	public function ajax_auth(): void {
		$this->ajax_guard();

		$user_id = $this->request_user_id();
		if ( null === $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user.', 'easy-2fa' ) ], 400 );
		}

		if ( get_current_user_id() !== $user_id && ! $this->user_can_manage( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to use this passkey challenge.', 'easy-2fa' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_guard via check_ajax_referer.
		$input = [
			'clientDataJSON'    => isset( $_POST['clientDataJSON'] ) ? wp_unslash( (string) $_POST['clientDataJSON'] ) : '',
			'authenticatorData' => isset( $_POST['authenticatorData'] ) ? wp_unslash( (string) $_POST['authenticatorData'] ) : '',
			'signature'         => isset( $_POST['signature'] ) ? wp_unslash( (string) $_POST['signature'] ) : '',
			'id'                => isset( $_POST['id'] ) ? wp_unslash( (string) $_POST['id'] ) : '',
		];

		if ( ! $this->validate( $user_id, $input ) ) {
			wp_send_json_error( [ 'message' => __( 'Passkey authentication failed.', 'easy-2fa' ) ], 400 );
		}

		wp_send_json_success( [ 'message' => __( 'Passkey verified.', 'easy-2fa' ) ] );
	}

	private function ajax_guard(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'easy-2fa' ) ], 401 );
		}

		check_ajax_referer( 'easy2fa_passkey', 'nonce' );
	}

	private function request_user_id(): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in ajax_guard.
		$raw = isset( $_REQUEST['user_id'] ) ? absint( wp_unslash( $_REQUEST['user_id'] ) ) : get_current_user_id();
		return $raw > 0 ? $raw : null;
	}

	private function user_can_manage( int $user_id ): bool {
		if ( get_current_user_id() === $user_id ) {
			return current_user_can( 'read' );
		}
		return current_user_can( 'edit_user', $user_id );
	}

	private function enqueue_script( int $user_id ): void {
		wp_enqueue_script(
			'easy2fa-passkey',
			EASY2FA_URL . 'assets/js/passkey.js',
			[],
			EASY2FA_VERSION,
			true
		);

		wp_localize_script(
			'easy2fa-passkey',
			'easy2faPasskey',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'easy2fa_passkey' ),
				'userId'  => $user_id,
				'i18n'    => [
					'unsupported'   => __( 'Passkeys are not supported in this browser or context. Use HTTPS and a modern browser.', 'easy-2fa' ),
					'registering'   => __( 'Follow the browser prompt to register your passkey…', 'easy-2fa' ),
					'registered'    => __( 'Passkey registered.', 'easy-2fa' ),
					'authenticating'=> __( 'Follow the browser prompt to use your passkey…', 'easy-2fa' ),
					'failed'        => __( 'Something went wrong. Please try again.', 'easy-2fa' ),
				],
			]
		);
	}

	/**
	 * @return \lbuchs\WebAuthn\WebAuthn
	 */
	private function webauthn() {
		$rp_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		if ( '' === $rp_name ) {
			$rp_name = 'WordPress';
		}

		$formats = [ 'none', 'packed', 'apple', 'android-key', 'android-safetynet', 'fido-u2f', 'tpm' ];

		return new \lbuchs\WebAuthn\WebAuthn( $rp_name, self::rp_id(), $formats, true );
	}

	public static function rp_id(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		/**
		 * Filter the WebAuthn relying-party ID (registrable domain).
		 *
		 * @param string $host Hostname derived from home_url().
		 */
		$rp_id = apply_filters( 'easy2fa_rp_id', $host );

		return is_string( $rp_id ) && '' !== $rp_id ? $rp_id : $host;
	}

	private function challenge_key( int $user_id, string $purpose ): string {
		return 'easy2fa_pk_' . $purpose . '_' . $user_id;
	}

	private function store_challenge( int $user_id, string $purpose, string $challenge ): void {
		set_transient( $this->challenge_key( $user_id, $purpose ), base64_encode( $challenge ), self::CHALLENGE_TTL );
	}

	private function get_challenge( int $user_id, string $purpose ): ?string {
		$raw = get_transient( $this->challenge_key( $user_id, $purpose ) );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = base64_decode( $raw, true );
		return false === $decoded || '' === $decoded ? null : $decoded;
	}

	private function clear_challenge( int $user_id, string $purpose ): void {
		delete_transient( $this->challenge_key( $user_id, $purpose ) );
	}

	/**
	 * @param mixed $value Base64 or base64url encoded binary, or raw binary string.
	 */
	private function decode_binary_field( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		// Prefer strict base64, then base64url.
		$decoded = base64_decode( $value, true );
		if ( false !== $decoded && '' !== $decoded ) {
			return $decoded;
		}

		$b64 = strtr( $value, '-_', '+/' );
		$pad = strlen( $b64 ) % 4;
		if ( $pad > 0 ) {
			$b64 .= str_repeat( '=', 4 - $pad );
		}
		$decoded = base64_decode( $b64, true );
		return false === $decoded ? '' : $decoded;
	}

	/**
	 * @param mixed $raw
	 */
	private function sanitize_transports( $raw ): string {
		if ( is_array( $raw ) ) {
			$parts = array_map( 'sanitize_text_field', $raw );
			return implode( ',', array_filter( $parts ) );
		}
		if ( is_string( $raw ) ) {
			// JSON array from the client.
			if ( '' !== $raw && ( '[' === $raw[0] || '{' === $raw[0] ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return $this->sanitize_transports( $decoded );
				}
			}
			return sanitize_text_field( $raw );
		}
		return '';
	}

	private function is_sign_count_error( \Throwable $e ): bool {
		$msg = strtolower( $e->getMessage() );
		return false !== strpos( $msg, 'signature counter' ) || false !== strpos( $msg, 'sign count' );
	}
}
