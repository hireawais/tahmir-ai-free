<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Form Entries Viewer — Admin Page
 * Lists all form entries across forms with filters, search, pagination, bulk actions and CSV export.
 */

$selected_form = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$nonce = wp_create_nonce('ugentic_admin_nonce');
?>

<div class="wrap ugentic-wrap ugentic-entries-viewer" id="ugentic-entries-viewer">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Form Entries', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Review, search, export, and manage submissions independently from the form builder.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-list-view',
        'actions' => [
            [
                'label' => esc_html__('Manage Forms', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-forms'),
                'class' => 'button',
                'icon' => 'dashicons-feedback',
            ],
        ],
    ]);
    ?>

    <!-- Filters -->
    <div class="entries-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filter-form"><?php esc_html_e('Form', 'tahmir-ai-to-wp-converter'); ?></label>
                <select id="filter-form">
                    <option value=""><?php esc_html_e('All Forms', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php foreach ($forms as $form): ?>
                    <option value="<?php echo intval($form->id); ?>" <?php selected($selected_form, $form->id); ?>>
                        <?php echo esc_html($form->title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-status"><?php esc_html_e('Status', 'tahmir-ai-to-wp-converter'); ?></label>
                <select id="filter-status">
                    <option value=""><?php esc_html_e('All', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="unread"><?php esc_html_e('Unread', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="read"><?php esc_html_e('Read', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="starred"><?php esc_html_e('Starred', 'tahmir-ai-to-wp-converter'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-date-from"><?php esc_html_e('From', 'tahmir-ai-to-wp-converter'); ?></label>
                <input type="date" id="filter-date-from">
            </div>

            <div class="filter-group">
                <label for="filter-date-to"><?php esc_html_e('To', 'tahmir-ai-to-wp-converter'); ?></label>
                <input type="date" id="filter-date-to">
            </div>

            <div class="filter-group filter-search">
                <label for="filter-search"><?php esc_html_e('Search', 'tahmir-ai-to-wp-converter'); ?></label>
                <input type="text" id="filter-search" placeholder="<?php esc_html_e('Search entries...', 'tahmir-ai-to-wp-converter'); ?>">
            </div>

            <div class="filter-group filter-actions">
                <button type="button" id="apply-filters" class="button button-primary"><?php esc_html_e('Filter', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="reset-filters" class="button"><?php esc_html_e('Reset', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="entries-bulk-bar">
        <div class="bulk-left">
            <select id="bulk-action">
                <option value=""><?php esc_html_e('Bulk Actions', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="mark_read"><?php esc_html_e('Mark as Read', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="mark_unread"><?php esc_html_e('Mark as Unread', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="delete"><?php esc_html_e('Delete', 'tahmir-ai-to-wp-converter'); ?></option>
            </select>
            <button type="button" id="apply-bulk" class="button" disabled><?php esc_html_e('Apply', 'tahmir-ai-to-wp-converter'); ?></button>
            <span id="entries-selected-label" class="entries-selected-label" aria-live="polite"></span>
            <span id="entries-count-label" class="entries-count-label"></span>
        </div>
        <div class="bulk-right">
            <button type="button" id="export-csv" class="button">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'tahmir-ai-to-wp-converter'); ?>
            </button>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="ug-entries-table-scroll" role="region" aria-label="<?php esc_attr_e('Form entries table', 'tahmir-ai-to-wp-converter'); ?>" tabindex="0">
    <table class="wp-list-table widefat fixed striped" id="entries-table">
        <thead>
            <tr>
                <th class="check-column"><input type="checkbox" id="select-all"></th>
                <th class="column-id"><?php esc_html_e('ID', 'tahmir-ai-to-wp-converter'); ?></th>
                <th class="column-form"><?php esc_html_e('Form', 'tahmir-ai-to-wp-converter'); ?></th>
                <th class="column-summary"><?php esc_html_e('Summary', 'tahmir-ai-to-wp-converter'); ?></th>
                <th class="column-date"><?php esc_html_e('Date', 'tahmir-ai-to-wp-converter'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'tahmir-ai-to-wp-converter'); ?></th>
                <th class="column-actions"><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
            </tr>
        </thead>
        <tbody id="entries-body">
            <tr><td colspan="7"><?php esc_html_e('Loading entries...', 'tahmir-ai-to-wp-converter'); ?></td></tr>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <div class="tablenav bottom" id="entries-pagination"></div>

    <input type="hidden" id="entries-nonce" value="<?php echo esc_attr($nonce); ?>">
</div>

<!-- Entry Detail Modal -->
<div id="entry-detail-modal" class="ugentic-modal" style="display:none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php esc_html_e('Entry Details', 'tahmir-ai-to-wp-converter'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="entry-detail-body"></div>
        <div class="modal-footer">
            <button type="button" class="button modal-close"><?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?></button>
        </div>
    </div>
</div>

<style>
.ugentic-entries-viewer .entries-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 16px;
    margin: 16px 0;
}
.entries-filters .filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.entries-filters .filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.entries-filters .filter-group label {
    font-weight: 600;
    font-size: 12px;
    color: #50575e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.filter-search { flex: 1; min-width: 180px; }
.filter-search input { width: 100%; }
.filter-actions { flex-direction: row !important; align-items: flex-end !important; gap: 6px !important; }

.entries-bulk-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.bulk-left { display: flex; gap: 6px; align-items: center; }
.entries-count-label, .entries-selected-label { color: #50575e; font-size: 13px; margin-left: 8px; }
.entries-selected-label { color: #c2410c; font-weight: 600; }

#entries-table .column-id { width: 50px; }
#entries-table .column-form { width: 160px; }
#entries-table .column-date { width: 150px; }
#entries-table .column-status { width: 80px; }
#entries-table .column-actions { width: 140px; }

.entry-row.unread td { font-weight: 600; }
.entry-status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; }
.entry-status.unread { background: #e0f2fe; color: #0369a1; }
.entry-status.read { background: #f3f4f6; color: #6b7280; }
.entry-starred { color: #eab308; cursor: pointer; font-size: 16px; }
.entry-starred.active { color: #f59e0b; }

.ugentic-modal { position: fixed; z-index: 100001; top: 0; left: 0; right: 0; bottom: 0; }
.ugentic-modal .modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.ugentic-modal .modal-content { position: relative; max-width: 640px; margin: 60px auto; background: #fff; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: calc(100vh - 120px); display: flex; flex-direction: column; }
.ugentic-modal .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }
.ugentic-modal .modal-header h3 { margin: 0; }
.ugentic-modal .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; padding: 0; line-height: 1; }
.ugentic-modal .modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.ugentic-modal .modal-footer { padding: 12px 20px; border-top: 1px solid #e5e7eb; text-align: right; }

.entry-detail-table { width: 100%; border-collapse: collapse; }
.entry-detail-table th { text-align: left; padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; width: 35%; font-weight: 600; color: #374151; }
.entry-detail-table td { padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937; word-break: break-word; }
.entry-detail-meta { margin-top: 16px; padding-top: 12px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px; }
.entry-detail-meta span { margin-right: 16px; }

#entries-pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.pagination-info { color: #50575e; font-size: 13px; }
.pagination-links { display: flex; gap: 4px; }
.pagination-links button { min-width: 32px; padding: 4px 8px; }
.pagination-links button.current { background: #2271b1; color: #fff; border-color: #2271b1; }
</style>



<style id="tahmir-free-form-access">.uf-forms-page .delete-form,.uf-forms-page .uf-bulk-bar,.uf-forms-page .uf-form-checkbox{display:none!important}</style>
<p class="description"><?php esc_html_e('Forms & Entries: collect submissions and read entries here for free. Form Builder, field editing, form settings, notifications and integrations require Pro.', 'tahmir-ai-to-wp-converter'); ?></p>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var perPage = 20;
    var nonce = $('#entries-nonce').val();

    // Initialize
    loadEntries();

    // Apply filters
    $('#apply-filters').on('click', function() { currentPage = 1; loadEntries(); });

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#filter-form, #filter-status').val('');
        $('#filter-date-from, #filter-date-to, #filter-search').val('');
        currentPage = 1;
        loadEntries();
    });

    // Search on Enter
    $('#filter-search').on('keypress', function(e) {
        if (e.which === 13) { currentPage = 1; loadEntries(); }
    });

    function selectedEntryIds() {
        return $('.entry-checkbox:checked').map(function() { return String($(this).val()); }).get();
    }

    function updateEntrySelection() {
        var $checkboxes = $('.entry-checkbox');
        var count = $checkboxes.filter(':checked').length;
        $('#select-all')
            .prop('checked', $checkboxes.length > 0 && count === $checkboxes.length)
            .prop('indeterminate', count > 0 && count < $checkboxes.length);
        $('#entries-selected-label').text(count ? count + ' selected' : '');
        $('#apply-bulk').prop('disabled', !count || !$('#bulk-action').val());
    }

    // Select all checkbox
    $('#select-all').on('change', function() {
        $('.entry-checkbox').prop('checked', this.checked);
        updateEntrySelection();
    });
    $(document).on('change', '.entry-checkbox', updateEntrySelection);
    $('#bulk-action').on('change', updateEntrySelection);

    // Apply bulk action
    $('#apply-bulk').on('click', function() {
        var action = $('#bulk-action').val();
        if (!action) { alert('Please select a bulk action'); return; }

        var ids = selectedEntryIds();
        if (ids.length === 0) { alert('Please select at least one entry'); return; }

        if (action === 'delete' && !confirm('Delete ' + ids.length + ' selected entries?')) return;

        var $button = $(this).prop('disabled', true).text(action === 'delete' ? 'Deleting...' : 'Updating...');
        $.post(ajaxurl, {
            action: 'ugentic_bulk_entries_action',
            nonce: nonce,
            bulk_action: action,
            entry_ids: ids
        }).done(function(resp) {
            if (resp && resp.success) {
                $('#bulk-action').val('');
                $('#select-all').prop('checked', false).prop('indeterminate', false);
                loadEntries();
                return;
            }
            alert(resp && resp.data && resp.data.message ? resp.data.message : 'The selected entries could not be updated.');
        }).fail(function() {
            alert('The bulk request could not be completed. Please check your connection and try again.');
        }).always(function() {
            $button.text('Apply');
            updateEntrySelection();
        });
    });

    // Export CSV
    $('#export-csv').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Exporting...');

        $.post(ajaxurl, {
            action: 'ugentic_export_entries_csv',
            nonce: nonce,
            form_id: $('#filter-form').val() || 0
        }, function(resp) {
            if (resp.success) {
                downloadCSV(resp.data.headers, resp.data.rows);
            } else {
                alert('Export failed');
            }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export CSV');
        });
    });

    // Load entries via AJAX
    function loadEntries() {
        var statusVal = $('#filter-status').val();
        var is_read = '', is_starred = '';

        if (statusVal === 'unread') is_read = '0';
        else if (statusVal === 'read') is_read = '1';
        else if (statusVal === 'starred') is_starred = '1';

        $.post(ajaxurl, {
            action: 'ugentic_get_all_entries',
            nonce: nonce,
            form_id: $('#filter-form').val() || 0,
            is_read: is_read,
            is_starred: is_starred,
            search: $('#filter-search').val() || '',
            date_from: $('#filter-date-from').val() || '',
            date_to: $('#filter-date-to').val() || '',
            page: currentPage,
            per_page: perPage
        }, function(resp) {
            if (resp.success) {
                if ((!resp.data.entries || resp.data.entries.length === 0) && currentPage > 1 && resp.data.total > 0) {
                    currentPage--;
                    loadEntries();
                    return;
                }
                renderEntries(resp.data.entries);
                renderPagination(resp.data.total, resp.data.page, resp.data.total_pages);
                $('#entries-count-label').text(resp.data.total + ' entries');
            } else {
                $('#entries-body').html('<tr><td colspan="7">Error loading entries.</td></tr>');
                updateEntrySelection();
            }
        }).fail(function() {
            $('#entries-body').html('<tr><td colspan="7">Error loading entries.</td></tr>');
            updateEntrySelection();
        });
    }

    function renderEntries(entries) {
        if (!entries || entries.length === 0) {
            $('#entries-body').html('<tr><td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">No entries found.</td></tr>');
            updateEntrySelection();
            return;
        }

        var html = '';
        entries.forEach(function(entry) {
            var summary = '';
            if (entry.entry_data) {
                var data = entry.entry_data;
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); }
                    catch (e) { data = {}; }
                }
                var vals = [];
                for (var k in data) {
                    var v = data[k];
                    vals.push(typeof v === 'object' ? (v.value || '') : v);
                    if (vals.length >= 2) break;
                }
                summary = vals.join(', ');
                if (summary.length > 60) summary = summary.substring(0, 60) + '...';
            }

            var statusClass = entry.is_read == 1 ? 'read' : 'unread';
            var formName = entry.form_title || 'Form #' + entry.form_id;

            html += '<tr class="entry-row ' + statusClass + '" data-id="' + entry.id + '">' +
                '<td><input type="checkbox" class="entry-checkbox" value="' + entry.id + '" aria-label="Select entry ' + entry.id + '"></td>' +
                '<td>' + entry.id + '</td>' +
                '<td>' + escapeHtml(formName) + '</td>' +
                '<td class="entry-summary">' + escapeHtml(summary) + '</td>' +
                '<td>' + entry.created_at + '</td>' +
                '<td><span class="entry-status ' + statusClass + '">' + (entry.is_read == 1 ? 'Read' : 'New') + '</span></td>' +
                '<td>' +
                    '<button type="button" class="button-small view-entry" data-id="' + entry.id + '">View</button> ' +
                    '<button type="button" class="button-small button-link-delete delete-entry" data-id="' + entry.id + '">Delete</button>' +
                '</td>' +
            '</tr>';
        });

        $('#entries-body').html(html);
        updateEntrySelection();
    }

    $(document).on('click', '.view-entry', function() { viewEntry($(this).data('id')); });
    $(document).on('click', '.delete-entry', function() {
        if (confirm('Delete this entry?')) deleteEntry($(this).data('id'), $(this));
    });

    function renderPagination(total, page, totalPages) {
        if (totalPages <= 1) { $('#entries-pagination').html(''); return; }

        var info = '<span class="pagination-info">Page ' + page + ' of ' + totalPages + ' (' + total + ' entries)</span>';
        var links = '<span class="pagination-links">';

        if (page > 1) {
            links += '<button class="button page-btn" data-page="1">&laquo;</button>';
            links += '<button class="button page-btn" data-page="' + (page - 1) + '">&lsaquo;</button>';
        }

        var start = Math.max(1, page - 2);
        var end = Math.min(totalPages, page + 2);
        for (var i = start; i <= end; i++) {
            links += '<button class="button page-btn' + (i === page ? ' current' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (page < totalPages) {
            links += '<button class="button page-btn" data-page="' + (page + 1) + '">&rsaquo;</button>';
            links += '<button class="button page-btn" data-page="' + totalPages + '">&raquo;</button>';
        }

        links += '</span>';
        $('#entries-pagination').html(info + links);

        $('.page-btn').off('click').on('click', function() {
            currentPage = parseInt($(this).data('page'));
            loadEntries();
        });
    }

    function viewEntry(entryId) {
        $.post(ajaxurl, {
            action: 'ugentic_get_form_entry',
            nonce: nonce,
            entry_id: entryId
        }, function(resp) {
            if (resp.success && resp.data.entry) {
                var entry = resp.data.entry;
                var data = typeof entry.entry_data === 'string' ? JSON.parse(entry.entry_data) : entry.entry_data;

                var html = '<table class="entry-detail-table">';
                for (var key in data) {
                    var label = typeof data[key] === 'object' ? (data[key].label || key) : key;
                    var value = typeof data[key] === 'object' ? (data[key].value || '') : data[key];
                    html += '<tr><th>' + escapeHtml(label) + '</th><td>' + escapeHtml(value) + '</td></tr>';
                }
                html += '</table>';
                html += '<div class="entry-detail-meta">';
                html += '<span><strong>Submitted:</strong> ' + entry.created_at + '</span>';
                html += '<span><strong>IP:</strong> ' + (entry.user_ip || 'N/A') + '</span>';
                if (entry.page_url) html += '<span><strong>Page:</strong> ' + escapeHtml(entry.page_url) + '</span>';
                html += '</div>';

                $('#entry-detail-body').html(html);
                $('#entry-detail-modal').show();

                // Update row status
                $('tr[data-id="' + entryId + '"]').removeClass('unread').addClass('read')
                    .find('.entry-status').removeClass('unread').addClass('read').text('Read');
            }
        });
    }

    function deleteEntry(entryId, $button) {
        $button = $button || $('.delete-entry[data-id="' + entryId + '"]');
        $button.prop('disabled', true).text('Deleting...');
        $.post(ajaxurl, {
            action: 'ugentic_delete_form_entry',
            nonce: nonce,
            entry_id: entryId
        }).done(function(resp) {
            if (resp && resp.success) {
                loadEntries();
                return;
            }
            alert(resp && resp.data && resp.data.message ? resp.data.message : 'The entry could not be deleted.');
            $button.prop('disabled', false).text('Delete');
        }).fail(function() {
            alert('The delete request could not be completed. Please check your connection and try again.');
            $button.prop('disabled', false).text('Delete');
        });
    }

    function downloadCSV(headers, rows) {
        var csv = headers.map(csvEscape).join(',') + '\n';
        rows.forEach(function(row) {
            csv += row.map(csvEscape).join(',') + '\n';
        });

        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'form-entries-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
    }

    function csvEscape(val) {
        val = String(val || '');
        if (val.indexOf(',') >= 0 || val.indexOf('"') >= 0 || val.indexOf('\n') >= 0) {
            return '"' + val.replace(/"/g, '""') + '"';
        }
        return val;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Modal close
    $('.modal-close, .modal-overlay').on('click', function() {
        $('#entry-detail-modal').hide();
    });
});
</script>