<?php

declare( strict_types=1 );

class Test_Plugin extends WP_UnitTestCase {
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'SIGIL_VERSION' ) );
		$this->assertSame( SIGIL_DIR . 'sigil-2fa.php', SIGIL_FILE );
	}

	public function test_instance_is_singleton(): void {
		$this->assertSame( \Sigil\Plugin::instance(), \Sigil\Plugin::instance() );
	}
}
