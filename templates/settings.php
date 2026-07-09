<?php
/**
 * Settings Template — Administrators only
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!FLD_Roles::is_admin()) {
    wp_die(esc_html__('You do not have permission to access this page.', 'lead-dashboard-for-forminator'));
}

// Handle form submission
$fld_settings_nonce = isset($_POST['fld_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['fld_settings_nonce'])) : '';
if (isset($_POST['fld_save_settings']) && wp_verify_nonce($fld_settings_nonce, 'fld_save_settings')) {
    update_option('fld_email_notifications', isset($_POST['fld_email_notifications']) ? 1 : 0);
    update_option('fld_notification_email', sanitize_email(wp_unslash($_POST['fld_notification_email'] ?? '')));
    update_option('fld_auto_assign', isset($_POST['fld_auto_assign']) ? 1 : 0);
    update_option('fld_default_assignee', isset($_POST['fld_default_assignee']) ? intval($_POST['fld_default_assignee']) : 0);
    update_option('fld_leads_per_page', isset($_POST['fld_leads_per_page']) ? intval($_POST['fld_leads_per_page']) : 20);

    // Brevo SMTP / OTP settings
    update_option('fld_smtp_host',          sanitize_text_field(wp_unslash($_POST['fld_smtp_host'] ?? '')));
    update_option('fld_smtp_port',          intval($_POST['fld_smtp_port'] ?? 587));
    update_option('fld_smtp_username',      sanitize_text_field(wp_unslash($_POST['fld_smtp_username'] ?? '')));
    // Only update password if a new value was actually submitted (non-empty).
    // Stored encrypted at rest; decrypted only when sending mail.
    if (!empty($_POST['fld_smtp_password'])) {
        $fld_new_pw = sanitize_text_field(wp_unslash($_POST['fld_smtp_password']));
        update_option('fld_smtp_password', FLD_OTP::encrypt_secret($fld_new_pw));
    }
    update_option('fld_smtp_encryption',    sanitize_text_field(wp_unslash($_POST['fld_smtp_encryption'] ?? 'tls')));
    update_option('fld_brevo_sender_name',  sanitize_text_field(wp_unslash($_POST['fld_brevo_sender_name'] ?? get_bloginfo('name'))));
    update_option('fld_brevo_sender_email', sanitize_email(wp_unslash($_POST['fld_brevo_sender_email'] ?? '')));
    update_option('fld_otp_enabled_forms',  array_map('intval', (array) ($_POST['fld_otp_enabled_forms'] ?? [])));

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'lead-dashboard-for-forminator' ) . '</p></div>';
}

$email_notifications = get_option('fld_email_notifications', 0);
$notification_email  = get_option('fld_notification_email', get_option('admin_email'));
$auto_assign         = get_option('fld_auto_assign', 0);
$default_assignee    = get_option('fld_default_assignee', 0);
$leads_per_page      = get_option('fld_leads_per_page', 20);

// Brevo SMTP / OTP settings
$smtp_host          = get_option('fld_smtp_host',          'smtp-relay.brevo.com');
$smtp_port          = get_option('fld_smtp_port',          587);
$smtp_username      = get_option('fld_smtp_username',      '');
$smtp_encryption    = get_option('fld_smtp_encryption',    'tls');
$brevo_sender_name  = get_option('fld_brevo_sender_name',  get_bloginfo('name'));
$brevo_sender_email = get_option('fld_brevo_sender_email', get_option('admin_email'));
$otp_enabled_forms  = array_map('intval', (array) get_option('fld_otp_enabled_forms', array()));
$all_forms          = FLD_Leads::get_forms();

$team_users    = FLD_Roles::get_team_users();
$sales_admins  = FLD_Roles::get_sales_admins();
?>

<div class="wrap fld-settings-page">
    <h1 class="fld-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('Lead Dashboard Settings', 'lead-dashboard-for-forminator'); ?>
    </h1>

    <form method="post" class="fld-settings-form">
        <?php wp_nonce_field('fld_save_settings', 'fld_settings_nonce'); ?>

        <!-- General Settings -->
        <div class="fld-settings-section">
            <h2><?php esc_html_e('General Settings', 'lead-dashboard-for-forminator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fld_leads_per_page"><?php esc_html_e('Leads Per Page', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="fld_leads_per_page" name="fld_leads_per_page"
                               value="<?php echo esc_attr($leads_per_page); ?>" min="10" max="100">
                        <p class="description"><?php esc_html_e('Number of leads to show per page in the leads list.', 'lead-dashboard-for-forminator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Notification Settings -->
        <div class="fld-settings-section">
            <h2><?php esc_html_e('Notification Settings', 'lead-dashboard-for-forminator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fld_email_notifications"><?php esc_html_e('Email Notifications', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="fld_email_notifications" name="fld_email_notifications"
                                   value="1" <?php checked($email_notifications, 1); ?>>
                            <?php esc_html_e('Send email notifications for new leads', 'lead-dashboard-for-forminator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_notification_email"><?php esc_html_e('Notification Email', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="fld_notification_email" name="fld_notification_email"
                               value="<?php echo esc_attr($notification_email); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Email address to receive new lead notifications.', 'lead-dashboard-for-forminator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Assignment Settings -->
        <div class="fld-settings-section">
            <h2><?php esc_html_e('Lead Assignment', 'lead-dashboard-for-forminator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fld_auto_assign"><?php esc_html_e('Auto-Assign Leads', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="fld_auto_assign" name="fld_auto_assign"
                                   value="1" <?php checked($auto_assign, 1); ?>>
                            <?php esc_html_e('Automatically assign new leads to a team member', 'lead-dashboard-for-forminator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_default_assignee"><?php esc_html_e('Default Assignee', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <select id="fld_default_assignee" name="fld_default_assignee">
                            <option value="0"><?php esc_html_e('— Select —', 'lead-dashboard-for-forminator'); ?></option>
                            <?php foreach ($team_users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($default_assignee, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Team member to auto-assign new leads to.', 'lead-dashboard-for-forminator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Status Labels -->
        <div class="fld-settings-section">
            <h2><?php esc_html_e('Lead Statuses', 'lead-dashboard-for-forminator'); ?></h2>
            <p class="description"><?php esc_html_e('These are the available lead statuses:', 'lead-dashboard-for-forminator'); ?></p>

            <div class="fld-status-list">
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-new"><?php esc_html_e('New', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Newly submitted leads', 'lead-dashboard-for-forminator'); ?></span>
                </div>
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-positive"><?php esc_html_e('Positive', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Qualified, interested leads', 'lead-dashboard-for-forminator'); ?></span>
                </div>
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-negative"><?php esc_html_e('Negative', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Unqualified or uninterested leads', 'lead-dashboard-for-forminator'); ?></span>
                </div>
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-follow_up"><?php esc_html_e('Follow Up', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Requires follow-up action', 'lead-dashboard-for-forminator'); ?></span>
                </div>
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-converted"><?php esc_html_e('Converted', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Lead converted to customer', 'lead-dashboard-for-forminator'); ?></span>
                </div>
                <div class="fld-status-item">
                    <span class="fld-status-badge fld-status-closed"><?php esc_html_e('Closed', 'lead-dashboard-for-forminator'); ?></span>
                    <span class="fld-status-desc"><?php esc_html_e('Lead closed/archived', 'lead-dashboard-for-forminator'); ?></span>
                </div>
            </div>
        </div>

        <!-- Spam Prevention — Brevo SMTP OTP -->
        <div class="fld-settings-section">
            <h2><?php esc_html_e('Spam Prevention — Email OTP', 'lead-dashboard-for-forminator'); ?></h2>
            <p class="description">
                <?php esc_html_e('Require visitors to verify their email via a one-time code sent through Brevo SMTP before a form submission becomes a lead.', 'lead-dashboard-for-forminator'); ?>
            </p>

            <h3 style="margin-top:16px;"><?php esc_html_e('Brevo SMTP Settings', 'lead-dashboard-for-forminator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fld_smtp_host"><?php esc_html_e('SMTP Host', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="fld_smtp_host" name="fld_smtp_host"
                               value="<?php echo esc_attr($smtp_host); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_smtp_port"><?php esc_html_e('SMTP Port', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="fld_smtp_port" name="fld_smtp_port"
                               value="<?php echo esc_attr($smtp_port); ?>" min="1" max="65535" style="width:100px;">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_smtp_encryption"><?php esc_html_e('Encryption', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <select id="fld_smtp_encryption" name="fld_smtp_encryption">
                            <option value="tls"  <?php selected($smtp_encryption, 'tls');  ?>>TLS (STARTTLS — Port 587)</option>
                            <option value="ssl"  <?php selected($smtp_encryption, 'ssl');  ?>>SSL — Port 465</option>
                            <option value=""     <?php selected($smtp_encryption, '');     ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_smtp_username"><?php esc_html_e('SMTP Username', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="fld_smtp_username" name="fld_smtp_username"
                               value="<?php echo esc_attr($smtp_username); ?>" class="regular-text"
                               autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_smtp_password"><?php esc_html_e('SMTP Password', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="fld_smtp_password" name="fld_smtp_password"
                               value="" placeholder="<?php esc_attr_e('Leave blank to keep current password', 'lead-dashboard-for-forminator'); ?>"
                               class="regular-text" autocomplete="new-password">
                        <p class="description"><?php esc_html_e('Leave blank to keep the saved password. Enter a new value only if you want to change it.', 'lead-dashboard-for-forminator'); ?></p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top:20px;"><?php esc_html_e('Sender Identity', 'lead-dashboard-for-forminator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fld_brevo_sender_name"><?php esc_html_e('From Name', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="fld_brevo_sender_name" name="fld_brevo_sender_name"
                               value="<?php echo esc_attr($brevo_sender_name); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fld_brevo_sender_email"><?php esc_html_e('From Email', 'lead-dashboard-for-forminator'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="fld_brevo_sender_email" name="fld_brevo_sender_email"
                               value="<?php echo esc_attr($brevo_sender_email); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Must match a verified sender in your Brevo account.', 'lead-dashboard-for-forminator'); ?></p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top:20px;"><?php esc_html_e('Enable OTP for Forms', 'lead-dashboard-for-forminator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Protected Forms', 'lead-dashboard-for-forminator'); ?></th>
                    <td>
                        <?php if (empty($all_forms)): ?>
                            <p class="description"><?php esc_html_e('No Forminator forms found.', 'lead-dashboard-for-forminator'); ?></p>
                        <?php else: ?>
                            <?php foreach ($all_forms as $form): ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox"
                                           name="fld_otp_enabled_forms[]"
                                           value="<?php echo esc_attr($form['id']); ?>"
                                           <?php checked(in_array(intval($form['id']), $otp_enabled_forms, true)); ?>>
                                    <?php echo esc_html($form['name']); ?>
                                    <span style="color:#999;font-size:12px;">(ID: <?php echo esc_html($form['id']); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            <p class="description" style="margin-top:8px;">
                                <?php esc_html_e('Checked forms require email verification before submission is accepted as a lead.', 'lead-dashboard-for-forminator'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="fld_save_settings" class="button button-primary button-large"
                   value="<?php esc_attr_e('Save Settings', 'lead-dashboard-for-forminator'); ?>">
        </p>
    </form>

    <!-- ============================================================
         Sales Admin User Management — visible to administrators only
         ============================================================ -->
    <div class="fld-settings-section fld-user-management">
        <h2><?php esc_html_e('Sales Admin Users', 'lead-dashboard-for-forminator'); ?></h2>
        <p class="description">
            <?php
            printf(
                /* translators: 1: opening <strong> tag, 2: closing </strong> tag */
                esc_html__( 'Users with the %1$sSales Admin%2$s role can log in and access the Lead Dashboard. They can view all leads and add feedback. Only Administrators can access Settings.', 'lead-dashboard-for-forminator' ),
                '<strong>',
                '</strong>'
            );
            ?>
        </p>

        <!-- Current Sales Admins -->
        <h3><?php esc_html_e('Current Sales Admins', 'lead-dashboard-for-forminator'); ?></h3>
        <table class="wp-list-table widefat fixed striped" id="fld-sales-admin-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Email', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Action', 'lead-dashboard-for-forminator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales_admins)): ?>
                    <tr id="fld-no-sales-admins">
                        <td colspan="3"><?php esc_html_e('No Sales Admin users yet.', 'lead-dashboard-for-forminator'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales_admins as $sa_user): ?>
                        <tr id="fld-sa-row-<?php echo esc_attr($sa_user->ID); ?>">
                            <td><?php echo esc_html($sa_user->display_name); ?></td>
                            <td><?php echo esc_html($sa_user->user_email); ?></td>
                            <td>
                                <button class="button fld-remove-sales-admin"
                                        data-id="<?php echo esc_attr($sa_user->ID); ?>"
                                        data-name="<?php echo esc_attr($sa_user->display_name); ?>">
                                    <?php esc_html_e('Remove', 'lead-dashboard-for-forminator'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Add Sales Admin -->
        <h3 style="margin-top:24px;"><?php esc_html_e('Add Sales Admin', 'lead-dashboard-for-forminator'); ?></h3>
        <p class="description"><?php esc_html_e('Assign the Sales Admin role to any existing WordPress user (except administrators).', 'lead-dashboard-for-forminator'); ?></p>

        <div class="fld-add-sales-admin-form">
            <select id="fld-assign-user-select" style="min-width:280px;">
                <option value=""><?php esc_html_e('— Select a user —', 'lead-dashboard-for-forminator'); ?></option>
            </select>
            <button id="fld-assign-sales-admin" class="button button-primary">
                <?php esc_html_e('Add as Sales Admin', 'lead-dashboard-for-forminator'); ?>
            </button>
            <span id="fld-assign-status" style="margin-left:12px;"></span>
        </div>
    </div>

    <!-- Database Tools -->
    <div class="fld-settings-section fld-danger-zone">
        <h2><?php esc_html_e('Database Tools', 'lead-dashboard-for-forminator'); ?></h2>
        <p class="description"><?php esc_html_e('Use these tools with caution.', 'lead-dashboard-for-forminator'); ?></p>

        <div class="fld-tools">
            <button id="fld-clear-activity" class="button">
                <?php esc_html_e('Clear Activity Log', 'lead-dashboard-for-forminator'); ?>
            </button>
            <button id="fld-reset-statuses" class="button">
                <?php esc_html_e('Reset All Statuses', 'lead-dashboard-for-forminator'); ?>
            </button>
            <span id="fld-db-tool-status" style="margin-left:12px;"></span>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    // Load assignable users on page ready
    $(document).ready(function() {
        loadAssignableUsers();

        // Assign button
        $('#fld-assign-sales-admin').on('click', function() {
            var userId = $('#fld-assign-user-select').val();
            if (!userId) {
                setStatus('warning', '<?php echo esc_js( __( 'Please select a user.', 'lead-dashboard-for-forminator' ) ); ?>');
                return;
            }
            assignSalesAdmin(userId);
        });

        // Remove buttons (delegated for dynamically added rows)
        $(document).on('click', '.fld-remove-sales-admin', function() {
            var userId = $(this).data('id');
            var name   = $(this).data('name');
            if (!confirm('<?php echo esc_js(__('Remove Sales Admin role from', 'lead-dashboard-for-forminator')); ?> ' + name + '?')) {
                return;
            }
            removeSalesAdmin(userId);
        });

        // Database Tools — Clear Activity Log
        $('#fld-clear-activity').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Permanently delete the entire activity log? This cannot be undone.', 'lead-dashboard-for-forminator')); ?>')) {
                return;
            }
            runDbTool($(this), 'fld_clear_activity_log');
        });

        // Database Tools — Reset All Statuses
        $('#fld-reset-statuses').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Reset every lead back to "new"? Assignments and statuses will be cleared. This cannot be undone.', 'lead-dashboard-for-forminator')); ?>')) {
                return;
            }
            runDbTool($(this), 'fld_reset_statuses');
        });
    });

    function runDbTool($btn, action) {
        var original = $btn.text();
        var colors   = { success: '#22c55e', error: '#ef4444' };
        var $status  = $('#fld-db-tool-status');
        $btn.prop('disabled', true).text(fld_ajax.strings.loading);
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: { action: action, nonce: fld_ajax.nonce },
            success: function(response) {
                var ok = response.success;
                $status.text(ok ? response.data.message : (response.data || fld_ajax.strings.error))
                       .css('color', ok ? colors.success : colors.error);
            },
            error: function() { $status.text(fld_ajax.strings.error).css('color', colors.error); },
            complete: function() { $btn.prop('disabled', false).text(original); }
        });
    }

    function loadAssignableUsers() {
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fld_get_assignable_users', nonce: fld_ajax.nonce },
            success: function(response) {
                if (!response.success) return;
                var select = $('#fld-assign-user-select');
                select.find('option:not(:first)').remove();
                $.each(response.data, function(i, user) {
                    if (!user.is_sales_admin) {
                        select.append($('<option>', { value: user.id, text: user.name + ' (' + user.email + ')' }));
                    }
                });
            }
        });
    }

    function assignSalesAdmin(userId) {
        setStatus('info', '<?php echo esc_js(__('Saving…', 'lead-dashboard-for-forminator')); ?>');
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fld_assign_sales_admin', nonce: fld_ajax.nonce, user_id: userId },
            success: function(response) {
                if (response.success) {
                    setStatus('success', response.data.message);
                    addRowToTable(userId);
                    loadAssignableUsers();
                } else {
                    setStatus('error', response.data);
                }
            },
            error: function() { setStatus('error', fld_ajax.strings.error); }
        });
    }

    function removeSalesAdmin(userId) {
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fld_remove_sales_admin', nonce: fld_ajax.nonce, user_id: userId },
            success: function(response) {
                if (response.success) {
                    $('#fld-sa-row-' + userId).remove();
                    // Show "no users" row if table is now empty
                    if ($('#fld-sales-admin-table tbody tr').length === 0) {
                        $('#fld-sales-admin-table tbody').append(
                            '<tr id="fld-no-sales-admins"><td colspan="3"><?php echo esc_js(__('No Sales Admin users yet.', 'lead-dashboard-for-forminator')); ?></td></tr>'
                        );
                    }
                    setStatus('success', response.data.message);
                    loadAssignableUsers();
                } else {
                    setStatus('error', response.data);
                }
            },
            error: function() { setStatus('error', fld_ajax.strings.error); }
        });
    }

    function addRowToTable(userId) {
        // Get user info from select option
        var option = $('#fld-assign-user-select option[value="' + userId + '"]');
        var text   = option.text(); // "Name (email)"
        var parts  = text.match(/^(.*)\s\(([^)]+)\)$/);
        var name   = parts ? parts[1] : text;
        var email  = parts ? parts[2] : '';

        $('#fld-no-sales-admins').remove();
        $('#fld-sales-admin-table tbody').append(
            '<tr id="fld-sa-row-' + userId + '">' +
            '<td>' + $('<span>').text(name).html() + '</td>' +
            '<td>' + $('<span>').text(email).html() + '</td>' +
            '<td><button class="button fld-remove-sales-admin" data-id="' + userId + '" data-name="' + $('<span>').text(name).html() + '"><?php echo esc_js(__('Remove', 'lead-dashboard-for-forminator')); ?></button></td>' +
            '</tr>'
        );
    }

    function setStatus(type, message) {
        var colors = { success: '#22c55e', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
        $('#fld-assign-status').text(message).css('color', colors[type] || '#000');
    }

})(jQuery);
</script>
