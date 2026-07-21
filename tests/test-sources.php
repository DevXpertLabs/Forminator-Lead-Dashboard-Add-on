<?php
/**
 * Multi-source behaviour: the same entry ID coming from two different form
 * plugins must stay two independent leads.
 */
class Test_DXLEDA_Sources extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        DXLEDA_Database::create_tables();
    }

    public function test_sanitize_falls_back_to_forminator() {
        $this->assertSame(DXLEDA_Sources::FORMINATOR, DXLEDA_Sources::sanitize('nonsense'));
        $this->assertSame(DXLEDA_Sources::FORMINATOR, DXLEDA_Sources::sanitize(''));
        $this->assertSame(DXLEDA_Sources::FORMINATOR, DXLEDA_Sources::sanitize(null));
        $this->assertSame(DXLEDA_Sources::CF7, DXLEDA_Sources::sanitize('cf7'));
    }

    public function test_known_sources_have_distinct_tables() {
        $this->assertNotSame(
            DXLEDA_Sources::entries_table(DXLEDA_Sources::FORMINATOR),
            DXLEDA_Sources::entries_table(DXLEDA_Sources::CF7)
        );
        $this->assertSame('', DXLEDA_Sources::entries_table('nope'));
    }

    public function test_same_entry_id_in_two_sources_keeps_separate_status() {
        DXLEDA_Leads::update_lead_status(500, 'positive', array(), DXLEDA_Sources::FORMINATOR);
        DXLEDA_Leads::update_lead_status(500, 'negative', array(), DXLEDA_Sources::CF7);

        global $wpdb;
        $table = $wpdb->prefix . 'dxleda_lead_status';

        $forminator = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE entry_id = %d AND source = %s",
            500,
            DXLEDA_Sources::FORMINATOR
        ));
        $cf7 = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE entry_id = %d AND source = %s",
            500,
            DXLEDA_Sources::CF7
        ));

        $this->assertSame('positive', $forminator);
        $this->assertSame('negative', $cf7);
    }

    public function test_same_entry_id_in_two_sources_keeps_separate_feedback() {
        DXLEDA_Feedback::add_feedback(array(
            'entry_id' => 600,
            'source'   => DXLEDA_Sources::FORMINATOR,
            'user_id'  => 1,
            'feedback' => 'forminator note',
            'rating'   => 'neutral',
        ));
        DXLEDA_Feedback::add_feedback(array(
            'entry_id' => 600,
            'source'   => DXLEDA_Sources::CF7,
            'user_id'  => 1,
            'feedback' => 'cf7 note',
            'rating'   => 'neutral',
        ));

        $forminator = DXLEDA_Feedback::get_feedback(600, DXLEDA_Sources::FORMINATOR);
        $cf7        = DXLEDA_Feedback::get_feedback(600, DXLEDA_Sources::CF7);

        $this->assertCount(1, $forminator);
        $this->assertCount(1, $cf7);
        $this->assertSame('forminator note', $forminator[0]->feedback);
        $this->assertSame('cf7 note', $cf7[0]->feedback);

        $counts = DXLEDA_Feedback::get_feedback_counts(array(600), DXLEDA_Sources::CF7);
        $this->assertSame(array(600 => 1), $counts);
    }

    public function test_same_entry_id_in_two_sources_keeps_separate_activity() {
        DXLEDA_Leads::log_activity(700, 'status_change', array(), DXLEDA_Sources::FORMINATOR);
        DXLEDA_Leads::log_activity(700, 'assigned', array(), DXLEDA_Sources::CF7);

        $forminator = DXLEDA_Leads::get_activity(700, DXLEDA_Sources::FORMINATOR);
        $cf7        = DXLEDA_Leads::get_activity(700, DXLEDA_Sources::CF7);

        $this->assertCount(1, $forminator);
        $this->assertCount(1, $cf7);
        $this->assertSame('status_change', $forminator[0]->action);
        $this->assertSame('assigned', $cf7[0]->action);
    }

    public function test_assign_lead_is_scoped_to_its_source() {
        DXLEDA_Leads::assign_lead(800, 1, 11, DXLEDA_Sources::FORMINATOR);
        DXLEDA_Leads::assign_lead(800, 1, 22, DXLEDA_Sources::CF7);

        global $wpdb;
        $table = $wpdb->prefix . 'dxleda_lead_status';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT source, assigned_to FROM $table WHERE entry_id = %d ORDER BY source",
            800
        ), OBJECT_K);

        $this->assertSame('22', (string) $rows[DXLEDA_Sources::CF7]->assigned_to);
        $this->assertSame('11', (string) $rows[DXLEDA_Sources::FORMINATOR]->assigned_to);
    }
}
