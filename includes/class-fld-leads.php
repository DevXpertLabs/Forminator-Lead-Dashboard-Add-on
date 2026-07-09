<?php
/**
 * Leads Handler Class
 * 
 * Manages leads from Forminator entries
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLD_Leads {

    /**
     * Get leads with filters
     */
    public static function get_leads($args = array()) {
        global $wpdb;

        $defaults = array(
            'form_id' => 0,
            'status' => '',
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'assigned_to' => 0,
            'orderby' => 'date_created',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        
        $table_entries = $wpdb->prefix . 'frmt_form_entry';
        $table_meta = $wpdb->prefix . 'frmt_form_entry_meta';
        $table_status = $wpdb->prefix . 'fld_lead_status';

        // Base query
        $query = "SELECT e.*, 
                         COALESCE(s.status, 'new') as lead_status,
                         s.assigned_to,
                         s.priority,
                         s.source
                  FROM $table_entries e
                  LEFT JOIN $table_status s ON e.entry_id = s.entry_id
                  WHERE e.entry_type = 'custom-forms'";

        $query_args = array();

        // Form filter
        if ($args['form_id'] > 0) {
            $query .= " AND e.form_id = %d";
            $query_args[] = $args['form_id'];
        }

        // Status filter
        if (!empty($args['status'])) {
            if ($args['status'] === 'new') {
                $query .= " AND (s.status IS NULL OR s.status = 'new')";
            } else {
                $query .= " AND s.status = %s";
                $query_args[] = $args['status'];
            }
        }

        // Date range filter
        if (!empty($args['date_from'])) {
            $query .= " AND e.date_created >= %s";
            $query_args[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $query .= " AND e.date_created <= %s";
            $query_args[] = $args['date_to'] . ' 23:59:59';
        }

        // Assigned to filter
        if ($args['assigned_to'] > 0) {
            $query .= " AND s.assigned_to = %d";
            $query_args[] = $args['assigned_to'];
        }

        // Search filter (searches in entry meta)
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= " AND e.entry_id IN (
                SELECT DISTINCT entry_id FROM $table_meta 
                WHERE meta_value LIKE %s
            )";
            $query_args[] = $search_term;
        }

        // Count total — wrap in subquery to avoid ONLY_FULL_GROUP_BY issues
        // (the inner SELECT has mixed aggregate + non-aggregate columns)
        if (!empty($query_args)) {
            $total = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM ($query) AS fld_count_subq", $query_args)
            );
        } else {
            $total = $wpdb->get_var("SELECT COUNT(*) FROM ($query) AS fld_count_subq");
        }

        // Order
        $allowed_orderby = array('date_created', 'entry_id', 'lead_status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'date_created';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        if ($orderby === 'date_created') {
            $query .= " ORDER BY e.date_created $order";
        } else {
            $query .= " ORDER BY $orderby $order";
        }

        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $query .= " LIMIT %d OFFSET %d";
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;

        // Execute query
        if (!empty($query_args)) {
            $entries = $wpdb->get_results($wpdb->prepare($query, $query_args));
        } else {
            $entries = $wpdb->get_results($query);
        }

        // Batch-load meta and feedback counts for all entries in two queries
        // (instead of two queries per row) to avoid an N+1 pattern.
        $entry_ids = wp_list_pluck($entries, 'entry_id');
        $meta_map  = self::get_entry_meta_bulk($entry_ids);
        $count_map = FLD_Feedback::get_feedback_counts($entry_ids);

        $leads = array();
        foreach ($entries as $entry) {
            $leads[] = array(
                'entry_id' => $entry->entry_id,
                'form_id' => $entry->form_id,
                'date_created' => $entry->date_created,
                'status' => $entry->lead_status,
                'assigned_to' => $entry->assigned_to,
                'priority' => $entry->priority,
                'source' => $entry->source,
                'meta' => isset($meta_map[$entry->entry_id]) ? $meta_map[$entry->entry_id] : array(),
                'feedback_count' => isset($count_map[$entry->entry_id]) ? $count_map[$entry->entry_id] : 0
            );
        }

        return array(
            'leads' => $leads,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }

    /**
     * Get entry meta data
     */
    public static function get_entry_meta($entry_id) {
        global $wpdb;
        
        $table_meta = $wpdb->prefix . 'frmt_form_entry_meta';
        
        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM $table_meta WHERE entry_id = %d",
            $entry_id
        ), OBJECT_K);

        $formatted = array();
        foreach ($meta as $key => $row) {
            $formatted[$key] = maybe_unserialize($row->meta_value);
        }

        return $formatted;
    }

    /**
     * Bulk-load entry meta for many entries in a single query.
     *
     * @param int[] $entry_ids
     * @return array<int,array<string,mixed>> Map of entry_id => [meta_key => value].
     */
    public static function get_entry_meta_bulk($entry_ids) {
        global $wpdb;

        $entry_ids = array_values(array_unique(array_map('intval', (array) $entry_ids)));
        if (empty($entry_ids)) {
            return array();
        }

        $table_meta   = $wpdb->prefix . 'frmt_form_entry_meta';
        $placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built from a count and passed to prepare().
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT entry_id, meta_key, meta_value FROM $table_meta WHERE entry_id IN ($placeholders)",
            $entry_ids
        ));

        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->entry_id][$row->meta_key] = maybe_unserialize($row->meta_value);
        }

        return $map;
    }

    /**
     * Update lead status
     */
    public static function update_lead_status($entry_id, $status, $additional = array()) {
        global $wpdb;

        // Defence in depth: only ever persist a known status value.
        if (!array_key_exists($status, self::get_statuses())) {
            return false;
        }

        $table = $wpdb->prefix . 'fld_lead_status';

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE entry_id = %d",
            $entry_id
        ));

        // Get form_id from entry
        $form_id = $wpdb->get_var($wpdb->prepare(
            "SELECT form_id FROM {$wpdb->prefix}frmt_form_entry WHERE entry_id = %d",
            $entry_id
        ));

        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        // Add additional fields if provided
        if (!empty($additional['assigned_to'])) {
            $data['assigned_to'] = intval($additional['assigned_to']);
        }
        if (!empty($additional['priority'])) {
            $data['priority'] = sanitize_text_field($additional['priority']);
        }
        if (!empty($additional['source'])) {
            $data['source'] = sanitize_text_field($additional['source']);
        }

        if ($exists) {
            $result = $wpdb->update($table, $data, array('entry_id' => $entry_id));
        } else {
            $data['entry_id'] = $entry_id;
            $data['form_id'] = $form_id;
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
        }

        // Log activity
        self::log_activity($entry_id, 'status_change', array(
            'new_status' => $status,
            'user' => get_current_user_id()
        ));

        return $result !== false;
    }

    /**
     * Get lead by entry ID
     */
    public static function get_lead($entry_id) {
        global $wpdb;

        $table_entries = $wpdb->prefix . 'frmt_form_entry';
        $table_status = $wpdb->prefix . 'fld_lead_status';

        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, 
                    COALESCE(s.status, 'new') as lead_status,
                    s.assigned_to,
                    s.priority,
                    s.source
             FROM $table_entries e
             LEFT JOIN $table_status s ON e.entry_id = s.entry_id
             WHERE e.entry_id = %d",
            $entry_id
        ));

        if (!$entry) {
            return null;
        }

        $meta = self::get_entry_meta($entry_id);
        $feedback = FLD_Feedback::get_feedback($entry_id);

        return array(
            'entry_id' => $entry->entry_id,
            'form_id' => $entry->form_id,
            'date_created' => $entry->date_created,
            'status' => $entry->lead_status,
            'assigned_to' => $entry->assigned_to,
            'priority' => $entry->priority,
            'source' => $entry->source,
            'meta' => $meta,
            'feedback' => $feedback
        );
    }

    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats($days = 30) {
        global $wpdb;

        $table_entries = $wpdb->prefix . 'frmt_form_entry';
        $table_status = $wpdb->prefix . 'fld_lead_status';

        $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        // Total leads
        $total_leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_entries 
             WHERE entry_type = 'custom-forms' AND date_created >= %s",
            $date_from
        ));

        // Leads by status
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(s.status, 'new') as status, COUNT(*) as count
             FROM $table_entries e
             LEFT JOIN $table_status s ON e.entry_id = s.entry_id
             WHERE e.entry_type = 'custom-forms' AND e.date_created >= %s
             GROUP BY COALESCE(s.status, 'new')",
            $date_from
        ), OBJECT_K);

        // Leads by day (for chart)
        $leads_by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(date_created) as date, COUNT(*) as count
             FROM $table_entries
             WHERE entry_type = 'custom-forms' AND date_created >= %s
             GROUP BY DATE(date_created)
             ORDER BY date ASC",
            $date_from
        ));

        // Leads by form
        $leads_by_form = $wpdb->get_results($wpdb->prepare(
            "SELECT e.form_id, COUNT(*) as count
             FROM $table_entries e
             WHERE e.entry_type = 'custom-forms' AND e.date_created >= %s
             GROUP BY e.form_id
             ORDER BY count DESC
             LIMIT 10",
            $date_from
        ));

        // Add form names from the cached map (one API call, not one per form)
        $names = self::form_names();
        foreach ($leads_by_form as $item) {
            $fid = (int) $item->form_id;
            $item->form_name = isset($names[$fid]) ? $names[$fid] : ('Form #' . $fid);
        }

        // Conversion rate (positive leads / total)
        $positive_count = isset($status_counts['positive']) ? $status_counts['positive']->count : 0;
        $converted_count = isset($status_counts['converted']) ? $status_counts['converted']->count : 0;
        $conversion_rate = $total_leads > 0 ? round((($positive_count + $converted_count) / $total_leads) * 100, 1) : 0;

        return array(
            'total_leads' => intval($total_leads),
            'status_counts' => $status_counts,
            'leads_by_day' => $leads_by_day,
            'leads_by_form' => $leads_by_form,
            'conversion_rate' => $conversion_rate,
            'positive_leads' => intval($positive_count),
            'negative_leads' => isset($status_counts['negative']) ? intval($status_counts['negative']->count) : 0,
            'new_leads' => isset($status_counts['new']) ? intval($status_counts['new']->count) : intval($total_leads - array_sum(array_column((array)$status_counts, 'count')))
        );
    }

    /**
     * Export leads to CSV
     */
    public static function export_leads_csv($form_id = 0, $status = '') {
        $leads_data = self::get_leads(array(
            'form_id' => $form_id,
            'status' => $status,
            'per_page' => 10000
        ));

        $leads = $leads_data['leads'];

        if (empty($leads)) {
            return '';
        }

        // Get all meta keys
        $all_keys = array();
        foreach ($leads as $lead) {
            $all_keys = array_merge($all_keys, array_keys($lead['meta']));
        }
        $all_keys = array_unique($all_keys);

        // Build the CSV in an in-memory stream. php://temp is used deliberately
        // (no file is written to disk); the WP_Filesystem API does not apply here.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen('php://temp', 'r+');

        // Header row
        $header = array_merge(
            array('Entry ID', 'Form ID', 'Date', 'Status', 'Feedback Count'),
            $all_keys
        );
        fputcsv($output, $header);

        // Data rows
        foreach ($leads as $lead) {
            $row = array(
                $lead['entry_id'],
                $lead['form_id'],
                $lead['date_created'],
                $lead['status'],
                $lead['feedback_count']
            );

            foreach ($all_keys as $key) {
                $value = isset($lead['meta'][$key]) ? $lead['meta'][$key] : '';
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[] = $value;
            }

            // Neutralise spreadsheet formula injection before writing.
            $row = array_map(array(__CLASS__, 'csv_escape'), $row);

            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output);

        return $csv;
    }

    /**
     * Neutralise CSV/formula injection: a leading =, +, -, @, tab or CR can be
     * interpreted as a formula by Excel/Sheets. Prefix such values with a
     * single quote so they are treated as literal text.
     *
     * @param mixed $value
     * @return string
     */
    private static function csv_escape($value) {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            $value = "'" . $value;
        }

        return $value;
    }

    /**
     * Log activity
     */
    public static function log_activity($entry_id, $action, $details = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fld_activity_log';

        return $wpdb->insert($table, array(
            'entry_id' => $entry_id,
            'user_id' => get_current_user_id(),
            'action' => $action,
            'details' => wp_json_encode($details),
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Cached map of Forminator form ID => form name.
     * Resolved once per request (one API call) and reused everywhere.
     *
     * @return array<int,string>
     */
    public static function form_names() {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = array();

        if (class_exists('Forminator_API')) {
            // Pass a high per-page so we get every form, not just the first 10.
            $forms = Forminator_API::get_forms(null, 1, 999);
            if (is_array($forms)) {
                foreach ($forms as $form) {
                    $settings = (array) $form->settings;
                    $map[(int) $form->id] = !empty($settings['formName'])
                        ? $settings['formName']
                        : ('Form #' . $form->id);
                }
            }
        }

        return $map;
    }

    /**
     * Get available forms as a list of id/name pairs (for dropdowns).
     */
    public static function get_forms() {
        $list = array();
        foreach (self::form_names() as $id => $name) {
            $list[] = array('id' => $id, 'name' => $name);
        }
        return $list;
    }

    /**
     * Get the activity log for a single entry, newest first.
     */
    public static function get_activity($entry_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'fld_activity_log';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.action, a.details, a.created_at, u.display_name AS user_name
             FROM $table a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.entry_id = %d
             ORDER BY a.created_at DESC",
            intval($entry_id)
        ));

        return is_array($rows) ? $rows : array();
    }

    /**
     * Empty the activity log entirely. Administrators only (enforced by caller).
     *
     * @return int Number of rows removed.
     */
    public static function clear_activity_log() {
        global $wpdb;

        $table = $wpdb->prefix . 'fld_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- deleting all rows from a plugin-owned table; name from trusted prefix.
        return (int) $wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );
    }

    /**
     * Reset every lead back to "new" by clearing the status table.
     * Administrators only (enforced by caller).
     *
     * @return int Number of status rows removed.
     */
    public static function reset_all_statuses() {
        global $wpdb;

        $table = $wpdb->prefix . 'fld_lead_status';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- deleting all rows from a plugin-owned table; name from trusted prefix.
        return (int) $wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );
    }

    /**
     * Assign a lead to a user without altering its status.
     * Creates the status row (status "new") if none exists yet.
     *
     * @return bool
     */
    public static function assign_lead($entry_id, $form_id, $user_id) {
        global $wpdb;

        $table    = $wpdb->prefix . 'fld_lead_status';
        $entry_id = intval($entry_id);
        $user_id  = intval($user_id);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE entry_id = %d",
            $entry_id
        ));

        if ($exists) {
            $result = $wpdb->update(
                $table,
                array('assigned_to' => $user_id, 'updated_at' => current_time('mysql')),
                array('entry_id' => $entry_id)
            );
        } else {
            $result = $wpdb->insert($table, array(
                'entry_id'    => $entry_id,
                'form_id'     => intval($form_id),
                'status'      => 'new',
                'assigned_to' => $user_id,
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ));
        }

        if ($result !== false) {
            self::log_activity($entry_id, 'assigned', array('assigned_to' => $user_id));
            return true;
        }

        return false;
    }

    /**
     * Get lead statuses
     */
    public static function get_statuses() {
        return array(
            'new' => __('New', 'lead-dashboard-for-forminator'),
            'positive' => __('Positive', 'lead-dashboard-for-forminator'),
            'negative' => __('Negative', 'lead-dashboard-for-forminator'),
            'follow_up' => __('Follow Up', 'lead-dashboard-for-forminator'),
            'converted' => __('Converted', 'lead-dashboard-for-forminator'),
            'closed' => __('Closed', 'lead-dashboard-for-forminator')
        );
    }
}
