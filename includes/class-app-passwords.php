<?php

declare( strict_types=1 );

namespace Sigil;

defined( 'ABSPATH' ) || exit;

final class App_Passwords {

	private static ?App_Passwords $instance = null;

	public static function instance(): App_Passwords {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filter_available' ), 10, 2 );
	}

	/**
	 * @param bool             $available Whether application passwords are available.
	 * @param int|\WP_User|null $user     User id or object.
	 */
	public function filter_available( $available, $user ): bool {
		if ( ! $available ) {
			return false;
		}

		if ( ! $user instanceof \WP_User ) {
			$user = get_userdata( (int) $user );
		}

		if ( ! $user instanceof \WP_User ) {
			return (bool) $available;
		}

		$blocked = Policy::get()['block_app_passwords'];
		foreach ( (array) $user->roles as $role ) {
			if ( ! is_string( $role ) || '' === $role ) {
				continue;
			}
			if ( ! empty( $blocked[ $role ] ) ) {
				return false;
			}
		}

		return true;
	}
}
