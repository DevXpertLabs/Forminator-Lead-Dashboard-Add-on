<?php
/**
 * Smoke tests for FLD_Feedback.
 */
class Test_FLD_Feedback extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        FLD_Database::create_tables();
    }

    public function test_add_and_count_feedback() {
        $id = FLD_Feedback::add_feedback(array(
            'entry_id' => 10,
            'user_id'  => 1,
            'feedback' => 'Looks promising',
            'rating'   => 'positive',
        ));

        $this->assertNotFalse($id);
        $this->assertSame(1, FLD_Feedback::get_feedback_count(10));
    }

    public function test_bulk_counts_group_by_entry() {
        FLD_Feedback::add_feedback(array('entry_id' => 20, 'user_id' => 1, 'feedback' => 'a', 'rating' => 'neutral'));
        FLD_Feedback::add_feedback(array('entry_id' => 20, 'user_id' => 1, 'feedback' => 'b', 'rating' => 'neutral'));
        FLD_Feedback::add_feedback(array('entry_id' => 21, 'user_id' => 1, 'feedback' => 'c', 'rating' => 'neutral'));

        $counts = FLD_Feedback::get_feedback_counts(array(20, 21, 22));
        $this->assertSame(2, $counts[20]);
        $this->assertSame(1, $counts[21]);
        $this->assertArrayNotHasKey(22, $counts); // no feedback => absent
    }

    public function test_bulk_counts_empty_input() {
        $this->assertSame(array(), FLD_Feedback::get_feedback_counts(array()));
    }
}
