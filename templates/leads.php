<?php
/**
 * All Leads Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$forms = DXLEDA_Leads::get_forms();
$statuses = DXLEDA_Leads::get_statuses();
$users = DXLEDA_Roles::get_team_users();
?>

<div class="wrap fld-leads-page">
    <h1 class="fld-page-title">
        <span class="dashicons dashicons-id"></span>
        <?php esc_html_e('All Leads', 'devxpert-lead-dashboard-for-forminator'); ?>
    </h1>

    <!-- Filters -->
    <div class="fld-filters">
        <div class="fld-filter-row">
            <div class="fld-filter-item">
                <label><?php esc_html_e('Form:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
                <select id="fld-filter-form">
                    <option value=""><?php esc_html_e('All Forms', 'devxpert-lead-dashboard-for-forminator'); ?></option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form['id']); ?>">
                            <?php echo esc_html($form['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fld-filter-item">
                <label><?php esc_html_e('Status:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
                <select id="fld-filter-status">
                    <option value=""><?php esc_html_e('All Statuses', 'devxpert-lead-dashboard-for-forminator'); ?></option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fld-filter-item">
                <label><?php esc_html_e('Date From:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
                <input type="date" id="fld-filter-date-from">
            </div>

            <div class="fld-filter-item">
                <label><?php esc_html_e('Date To:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
                <input type="date" id="fld-filter-date-to">
            </div>

            <div class="fld-filter-item">
                <label><?php esc_html_e('Search:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
                <input type="text" id="fld-filter-search" placeholder="<?php esc_attr_e('Search leads...', 'devxpert-lead-dashboard-for-forminator'); ?>">
            </div>

            <div class="fld-filter-actions">
                <button id="fld-apply-filters" class="button button-primary">
                    <?php esc_html_e('Apply Filters', 'devxpert-lead-dashboard-for-forminator'); ?>
                </button>
                <button id="fld-reset-filters" class="button">
                    <?php esc_html_e('Reset', 'devxpert-lead-dashboard-for-forminator'); ?>
                </button>
                <button id="fld-export-leads" class="button">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export CSV', 'devxpert-lead-dashboard-for-forminator'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="fld-bulk-actions">
        <select id="fld-bulk-action">
            <option value=""><?php esc_html_e('Bulk Actions', 'devxpert-lead-dashboard-for-forminator'); ?></option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="status_<?php echo esc_attr($key); ?>">
                    <?php
                    /* translators: %s: lead status label (e.g. New, Positive, Converted) */
                    echo esc_html( sprintf( __( 'Mark as %s', 'devxpert-lead-dashboard-for-forminator' ), $label ) );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button id="fld-apply-bulk" class="button"><?php esc_html_e('Apply', 'devxpert-lead-dashboard-for-forminator'); ?></button>
        <span id="fld-selected-count">0 <?php esc_html_e('selected', 'devxpert-lead-dashboard-for-forminator'); ?></span>
    </div>

    <!-- Leads Table -->
    <div class="fld-table-wrapper">
        <table class="fld-table fld-leads-table" id="fld-leads-table">
            <thead>
                <tr>
                    <th class="fld-col-check">
                        <input type="checkbox" id="fld-select-all">
                    </th>
                    <th class="fld-col-id"><?php esc_html_e('ID', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-date"><?php esc_html_e('Date', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-form"><?php esc_html_e('Form', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-contact"><?php esc_html_e('Contact Info', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-status"><?php esc_html_e('Status', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-feedback"><?php esc_html_e('Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th class="fld-col-actions"><?php esc_html_e('Actions', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                </tr>
            </thead>
            <tbody id="fld-leads-tbody">
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="fld-pagination" id="fld-pagination">
        <!-- Populated by JS -->
    </div>

    <!-- Loading Overlay -->
    <div id="fld-loading" class="fld-loading" style="display: none;">
        <div class="fld-spinner"></div>
        <p><?php esc_html_e('Loading...', 'devxpert-lead-dashboard-for-forminator'); ?></p>
    </div>
</div>

<!-- Lead Detail Modal -->
<div id="fld-lead-modal" class="fld-modal" style="display: none;">
    <div class="fld-modal-content fld-modal-large">
        <div class="fld-modal-header">
            <h2><?php esc_html_e('Lead Details', 'devxpert-lead-dashboard-for-forminator'); ?> #<span id="fld-modal-lead-id"></span></h2>
            <button class="fld-modal-close">&times;</button>
        </div>
        <div class="fld-modal-body">
            <div class="fld-lead-detail-grid">
                <!-- Lead Info -->
                <div class="fld-lead-info">
                    <h4><?php esc_html_e('Submission Details', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                    <div id="fld-lead-meta">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Status & Actions -->
                <div class="fld-lead-actions-panel">
                    <h4><?php esc_html_e('Lead Status', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                    <div class="fld-status-selector">
                        <?php foreach ($statuses as $key => $label): ?>
                            <label class="fld-status-option fld-status-<?php echo esc_attr($key); ?>">
                                <input type="radio" name="fld-lead-status" value="<?php echo esc_attr($key); ?>">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button id="fld-save-status" class="button button-primary button-large">
                        <?php esc_html_e('Save Status', 'devxpert-lead-dashboard-for-forminator'); ?>
                    </button>
                </div>
            </div>

            <!-- Feedback Section -->
            <div class="fld-feedback-panel">
                <h4><?php esc_html_e('Sales Team Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                
                <div id="fld-feedback-list" class="fld-feedback-list">
                    <!-- Populated by JS -->
                </div>

                <div class="fld-add-feedback-form">
                    <h5><?php esc_html_e('Add New Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></h5>
                    <div class="fld-feedback-rating-selector">
                        <label class="fld-rating-option fld-rating-positive">
                            <input type="radio" name="fld-new-rating" value="positive">
                            <span class="fld-rating-ico fld-rating-ico--positive"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Positive', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-neutral">
                            <input type="radio" name="fld-new-rating" value="neutral" checked>
                            <span class="fld-rating-ico fld-rating-ico--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>
                            <span><?php esc_html_e('Neutral', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-negative">
                            <input type="radio" name="fld-new-rating" value="negative">
                            <span class="fld-rating-ico fld-rating-ico--negative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Negative', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                    </div>
                    <textarea id="fld-new-feedback" rows="3" placeholder="<?php esc_attr_e('Enter your feedback about this lead...', 'devxpert-lead-dashboard-for-forminator'); ?>"></textarea>
                    <button id="fld-submit-feedback" class="button button-primary">
                        <?php esc_html_e('Add Feedback', 'devxpert-lead-dashboard-for-forminator'); ?>
                    </button>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="fld-activity-panel">
                <h4><?php esc_html_e('Activity Log', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                <div id="fld-activity-log">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>
</div>
