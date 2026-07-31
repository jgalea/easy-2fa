<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

final class Enforcement {

	public const SETUP_PAGE = 'sigil-2fa-setup';

	private const DISMISS_COOKIE = 'sigil_grace_notice';

	private static ?Enforcement $instance = null;

	public static function instance(): Enforcement {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_notices', array( $this, 'grace_notice' ) );
		add_action( 'admin_post_sigil_dismiss_grace_notice', array( $this, 'dismiss_grace_notice' ) );
	}

	/**
	 * URL to force the user into enrolment, or empty when no redirect is needed.
	 */
	public function redirect_target( int $user_id ): string {
		if ( $user_id <= 0 || ! Policy::must_enrol_now( $user_id ) ) {
			return '';
		}

		if ( $this->is_exempt_request() ) {
			return '';
		}

		return admin_url( 'admin.php?page=' . self::SETUP_PAGE );
	}

	public function maybe_redirect(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$target = $this->redirect_target( get_current_user_id() );
		if ( '' === $target ) {
			return;
		}

		wp_safe_redirect( $target );
		exit;
	}

	public function grace_notice(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $this->should_show_grace_notice( $user_id ) ) {
			return;
		}

		$deadline = Policy::deadline_for( $user_id );
		if ( null === $deadline ) {
			return;
		}

		$seconds_left = max( 0, $deadline - time() );
		$days_left    = (int) ceil( $seconds_left / DAY_IN_SECONDS );
		if ( $days_left < 1 && $seconds_left > 0 ) {
			$days_left = 1;
		}

		$setup_url = admin_url( 'admin.php?page=' . self::SETUP_PAGE );
		$dismiss   = wp_nonce_url(
			admin_url( 'admin-post.php?action=sigil_dismiss_grace_notice' ),
			'sigil_dismiss_grace_notice'
		);

		if ( 1 === $days_left ) {
			$message = __( 'Two-factor authentication is required for your account. You have 1 day left to set it up.', 'sigil-2fa' );
		} else {
			$message = sprintf(
				/* translators: %d: days remaining in the grace period */
				__( 'Two-factor authentication is required for your account. You have %d days left to set it up.', 'sigil-2fa' ),
				$days_left
			);
		}

		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html( $message );
		echo ' <a href="' . esc_url( $setup_url ) . '">' . esc_html__( 'Set up 2FA now', 'sigil-2fa' ) . '</a>';
		echo ' | <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'sigil-2fa' ) . '</a>';
		echo '</p></div>';
	}

	public function dismiss_grace_notice(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'sigil-2fa' ) );
		}

		check_admin_referer( 'sigil_dismiss_grace_notice' );

		// Session cookie: expires when the browser session ends.
		setcookie( self::DISMISS_COOKIE, '1', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::DISMISS_COOKIE ] = '1';

		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url();
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private function should_show_grace_notice( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( ! Policy::required_for( $user_id ) ) {
			return false;
		}

		if ( Store::has_any( $user_id ) ) {
			return false;
		}

		// Past the deadline: redirect handles it; no soft notice.
		if ( Policy::must_enrol_now( $user_id ) ) {
			return false;
		}

		if ( ! empty( $_COOKIE[ self::DISMISS_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return false;
		}

		return true;
	}

	/**
	 * Paths that must remain reachable so enforcement never traps the user.
	 */
	private function is_exempt_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		global $pagenow;
		if ( is_string( $pagenow ) ) {
			if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'wp-login.php' ), true ) ) {
				return true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( self::SETUP_PAGE === $page ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check only.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		if ( 'logout' === $action ) {
			return true;
		}

		// Front-end REST pretty permalinks before REST_REQUEST is defined.
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check only.
		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}

		$script = isset( $_SERVER['SCRIPT_FILENAME'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_FILENAME'] ) )
			: '';
		if ( '' !== $script ) {
			$base = basename( $script );
			if ( in_array( $base, array( 'admin-ajax.php', 'admin-post.php', 'wp-login.php' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}
