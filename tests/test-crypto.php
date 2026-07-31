<?php

declare( strict_types=1 );

class Test_Crypto extends WP_UnitTestCase {
	public function test_roundtrip(): void {
		$secret = 'JBSWY3DPEHPK3PXP';
		$this->assertSame( $secret, \Sigil\Crypto::decrypt( \Sigil\Crypto::encrypt( $secret ) ) );
	}

	public function test_ciphertext_is_not_plaintext(): void {
		$this->assertNotSame( 'hello', \Sigil\Crypto::encrypt( 'hello' ) );
	}

	public function test_tampered_ciphertext_returns_empty(): void {
		$ct = \Sigil\Crypto::encrypt( 'hello' );
		$this->assertSame( '', \Sigil\Crypto::decrypt( $ct . 'x' ) );
	}

	public function test_same_plaintext_gives_different_ciphertext(): void {
		$this->assertNotSame( \Sigil\Crypto::encrypt( 'a' ), \Sigil\Crypto::encrypt( 'a' ) );
	}
}
