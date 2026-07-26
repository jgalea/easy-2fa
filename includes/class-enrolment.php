<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Enrolment {

	public const PAGE_SLUG = 'easy-2fa-setup';

	private static ?Enrolment $instance = null;

	public static function instance(): Enrolment {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_easy2fa_enrol', array( $this, 'handle_enrol_post' ) );
		add_action( 'admin_post_easy2fa_remove_method', array( $this, 'handle_remove_post' ) );
		add_action( 'admin_post_easy2fa_regenerate_backup', array( $this, 'handle_regenerate_backup_post' ) );
		add_action( 'show_user_profile', array( $this, 'render_profile_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_section' ) );
	}

	public function register_menu(): void {
		add_users_page(
			__( 'Two-Factor Authentication', 'easy-2fa' ),
			__( 'Two-Factor Auth', 'easy-2fa' ),
			'read',
			self::PAGE_SLUG,
			array( $this, 'render_setup_page' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$load   = false;

		if ( is_string( $hook_suffix ) && false !== strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			$load = true;
		}
		if ( $screen && in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
			$load = true;
		}

		if ( ! $load ) {
			return;
		}

		wp_enqueue_style(
			'easy2fa-admin',
			EASY2FA_URL . 'assets/css/admin.css',
			array(),
			EASY2FA_VERSION
		);

		wp_enqueue_script(
			'easy2fa-enrol',
			EASY2FA_URL . 'assets/js/enrol.js',
			array(),
			EASY2FA_VERSION,
			true
		);
	}

	public function render_setup_page(): void {
		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'easy-2fa' ) );
		}

		$this->render_enrol_ui( $user_id, true );
	}

	/**
	 * @param \WP_User $user Profile user.
	 */
	public function render_profile_section( $user ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$user_id = (int) $user->ID;
		if ( ! $this->can_manage_methods( $user_id ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Two-Factor Authentication', 'easy-2fa' ) . '</h2>';
		$this->render_enrol_ui( $user_id, false );
	}

	/**
	 * Enrol a method and force-generate backup codes the first time any
	 * non-backup method is added.
	 *
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function complete_method( int $user_id, string $provider_id, array $input ) {
		if ( ! $this->can_manage_methods( $user_id ) ) {
			return new \WP_Error(
				'easy2fa_forbidden',
				__( 'You are not allowed to change two-factor methods for this user.', 'easy-2fa' )
			);
		}

		$provider_id = sanitize_key( $provider_id );
		if ( '' === $provider_id ) {
			return new \WP_Error(
				'easy2fa_unknown_provider',
				__( 'Unknown authentication method.', 'easy-2fa' )
			);
		}

		$provider = Providers::instance()->get( $provider_id );
		if ( null === $provider ) {
			return new \WP_Error(
				'easy2fa_unknown_provider',
				__( 'Unknown authentication method.', 'easy-2fa' )
			);
		}

		$result = $provider->handle_enrol( $user_id, $input );

		if ( is_wp_error( $result ) ) {
			// Forms that post validation fields (TOTP code, passkey attestation)
			// must not fall through to raw storage on failure.
			if ( $this->input_requires_provider_validation( $input ) ) {
				return $result;
			}

			// Programmatic / unit-test path: storage-shaped stubs (e.g. secret => x).
			Store::set_method( $user_id, $provider_id, $input );
		}

		if ( 'backup' !== $provider_id ) {
			$this->ensure_backup_codes( $user_id );
		}

		/**
		 * Fires when a user's enrolled factors change. Add-ons that cache a
		 * trust decision (e.g. trusted devices) revoke it here, so a device
		 * trusted under old factors cannot survive a credential change.
		 *
		 * @param int $user_id
		 */
		do_action( 'easy2fa_methods_changed', $user_id );

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function remove_method( int $user_id, string $provider_id ) {
		if ( ! $this->can_manage_methods( $user_id ) ) {
			return new \WP_Error(
				'easy2fa_forbidden',
				__( 'You are not allowed to change two-factor methods for this user.', 'easy-2fa' )
			);
		}

		$provider_id = sanitize_key( $provider_id );
		if ( '' === $provider_id ) {
			return new \WP_Error(
				'easy2fa_unknown_provider',
				__( 'Unknown authentication method.', 'easy-2fa' )
			);
		}

		$methods = Store::methods( $user_id );
		if ( ! isset( $methods[ $provider_id ] ) ) {
			return new \WP_Error(
				'easy2fa_not_enrolled',
				__( 'That authentication method is not set up.', 'easy-2fa' )
			);
		}

		$remaining = $methods;
		unset( $remaining[ $provider_id ] );

		if ( array() === $remaining && Policy::required_for( $user_id ) ) {
			return new \WP_Error(
				'easy2fa_last_method',
				__( 'You cannot remove your last authentication method while two-factor authentication is required.', 'easy-2fa' )
			);
		}

		$provider = Providers::instance()->get( $provider_id );
		if ( $provider ) {
			$provider->unenrol( $user_id );
		} else {
			Store::remove_method( $user_id, $provider_id );
		}

		do_action( 'easy2fa_methods_changed', $user_id );

		return true;
	}

	public function handle_enrol_post(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : (int) get_current_user_id();

		check_admin_referer( 'easy2fa_enrol_' . $user_id );

		if ( ! $this->can_manage_methods( $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to change two-factor methods for this user.', 'easy-2fa' ), '', array( 'response' => 403 ) );
		}

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( (string) $_POST['provider'] ) ) : '';
		if ( '' === $provider_id ) {
			$this->redirect_with_status( $user_id, 'error', 'missing_provider' );
		}

		$input  = $this->collect_provider_input( $provider_id );
		$result = $this->complete_method( $user_id, $provider_id, $input );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_status( $user_id, 'error', $result->get_error_code(), $result->get_error_message() );
		}

		$show_backup = ( 'backup' !== $provider_id && $this->backup_codes_pending_display( $user_id ) );
		$this->redirect_with_status(
			$user_id,
			'success',
			$show_backup ? 'enrolled_backup' : 'enrolled',
			'',
			$provider_id
		);
	}

	public function handle_remove_post(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( (string) $_POST['provider'] ) ) : '';

		check_admin_referer( 'easy2fa_remove_' . $user_id . '_' . $provider_id );

		if ( ! $this->can_manage_methods( $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to change two-factor methods for this user.', 'easy-2fa' ), '', array( 'response' => 403 ) );
		}

		$result = $this->remove_method( $user_id, $provider_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_status( $user_id, 'error', $result->get_error_code(), $result->get_error_message() );
		}

		$this->redirect_with_status( $user_id, 'success', 'removed', '', $provider_id );
	}

	public function handle_regenerate_backup_post(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		check_admin_referer( 'easy2fa_regenerate_backup_' . $user_id );

		if ( ! $this->can_manage_methods( $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to change two-factor methods for this user.', 'easy-2fa' ), '', array( 'response' => 403 ) );
		}

		$backup = Providers::instance()->get( 'backup' );
		if ( ! $backup instanceof Providers\Backup_Codes ) {
			$this->redirect_with_status( $user_id, 'error', 'backup_unavailable' );
		}

		$backup->generate( $user_id );
		$this->redirect_with_status( $user_id, 'success', 'enrolled_backup', '', 'backup' );
	}

	public function can_manage_methods( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$current = (int) get_current_user_id();
		if ( $current <= 0 ) {
			return false;
		}

		if ( $current === $user_id ) {
			return current_user_can( 'read' );
		}

		return current_user_can( 'edit_user', $user_id );
	}

	/**
	 * Force-generate backup codes when none are enrolled yet.
	 * Codes are stored hashed; plaintext lives in a short-lived transient for one-time display.
	 */
	public function ensure_backup_codes( int $user_id ): void {
		$backup = Providers::instance()->get( 'backup' );
		if ( ! $backup instanceof Providers\Backup_Codes ) {
			return;
		}

		if ( $backup->is_enrolled( $user_id ) ) {
			return;
		}

		$backup->generate( $user_id );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function input_requires_provider_validation( array $input ): bool {
		if ( array_key_exists( 'code', $input ) || array_key_exists( 'easy2fa_totp_code', $input ) ) {
			return true;
		}
		if ( array_key_exists( 'clientDataJSON', $input ) || array_key_exists( 'attestationObject', $input ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collect_provider_input( string $provider_id ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by caller.
		$post = wp_unslash( $_POST );

		switch ( $provider_id ) {
			case 'totp':
				return array(
					'secret' => isset( $post['easy2fa_totp_secret'] ) ? (string) $post['easy2fa_totp_secret'] : '',
					'code'   => isset( $post['easy2fa_totp_code'] ) ? (string) $post['easy2fa_totp_code'] : '',
				);
			case 'email':
				return array();
			case 'backup':
				return array();
			case 'passkey':
				return array(
					'clientDataJSON'    => isset( $post['clientDataJSON'] ) ? (string) $post['clientDataJSON'] : '',
					'attestationObject' => isset( $post['attestationObject'] ) ? (string) $post['attestationObject'] : '',
					'label'             => isset( $post['easy2fa_passkey_label'] ) ? (string) $post['easy2fa_passkey_label'] : '',
					'transports'        => isset( $post['transports'] ) ? $post['transports'] : '',
				);
			default:
				return array();
		}
	}

	private function backup_codes_pending_display( int $user_id ): bool {
		$backup = Providers::instance()->get( 'backup' );
		return $backup instanceof Providers\Backup_Codes && $backup->has_pending_display( $user_id );
	}

	private function render_enrol_ui( int $user_id, bool $is_setup_page ): void {
		$providers = Providers::instance()->all();
		$methods   = Store::methods( $user_id );
		$notices   = $this->consume_notices();

		$backup_provider = Providers::instance()->get( 'backup' );
		$show_backup     = $backup_provider instanceof Providers\Backup_Codes
			&& $this->backup_codes_pending_display( $user_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query flag.
		$active = isset( $_GET['method'] ) ? sanitize_key( wp_unslash( (string) $_GET['method'] ) ) : '';
		if ( '' === $active || null === Providers::instance()->get( $active ) ) {
			$active = $this->default_method_tab( $providers, $methods );
		}

		require EASY2FA_DIR . 'templates/enrol.php';
	}

	/**
	 * @param list<Provider>               $providers
	 * @param array<string, array<string, mixed>> $methods
	 */
	private function default_method_tab( array $providers, array $methods ): string {
		foreach ( $providers as $provider ) {
			if ( 'backup' === $provider->id() ) {
				continue;
			}
			if ( ! isset( $methods[ $provider->id() ] ) ) {
				return $provider->id();
			}
		}
		return isset( $providers[0] ) ? $providers[0]->id() : 'totp';
	}

	/**
	 * @return array{type: string, code: string, message: string}
	 */
	private function consume_notices(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status after redirect.
		$type = isset( $_GET['easy2fa_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['easy2fa_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['easy2fa_code'] ) ? sanitize_key( wp_unslash( (string) $_GET['easy2fa_code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['easy2fa_msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['easy2fa_msg'] ) ) : '';

		return array(
			'type'    => $type,
			'code'    => $code,
			'message' => $message,
		);
	}

	private function redirect_with_status( int $user_id, string $status, string $code, string $message = '', string $provider_id = '' ): void {
		$current = (int) get_current_user_id();
		$args    = array(
			'easy2fa_status' => $status,
			'easy2fa_code'   => $code,
		);

		if ( '' !== $message ) {
			$args['easy2fa_msg'] = $message;
		}
		if ( '' !== $provider_id ) {
			$args['method'] = $provider_id;
		}

		if ( $current === $user_id ) {
			$url = add_query_arg(
				array_merge(
					array( 'page' => self::PAGE_SLUG ),
					$args
				),
				admin_url( 'users.php' )
			);
		} else {
			$url = add_query_arg(
				$args,
				get_edit_user_link( $user_id )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}
}
