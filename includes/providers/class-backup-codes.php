<?php

declare( strict_types=1 );

namespace Easy2FA\Providers;

use Easy2FA\Provider;
use Easy2FA\Store;

defined( 'ABSPATH' ) || exit;

final class Backup_Codes implements Provider {

	private const CODE_LENGTH = 8;
	private const CODE_COUNT  = 10;
	// Unambiguous alphabet: no 0/O/1/I.
	private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

	public function id(): string {
		return 'backup';
	}

	public function label(): string {
		return __( 'Backup codes', 'easy-2fa' );
	}

	public function priority(): int {
		return 40;
	}

	public function is_available(): bool {
		return true;
	}

	public function is_enrolled( int $user_id ): bool {
		$methods = Store::methods( $user_id );
		return isset( $methods[ $this->id() ] );
	}

	/**
	 * Generate ten single-use codes. Returns plaintext once; only hashes are stored.
	 *
	 * @return list<string>
	 */
	public function generate( int $user_id ): array {
		$plaintext = [];
		$stored    = [];

		for ( $i = 0; $i < self::CODE_COUNT; $i++ ) {
			$code        = $this->random_code();
			$plaintext[] = $code;
			$stored[]    = array(
				'hash' => wp_hash_password( $code ),
				'used' => false,
			);
		}

		Store::set_method(
			$user_id,
			$this->id(),
			array(
				'codes' => $stored,
			)
		);

		set_transient( $this->display_transient_key( $user_id ), $plaintext, 5 * MINUTE_IN_SECONDS );

		return $plaintext;
	}

	public function remaining( int $user_id ): int {
		$methods = Store::methods( $user_id );
		if ( ! isset( $methods[ $this->id() ]['codes'] ) || ! is_array( $methods[ $this->id() ]['codes'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $methods[ $this->id() ]['codes'] as $entry ) {
			if ( is_array( $entry ) && empty( $entry['used'] ) && ! empty( $entry['hash'] ) && is_string( $entry['hash'] ) ) {
				++$count;
			}
		}

		return $count;
	}

	public function render_enrol( int $user_id ): void {
		$codes = get_transient( $this->display_transient_key( $user_id ) );
		if ( ! is_array( $codes ) || [] === $codes ) {
			if ( $this->is_enrolled( $user_id ) ) {
				echo '<p>' . esc_html__( 'Backup codes are already set. Generate new codes to replace them; the old ones will stop working.', 'easy-2fa' ) . '</p>';
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %d: number of unused backup codes remaining */
						_n( '%d backup code remaining.', '%d backup codes remaining.', $this->remaining( $user_id ), 'easy-2fa' ),
						$this->remaining( $user_id )
					)
				) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Backup codes have not been generated yet.', 'easy-2fa' ) . '</p>';
			}
			return;
		}

		delete_transient( $this->display_transient_key( $user_id ) );

		$body = implode( "\n", array_map( 'strval', $codes ) );

		echo '<div class="easy2fa-backup-codes">';
		echo '<p><strong>' . esc_html__( 'Save these backup codes now. They will not be shown again.', 'easy-2fa' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Each code can be used once if you lose access to your other authentication methods.', 'easy-2fa' ) . '</p>';
		echo '<ul class="easy2fa-backup-codes-list">';
		foreach ( $codes as $code ) {
			echo '<li><code>' . esc_html( (string) $code ) . '</code></li>';
		}
		echo '</ul>';
		printf(
			'<p><a class="button" download="%1$s" href="data:text/plain;charset=utf-8,%2$s">%3$s</a></p>',
			esc_attr( 'easy-2fa-backup-codes.txt' ),
			rawurlencode( $body ),
			esc_html__( 'Download as text', 'easy-2fa' )
		);
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function handle_enrol( int $user_id, array $input ) {
		$this->generate( $user_id );
		return true;
	}

	public function render_challenge( int $user_id ): void {
		echo '<p>' . esc_html__( 'Enter one of your backup codes.', 'easy-2fa' ) . '</p>';
		echo '<p><label for="easy2fa-backup-code">' . esc_html__( 'Backup code', 'easy-2fa' ) . '</label> ';
		echo '<input type="text" name="code" id="easy2fa-backup-code" autocomplete="one-time-code" inputmode="text" autocapitalize="characters" spellcheck="false" required /></p>';
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function validate( int $user_id, array $input ): bool {
		$code = isset( $input['code'] ) ? (string) $input['code'] : '';
		$code = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $code ) ?? '' );

		if ( self::CODE_LENGTH !== strlen( $code ) ) {
			return false;
		}

		$methods = Store::methods( $user_id );
		if ( ! isset( $methods[ $this->id() ]['codes'] ) || ! is_array( $methods[ $this->id() ]['codes'] ) ) {
			return false;
		}

		$codes   = $methods[ $this->id() ]['codes'];
		$matched = false;

		foreach ( $codes as $i => $entry ) {
			if ( ! is_array( $entry ) || ! empty( $entry['used'] ) || empty( $entry['hash'] ) || ! is_string( $entry['hash'] ) ) {
				continue;
			}
			if ( wp_check_password( $code, $entry['hash'] ) ) {
				$codes[ $i ]['used'] = true;
				$matched             = true;
				break;
			}
		}

		if ( ! $matched ) {
			return false;
		}

		Store::set_method(
			$user_id,
			$this->id(),
			array(
				'codes' => $codes,
			)
		);

		return true;
	}

	public function unenrol( int $user_id ): void {
		Store::remove_method( $user_id, $this->id() );
		delete_transient( $this->display_transient_key( $user_id ) );
	}

	private function random_code(): string {
		$alphabet = self::ALPHABET;
		$max      = strlen( $alphabet ) - 1;
		$code     = '';

		for ( $i = 0; $i < self::CODE_LENGTH; $i++ ) {
			$code .= $alphabet[ random_int( 0, $max ) ];
		}

		return $code;
	}

	private function display_transient_key( int $user_id ): string {
		return 'easy2fa_backup_show_' . $user_id;
	}
}
