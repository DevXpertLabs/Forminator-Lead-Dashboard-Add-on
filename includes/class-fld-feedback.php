<?php
/**
 * Feedback Handler Class
 * 
 * Manages sales team feedback for leads
 */

if (!defined('ABSPATH')) {
    exit;
}

class DXLEDA_Feedback {

    /**
     * Add feedback
     */
    public static function add_feedback($args) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dxleda_feedback';

        $source = DXLEDA_Sources::sanitize(isset($args['source']) ? $args['source'] : '');

        $data = array(
            'entry_id' => intval($args['entry_id']),
            'source' => $source,
            'user_id' => intval($args['user_id']),
            'feedback' => sanitize_textarea_field($args['feedback']),
            'rating' => sanitize_text_field($args['rating']),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table, $data);

        if ($result) {
            // Log activity
            DXLEDA_Leads::log_activity($args['entry_id'], 'feedback_added', array(
                'feedback_id' => $wpdb->insert_id,
                'rating' => $args['rating']
            ), $source);

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get feedback for an entry
     */
    public static function get_feedback($entry_id, $source = DXLEDA_Sources::FORMINATOR) {
        global $wpdb;

        $table = $wpdb->prefix . 'dxleda_feedback';

        $feedback = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, u.display_name as user_name
             FROM $table f
             LEFT JOIN {$wpdb->users} u ON f.user_id = u.ID
             WHERE f.entry_id = %d AND f.source = %s
             ORDER BY f.created_at DESC",
            $entry_id,
            DXLEDA_Sources::sanitize($source)
        ));

        return $feedback;
    }

    /**
     * Get feedback count for an entry
     */
    public static function get_feedback_count($entry_id, $source = DXLEDA_Sources::FORMINATOR) {
        global $wpdb;

        $table = $wpdb->prefix . 'dxleda_feedback';

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE entry_id = %d AND source = %s",
            $entry_id,
            DXLEDA_Sources::sanitize($source)
        )));
    }

    /**
     * Get feedback counts for many entries in a single query.
     *
     * All ids must belong to the same source, since entry IDs are only unique
     * within one form plugin.
     *
     * @param int[]  $entry_ids
     * @param string $source
     * @return array<int,int> Map of entry_id => count (only entries with feedback).
     */
    public static function get_feedback_counts($entry_ids, $source = DXLEDA_Sources::FORMINATOR) {
        global $wpdb;

        $entry_ids = array_values(array_unique(array_map('intval', (array) $entry_ids)));
        if (empty($entry_ids)) {
            return array();
        }

        $table        = $wpdb->prefix . 'dxleda_feedback';
        $placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built from a count and passed to prepare().
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT entry_id, COUNT(*) AS c FROM $table
             WHERE entry_id IN ($placeholders) AND source = %s
             GROUP BY entry_id",
            array_merge($entry_ids, array(DXLEDA_Sources::sanitize($source)))
        ));

        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->entry_id] = (int) $row->c;
        }

        return $map;
    }

    /**
     * Get the user_id that owns a feedback entry
     *
     * @param int $feedback_id
     * @return int|null
     */
    public static function get_feedback_owner($feedback_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dxleda_feedback';

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            intval($feedback_id)
        ));

        return $user_id !== null ? intval($user_id) : null;
    }

    /**
     * Delete feedback
     */
    public static function delete_feedback($feedback_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dxleda_feedback';

        // Get the lead reference before deleting
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT entry_id, source FROM $table WHERE id = %d",
            $feedback_id
        ));

        $result = $wpdb->delete($table, array('id' => $feedback_id));

        if ($result && $row) {
            // Log activity
            DXLEDA_Leads::log_activity($row->entry_id, 'feedback_deleted', array(
                'feedback_id' => $feedback_id
            ), $row->source);
        }

        return $result !== false;
    }

    /**
     * Update feedback
     */
    public static function update_feedback($feedback_id, $args) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dxleda_feedback';

        $data = array();

        if (isset($args['feedback'])) {
            $data['feedback'] = sanitize_textarea_field($args['feedback']);
        }

        if (isset($args['rating'])) {
            $data['rating'] = sanitize_text_field($args['rating']);
        }

        if (empty($data)) {
            return false;
        }

        return $wpdb->update($table, $data, array('id' => $feedback_id)) !== false;
    }

    /**
     * Get feedback ratings
     */
    public static function get_ratings() {
        return array(
            'positive' => array(
                'label' => __('Positive', 'devxpert-lead-dashboard-for-forminator'),
                'icon'  => 'positive', // maps to .fld-rating-ico--positive SVG
                'color' => '#22c55e'
            ),
            'neutral' => array(
                'label' => __('Neutral', 'devxpert-lead-dashboard-for-forminator'),
                'icon'  => 'neutral', // maps to .fld-rating-ico--neutral SVG
                'color' => '#eab308'
            ),
            'negative' => array(
                'label' => __('Negative', 'devxpert-lead-dashboard-for-forminator'),
                'icon'  => 'negative', // maps to .fld-rating-ico--negative SVG
                'color' => '#ef4444'
            )
        );
    }

    /**
     * Get feedback statistics
     */
    public static function get_stats($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dxleda_feedback';
        $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT rating, COUNT(*) as count
             FROM $table
             WHERE created_at >= %s
             GROUP BY rating",
            $date_from
        ), OBJECT_K);

        return $stats;
    }
}
