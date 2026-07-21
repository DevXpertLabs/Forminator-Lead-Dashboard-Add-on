/**
 * Forminator Lead Dashboard - Admin Scripts
 */

(function($) {
    'use strict';

    // Global state
    let currentLeadId = null;
    // Entry IDs are only unique within one form plugin, so the open lead is
    // always tracked as the pair (id, source).
    let currentLeadSource = null;
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
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_dashboard_stats',
                nonce: dxleda_ajax.nonce,
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
                showNotice('error', dxleda_ajax.strings.error);
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
                    <td>
                        ${escapeHtml(form.form_name)}
                        <span class="fld-source-badge fld-source-${escapeHtml(form.source)}">${escapeHtml(form.source_label || form.source)}</span>
                    </td>
                    <td><strong>${form.count}</strong></td>
                    <td>
                        <a href="admin.php?page=dxleda-leads&form_id=${form.form_id}&source=${encodeURIComponent(form.source)}" class="button button-small">
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
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_leads',
                nonce: dxleda_ajax.nonce,
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
                <tr data-entry-id="${lead.entry_id}" data-source="${escapeHtml(lead.source)}">
                    <td>#${lead.entry_id}</td>
                    <td>${formattedDate}</td>
                    <td>
                        ${escapeHtml(lead.form_name || ('Form #' + lead.form_id))}
                        <span class="fld-source-badge fld-source-${escapeHtml(lead.source)}">${escapeHtml(lead.source_label || lead.source)}</span>
                    </td>
                    <td><span class="fld-status-badge fld-status-${lead.status}">${formatStatus(lead.status)}</span></td>
                    <td>${lead.feedback_count} feedback(s)</td>
                    <td>
                        <button class="fld-action-btn view fld-view-lead" data-id="${lead.entry_id}" data-source="${escapeHtml(lead.source)}">View</button>
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
            $('#fld-filter-source').val('');
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
        if (urlParams.has('source')) {
            $('#fld-filter-source').val(urlParams.get('source'));
        }
        if (urlParams.has('form_id')) {
            // The form dropdown is keyed "source|form_id"; without a source in
            // the URL, fall back to whichever option matches the ID.
            const wanted = urlParams.get('source')
                ? urlParams.get('source') + '|' + urlParams.get('form_id')
                : null;

            if (wanted && $('#fld-filter-form option[value="' + wanted + '"]').length) {
                $('#fld-filter-form').val(wanted);
            } else {
                $('#fld-filter-form option').each(function() {
                    if (($(this).val() || '').split('|')[1] === urlParams.get('form_id')) {
                        $('#fld-filter-form').val($(this).val());
                        return false;
                    }
                });
            }
        }
    }

    /**
     * Load Leads
     */
    /**
     * Read the current form/source filter selection.
     *
     * The form dropdown encodes "source|form_id" because a form ID alone is
     * ambiguous across plugins. Picking a specific form implies its source and
     * overrides the standalone source dropdown.
     */
    function currentFilterScope() {
        const formValue = $('#fld-filter-form').val() || '';

        if (formValue.indexOf('|') !== -1) {
            const parts = formValue.split('|');
            return { source: parts[0], form_id: parts[1] };
        }

        return { source: $('#fld-filter-source').val() || '', form_id: '' };
    }

    function loadLeads() {
        showLoading(true);

        const scope = currentFilterScope();

        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_leads',
                nonce: dxleda_ajax.nonce,
                form_id: scope.form_id,
                source: scope.source,
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
                showNotice('error', dxleda_ajax.strings.error);
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

            const formLabel = lead.form_name || ('Form #' + lead.form_id);

            tbody.append(`
                <tr data-entry-id="${lead.entry_id}" data-source="${escapeHtml(lead.source)}">
                    <td class="fld-col-check">
                        <input type="checkbox" class="fld-lead-checkbox" value="${escapeHtml(lead.source)}|${lead.entry_id}">
                    </td>
                    <td class="fld-col-id">#${lead.entry_id}</td>
                    <td class="fld-col-date">${formattedDate}</td>
                    <td class="fld-col-form">
                        ${escapeHtml(formLabel)}
                        <span class="fld-source-badge fld-source-${escapeHtml(lead.source)}">${escapeHtml(lead.source_label || lead.source)}</span>
                    </td>
                    <td class="fld-col-contact">${contactInfo || 'N/A'}</td>
                    <td class="fld-col-status">
                        <span class="fld-status-badge fld-status-${lead.status}">${formatStatus(lead.status)}</span>
                    </td>
                    <td class="fld-col-feedback">
                        ${lead.feedback_count > 0 ? `<span class="dashicons dashicons-testimonial"></span> ${lead.feedback_count}` : '-'}
                    </td>
                    <td class="fld-col-actions">
                        <button class="fld-action-btn view fld-view-lead" data-id="${lead.entry_id}" data-source="${escapeHtml(lead.source)}">View</button>
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

        // Update each lead. Checkbox values are "source|entry_id".
        let completed = 0;
        selected.forEach(function(ref) {
            const parts = ref.split('|');

            $.ajax({
                url: dxleda_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dxleda_update_lead_status',
                    nonce: dxleda_ajax.nonce,
                    entry_id: parts[1],
                    source: parts[0],
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
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_export_leads',
                nonce: dxleda_ajax.nonce,
                form_id: currentFilterScope().form_id,
                source: currentFilterScope().source,
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
                showNotice('error', dxleda_ajax.strings.error);
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
            const source = $(this).data('source');
            openLeadModal(entryId, source);
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
    function openLeadModal(entryId, source) {
        currentLeadId = entryId;
        currentLeadSource = source;
        showLoading(true);

        // Get lead details by exact (entry ID, source) pair
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_lead',
                nonce: dxleda_ajax.nonce,
                entry_id: entryId,
                source: source
            },
            success: function(response) {
                showLoading(false);
                if (response.success) {
                    renderLeadModal(response.data);
                    loadFeedback(entryId, source);
                    loadActivity(entryId, source);
                    $('#fld-lead-modal').show();
                } else {
                    showNotice('error', dxleda_ajax.strings.error);
                }
            },
            error: function() {
                showLoading(false);
                showNotice('error', dxleda_ajax.strings.error);
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
    function loadFeedback(entryId, source) {
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_feedback',
                nonce: dxleda_ajax.nonce,
                entry_id: entryId,
                source: source || currentLeadSource
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

        const svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
        const ratingIcons = {
            'positive': '<span class="fld-rating-ico fld-rating-ico--positive">' + svg + '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg></span>',
            'neutral':  '<span class="fld-rating-ico fld-rating-ico--neutral">'  + svg + '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>',
            'negative': '<span class="fld-rating-ico fld-rating-ico--negative">' + svg + '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3 3.88Z"/></svg></span>'
        };

        feedbackList.forEach(function(feedback) {
            const date = new Date(feedback.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            container.append(`
                <div class="fld-feedback-item">
                    <div class="fld-feedback-rating">${ratingIcons[feedback.rating] || ratingIcons.neutral}</div>
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
     * Load the activity log for a lead (only present in the All Leads modal).
     */
    function loadActivity(entryId, source) {
        const container = $('#fld-activity-log');
        if (container.length === 0) {
            return; // no activity panel on this page (e.g. dashboard modal)
        }

        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_get_activity',
                nonce: dxleda_ajax.nonce,
                entry_id: entryId,
                source: source || currentLeadSource
            },
            success: function(response) {
                if (response.success) {
                    renderActivity(response.data);
                }
            }
        });
    }

    /**
     * Render the activity log timeline.
     */
    function renderActivity(list) {
        const container = $('#fld-activity-log');
        container.empty();

        if (!list || list.length === 0) {
            container.html('<p class="fld-empty-state">No activity yet</p>');
            return;
        }

        const labels = {
            'status_change':   'Status changed',
            'feedback_added':  'Feedback added',
            'feedback_deleted':'Feedback removed',
            'assigned':        'Lead assigned'
        };

        list.forEach(function(item) {
            const date = new Date(item.created_at);
            const when = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

            let detail = '';
            try {
                const d = item.details ? JSON.parse(item.details) : null;
                if (d && d.new_status) { detail = ' → ' + d.new_status; }
                else if (d && d.rating) { detail = ' (' + d.rating + ')'; }
            } catch (e) { /* ignore malformed details */ }

            container.append(`
                <div class="fld-activity-item">
                    <span class="fld-activity-action">${escapeHtml((labels[item.action] || item.action) + detail)}</span>
                    <span class="fld-activity-time">${escapeHtml(item.user_name || 'System')} · ${when}</span>
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
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_update_lead_status',
                nonce: dxleda_ajax.nonce,
                entry_id: currentLeadId,
                source: currentLeadSource,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Status updated');

                    // Update table row if exists
                    const row = $(`tr[data-entry-id="${currentLeadId}"][data-source="${currentLeadSource}"]`);
                    if (row.length) {
                        row.find('.fld-status-badge')
                            .removeClass()
                            .addClass('fld-status-badge fld-status-' + status)
                            .text(formatStatus(status));
                    }
                    loadActivity(currentLeadId);
                } else {
                    showNotice('error', response.data || dxleda_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', dxleda_ajax.strings.error);
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
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_add_feedback',
                nonce: dxleda_ajax.nonce,
                entry_id: currentLeadId,
                source: currentLeadSource,
                feedback: feedback,
                rating: rating || 'neutral'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Feedback added');
                    $('#fld-new-feedback, #fld-feedback-text').val('');
                    loadFeedback(currentLeadId);
                    loadActivity(currentLeadId);
                } else {
                    showNotice('error', response.data || dxleda_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', dxleda_ajax.strings.error);
            }
        });
    }

    /**
     * Delete Feedback
     */
    function deleteFeedback(feedbackId) {
        if (!confirm(dxleda_ajax.strings.confirm_delete)) {
            return;
        }

        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dxleda_delete_feedback',
                nonce: dxleda_ajax.nonce,
                feedback_id: feedbackId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Feedback deleted');
                    loadFeedback(currentLeadId);
                    loadActivity(currentLeadId);
                } else {
                    showNotice('error', response.data || dxleda_ajax.strings.error);
                }
            },
            error: function() {
                showNotice('error', dxleda_ajax.strings.error);
            }
        });
    }

    /**
     * Close Modal
     */
    function closeModal() {
        $('#fld-lead-modal').hide();
        currentLeadId = null;
        currentLeadSource = null;
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
