<?php

declare( strict_types=1 );

class Test_Passkey_Availability extends WP_UnitTestCase {
	public function test_hidden_below_php_80(): void {
		$p = new \Sigil\Providers\Passkey();
		$this->assertSame( PHP_VERSION_ID >= 80000, $p->is_available() );
	}

	public function test_registry_excludes_when_unavailable(): void {
		if ( PHP_VERSION_ID >= 80000 ) {
			$this->markTestSkipped( 'Environment supports passkeys.' );
		}
		$this->assertNull( \Sigil\Providers::instance()->get( 'passkey' ) );
	}
}
