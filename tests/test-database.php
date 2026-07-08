<?php
/**
 * Smoke tests for FLD_Database.
 */
class Test_FLD_Database extends WP_UnitTestCase {

    public function test_create_tables_creates_all_three() {
        global $wpdb;

        FLD_Database::create_tables();

        foreach (array('fld_lead_status', 'fld_feedback', 'fld_activity_log') as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            $this->assertSame($table, $found, "Expected table {$table} to exist.");
        }
    }

    public function test_get_table_prefixes_correctly() {
        global $wpdb;
        $this->assertSame($wpdb->prefix . 'fld_feedback', FLD_Database::get_table('feedback'));
    }
}
