<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

final class Providers {

	private static ?Providers $instance = null;

	/** @var array<string, Provider> */
	private array $providers = [];

	public static function instance(): Providers {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_core_providers();
	}

	public function register( Provider $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	public function get( string $id ): ?Provider {
		foreach ( $this->all() as $provider ) {
			if ( $provider->id() === $id ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Available providers only, sorted by priority (lower first).
	 *
	 * @return list<Provider>
	 */
	public function all(): array {
		$registry = apply_filters( 'sigil_providers', $this->providers );
		if ( ! is_array( $registry ) ) {
			$registry = $this->providers;
		}

		$available = [];
		foreach ( $registry as $provider ) {
			if ( ! $provider instanceof Provider || ! $provider->is_available() ) {
				continue;
			}
			$available[ $provider->id() ] = $provider;
		}

		uasort(
			$available,
			static function ( Provider $a, Provider $b ): int {
				return $a->priority() <=> $b->priority();
			}
		);

		return array_values( $available );
	}

	/**
	 * @return list<Provider>
	 */
	public function enrolled_for( int $user_id ): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( Provider $provider ) use ( $user_id ): bool {
					return $provider->is_enrolled( $user_id );
				}
			)
		);
	}

	/**
	 * Whether the user has a second factor that can actually be used right now.
	 *
	 * Deliberately not Store::has_any(), which only reads the stored method list.
	 * A method row can outlive the thing behind it: passkey credentials restored
	 * away, a table lost, or a provider unavailable on this PHP version. Treating
	 * those as enrolled would leave the account on password only at login while
	 * enforcement believed it was covered, and never prompt a re-enrol.
	 */
	public function has_usable( int $user_id ): bool {
		return array() !== $this->enrolled_for( $user_id );
	}

	public function preferred_for( int $user_id ): ?Provider {
		$enrolled = $this->enrolled_for( $user_id );
		return $enrolled[0] ?? null;
	}

	private function load_core_providers(): void {
		$map = [
			SIGIL_DIR . 'includes/providers/class-passkey.php'      => Providers\Passkey::class,
			SIGIL_DIR . 'includes/providers/class-totp.php'         => Providers\TOTP::class,
			SIGIL_DIR . 'includes/providers/class-email.php'        => Providers\Email::class,
			SIGIL_DIR . 'includes/providers/class-backup-codes.php' => Providers\Backup_Codes::class,
		];

		foreach ( $map as $file => $class ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}
			require_once $file;
			if ( class_exists( $class ) ) {
				$this->register( new $class() );
			}
		}
	}
}
