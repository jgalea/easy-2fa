<?php

declare( strict_types=1 );

class Test_Plugin extends WP_UnitTestCase {
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'EASY2FA_VERSION' ) );
		$this->assertSame( EASY2FA_DIR . 'easy-2fa.php', EASY2FA_FILE );
	}

	public function test_instance_is_singleton(): void {
		$this->assertSame( \Easy2FA\Plugin::instance(), \Easy2FA\Plugin::instance() );
	}
}
