<?php
/**
 * Tests for DXLEDA_Leads queries, status updates, and assignment.
 *
 * @package DevXpert_Lead_Dashboard
 */

/**
 * Smoke tests for DXLEDA_Leads.
 */
class Test_DXLEDA_Leads extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		DXLEDA_Database::create_tables();
	}

	/**
	 * Test that statuses contains expected keys.
	 */
	public function test_statuses_contains_expected_keys() {
		$statuses = DXLEDA_Leads::get_statuses();
		foreach ( array( 'new', 'positive', 'negative', 'follow_up', 'converted', 'closed' ) as $key ) {
			$this->assertArrayHasKey( $key, $statuses );
		}
	}

	/**
	 * Test that update rejects invalid status.
	 */
	public function test_update_rejects_invalid_status() {
		$this->assertFalse( DXLEDA_Leads::update_lead_status( 123, 'definitely_not_valid' ) );
	}

	/**
	 * Test that update accepts valid status and logs activity.
	 */
	public function test_update_accepts_valid_status_and_logs_activity() {
		// Create the status row first (avoids the Forminator entry lookup path).
		DXLEDA_Leads::assign_lead( 456, 1, 0 );

		$this->assertTrue( DXLEDA_Leads::update_lead_status( 456, 'positive' ) );

		$activity = DXLEDA_Leads::get_activity( 456 );
		$this->assertNotEmpty( $activity );
		$this->assertSame( 'status_change', $activity[0]->action ); // newest first.
	}

	/**
	 * Test that assign lead records assignment.
	 */
	public function test_assign_lead_records_assignment() {
		$this->assertTrue( DXLEDA_Leads::assign_lead( 789, 1, 5 ) );

		$activity = DXLEDA_Leads::get_activity( 789 );
		$this->assertNotEmpty( $activity );
		$this->assertSame( 'assigned', $activity[0]->action );
	}

	/**
	 * Test that reset all statuses returns count.
	 */
	public function test_reset_all_statuses_returns_count() {
		DXLEDA_Leads::assign_lead( 111, 1, 0 );
		DXLEDA_Leads::assign_lead( 222, 1, 0 );

		$removed = DXLEDA_Leads::reset_all_statuses();
		$this->assertSame( 2, $removed );
	}

	/**
	 * Test that bulk meta handles empty input.
	 */
	public function test_bulk_meta_handles_empty_input() {
		$this->assertSame( array(), DXLEDA_Leads::get_entry_meta_bulk( array() ) );
	}

	/**
	 * Deleting a lead must take the submission with it, not just the plugin's
	 * own rows — the leads list is read from the source's entries table.
	 *
	 * The CF7 path is exercised because those entries live in the plugin's own
	 * tables, so no form plugin has to be installed for the test to run.
	 */
	public function test_delete_lead_removes_cf7_entry_and_tracking_rows() {
		global $wpdb;

		$entries = DXLEDA_Sources::entries_table( DXLEDA_Sources::CF7 );
		$meta    = DXLEDA_Sources::meta_table( DXLEDA_Sources::CF7 );

		$wpdb->insert(
			$entries,
			array(
				'form_id'      => 42,
				'date_created' => current_time( 'mysql' ),
			)
		);
		$entry_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$meta,
			array(
				'entry_id'   => $entry_id,
				'meta_key'   => 'email-1',
				'meta_value' => 'test@example.com',
			)
		);

		DXLEDA_Leads::assign_lead( $entry_id, 42, 0, DXLEDA_Sources::CF7 );
		DXLEDA_Feedback::add_feedback(
			array(
				'entry_id' => $entry_id,
				'source'   => DXLEDA_Sources::CF7,
				'user_id'  => 0,
				'feedback' => 'test note',
				'rating'   => 'neutral',
			)
		);

		$this->assertTrue( DXLEDA_Leads::delete_lead( $entry_id, DXLEDA_Sources::CF7 ) );

		// Table names come from $wpdb->prefix; the ids are bound via prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// The submission itself.
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $entries WHERE entry_id = %d", $entry_id ) )
		);
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $meta WHERE entry_id = %d", $entry_id ) )
		);

		// Everything the plugin tracked about it.
		foreach ( array( 'dxleda_lead_status', 'dxleda_feedback', 'dxleda_activity_log' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;

			$this->assertSame(
				'0',
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $table WHERE entry_id = %d AND source = %s",
						$entry_id,
						DXLEDA_Sources::CF7
					)
				),
				$suffix . ' still holds rows for the deleted lead'
			);
		}

		// phpcs:enable
	}

	/**
	 * Test that deleting an entry that was never there reports not-found rather
	 * than silently succeeding.
	 */
	public function test_delete_lead_rejects_unknown_entry() {
		$result = DXLEDA_Leads::delete_lead( 999999, DXLEDA_Sources::CF7 );

		$this->assertWPError( $result );
		$this->assertSame( 'dxleda_not_found', $result->get_error_code() );
	}

	/**
	 * Test that delete rejects a non-positive entry ID outright.
	 */
	public function test_delete_lead_rejects_invalid_id() {
		$result = DXLEDA_Leads::delete_lead( 0, DXLEDA_Sources::CF7 );

		$this->assertWPError( $result );
		$this->assertSame( 'dxleda_invalid_entry', $result->get_error_code() );
	}
}
