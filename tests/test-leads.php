<?php
/**
 * Smoke tests for FLD_Leads.
 */
class Test_FLD_Leads extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        FLD_Database::create_tables();
    }

    public function test_statuses_contains_expected_keys() {
        $statuses = FLD_Leads::get_statuses();
        foreach (array('new', 'positive', 'negative', 'follow_up', 'converted', 'closed') as $key) {
            $this->assertArrayHasKey($key, $statuses);
        }
    }

    public function test_update_rejects_invalid_status() {
        $this->assertFalse(FLD_Leads::update_lead_status(123, 'definitely_not_valid'));
    }

    public function test_update_accepts_valid_status_and_logs_activity() {
        // Create the status row first (avoids the Forminator entry lookup path).
        FLD_Leads::assign_lead(456, 1, 0);

        $this->assertTrue(FLD_Leads::update_lead_status(456, 'positive'));

        $activity = FLD_Leads::get_activity(456);
        $this->assertNotEmpty($activity);
        $this->assertSame('status_change', $activity[0]->action); // newest first
    }

    public function test_assign_lead_records_assignment() {
        $this->assertTrue(FLD_Leads::assign_lead(789, 1, 5));

        $activity = FLD_Leads::get_activity(789);
        $this->assertNotEmpty($activity);
        $this->assertSame('assigned', $activity[0]->action);
    }

    public function test_reset_all_statuses_returns_count() {
        FLD_Leads::assign_lead(111, 1, 0);
        FLD_Leads::assign_lead(222, 1, 0);

        $removed = FLD_Leads::reset_all_statuses();
        $this->assertSame(2, $removed);
    }

    public function test_bulk_meta_handles_empty_input() {
        $this->assertSame(array(), FLD_Leads::get_entry_meta_bulk(array()));
    }
}
