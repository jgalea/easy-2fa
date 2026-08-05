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
	 * many requests.
	 */
	protected static function allow_repair_again(): void {
		$latch = new ReflectionProperty( \Sigil\Rate_Limit::class, 'repaired' );
		$latch->setAccessible( true );
		$latch->setValue( null, false );
	}
}
