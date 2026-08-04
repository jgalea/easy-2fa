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
	 * The page holding the shortcode, remembered so enforcement can redirect to
	 * it. Recorded on render rather than asked for in settings: the page is
	 * whichever one the site actually put the shortcode on.
	 */
	public static function page_url(): string {
		$page_id = (int) Network::get_option( self::PAGE_OPTION, 0 );
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
		if ( ! $this->post_has_shortcode() ) {
			return;
		}

		wp_enqueue_style(
			'sigil-frontend',
			SIGIL_URL . 'assets/css/frontend.css',
			array(),
			SIGIL_VERSION
		);

		wp_enqueue_script(
			'sigil-enrol',
			SIGIL_URL . 'assets/js/enrol.js',
			array(),
			SIGIL_VERSION,
			true
		);
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

	private function remember_page(): void {
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! is_singular() ) {
			return;
		}

		if ( (int) Network::get_option( self::PAGE_OPTION, 0 ) === $post_id ) {
			return;
		}

		Network::update_option( self::PAGE_OPTION, $post_id );
	}

	private function post_has_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}
}
