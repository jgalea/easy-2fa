<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

/**
 * Enrolment outside wp-admin.
 *
 * Membership and headless sites routinely keep their members out of the
 * dashboard, which leaves them unable to set up a second factor at all, and
 * unable to satisfy a policy that requires one. The [sigil_2fa] shortcode puts
 * the same enrolment UI on a normal page, and enforcement sends people there
 * instead of to an admin screen they cannot open.
 */
final class Frontend {

	public const SHORTCODE = 'sigil_2fa';

	/**
	 * Per site, never per network. A post ID means nothing on a sibling site, so
	 * a network-wide value would point every site at whatever happens to share
	 * that number.
	 */
	public const PAGE_OPTION = 'sigil_frontend_page';

	private static ?Frontend $instance = null;

	public static function instance(): Frontend {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'sigil_setup_url', array( $this, 'filter_setup_url' ) );
	}

	/**
	 * The recorded enrolment page, if it still qualifies as one.
	 *
	 * Re-checked on every use rather than trusted from storage: the page can be
	 * unpublished, deleted, or edited to drop the shortcode after it was
	 * recorded, and enforcement must not march users at any of those.
	 */
	public static function page_id(): int {
		$page_id = (int) get_option( self::PAGE_OPTION, 0 );
		if ( $page_id <= 0 ) {
			return 0;
		}

		return self::qualifies( get_post( $page_id ) ) ? $page_id : 0;
	}

	public static function page_url(): string {
		$page_id = self::page_id();
		if ( $page_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return is_string( $permalink ) ? $permalink : '';
	}

	public function filter_setup_url( string $url ): string {
		$front = self::page_url();

		return '' !== $front ? $front : $url;
	}

	public function enqueue_assets(): void {
		if ( ! $this->post_has_shortcode() && ! (bool) apply_filters( 'sigil_needs_frontend_assets', false ) ) {
			return;
		}

		wp_enqueue_style(
			'sigil-frontend',
			SIGIL_URL . 'assets/css/frontend.css',
			array(),
			SIGIL_VERSION
		);

		Enrolment::enqueue_enrol_assets();
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( $atts = array() ): string {
		unset( $atts );

		if ( is_admin() ) {
			return '';
		}

		$this->remember_page();

		if ( ! is_user_logged_in() ) {
			return '<div class="sigil-enrol sigil-enrol--frontend"><p>'
				. esc_html__( 'Sign in to manage two-factor authentication.', 'sigil-2fa' )
				. '</p></div>';
		}

		ob_start();
		echo '<div class="sigil-enrol--frontend">';
		Enrolment::instance()->render_enrol_ui( (int) get_current_user_id(), false );
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Note where the enrolment page lives, so enforcement can send people there
	 * without the site having to configure anything.
	 *
	 * Only an administrator viewing the page claims the slot. Rendering itself
	 * carries no capability, and the page is not neutral ground: it prints
	 * one-time backup codes and an authenticator secret for whoever is signed in.
	 * Anyone who could publish a page containing the shortcode could otherwise
	 * aim forced enrolment at a page they control. It also only ever fills an
	 * empty slot. Sites that want it pinned can use the sigil_setup_url filter,
	 * which wins over this entirely.
	 */
	private function remember_page(): void {
		if ( self::page_id() > 0 ) {
			return;
		}

		// manage_options, not the network capability: the page is a post, and
		// posts belong to one site.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! self::qualifies( $post ) ) {
			return;
		}

		// Written by somebody who could have configured this anyway. An
		// administrator only has to open the page once for it to become the
		// place people are sent to set up their second factor, and an editor who
		// can publish can choose every word around the form. The form itself is
		// the real one, so this is framing rather than capture, but the framing
		// is the whole of a convincing prompt.
		if ( ! user_can( (int) $post->post_author, 'manage_options' ) ) {
			return;
		}

		update_option( self::PAGE_OPTION, (int) $post->ID, false );
	}

	/**
	 * @param \WP_Post|null $post
	 */
	private static function qualifies( $post ): bool {
		return $post instanceof \WP_Post
			&& 'page' === $post->post_type
			&& 'publish' === $post->post_status
			&& has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	private function post_has_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}
}
