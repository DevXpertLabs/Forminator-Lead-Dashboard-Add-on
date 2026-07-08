<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}



$forms = FLD_Leads::get_forms();
$statuses = FLD_Leads::get_statuses();
?>

<div class="wrap fld-dashboard">
    <h1 class="fld-page-title">
        <span class="dashicons dashicons-chart-line"></span>
        <?php esc_html_e('Lead Dashboard', 'lead-dashboard-for-forminator'); ?>
    </h1>

    <!-- Date Range Filter -->
    <div class="fld-filters-bar">
        <div class="fld-date-range">
            <label><?php esc_html_e('Date Range:', 'lead-dashboard-for-forminator'); ?></label>
            <select id="fld-date-range">
                <option value="7"><?php esc_html_e('Last 7 Days', 'lead-dashboard-for-forminator'); ?></option>
                <option value="30" selected><?php esc_html_e('Last 30 Days', 'lead-dashboard-for-forminator'); ?></option>
                <option value="90"><?php esc_html_e('Last 90 Days', 'lead-dashboard-for-forminator'); ?></option>
                <option value="365"><?php esc_html_e('Last Year', 'lead-dashboard-for-forminator'); ?></option>
            </select>
        </div>
        <button id="fld-refresh-stats" class="button">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Refresh', 'lead-dashboard-for-forminator'); ?>
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="fld-stats-grid">
        <div class="fld-stat-card fld-stat-total">
            <div class="fld-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="fld-stat-content">
                <h3 id="stat-total-leads">0</h3>
                <p><?php esc_html_e('Total Leads', 'lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>

        <div class="fld-stat-card fld-stat-new">
            <div class="fld-stat-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="fld-stat-content">
                <h3 id="stat-new-leads">0</h3>
                <p><?php esc_html_e('New Leads', 'lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>

        <div class="fld-stat-card fld-stat-positive">
            <div class="fld-stat-icon">
                <span class="dashicons dashicons-thumbs-up"></span>
            </div>
            <div class="fld-stat-content">
                <h3 id="stat-positive-leads">0</h3>
                <p><?php esc_html_e('Positive Leads', 'lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>

        <div class="fld-stat-card fld-stat-negative">
            <div class="fld-stat-icon">
                <span class="dashicons dashicons-thumbs-down"></span>
            </div>
            <div class="fld-stat-content">
                <h3 id="stat-negative-leads">0</h3>
                <p><?php esc_html_e('Negative Leads', 'lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>

        <div class="fld-stat-card fld-stat-conversion">
            <div class="fld-stat-icon">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="fld-stat-content">
                <h3 id="stat-conversion-rate">0%</h3>
                <p><?php esc_html_e('Conversion Rate', 'lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="fld-charts-row">
        <div class="fld-chart-card">
            <h3><?php esc_html_e('Leads Over Time', 'lead-dashboard-for-forminator'); ?></h3>
            <div class="fld-chart-wrap">
                <canvas id="fld-leads-chart"></canvas>
            </div>
        </div>

        <div class="fld-chart-card">
            <h3><?php esc_html_e('Leads by Status', 'lead-dashboard-for-forminator'); ?></h3>
            <div class="fld-chart-wrap">
                <canvas id="fld-status-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Forms Table -->
    <div class="fld-table-card">
        <h3><?php esc_html_e('Top Forms by Leads', 'lead-dashboard-for-forminator'); ?></h3>
        <table class="fld-table" id="fld-forms-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Form Name', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Total Leads', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Actions', 'lead-dashboard-for-forminator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

    <!-- Recent Leads -->
    <div class="fld-table-card">
        <div class="fld-table-header">
            <h3><?php esc_html_e('Recent Leads', 'lead-dashboard-for-forminator'); ?></h3>
            <a href="<?php echo esc_url( admin_url('admin.php?page=lead-dashboard-leads') ); ?>" class="button">
                <?php esc_html_e('View All', 'lead-dashboard-for-forminator'); ?>
            </a>
        </div>
        <table class="fld-table" id="fld-recent-leads">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Date', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Form', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Status', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Feedback', 'lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Actions', 'lead-dashboard-for-forminator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- Lead Detail Modal -->
<div id="fld-lead-modal" class="fld-modal" style="display: none;">
    <div class="fld-modal-content">
        <div class="fld-modal-header">
            <h2><?php esc_html_e('Lead Details', 'lead-dashboard-for-forminator'); ?></h2>
            <button class="fld-modal-close">&times;</button>
        </div>
        <div class="fld-modal-body">
            <div id="fld-lead-details">
                <!-- Populated by JS -->
            </div>

            <!-- Status Update -->
            <div class="fld-lead-status-section">
                <h4><?php esc_html_e('Update Status', 'lead-dashboard-for-forminator'); ?></h4>
                <select id="fld-lead-status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="fld-update-status" class="button button-primary">
                    <?php esc_html_e('Update Status', 'lead-dashboard-for-forminator'); ?>
                </button>
            </div>

            <!-- Feedback Section -->
            <div class="fld-feedback-section">
                <h4><?php esc_html_e('Sales Team Feedback', 'lead-dashboard-for-forminator'); ?></h4>
                
                <div id="fld-feedback-list">
                    <!-- Populated by JS -->
                </div>

                <div class="fld-add-feedback">
                    <h5><?php esc_html_e('Add Feedback', 'lead-dashboard-for-forminator'); ?></h5>
                    <div class="fld-feedback-rating">
                        <label class="fld-rating-option fld-rating-positive">
                            <input type="radio" name="fld-feedback-rating" value="positive">
                            <span class="fld-rating-ico fld-rating-ico--positive"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Positive', 'lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-neutral">
                            <input type="radio" name="fld-feedback-rating" value="neutral" checked>
                            <span class="fld-rating-ico fld-rating-ico--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>
                            <span><?php esc_html_e('Neutral', 'lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-negative">
                            <input type="radio" name="fld-feedback-rating" value="negative">
                            <span class="fld-rating-ico fld-rating-ico--negative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Negative', 'lead-dashboard-for-forminator'); ?></span>
                        </label>
                    </div>
                    <textarea id="fld-feedback-text" placeholder="<?php esc_attr_e('Enter your feedback...', 'lead-dashboard-for-forminator'); ?>"></textarea>
                    <button id="fld-submit-feedback" class="button button-primary">
                        <?php esc_html_e('Submit Feedback', 'lead-dashboard-for-forminator'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
