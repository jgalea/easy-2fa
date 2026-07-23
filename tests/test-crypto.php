<?php

declare( strict_types=1 );

class Test_Crypto extends WP_UnitTestCase {
	public function test_roundtrip(): void {
		$secret = 'JBSWY3DPEHPK3PXP';
		$this->assertSame( $secret, \Easy2FA\Crypto::decrypt( \Easy2FA\Crypto::encrypt( $secret ) ) );
	}

	public function test_ciphertext_is_not_plaintext(): void {
		$this->assertNotSame( 'hello', \Easy2FA\Crypto::encrypt( 'hello' ) );
	}

	public function test_tampered_ciphertext_returns_empty(): void {
		$ct = \Easy2FA\Crypto::encrypt( 'hello' );
		$this->assertSame( '', \Easy2FA\Crypto::decrypt( $ct . 'x' ) );
	}

	public function test_same_plaintext_gives_different_ciphertext(): void {
		$this->assertNotSame( \Easy2FA\Crypto::encrypt( 'a' ), \Easy2FA\Crypto::encrypt( 'a' ) );
	}
}
