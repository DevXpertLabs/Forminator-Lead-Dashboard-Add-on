<?php
/**
 * Smoke tests for DXLEDA_Feedback.
 */
class Test_DXLEDA_Feedback extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        DXLEDA_Database::create_tables();
    }

    public function test_add_and_count_feedback() {
        $id = DXLEDA_Feedback::add_feedback(array(
            'entry_id' => 10,
            'user_id'  => 1,
            'feedback' => 'Looks promising',
            'rating'   => 'positive',
        ));

        $this->assertNotFalse($id);
        $this->assertSame(1, DXLEDA_Feedback::get_feedback_count(10));
    }

    public function test_bulk_counts_group_by_entry() {
        DXLEDA_Feedback::add_feedback(array('entry_id' => 20, 'user_id' => 1, 'feedback' => 'a', 'rating' => 'neutral'));
        DXLEDA_Feedback::add_feedback(array('entry_id' => 20, 'user_id' => 1, 'feedback' => 'b', 'rating' => 'neutral'));
        DXLEDA_Feedback::add_feedback(array('entry_id' => 21, 'user_id' => 1, 'feedback' => 'c', 'rating' => 'neutral'));

        $counts = DXLEDA_Feedback::get_feedback_counts(array(20, 21, 22));
        $this->assertSame(2, $counts[20]);
        $this->assertSame(1, $counts[21]);
        $this->assertArrayNotHasKey(22, $counts); // no feedback => absent
    }

    public function test_bulk_counts_empty_input() {
        $this->assertSame(array(), DXLEDA_Feedback::get_feedback_counts(array()));
    }
}
