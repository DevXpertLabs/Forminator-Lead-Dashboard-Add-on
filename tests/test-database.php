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
	 * Test that create_tables() creates every plugin table.
	 *
	 * The WP test suite turns CREATE TABLE into CREATE TEMPORARY TABLE, which
	 * SHOW TABLES cannot see — so assert each table is queryable instead.
	 */
	public function test_create_tables_creates_all_tables() {
		global $wpdb;

		DXLEDA_Database::create_tables();

		$suffixes = array(
			'dxleda_lead_status',
			'dxleda_feedback',
			'dxleda_activity_log',
			'dxleda_cf7_entries',
			'dxleda_cf7_entry_meta',
		);

		foreach ( $suffixes as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table name; existence probe in tests.
			$result = $wpdb->query( "SELECT 1 FROM {$table} LIMIT 1" );
			$this->assertNotFalse( $result, "Expected table {$table} to exist. {$wpdb->last_error}" );
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
