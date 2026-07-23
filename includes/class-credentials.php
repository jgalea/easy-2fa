<?php

declare( strict_types=1 );

namespace Easy2FA;

defined( 'ABSPATH' ) || exit;

final class Credentials {

	/**
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function add(
		int $user_id,
		string $credential_id,
		string $public_key,
		int $sign_count,
		string $label,
		string $transports
	): int {
		global $wpdb;

		if ( $user_id <= 0 || '' === $credential_id || '' === $public_key ) {
			return 0;
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			Schema::table(),
			[
				'user_id'       => $user_id,
				'credential_id' => $credential_id,
				'public_key'    => $public_key,
				'sign_count'    => max( 0, $sign_count ),
				'transports'    => sanitize_text_field( $transports ),
				'label'         => sanitize_text_field( $label ),
				'created_at'    => $now,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( false === $ok ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return list<object{
	 *   id: int|string,
	 *   user_id: int|string,
	 *   credential_id: string,
	 *   public_key: string,
	 *   sign_count: int|string,
	 *   transports: string,
	 *   label: string,
	 *   created_at: string,
	 *   last_used_at: string|null
	 * }>
	 */
	public static function for_user( int $user_id ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return [];
		}

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC",
				$user_id
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	public static function by_credential_id( string $credential_id ): ?object {
		global $wpdb;

		if ( '' === $credential_id ) {
			return null;
		}

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE credential_id = %s LIMIT 1",
				$credential_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public static function touch( int $id, int $sign_count ): void {
		global $wpdb;

		if ( $id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Schema::table(),
			[
				'sign_count'   => max( 0, $sign_count ),
				'last_used_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	public static function delete( int $id, int $user_id ): bool {
		global $wpdb;

		if ( $id <= 0 || $user_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			Schema::table(),
			[
				'id'      => $id,
				'user_id' => $user_id,
			],
			[ '%d', '%d' ]
		);

		return is_int( $deleted ) && $deleted > 0;
	}

	/**
	 * Delete every credential row for a user.
	 */
	public static function delete_for_user( int $user_id ): void {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			Schema::table(),
			[ 'user_id' => $user_id ],
			[ '%d' ]
		);
	}
}
