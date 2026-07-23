<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	private const VERSION_SODIUM  = "\x01";
	private const VERSION_OPENSSL = "\x02";

	public static function encrypt( string $plaintext ): string {
		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return self::VERSION_SODIUM . $nonce . $ciphertext;
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $ct ) {
			return '';
		}

		return self::VERSION_OPENSSL . $iv . $tag . $ct;
	}

	public static function decrypt( string $ciphertext ): string {
		if ( '' === $ciphertext ) {
			return '';
		}

		$version = $ciphertext[0];
		$payload = substr( $ciphertext, 1 );
		$key     = self::key();

		try {
			if ( self::VERSION_SODIUM === $version ) {
				return self::decrypt_sodium( $payload, $key );
			}
			if ( self::VERSION_OPENSSL === $version ) {
				return self::decrypt_openssl( $payload, $key );
			}
		} catch ( \Throwable $e ) {
			return '';
		}

		return '';
	}

	private static function key(): string {
		if ( defined( 'EASY2FA_KEY' ) && is_string( EASY2FA_KEY ) && '' !== EASY2FA_KEY ) {
			return hash( 'sha256', EASY2FA_KEY, true );
		}

		$material = ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );
		return hash_hkdf( 'sha256', $material, 32, 'easy2fa' );
	}

	private static function decrypt_sodium( string $payload, string $key ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		$nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $payload ) < $nonce_len + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return '';
		}

		$nonce      = substr( $payload, 0, $nonce_len );
		$ciphertext = substr( $payload, $nonce_len );
		$plain      = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		return false === $plain ? '' : $plain;
	}

	private static function decrypt_openssl( string $payload, string $key ): string {
		// 12-byte IV + 16-byte tag + ciphertext.
		if ( strlen( $payload ) < 28 ) {
			return '';
		}

		$iv  = substr( $payload, 0, 12 );
		$tag = substr( $payload, 12, 16 );
		$ct  = substr( $payload, 28 );

		$plain = openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? '' : $plain;
	}
}
