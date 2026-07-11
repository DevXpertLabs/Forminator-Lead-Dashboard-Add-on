<?php
/**
 * New-lead automation: email notifications and auto-assignment.
 *
 * Hooks Forminator's after-save event so that, whenever a form submission
 * becomes a lead, the configured team is notified and (optionally) the lead
 * is assigned to a default team member.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLD_Notifications {

    /**
     * Register the Forminator after-save hook.
     */
    public static function init() {
        // Forminator fires this after an entry row has been saved.
        // Signature: do_action( 'forminator_form_after_save_entry', $form_id, $response ).
        add_action('forminator_form_after_save_entry', array(__CLASS__, 'on_new_lead'), 20, 2);
    }

    /**
     * Handle a freshly saved Forminator entry.
     *
     * @param int   $form_id  Forminator form ID.
     * @param array $response Save response; contains the new entry_id on success.
     */
    public static function on_new_lead($form_id, $response) {
        // Only act on successful saves that produced an entry ID.
        if (!is_array($response) || empty($response['success']) || empty($response['entry_id'])) {
            return;
        }

        $entry_id = intval($response['entry_id']);
        $form_id  = intval($form_id);

        self::maybe_auto_assign($entry_id, $form_id);
        self::maybe_notify($entry_id, $form_id);
    }

    /**
     * Assign the lead to the default assignee when auto-assign is enabled.
     */
    private static function maybe_auto_assign($entry_id, $form_id) {
        if (!get_option('fld_auto_assign', 0)) {
            return;
        }

        $assignee = intval(get_option('fld_default_assignee', 0));
        if ($assignee <= 0) {
            return;
        }

        // Only assign to a user who can actually work leads.
        $user = get_userdata($assignee);
        if (!$user || (!user_can($user, FLD_Roles::CAP) && !user_can($user, 'manage_options'))) {
            return;
        }

        FLD_Leads::assign_lead($entry_id, $form_id, $assignee);
    }

    /**
     * Email the configured recipient about the new lead when enabled.
     */
    private static function maybe_notify($entry_id, $form_id) {
        if (!get_option('fld_email_notifications', 0)) {
            return;
        }

        $to = sanitize_email(get_option('fld_notification_email', get_option('admin_email')));
        if (!is_email($to)) {
            return;
        }

        $form_name = self::get_form_name($form_id);
        $lead      = FLD_Leads::get_lead($entry_id);

        $subject = sprintf(
            /* translators: 1: form name, 2: entry ID */
            __('[%1$s] New lead #%2$d', 'devxpert-lead-dashboard-for-forminator'),
            $form_name,
            $entry_id
        );

        $lines   = array();
        /* translators: %s: form name */
        $lines[] = sprintf(__('A new lead was submitted via "%s".', 'devxpert-lead-dashboard-for-forminator'), $form_name);
        $lines[] = '';

        if ($lead && !empty($lead['meta']) && is_array($lead['meta'])) {
            foreach ($lead['meta'] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $lines[] = self::humanize_key($key) . ': ' . $value;
            }
            $lines[] = '';
        }

        $lines[] = __('View in the Lead Dashboard:', 'devxpert-lead-dashboard-for-forminator');
        $lines[] = admin_url('admin.php?page=lead-dashboard-leads');

        $body = implode("\n", $lines);

        wp_mail($to, $subject, $body);
    }

    /**
     * Resolve a Forminator form's display name, falling back to its ID.
     */
    private static function get_form_name($form_id) {
        $names  = FLD_Leads::form_names();
        $form_id = (int) $form_id;

        return isset($names[$form_id]) ? $names[$form_id] : ('Form #' . $form_id);
    }

    /**
     * Turn a Forminator meta key into a human-readable label.
     */
    private static function humanize_key($key) {
        $key = preg_replace('/-\d+$/', '', (string) $key);      // strip trailing "-1"
        $key = str_replace(array('-', '_'), ' ', $key);
        return ucwords(trim($key));
    }
}
