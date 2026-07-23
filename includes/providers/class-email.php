<?php

declare( strict_types=1 );

namespace Easy2FA\Providers;

use Easy2FA\Provider;
use Easy2FA\Store;

defined( 'ABSPATH' ) || exit;

final class Email implements Provider {

	public function id(): string {
		return 'email';
	}

	public function label(): string {
		return __( 'Email code', 'easy-2fa' );
	}

	public function priority(): int {
		return 30;
	}

	public function is_available(): bool {
		return true;
	}

	public function is_enrolled( int $user_id ): bool {
		$methods = Store::methods( $user_id );
		return isset( $methods[ $this->id() ] );
	}

	public function render_enrol( int $user_id ): void {
	}

	public function handle_enrol( int $user_id, array $input ) {
		return new \WP_Error( 'easy2fa_not_implemented', __( 'Email codes are not available yet.', 'easy-2fa' ) );
	}

	public function render_challenge( int $user_id ): void {
	}

	public function validate( int $user_id, array $input ): bool {
		return false;
	}

	public function unenrol( int $user_id ): void {
		Store::remove_method( $user_id, $this->id() );
	}
}
