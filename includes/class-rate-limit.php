<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

final class Rate_Limit {

	private const MAX_ATTEMPTS = 5;
	private const WINDOW       = 15 * MINUTE_IN_SECONDS;

	public static function hit( string $key ): void {
		if ( '' === $key ) {
			return;
		}

		$tk    = self::transient_key( $key );
		$count = (int) Network::get_transient( $tk );
		if ( $count < 0 ) {
			$count = 0;
		}
		Network::set_transient( $tk, $count + 1, self::WINDOW );
	}

	public static function blocked( string $key ): bool {
		if ( '' === $key ) {
			return false;
		}

		return (int) Network::get_transient( self::transient_key( $key ) ) >= self::MAX_ATTEMPTS;
	}

	public static function clear( string $key ): void {
		if ( '' === $key ) {
			return;
		}

		Network::delete_transient( self::transient_key( $key ) );
	}

	private static function transient_key( string $key ): string {
		return 'sigil_rl_' . hash( 'sha256', $key );
	}
}
