<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

interface Provider {

	public function id(): string;

	public function label(): string;

	public function priority(): int;

	public function is_available(): bool;

	public function is_enrolled( int $user_id ): bool;

	public function render_enrol( int $user_id ): void;

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function handle_enrol( int $user_id, array $input );

	public function render_challenge( int $user_id ): void;

	/**
	 * @param array<string, mixed> $input
	 */
	public function validate( int $user_id, array $input ): bool;

	public function unenrol( int $user_id ): void;
}
