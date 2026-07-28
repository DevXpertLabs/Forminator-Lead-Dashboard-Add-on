/**
 * DevXpert Lead Dashboard for Forminator — Settings page (Sales Admin user
 * management + Database Tools). Enqueued only on the plugin Settings screen.
 * Depends on the localized objects `dxleda_ajax` and `dxleda_settings_l10n`.
 */
(function ($) {
    'use strict';

    var l10n = window.dxleda_settings_l10n || {};

    $(document).ready(function () {
        loadAssignableUsers();

        // Assign button
        $('#fld-assign-sales-admin').on('click', function () {
            var userId = $('#fld-assign-user-select').val();
            if (!userId) {
                setStatus('warning', l10n.select_user);
                return;
            }
            assignSalesAdmin(userId);
        });

        // Remove buttons (delegated for dynamically added rows)
        $(document).on('click', '.fld-remove-sales-admin', function () {
            var userId = $(this).data('id');
            var name = $(this).data('name');
            if (!confirm(l10n.remove_confirm + ' ' + name + '?')) {
                return;
            }
            removeSalesAdmin(userId);
        });

        // Database Tools — Clear Activity Log
        $('#fld-clear-activity').on('click', function () {
            if (!confirm(l10n.clear_confirm)) {
                return;
            }
            runDbTool($(this), 'dxleda_clear_activity_log');
        });

        // Database Tools — Reset All Statuses
        $('#fld-reset-statuses').on('click', function () {
            if (!confirm(l10n.reset_confirm)) {
                return;
            }
            runDbTool($(this), 'dxleda_reset_statuses');
        });

        // Telegram — send a test message using the saved credentials.
        $('#dxleda-test-telegram').on('click', function () {
            testTelegram($(this));
        });
    });

    function testTelegram($btn) {
        var original = $btn.text();
        var colors = { success: '#22c55e', error: '#ef4444' };
        var $result = $('#dxleda-test-telegram-result');

        $btn.prop('disabled', true).text(dxleda_ajax.strings.loading);
        $result.text('').css('color', '');

        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: { action: 'dxleda_test_telegram', nonce: dxleda_ajax.nonce },
            success: function (response) {
                // Telegram's own error text ("chat not found", "Unauthorized")
                // comes back in response.data and is the useful part.
                var ok = response.success;
                $result.text(ok ? response.data.message : (response.data || dxleda_ajax.strings.error))
                    .css('color', ok ? colors.success : colors.error);
            },
            error: function () { $result.text(dxleda_ajax.strings.error).css('color', colors.error); },
            complete: function () { $btn.prop('disabled', false).text(original); }
        });
    }

    function runDbTool($btn, action) {
        var original = $btn.text();
        var colors = { success: '#22c55e', error: '#ef4444' };
        var $status = $('#fld-db-tool-status');
        $btn.prop('disabled', true).text(dxleda_ajax.strings.loading);
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: { action: action, nonce: dxleda_ajax.nonce },
            success: function (response) {
                var ok = response.success;
                $status.text(ok ? response.data.message : (response.data || dxleda_ajax.strings.error))
                    .css('color', ok ? colors.success : colors.error);
            },
            error: function () { $status.text(dxleda_ajax.strings.error).css('color', colors.error); },
            complete: function () { $btn.prop('disabled', false).text(original); }
        });
    }

    function loadAssignableUsers() {
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: { action: 'dxleda_get_assignable_users', nonce: dxleda_ajax.nonce },
            success: function (response) {
                if (!response.success) return;
                var select = $('#fld-assign-user-select');
                select.find('option:not(:first)').remove();
                $.each(response.data, function (i, user) {
                    if (!user.is_sales_admin) {
                        select.append($('<option>', { value: user.id, text: user.name + ' (' + user.email + ')' }));
                    }
                });
            }
        });
    }

    function assignSalesAdmin(userId) {
        setStatus('info', l10n.saving);
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: { action: 'dxleda_assign_sales_admin', nonce: dxleda_ajax.nonce, user_id: userId },
            success: function (response) {
                if (response.success) {
                    setStatus('success', response.data.message);
                    addRowToTable(userId);
                    loadAssignableUsers();
                } else {
                    setStatus('error', response.data);
                }
            },
            error: function () { setStatus('error', dxleda_ajax.strings.error); }
        });
    }

    function removeSalesAdmin(userId) {
        $.ajax({
            url: dxleda_ajax.ajax_url,
            type: 'POST',
            data: { action: 'dxleda_remove_sales_admin', nonce: dxleda_ajax.nonce, user_id: userId },
            success: function (response) {
                if (response.success) {
                    $('#fld-sa-row-' + userId).remove();
                    // Show "no users" row if table is now empty
                    if ($('#fld-sales-admin-table tbody tr').length === 0) {
                        $('#fld-sales-admin-table tbody').append(
                            '<tr id="fld-no-sales-admins"><td colspan="3">' + $('<span>').text(l10n.no_admins).html() + '</td></tr>'
                        );
                    }
                    setStatus('success', response.data.message);
                    loadAssignableUsers();
                } else {
                    setStatus('error', response.data);
                }
            },
            error: function () { setStatus('error', dxleda_ajax.strings.error); }
        });
    }

    function addRowToTable(userId) {
        var option = $('#fld-assign-user-select option[value="' + userId + '"]');
        var text = option.text(); // "Name (email)"
        var parts = text.match(/^(.*)\s\(([^)]+)\)$/);
        var name = parts ? parts[1] : text;
        var email = parts ? parts[2] : '';

        $('#fld-no-sales-admins').remove();
        $('#fld-sales-admin-table tbody').append(
            '<tr id="fld-sa-row-' + userId + '">' +
            '<td>' + $('<span>').text(name).html() + '</td>' +
            '<td>' + $('<span>').text(email).html() + '</td>' +
            '<td><button class="button fld-remove-sales-admin" data-id="' + userId + '" data-name="' + $('<span>').text(name).html() + '">' + $('<span>').text(l10n.remove).html() + '</button></td>' +
            '</tr>'
        );
    }

    function setStatus(type, message) {
        var colors = { success: '#22c55e', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
        $('#fld-assign-status').text(message).css('color', colors[type] || '#000');
    }

})(jQuery);
