<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables here live in the scope of the render_dashboard_page() method that
// include()s this template — not the global scope — so the global-prefix rule
// does not apply.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$forms = DXLEDA_Leads::get_forms();
$statuses = DXLEDA_Leads::get_statuses();
?>

<div class="wrap fld-dashboard">
    <div class="fld-hero">
        <div class="fld-hero-brand">
            <span class="fld-logo-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
            </span>
            <div>
                <h1 class="fld-page-title"><?php esc_html_e('Lead Dashboard', 'devxpert-lead-dashboard-for-forminator'); ?></h1>
                <p class="fld-hero-sub"><?php esc_html_e('Track, qualify, and convert every form submission.', 'devxpert-lead-dashboard-for-forminator'); ?></p>
            </div>
        </div>
        <div class="fld-hero-controls">
            <label for="fld-date-range" class="screen-reader-text"><?php esc_html_e('Date Range:', 'devxpert-lead-dashboard-for-forminator'); ?></label>
            <select id="fld-date-range">
                <option value="7"><?php esc_html_e('Last 7 Days', 'devxpert-lead-dashboard-for-forminator'); ?></option>
                <option value="30" selected><?php esc_html_e('Last 30 Days', 'devxpert-lead-dashboard-for-forminator'); ?></option>
                <option value="90"><?php esc_html_e('Last 90 Days', 'devxpert-lead-dashboard-for-forminator'); ?></option>
                <option value="365"><?php esc_html_e('Last Year', 'devxpert-lead-dashboard-for-forminator'); ?></option>
            </select>
            <button id="fld-refresh-stats" class="button">
                <?php esc_html_e('Refresh', 'devxpert-lead-dashboard-for-forminator'); ?>
            </button>
        </div>
    </div>

    <!-- Stat cards: each shows the count and, underneath, a meter with that
         status's share of total leads in the range. -->
    <div class="fld-tally" role="group" aria-label="<?php esc_attr_e('Lead totals', 'devxpert-lead-dashboard-for-forminator'); ?>">
        <div class="fld-tally-col">
            <div class="fld-tally-top">
                <span class="fld-tally-label"><?php esc_html_e('Total Leads', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                <span class="fld-tally-ico fld-ico-total" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
            </div>
            <span class="fld-tally-num" id="stat-total-leads">0</span>
            <div class="fld-tally-foot">
                <span class="fld-tally-meter"><i id="fld-meter-total"></i></span>
                <span class="fld-tally-share" id="fld-share-total">&mdash;</span>
            </div>
        </div>

        <div class="fld-tally-col">
            <div class="fld-tally-top">
                <span class="fld-tally-label"><?php esc_html_e('New Leads', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                <span class="fld-tally-ico fld-ico-new" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                </span>
            </div>
            <span class="fld-tally-num" id="stat-new-leads">0</span>
            <div class="fld-tally-foot">
                <span class="fld-tally-meter fld-meter-new"><i id="fld-meter-new"></i></span>
                <span class="fld-tally-share" id="fld-share-new">&mdash;</span>
            </div>
        </div>

        <div class="fld-tally-col">
            <div class="fld-tally-top">
                <span class="fld-tally-label"><?php esc_html_e('Positive Leads', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                <span class="fld-tally-ico fld-ico-positive" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
                </span>
            </div>
            <span class="fld-tally-num" id="stat-positive-leads">0</span>
            <div class="fld-tally-foot">
                <span class="fld-tally-meter fld-meter-positive"><i id="fld-meter-positive"></i></span>
                <span class="fld-tally-share" id="fld-share-positive">&mdash;</span>
            </div>
        </div>

        <div class="fld-tally-col">
            <div class="fld-tally-top">
                <span class="fld-tally-label"><?php esc_html_e('Negative Leads', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                <span class="fld-tally-ico fld-ico-negative" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3 3.88Z"/></svg>
                </span>
            </div>
            <span class="fld-tally-num" id="stat-negative-leads">0</span>
            <div class="fld-tally-foot">
                <span class="fld-tally-meter fld-meter-negative"><i id="fld-meter-negative"></i></span>
                <span class="fld-tally-share" id="fld-share-negative">&mdash;</span>
            </div>
        </div>

        <div class="fld-tally-col">
            <div class="fld-tally-top">
                <span class="fld-tally-label"><?php esc_html_e('Conversion Rate', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                <span class="fld-tally-ico fld-ico-conversion" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                </span>
            </div>
            <span class="fld-tally-num" id="stat-conversion-rate">0%</span>
            <div class="fld-tally-foot">
                <span class="fld-tally-meter fld-meter-converted"><i id="fld-meter-conversion"></i></span>
                <span class="fld-tally-share" id="fld-share-conversion">&mdash;</span>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="fld-charts-row">
        <div class="fld-chart-card">
            <h3><?php esc_html_e('Leads Over Time', 'devxpert-lead-dashboard-for-forminator'); ?></h3>
            <div class="fld-chart-wrap">
                <canvas id="fld-leads-chart"></canvas>
            </div>
        </div>

        <div class="fld-chart-card">
            <h3><?php esc_html_e('Leads by Status', 'devxpert-lead-dashboard-for-forminator'); ?></h3>
            <div class="fld-chart-wrap">
                <canvas id="fld-status-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Forms Table -->
    <div class="fld-table-card">
        <h3><?php esc_html_e('Top Forms by Leads', 'devxpert-lead-dashboard-for-forminator'); ?></h3>
        <table class="fld-table" id="fld-forms-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Form Name', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Total Leads', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Actions', 'devxpert-lead-dashboard-for-forminator'); ?></th>
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
            <h3><?php esc_html_e('Recent Leads', 'devxpert-lead-dashboard-for-forminator'); ?></h3>
            <a href="<?php echo esc_url( admin_url('admin.php?page=dxleda-leads') ); ?>" class="button">
                <?php esc_html_e('View All', 'devxpert-lead-dashboard-for-forminator'); ?>
            </a>
        </div>
        <table class="fld-table" id="fld-recent-leads">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Date', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Form', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Status', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></th>
                    <th><?php esc_html_e('Actions', 'devxpert-lead-dashboard-for-forminator'); ?></th>
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
            <h2><?php esc_html_e('Lead Details', 'devxpert-lead-dashboard-for-forminator'); ?></h2>
            <button class="fld-modal-close">&times;</button>
        </div>
        <div class="fld-modal-body">
            <div id="fld-lead-details">
                <!-- Populated by JS -->
            </div>

            <!-- Status Update -->
            <div class="fld-lead-status-section">
                <h4><?php esc_html_e('Update Status', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                <select id="fld-lead-status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="fld-update-status" class="button button-primary">
                    <?php esc_html_e('Update Status', 'devxpert-lead-dashboard-for-forminator'); ?>
                </button>
            </div>

            <!-- Feedback Section -->
            <div class="fld-feedback-section">
                <h4><?php esc_html_e('Sales Team Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></h4>
                
                <div id="fld-feedback-list">
                    <!-- Populated by JS -->
                </div>

                <div class="fld-add-feedback">
                    <h5><?php esc_html_e('Add Feedback', 'devxpert-lead-dashboard-for-forminator'); ?></h5>
                    <div class="fld-feedback-rating">
                        <label class="fld-rating-option fld-rating-positive">
                            <input type="radio" name="fld-feedback-rating" value="positive">
                            <span class="fld-rating-ico fld-rating-ico--positive"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Positive', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-neutral">
                            <input type="radio" name="fld-feedback-rating" value="neutral" checked>
                            <span class="fld-rating-ico fld-rating-ico--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>
                            <span><?php esc_html_e('Neutral', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                        <label class="fld-rating-option fld-rating-negative">
                            <input type="radio" name="fld-feedback-rating" value="negative">
                            <span class="fld-rating-ico fld-rating-ico--negative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3 3.88Z"/></svg></span>
                            <span><?php esc_html_e('Negative', 'devxpert-lead-dashboard-for-forminator'); ?></span>
                        </label>
                    </div>
                    <textarea id="fld-feedback-text" placeholder="<?php esc_attr_e('Enter your feedback...', 'devxpert-lead-dashboard-for-forminator'); ?>"></textarea>
                    <button id="fld-submit-feedback" class="button button-primary">
                        <?php esc_html_e('Submit Feedback', 'devxpert-lead-dashboard-for-forminator'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
