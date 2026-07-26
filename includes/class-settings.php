<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Settings {

	private static ?Settings $instance = null;

	public static function instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_easy2fa_save_settings', array( $this, 'handle_save' ) );
	}

	public function register_menu(): void {
		add_options_page(
			__( 'Easy 2FA', 'easy-2fa' ),
			__( 'Easy 2FA', 'easy-2fa' ),
			'manage_options',
			'easy-2fa',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'easy-2fa' ) );
		}

		$policy = Policy::get();
		$roles  = wp_roles()->roles;
		if ( ! is_array( $roles ) ) {
			$roles = array();
		}

		$updated = isset( $_GET['updated'] ) && '1' === (string) $_GET['updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		require EASY2FA_DIR . 'templates/settings.php';
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'easy-2fa' ) );
		}

		check_admin_referer( 'easy2fa_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below field by field.
		$raw = isset( $_POST['easy2fa_policy'] ) && is_array( $_POST['easy2fa_policy'] ) ? wp_unslash( $_POST['easy2fa_policy'] ) : array();

		// Unchecked boxes are absent from POST; enumerate every role so clears stick.
		$known_roles = array_keys( wp_roles()->roles );
		$posted_roles = ( isset( $raw['roles'] ) && is_array( $raw['roles'] ) ) ? $raw['roles'] : array();
		$posted_block = ( isset( $raw['block_app_passwords'] ) && is_array( $raw['block_app_passwords'] ) ) ? $raw['block_app_passwords'] : array();

		$roles_in = array();
		$block_in = array();
		foreach ( $known_roles as $role ) {
			if ( ! is_string( $role ) || '' === $role ) {
				continue;
			}
			$role               = sanitize_key( $role );
			$roles_in[ $role ] = ! empty( $posted_roles[ $role ] );
			$block_in[ $role ] = ! empty( $posted_block[ $role ] );
		}

		$grace = isset( $raw['grace_days'] ) ? (int) $raw['grace_days'] : 7;
		if ( $grace < 0 ) {
			$grace = 0;
		}

		$cap = '';
		if ( isset( $raw['min_capability'] ) && is_string( $raw['min_capability'] ) ) {
			$cap = sanitize_key( $raw['min_capability'] );
		}

		Policy::update(
			array(
				'enabled'             => ! empty( $raw['enabled'] ),
				'roles'               => $roles_in,
				'min_capability'      => $cap,
				'grace_days'          => $grace,
				'block_app_passwords' => $block_in,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'easy-2fa',
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
