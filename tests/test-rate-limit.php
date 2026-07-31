<?php

declare( strict_types=1 );

class Test_Rate_Limit extends WP_UnitTestCase {
	public function test_blocks_after_five_failures(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			\Sigil\Rate_Limit::hit( 'u:1' );
		}
		$this->assertFalse( \Sigil\Rate_Limit::blocked( 'u:1' ) );
		\Sigil\Rate_Limit::hit( 'u:1' );
		$this->assertTrue( \Sigil\Rate_Limit::blocked( 'u:1' ) );
	}

	public function test_clear_resets(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			\Sigil\Rate_Limit::hit( 'u:2' );
		}
		\Sigil\Rate_Limit::clear( 'u:2' );
		$this->assertFalse( \Sigil\Rate_Limit::blocked( 'u:2' ) );
	}
}
