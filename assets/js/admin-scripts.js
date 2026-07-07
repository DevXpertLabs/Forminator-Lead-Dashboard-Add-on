/**
 * Forminator Lead Dashboard - Admin Scripts
 */

(function($) {
    'use strict';

    // Global state
    let currentLeadId = null;
    let currentPage = 1;
    let leadsChart = null;
    let statusChart = null;

    // Initialize on document ready
    $(document).ready(function() {
        // Check which page we're on
        if ($('.fld-dashboard').length) {
            initDashboard();
        }
        
        if ($('.fld-leads-page').length) {
            initLeadsPage();
        }

        // Initialize common handlers
        initModalHandlers();
    });

    /**
     * Initialize Dashboard Page
     */
    function initDashboard() {
        loadDashboardStats();

        // Date range change
        $('#fld-date-range').on('change', function() {
            loadDashboardStats();
        });

        // Refresh button
        $('#fld-refresh-stats').on('click', function() {
            loadDashboardStats();
        });

        // Load recent leads
        loadRecentLeads();
    }

    /**
     * Load Dashboard Statistics
     */
    function loadDashboardStats() {
        const dateRange = $('#fld-date-range').val() || 30;

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_get_dashboard_stats',
                nonce: fld_ajax.nonce,
                date_range: dateRange
            },
            success: function(response) {
                if (response.success) {
                    updateStatsCards(response.data);
                    updateCharts(response.data);
                    updateFormsTable(response.data.leads_by_form);
                }
            },
            error: function() {
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Update Stats Cards
     */
    function updateStatsCards(data) {
        $('#stat-total-leads').text(data.total_leads || 0);
        $('#stat-new-leads').text(data.new_leads || 0);
        $('#stat-positive-leads').text(data.positive_leads || 0);
        $('#stat-negative-leads').text(data.negative_leads || 0);
        $('#stat-conversion-rate').text((data.conversion_rate || 0) + '%');
    }

    /**
     * Update Charts
     */
    function updateCharts(data) {
        // Leads over time chart
        const leadsCtx = document.getElementById('fld-leads-chart');
        if (leadsCtx) {
            if (leadsChart) {
                leadsChart.destroy();
            }

            const labels = data.leads_by_day.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            const values = data.leads_by_day.map(item => parseInt(item.count));

            leadsChart = new Chart(leadsCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Leads',
                        data: values,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Status pie chart
        const statusCtx = document.getElementById('fld-status-chart');
        if (statusCtx) {
            if (statusChart) {
                statusChart.destroy();
            }

            const statusLabels = [];
            const statusValues = [];
            const statusColors = {
                'new': '#fbbf24',
                'positive': '#22c55e',
                'negative': '#ef4444',
                'follow_up': '#3b82f6',
                'converted': '#a855f7',
                'closed': '#64748b'
            };
            const colors = [];

            for (const [status, info] of Object.entries(data.status_counts || {})) {
                statusLabels.push(status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' '));
                statusValues.push(parseInt(info.count));
                colors.push(statusColors[status] || '#64748b');
            }

            // Add "New" if not present
            if (!data.status_counts || !data.status_counts.new) {
                const newCount = data.new_leads || 0;
                if (newCount > 0) {
                    statusLabels.unshift('New');
                    statusValues.unshift(newCount);
                    colors.unshift('#fbbf24');
                }
            }

            statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    /**
     * Update Forms Table
     */
    function updateFormsTable(forms) {
        const tbody = $('#fld-forms-table tbody');
        tbody.empty();

        if (!forms || forms.length === 0) {
            tbody.html('<tr><td colspan="3" class="fld-empty-state">No forms found</td></tr>');
            return;
        }

        forms.forEach(function(form) {
            tbody.append(`
                <tr>
                    <td>${escapeHtml(form.form_name)}</td>
                    <td><strong>${form.count}</strong></td>
                    <td>
                        <a href="admin.php?page=lead-dashboard-leads&form_id=${form.form_id}" class="button button-small">
                            View Leads
                        </a>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Load Recent Leads
     */
    function loadRecentLeads() {
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_get_leads',
                nonce: fld_ajax.nonce,
                page: 1,
                per_page: 10
            },
            success: function(response) {
                if (response.success) {
                    renderRecentLeads(response.data.leads);
                }
            }
        });
    }

    /**
     * Render Recent Leads Table
     */
    function renderRecentLeads(leads) {
        const tbody = $('#fld-recent-leads tbody');
        tbody.empty();

        if (!leads || leads.length === 0) {
            tbody.html('<tr><td colspan="6" class="fld-empty-state">No leads yet</td></tr>');
            return;
        }

        leads.forEach(function(lead) {
            const date = new Date(lead.date_created);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            tbody.append(`
                <tr data-entry-id="${lead.entry_id}">
                    <td>#${lead.entry_id}</td>
                    <td>${formattedDate}</td>
                    <td>Form #${lead.form_id}</td>
                    <td><span class="fld-status-badge fld-status-${lead.status}">${formatStatus(lead.status)}</span></td>
                    <td>${lead.feedback_count} feedback(s)</td>
                    <td>
                        <button class="fld-action-btn view fld-view-lead" data-id="${lead.entry_id}">View</button>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Initialize Leads Page
     */
    function initLeadsPage() {
        loadLeads();

        // Apply filters
        $('#fld-apply-filters').on('click', function() {
            currentPage = 1;
            loadLeads();
        });

        // Reset filters
        $('#fld-reset-filters').on('click', function() {
            $('#fld-filter-form').val('');
            $('#fld-filter-status').val('');
            $('#fld-filter-date-from').val('');
            $('#fld-filter-date-to').val('');
            $('#fld-filter-search').val('');
            currentPage = 1;
            loadLeads();
        });

        // Search on enter
        $('#fld-filter-search').on('keypress', function(e) {
            if (e.which === 13) {
                currentPage = 1;
                loadLeads();
            }
        });

        // Export CSV
        $('#fld-export-leads').on('click', function() {
            exportLeads();
        });

        // Select all checkbox
        $('#fld-select-all').on('change', function() {
            $('.fld-lead-checkbox').prop('checked', $(this).is(':checked'));
            updateSelectedCount();
        });

        // Individual checkbox
        $(document).on('change', '.fld-lead-checkbox', function() {
            updateSelectedCount();
        });

        // Bulk action
        $('#fld-apply-bulk').on('click', function() {
            applyBulkAction();
        });

        // Check for URL params
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('form_id')) {
            $('#fld-filter-form').val(urlParams.get('form_id'));
        }
    }

    /**
     * Load Leads
     */
    function loadLeads() {
        showLoading(true);

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_get_leads',
                nonce: fld_ajax.nonce,
                form_id: $('#fld-filter-form').val(),
                status: $('#fld-filter-status').val(),
                date_from: $('#fld-filter-date-from').val(),
                date_to: $('#fld-filter-date-to').val(),
                search: $('#fld-filter-search').val(),
                page: currentPage,
                per_page: 20
            },
            success: function(response) {
                showLoading(false);
                if (response.success) {
                    renderLeadsTable(response.data.leads);
                    renderPagination(response.data);
                }
            },
            error: function() {
                showLoading(false);
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Render Leads Table
     */
    function renderLeadsTable(leads) {
        const tbody = $('#fld-leads-tbody');
        tbody.empty();

        if (!leads || leads.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="8" class="fld-empty-state">
                        <span class="dashicons dashicons-id"></span>
                        <p>No leads found</p>
                    </td>
                </tr>
            `);
            return;
        }

        leads.forEach(function(lead) {
            const date = new Date(lead.date_created);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            // Extract contact info from meta
            let contactInfo = '';
            if (lead.meta) {
                const email = findMetaValue(lead.meta, ['email', 'email-1']);
                const phone = findMetaValue(lead.meta, ['phone', 'phone-1']);
                const name = findMetaValue(lead.meta, ['name', 'name-1', 'text-1']);
                
                if (name) contactInfo += `<strong>${escapeHtml(name)}</strong><br>`;
                if (email) contactInfo += `${escapeHtml(email)}<br>`;
                if (phone) contactInfo += `${escapeHtml(phone)}`;
            }

            tbody.append(`
                <tr data-entry-id="${lead.entry_id}">
                    <td class="fld-col-check">
                        <input type="checkbox" class="fld-lead-checkbox" value="${lead.entry_id}">
                    </td>
                    <td class="fld-col-id">#${lead.entry_id}</td>
                    <td class="fld-col-date">${formattedDate}</td>
                    <td class="fld-col-form">Form #${lead.form_id}</td>
                    <td class="fld-col-contact">${contactInfo || 'N/A'}</td>
                    <td class="fld-col-status">
                        <span class="fld-status-badge fld-status-${lead.status}">${formatStatus(lead.status)}</span>
                    </td>
                    <td class="fld-col-feedback">
                        ${lead.feedback_count > 0 ? `<span class="dashicons dashicons-testimonial"></span> ${lead.feedback_count}` : '-'}
                    </td>
                    <td class="fld-col-actions">
                        <button class="fld-action-btn view fld-view-lead" data-id="${lead.entry_id}">View</button>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Find meta value by possible keys
     */
    function findMetaValue(meta, keys) {
        for (const key of keys) {
            if (meta[key]) {
                return typeof meta[key] === 'object' ? JSON.stringify(meta[key]) : meta[key];
            }
        }
        return null;
    }

    /**
     * Render Pagination
     */
    function renderPagination(data) {
        const container = $('#fld-pagination');
        container.empty();

        if (data.pages <= 1) return;

        // Previous button
        container.append(`
            <button ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">
                &laquo; Prev
            </button>
        `);

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(data.pages, currentPage + 2);

        if (startPage > 1) {
            container.append(`<button data-page="1">1</button>`);
            if (startPage > 2) {
                container.append(`<span>...</span>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            container.append(`
                <button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>
            `);
        }

        if (endPage < data.pages) {
            if (endPage < data.pages - 1) {
                container.append(`<span>...</span>`);
            }
            container.append(`<button data-page="${data.pages}">${data.pages}</button>`);
        }

        // Next button
        container.append(`
            <button ${currentPage === data.pages ? 'disabled' : ''} data-page="${currentPage + 1}">
                Next &raquo;
            </button>
        `);

        // Click handlers
        container.find('button').on('click', function() {
            if (!$(this).is(':disabled')) {
                currentPage = parseInt($(this).data('page'));
                loadLeads();
            }
        });
    }

    /**
     * Update Selected Count
     */
    function updateSelectedCount() {
        const count = $('.fld-lead-checkbox:checked').length;
        $('#fld-selected-count').text(count + ' selected');
    }

    /**
     * Apply Bulk Action
     */
    function applyBulkAction() {
        const action = $('#fld-bulk-action').val();
        const selected = $('.fld-lead-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (!action || selected.length === 0) {
            showNotice('warning', 'Please select leads and an action');
            return;
        }

        if (!confirm('Apply this action to ' + selected.length + ' leads?')) {
            return;
        }

        const status = action.replace('status_', '');

        // Update each lead
        let completed = 0;
        selected.forEach(function(entryId) {
            $.ajax({
                url: fld_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fld_update_lead_status',
                    nonce: fld_ajax.nonce,
                    entry_id: entryId,
                    status: status
                },
                success: function() {
                    completed++;
                    if (completed === selected.length) {
                        showNotice('success', 'Status updated for ' + selected.length + ' leads');
                        loadLeads();
                    }
                }
            });
        });
    }

    /**
     * Export Leads to CSV
     */
    function exportLeads() {
        showLoading(true);

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_export_leads',
                nonce: fld_ajax.nonce,
                form_id: $('#fld-filter-form').val(),
                status: $('#fld-filter-status').val()
            },
            success: function(response) {
                showLoading(false);
                if (response.success && response.data.csv) {
                    downloadCSV(response.data.csv, 'leads-export.csv');
                } else {
                    showNotice('error', 'No data to export');
                }
            },
            error: function() {
                showLoading(false);
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Download CSV
     */
    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Initialize Modal Handlers
     */
    function initModalHandlers() {
        // View lead button
        $(document).on('click', '.fld-view-lead', function() {
            const entryId = $(this).data('id');
            openLeadModal(entryId);
        });

        // Close modal
        $(document).on('click', '.fld-modal-close', function() {
            closeModal();
        });

        // Close on background click
        $(document).on('click', '.fld-modal', function(e) {
            if ($(e.target).hasClass('fld-modal')) {
                closeModal();
            }
        });

        // Close on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Save status
        $(document).on('click', '#fld-save-status, #fld-update-status', function() {
            saveLeadStatus();
        });

        // Submit feedback
        $(document).on('click', '#fld-submit-feedback', function() {
            submitFeedback();
        });

        // Delete feedback
        $(document).on('click', '.fld-feedback-delete', function() {
            const feedbackId = $(this).data('id');
            deleteFeedback(feedbackId);
        });
    }

    /**
     * Open Lead Modal
     */
    function openLeadModal(entryId) {
        currentLeadId = entryId;
        showLoading(true);

        // Get lead details by exact entry ID
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_get_lead',
                nonce: fld_ajax.nonce,
                entry_id: entryId
            },
            success: function(response) {
                showLoading(false);
                if (response.success) {
                    renderLeadModal(response.data);
                    loadFeedback(entryId);
                    $('#fld-lead-modal').show();
                } else {
                    showNotice('error', fld_ajax.strings.error);
                }
            },
            error: function() {
                showLoading(false);
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Render Lead Modal Content
     */
    function renderLeadModal(lead) {
        $('#fld-modal-lead-id').text(lead.entry_id);

        // Render meta data
        const metaContainer = $('#fld-lead-meta');
        metaContainer.empty();

        if (lead.meta) {
            for (const [key, value] of Object.entries(lead.meta)) {
                const displayValue = typeof value === 'object' ? JSON.stringify(value) : value;
                metaContainer.append(`
                    <div class="fld-meta-item">
                        <span class="fld-meta-label">${formatFieldName(key)}</span>
                        <span class="fld-meta-value">${escapeHtml(displayValue)}</span>
                    </div>
                `);
            }
        }

        // Set current status
        $(`input[name="fld-lead-status"][value="${lead.status}"]`).prop('checked', true);
    }

    /**
     * Load Feedback
     */
    function loadFeedback(entryId) {
        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_get_feedback',
                nonce: fld_ajax.nonce,
                entry_id: entryId
            },
            success: function(response) {
                if (response.success) {
                    renderFeedback(response.data);
                }
            }
        });
    }

    /**
     * Render Feedback List
     */
    function renderFeedback(feedbackList) {
        const container = $('#fld-feedback-list');
        container.empty();

        if (!feedbackList || feedbackList.length === 0) {
            container.html('<p class="fld-empty-state">No feedback yet</p>');
            return;
        }

        const ratingIcons = {
            'positive': '👍',
            'neutral': '😐',
            'negative': '👎'
        };

        feedbackList.forEach(function(feedback) {
            const date = new Date(feedback.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            container.append(`
                <div class="fld-feedback-item">
                    <div class="fld-feedback-rating">${ratingIcons[feedback.rating] || '😐'}</div>
                    <div class="fld-feedback-content">
                        <p class="fld-feedback-text">${escapeHtml(feedback.feedback)}</p>
                        <div class="fld-feedback-meta">
                            <span>By ${escapeHtml(feedback.user_name || 'Unknown')}</span>
                            <span>${formattedDate}</span>
                            <button class="fld-feedback-delete" data-id="${feedback.id}">Delete</button>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    /**
     * Save Lead Status
     */
    function saveLeadStatus() {
        const status = $('input[name="fld-lead-status"]:checked, #fld-lead-status').val();

        if (!status) {
            showNotice('warning', 'Please select a status');
            return;
        }

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_update_lead_status',
                nonce: fld_ajax.nonce,
                entry_id: currentLeadId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Status updated');
                    
                    // Update table row if exists
                    const row = $(`tr[data-entry-id="${currentLeadId}"]`);
                    if (row.length) {
                        row.find('.fld-status-badge')
                            .removeClass()
                            .addClass('fld-status-badge fld-status-' + status)
                            .text(formatStatus(status));
                    }
                } else {
                    showNotice('error', response.data || fld_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Submit Feedback
     */
    function submitFeedback() {
        const feedback = $('#fld-new-feedback, #fld-feedback-text').val();
        const rating = $('input[name="fld-new-rating"]:checked, input[name="fld-feedback-rating"]:checked').val();

        if (!feedback || !feedback.trim()) {
            showNotice('warning', 'Please enter feedback');
            return;
        }

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_add_feedback',
                nonce: fld_ajax.nonce,
                entry_id: currentLeadId,
                feedback: feedback,
                rating: rating || 'neutral'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Feedback added');
                    $('#fld-new-feedback, #fld-feedback-text').val('');
                    loadFeedback(currentLeadId);
                } else {
                    showNotice('error', response.data || fld_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Delete Feedback
     */
    function deleteFeedback(feedbackId) {
        if (!confirm(fld_ajax.strings.confirm_delete)) {
            return;
        }

        $.ajax({
            url: fld_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_delete_feedback',
                nonce: fld_ajax.nonce,
                feedback_id: feedbackId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Feedback deleted');
                    loadFeedback(currentLeadId);
                } else {
                    showNotice('error', response.data || fld_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', fld_ajax.strings.error);
            }
        });
    }

    /**
     * Close Modal
     */
    function closeModal() {
        $('#fld-lead-modal').hide();
        currentLeadId = null;
    }

    /**
     * Show/Hide Loading
     */
    function showLoading(show) {
        if (show) {
            $('#fld-loading').show();
        } else {
            $('#fld-loading').hide();
        }
    }

    /**
     * Show Notice
     */
    function showNotice(type, message) {
        // Remove existing notices
        $('.fld-notice').remove();

        const notice = $(`
            <div class="fld-notice fld-notice-${type}">
                <p>${message}</p>
                <button class="fld-notice-close">&times;</button>
            </div>
        `);

        $('body').append(notice);

        // Auto-hide after 3 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);

        // Close button
        notice.find('.fld-notice-close').on('click', function() {
            notice.remove();
        });
    }

    /**
     * Format Status
     */
    function formatStatus(status) {
        const statuses = {
            'new': 'New',
            'positive': 'Positive',
            'negative': 'Negative',
            'follow_up': 'Follow Up',
            'converted': 'Converted',
            'closed': 'Closed'
        };
        return statuses[status] || status;
    }

    /**
     * Format Field Name
     */
    function formatFieldName(key) {
        return key
            .replace(/[-_]/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);

// Add notice styles dynamically
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .fld-notice {
            position: fixed;
            top: 50px;
            right: 20px;
            padding: 15px 40px 15px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 100002;
            animation: slideIn 0.3s ease;
        }
        .fld-notice-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .fld-notice-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .fld-notice-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .fld-notice p { margin: 0; }
        .fld-notice-close {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.5;
        }
        .fld-notice-close:hover { opacity: 1; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
})();
