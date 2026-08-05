<?php

declare( strict_types=1 );

/**
 * Start each test with an empty attempt counter.
 *
 * The WordPress test suite gives every request the same REMOTE_ADDR, so every
 * test shares one address bucket, and the tests that create or drop the counter
 * table run DDL, which commits the transaction the suite would otherwise roll
 * back. Together that leaks attempts from one test into the next. Clearing on
 * the way in is what makes each of these tests mean what it says.
 */
trait Clears_Attempts {

	private static bool $installed = false;

	public function set_up(): void {
		parent::set_up();
		self::forget_attempts();
	}

	protected static function forget_attempts(): void {
		global $wpdb;

		// The suite reinstalls WordPress for every run, which takes the plugin's
		// tables with it, and nothing in a test request fires the activation
		// hook that would put them back.
		if ( ! self::$installed ) {
			self::$installed = true;
			\Sigil\Schema::install();
		}

		$table = \Sigil\Rate_Limit::table();

		$prior = $wpdb->suppress_errors( true );
		$wpdb->query( "DELETE FROM {$table}" );
		$wpdb->suppress_errors( $prior );
	}

	/**
	 * The one-repair-per-request latch is process-wide, and a test process is
	 * many requests. Set it rather than inheriting whatever the test before
	 * happened to leave behind.
	 */
	protected static function set_repair_spent( bool $spent ): void {
		self::latch()->setValue( null, $spent );
	}

	protected static function repair_is_spent(): bool {
		return (bool) self::latch()->getValue();
	}

	private static function latch(): ReflectionProperty {
		$latch = new ReflectionProperty( \Sigil\Rate_Limit::class, 'repaired' );
		$latch->setAccessible( true );

		return $latch;
	}

	protected static function table_exists(): bool {
		return '' !== self::found_table();
	}

	protected static function found_table(): string {
		global $wpdb;

		$prior = $wpdb->suppress_errors( true );
		$found = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( \Sigil\Rate_Limit::table() ) . "'" );
		$wpdb->suppress_errors( $prior );

		return (string) $found;
	}

	/**
	 * Drop the counter and say what happened, so a test that depends on it
	 * being gone can fail on that rather than on what it was trying to prove.
	 */
	protected static function drop_table(): string {
		global $wpdb;

		$table = \Sigil\Rate_Limit::table();

		$prior   = $wpdb->suppress_errors( true );
		$dropped = $wpdb->query( "DROP TABLE {$table}" );
		$error   = $wpdb->last_error;
		$wpdb->suppress_errors( $prior );

		return sprintf(
			'drop returned %s, error "%s", SHOW TABLES now "%s"',
			var_export( $dropped, true ),
			$error,
			self::found_table()
		);
	}
}
