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
                        <label>
                            <input type="radio" name="fld-feedback-rating" value="positive"> 
                            👍 <?php esc_html_e('Positive', 'lead-dashboard-for-forminator'); ?>
                        </label>
                        <label>
                            <input type="radio" name="fld-feedback-rating" value="neutral" checked> 
                            😐 <?php esc_html_e('Neutral', 'lead-dashboard-for-forminator'); ?>
                        </label>
                        <label>
                            <input type="radio" name="fld-feedback-rating" value="negative"> 
                            👎 <?php esc_html_e('Negative', 'lead-dashboard-for-forminator'); ?>
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
