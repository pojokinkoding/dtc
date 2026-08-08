$(document).ready(function () {
    // 0. Load dropdown options for Line, Section, and Item Check
    loadSelectOptions();

    // Fetch month dropdown options for History
    $.ajax({
        url: 'Script/php/dtc/c_dtc_list.php?action=get_months',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.months) {
                let monthOpts = '<option value="">Semua Bulan Lalu</option>';
                res.months.forEach(m => {
                    monthOpts += `<option value="${m.target_month}">${m.label}</option>`;
                });
                $('#filter-month').html(monthOpts);
                applyUrlFilters();
            }
        }
    });

    // 1. Initialize DataTable for History with Server-Side AJAX Pagination
    var table = $('#dtc-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'Script/php/dtc/c_dtc_list.php?period=history',
            type: 'GET',
            data: function (d) {
                d.month = $('#filter-month').val() || '';
                d.line = $('#filter-line').val() || '';
                d.section = $('#filter-section').val() || '';
                d.item_check = $('#filter-item-check').val() || '';
                d.type = $('.filter-tab-btn.active').data('filter') || '';
            }
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) {
                    return meta.row + 1 + meta.settings._iDisplayStart;
                }
            },
            {
                data: 'raw_month',
                render: function (data, type, row) {
                    if (type === 'display') {
                        return `<span style="color: #cbd5e1;"><i class="fa-regular fa-calendar" style="color: #94a3b8; margin-right: 4px;"></i> ${row.inspection_month}</span>`;
                    }
                    return data;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    let l = row.line_name || '-';
                    let s = row.section_name || '-';
                    return `<span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px;">${l}</span>` +
                        `<span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${s}</span>`;
                }
            },
            {
                data: 'model_name',
                render: function (data) {
                    return `<span style="color: #e2e8f0; font-weight: 500;">${data}</span>`;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    let cat = row.item_check_name;
                    let sub = row.sub_item_check_name;
                    let typeStr = row.data_type || '';
                    let proc = row.process_name || '-';

                    let html = `<div style="color: var(--accent); font-weight: 600; margin-bottom: 2px;">${cat} <span style="font-size:10px; color:#94a3b8; font-weight:normal;">[${typeStr}]</span></div>`;
                    if (sub && sub !== '-') {
                        html += `<div style="color: #cbd5e1; font-size: 12px; margin-bottom: 2px;">Sub: ${sub}</div>`;
                    }
                    html += `<div style="color: #94a3b8; font-size: 11px;"><i class="fa-solid fa-gear" style="margin-right:3px;"></i> ${proc}</div>`;
                    return html;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    let lsl = row.lsl !== null ? row.lsl : '-';
                    let usl = row.usl !== null ? row.usl : '-';
                    return `<span style="color: #94a3b8; font-size: 12px;">LSL: <span style="color: #f8fafc;">${lsl}</span> &nbsp;|&nbsp; USL: <span style="color: #f8fafc;">${usl}</span></span>`;
                }
            },
            {
                data: 'operator_name',
                render: function (data) {
                    return `<span style="color: #94a3b8;"><i class="fa-regular fa-user" style="margin-right: 4px;"></i> ${data || '-'}</span>`;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    let currentMonthStr = new Date().toISOString().slice(0, 7);
                    let isPastMonth = (row.raw_month && row.raw_month < currentMonthStr);
                    let deleteBtnHtml = isPastMonth ? '' : '<button onclick="deleteDTC(' + row.parameter_id + ')" style="background-color: var(--danger); border: none; color: #fff; padding: 4px 10px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;" title="Delete Parameter"><i class="fa-solid fa-trash"></i></button>';

                    return '<div class="btn-group-action">' +
                        '<a href="index.php?page=dtc_detail&param_id=' + row.parameter_id + '&month=' + row.raw_month + '" class="btn-detail" style="background-color: var(--accent); color: #fff; padding: 4px 10px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-eye"></i> View</a>' +
                        deleteBtnHtml +
                        '</div>';
                }
            }
        ],
        responsive: true,
        order: [[1, 'desc']], // Sort by month desc default
        pageLength: 10,
        lengthChange: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search history records..."
        }
    });

    // 2. Filter Tabs Logic
    $('.filter-tab-btn').on('click', function () {
        $('.filter-tab-btn').removeClass('active');
        $(this).addClass('active');
        if (table) table.draw();
    });

    $('#filter-month, #filter-line, #filter-section, #filter-item-check').on('change', function () {
        if (table) table.draw();
    });

    // 4. Load options for Line, Section, and Item Check filters
    function loadSelectOptions() {
        $.ajax({
            url: 'Script/php/dtc/c_dtc_master_data.php',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.specs) {
                    let itemChecks = [...new Set(res.specs.map(s => s.item_check_name).filter(Boolean))].sort();
                    let itemCheckOpts = '<option value="">All Item Checks</option>';
                    itemChecks.forEach(ic => {
                        itemCheckOpts += `<option value="${ic}">${ic}</option>`;
                    });
                    $('#filter-item-check').html(itemCheckOpts);
                }
                if (res.sections) {
                    let filterOpts = '<option value="">All Sections</option>';
                    res.sections.forEach(s => {
                        filterOpts += `<option value="${s.section_name}">${s.section_name}</option>`;
                    });
                    $('#filter-section').html(filterOpts);
                }
                if (res.lines) {
                    let filterOpts = res.lines.length > 1 ? '<option value="">All Lines</option>' : '';
                    res.lines.forEach(l => {
                        filterOpts += `<option value="${l.line_name}">${l.line_name}</option>`;
                    });
                    $('#filter-line').html(filterOpts);
                    if (res.lines.length === 1) {
                        $('#filter-line').val(res.lines[0].line_name);
                    }
                }

                applyUrlFilters();
            }
        });
    }

    function applyUrlFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        const monthParam = urlParams.get('month');
        const lineParam = urlParams.get('line');
        const sectionParam = urlParams.get('section');
        const typeParam = urlParams.get('type');

        let filterChanged = false;

        if (monthParam) {
            if ($('#filter-month option[value="' + monthParam + '"]').length === 0) {
                $('#filter-month').append(new Option(monthParam, monthParam));
            }
            $('#filter-month').val(monthParam);
            filterChanged = true;
        }

        if (lineParam) {
            if ($('#filter-line option[value="' + lineParam + '"]').length === 0) {
                $('#filter-line').append(new Option(lineParam, lineParam));
            }
            $('#filter-line').val(lineParam);
            filterChanged = true;
        }

        if (sectionParam) {
            if ($('#filter-section option[value="' + sectionParam + '"]').length === 0) {
                $('#filter-section').append(new Option(sectionParam, sectionParam));
            }
            $('#filter-section').val(sectionParam);
            filterChanged = true;
        }

        if (typeParam) {
            $('.filter-tab-btn').removeClass('active');
            $(`.filter-tab-btn[data-filter="${typeParam}"]`).addClass('active');
            filterChanged = true;
        }

        if (filterChanged && typeof table !== 'undefined' && table) {
            table.ajax.reload();
        }
    }

    loadSelectOptions();
    applyUrlFilters();
});

// Global function to handle delete action
if (!window.deleteDTC) {
    window.deleteDTC = function (param_id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "Are you sure you want to completely delete this parameter and ALL of its measurement data? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_dtc_delete.php',
                    type: 'POST',
                    data: { parameter_id: param_id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Deleted successfully!',
                                icon: 'success',
                                background: '#1e293b',
                                color: '#f8fafc',
                                confirmButtonColor: '#3b82f6'
                            });
                            if ($('#dtc-table').length) $('#dtc-table').DataTable().ajax.reload(null, false);
                            if ($('#dtc-history-table').length) $('#dtc-history-table').DataTable().ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                title: 'Failed!',
                                text: "Failed to delete: " + res.message,
                                icon: 'error',
                                background: '#1e293b',
                                color: '#f8fafc',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            title: 'Error!',
                            text: "Server error occurred while deleting.",
                            icon: 'error',
                            background: '#1e293b',
                            color: '#f8fafc',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                });
            }
        });
    };
}
