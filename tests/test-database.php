<?php
/**
 * Tests for DXLEDA_Database schema creation and upgrades.
 *
 * @package DevXpert_Lead_Dashboard
 */

/**
 * Smoke tests for DXLEDA_Database.
 */
class Test_DXLEDA_Database extends WP_UnitTestCase {

	/**
	 * Test that create tables creates all three.
	 */
	public function test_create_tables_creates_all_three() {
		global $wpdb;

		DXLEDA_Database::create_tables();

		foreach ( array( 'dxleda_lead_status', 'dxleda_feedback', 'dxleda_activity_log' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$this->assertSame( $table, $found, "Expected table {$table} to exist." );
		}
	}

	/**
	 * Test that get table prefixes correctly.
	 */
	public function test_get_table_prefixes_correctly() {
		global $wpdb;
		$this->assertSame( $wpdb->prefix . 'dxleda_feedback', DXLEDA_Database::get_table( 'feedback' ) );
	}
}
