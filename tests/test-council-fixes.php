<?php

declare( strict_types=1 );

/**
 * Regressions for the issues the 0.2.0 security review turned up.
 */
class Test_Council_Fixes extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\Sigil\Schema::install();
	}

	public function tear_down(): void {
		delete_option( \Sigil\Frontend::PAGE_OPTION );
		\Sigil\Network::delete_option( \Sigil\Policy::OPTION_KEY );
		parent::tear_down();
	}

	private function require_2fa_for_everyone(): void {
		\Sigil\Policy::update(
			array(
				'enabled'    => true,
				'roles'      => array( 'subscriber' => true, 'administrator' => true, 'editor' => true ),
				'grace_days' => 0,
			)
		);
	}

	// A method row can outlive what backs it: credentials restored away, a lost
	// table, a provider unavailable on this PHP build. Counting that as enrolled
	// left the account on password only at login while enforcement believed it
	// was covered, so nothing ever asked the user to re-enrol.
	public function test_a_method_row_without_a_usable_provider_does_not_count_as_enrolled(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->require_2fa_for_everyone();

		// Exactly the state a lost credentials table leaves behind.
		\Sigil\Store::set_method( $user, 'passkey', array( 'enrolled_at' => time() ) );

		$this->assertTrue( \Sigil\Store::has_any( $user ), 'the raw method row is still there' );
		$this->assertFalse(
			\Sigil\Providers::instance()->has_usable( $user ),
			'but nothing usable backs it'
		);
		$this->assertTrue(
			\Sigil\Policy::must_enrol_now( $user ),
			'so the user must be pushed to enrol rather than left on password only'
		);
	}

	public function test_a_real_method_still_satisfies_the_policy(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->require_2fa_for_everyone();
		\Sigil\Store::set_method( $user, 'totp', array( 'secret' => 'JBSWY3DPEHPK3PXP' ) );

		$this->assertTrue( \Sigil\Providers::instance()->has_usable( $user ) );
		$this->assertFalse( \Sigil\Policy::must_enrol_now( $user ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function make_content( array $args ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => '[sigil_2fa]',
				),
				$args
			)
		);
	}

	private function render( int $post_id ): void {
		$this->go_to( (string) get_permalink( $post_id ) );
		do_shortcode( '[sigil_2fa]' );
	}

	public function test_a_published_page_claims_the_empty_slot(): void {
		$page = $this->make_content( array() );
		$this->render( $page );

		$this->assertSame( $page, \Sigil\Frontend::page_id() );
	}

	// Rendering is anonymous and carries no capability, so a second page must not
	// be able to take the destination from a site that already has one.
	public function test_a_later_page_cannot_steal_the_slot(): void {
		$first = $this->make_content( array() );
		$this->render( $first );

		$attacker = $this->make_content( array( 'post_title' => 'Free iPhone' ) );
		$this->render( $attacker );

		$this->assertSame( $first, \Sigil\Frontend::page_id() );
	}

	// Publishing a page is an editor-and-above capability. A contributor or
	// author writing a post must not be able to claim it.
	public function test_a_post_cannot_claim_the_slot(): void {
		$post = $this->make_content( array( 'post_type' => 'post' ) );
		$this->render( $post );

		$this->assertSame( 0, \Sigil\Frontend::page_id() );
	}

	public function test_an_unpublished_page_cannot_claim_the_slot(): void {
		$draft = $this->make_content( array( 'post_status' => 'draft' ) );
		$this->render( $draft );

		$this->assertSame( 0, \Sigil\Frontend::page_id() );
	}

	// Stored state is not trusted: the page can lose the shortcode or be
	// unpublished after the fact, and enforcement must stop pointing at it.
	public function test_a_page_that_stops_qualifying_is_dropped(): void {
		$page = $this->make_content( array() );
		$this->render( $page );
		$this->assertSame( $page, \Sigil\Frontend::page_id() );

		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => 'nothing here any more',
			)
		);

		$this->assertSame( 0, \Sigil\Frontend::page_id() );
		$this->assertSame( '', \Sigil\Frontend::page_url() );
	}

	public function test_the_enrolment_page_is_stored_per_site(): void {
		$page = $this->make_content( array() );
		$this->render( $page );

		$this->assertSame( $page, (int) get_option( \Sigil\Frontend::PAGE_OPTION ) );

		if ( is_multisite() ) {
			$this->assertFalse(
				get_site_option( \Sigil\Frontend::PAGE_OPTION, false ),
				'a post id means nothing on a sibling site, so it must not be network-wide'
			);
		}
	}
}
