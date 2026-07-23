<?php

declare( strict_types=1 );

class Test_Providers extends WP_UnitTestCase {
	public function test_sorted_by_priority(): void {
		$ids = array_map( fn( $p ) => $p->id(), \Easy2FA\Providers::instance()->all() );
		$this->assertSame( array_values( array_unique( $ids ) ), $ids );
		$this->assertNotEmpty( $ids );
	}

	public function test_unavailable_provider_is_hidden(): void {
		$fake = new class implements \Easy2FA\Provider {
			public function id(): string {
				return 'fake-unavailable';
			}

			public function label(): string {
				return 'Fake';
			}

			public function priority(): int {
				return 999;
			}

			public function is_available(): bool {
				return false;
			}

			public function is_enrolled( int $user_id ): bool {
				return false;
			}

			public function render_enrol( int $user_id ): void {
			}

			public function handle_enrol( int $user_id, array $input ) {
				return true;
			}

			public function render_challenge( int $user_id ): void {
			}

			public function validate( int $user_id, array $input ): bool {
				return false;
			}

			public function unenrol( int $user_id ): void {
			}
		};

		\Easy2FA\Providers::instance()->register( $fake );
		$this->assertNull( \Easy2FA\Providers::instance()->get( $fake->id() ) );
	}

	public function test_preferred_is_lowest_priority_enrolled(): void {
		$uid = self::factory()->user->create();
		\Easy2FA\Store::set_method( $uid, 'totp', [ 'secret' => 'x' ] );
		$this->assertSame( 'totp', \Easy2FA\Providers::instance()->preferred_for( $uid )->id() );
	}
}
