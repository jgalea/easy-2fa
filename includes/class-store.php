<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

final class Store {

	public const META_KEY = '_sigil_methods';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function methods( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$raw = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $provider_id => $data ) {
			if ( ! is_string( $provider_id ) || '' === $provider_id || ! is_array( $data ) ) {
				continue;
			}
			$out[ $provider_id ] = $data;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function set_method( int $user_id, string $provider_id, array $data ): void {
		if ( $user_id <= 0 || '' === $provider_id ) {
			return;
		}

		$methods                   = self::methods( $user_id );
		$methods[ $provider_id ] = $data;
		update_user_meta( $user_id, self::META_KEY, $methods );
	}

	public static function remove_method( int $user_id, string $provider_id ): void {
		if ( $user_id <= 0 || '' === $provider_id ) {
			return;
		}

		$methods = self::methods( $user_id );
		if ( ! isset( $methods[ $provider_id ] ) ) {
			return;
		}

		unset( $methods[ $provider_id ] );

		if ( [] === $methods ) {
			delete_user_meta( $user_id, self::META_KEY );
			return;
		}

		update_user_meta( $user_id, self::META_KEY, $methods );
	}

	public static function has_any( int $user_id ): bool {
		return [] !== self::methods( $user_id );
	}
}
