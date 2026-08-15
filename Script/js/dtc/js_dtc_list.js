$(document).ready(function () {
    // Shared state variables
    window.runningModelsList = [];
    var runningModelsList = [];
    var activeModelFilter = null;

    // 1. Initialize DataTable with Server-Side AJAX Pagination
    var table = $('#dtc-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'Script/php/dtc/c_dtc_list.php?period=current',
            type: 'GET',
            data: function (d) {
                d.line = $('#filter-line').val() || '';
                d.section = $('#filter-section').val() || '';
                d.item_check = $('#filter-item-check').val() || '';
                d.type = $('.filter-tab-btn.active').data('filter') || '';
                d.oos_only = $('#filter-oos-only').is(':checked') ? '1' : '0';
                if (typeof activeModelFilter !== 'undefined' && activeModelFilter) {
                    d.model = activeModelFilter.model || '';
                    if (activeModelFilter.line) d.line = activeModelFilter.line;
                    if (activeModelFilter.section) d.section = activeModelFilter.section;
                } else if (typeof runningModelsList !== 'undefined' && runningModelsList && runningModelsList.length > 0) {
                    let rMods = runningModelsList.map(m => ({
                        line_name: m.line_name || '',
                        section_name: m.section_name || '',
                        model_name: m.model_name || ''
                    })).filter(m => m.model_name);
                    if (rMods.length > 0) {
                        d.running_models = JSON.stringify(rMods);
                    }
                }
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
                render: function (data, type, row) {
                    let html = `<span style="color: #e2e8f0; font-weight: 500;">${data}</span>`;
                    let overdue = parseInt(row.overdue_today_count || 0);
                    if (overdue > 0) {
                        html += `<br><span title="${overdue} sesi shift hari ini belum terisi dan sudah melewati waktunya"
                            style="display:inline-flex; align-items:center; gap:4px; margin-top:4px;
                                   background: rgba(239,68,68,0.18); border: 1px solid rgba(239,68,68,0.45);
                                   color: #fca5a5; padding: 2px 8px; border-radius: 10px;
                                   font-size: 10px; font-weight: 700; cursor: default;
                                   animation: pulse-yellow-glow 1.6s ease-in-out infinite;">
                            <i class="fa-solid fa-triangle-exclamation" style="color:#f87171;"></i>
                            ${overdue} sesi telat
                        </span>`;
                    }
                    return html;
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
                data: 'oos_count',
                render: function (data, type, row) {
                    let count = parseInt(data || 0);
                    if (count > 0) {
                        return `<span class="btn-open-oos-modal" data-param="${row.parameter_id}" data-date="${row.prod_today || ''}" data-month="${row.raw_month || ''}" style="background: rgba(239, 68, 68, 0.18); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.45); padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: transform 0.15s;" title="Klik untuk meng-update nilai Out of Spec Hari Ini (${count} OOS)">
                                    <i class="fa-solid fa-triangle-exclamation" style="color: #f87171;"></i> ${count} OOS
                                </span>`;
                    }
                    return `<span style="color: #64748b; font-size: 11px; font-weight: 500;">0</span>`;
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
                    let isQualitative = (row.data_type === 'Time Check' || row.data_type === 'F/Proof');
                    let viewUrl = '';
                    if (isQualitative) {
                        viewUrl = `index.php?page=dtc_matrix_qualitative&param_id=${row.parameter_id}&model=${encodeURIComponent(row.model_name)}&line=${encodeURIComponent(row.line_name)}&section=${encodeURIComponent(row.section_name)}&month=${encodeURIComponent(row.raw_month)}`;
                    } else {
                        viewUrl = `index.php?page=dtc_detail&param_id=${row.parameter_id}&month=${row.raw_month}`;
                    }

                    let currentMonthStr = new Date().toISOString().slice(0, 7);
                    let isPastMonth = (row.raw_month && row.raw_month < currentMonthStr);
                    let deleteBtnHtml = isPastMonth ? '' : `<button onclick="deleteDTC(${row.parameter_id})" style="background-color: var(--danger); border: none; color: #fff; padding: 4px 10px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;" title="Delete Parameter"><i class="fa-solid fa-trash"></i></button>`;

                    return '<div class="btn-group-action">' +
                        `<a href="${viewUrl}" class="btn-detail" style="background-color: var(--accent); color: #fff; padding: 4px 10px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-eye"></i> View</a>` +
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
            searchPlaceholder: "Search records..."
        },
        initComplete: function () {
            if (typeof loadRunningModels === 'function') {
                loadRunningModels();
            }
            loadMissingCounts();
            loadDTCSummaryTicker();

            table.on('draw', function () {
                updateTabCounts();
            });
        }
    });

    function loadDTCSummaryTicker() {
        $.ajax({
            url: 'Script/php/dtc/c_dtc_summary_ticker.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function (res) {
                if (res.status !== 'success') return;

                let sep = `<span style="margin: 0 20px; color: #1e3a5f; font-size:14px;">┃</span>`;
                let divider = `<span style="margin: 0 30px; color: #334155; font-size:16px;">◆</span>`;

                if (res.no_models || !res.models || res.models.length === 0) {
                    let html = `<span style="color:#64748b; font-style:italic;">
                        <i class="fa-solid fa-circle-info"></i>
                        Tidak ada Running Model aktif &mdash; ${res.today_formatted}
                    </span>`;
                    $('#dtc-summary-ticker-text').html(html);
                    return;
                }

                // Build one segment per running model, duplicated for seamless loop
                function buildSegments() {
                    return res.models.map(m => {
                        let closedColor = m.closed_count > 0 ? '#34d399' : '#64748b';
                        let unclosedColor = m.unclosed_count > 0 ? '#fbbf24' : '#64748b';
                        let overdueColor = m.overdue_slots > 0 ? '#f87171' : '#34d399';
                        let compColor = m.compliance_rate >= 100 ? '#34d399' : (m.compliance_rate >= 75 ? '#fbbf24' : '#f87171');

                        return `
                            <span style="color:#38bdf8; font-weight:800; font-size:12px;">
                                <i class="fa-solid fa-cube"></i> ${m.model_name}
                            </span>
                            <span style="color:#64748b; font-size:10px; margin-left:4px;">(${m.line_name} &bull; ${m.section_name})</span>
                            ${sep}
                            <span style="color:${closedColor}; font-weight:700;">
                                <i class="fa-solid fa-circle-check"></i> Sudah Close: ${m.closed_count}/${m.total_params}
                            </span>
                            ${sep}
                            <span style="color:${unclosedColor}; font-weight:700;">
                                <i class="fa-solid fa-clock"></i> Belum Close: ${m.unclosed_count}
                            </span>
                            ${sep}
                            <span style="color:${overdueColor}; font-weight:700;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Sesi Telat: ${m.overdue_slots}
                            </span>
                            ${sep}
                            <span style="color:${compColor}; font-weight:700;">
                                <i class="fa-solid fa-chart-pie"></i> Compliance: ${m.compliance_rate}%
                            </span>
                        `;
                    }).join(divider);
                }

                let dateTag = `<span style="color:#60a5fa; font-weight:700; margin-right:18px;">
                    <i class="fa-regular fa-calendar"></i> ${res.today_formatted}
                </span>`;

                // Duplicate content for seamless marquee loop
                let content = dateTag + buildSegments() + divider + dateTag + buildSegments();
                let $tickerEl = $('#dtc-summary-ticker-text');
                $tickerEl.html(content);

                // Calculate constant scroll speed (pixels per second) regardless of data volume
                // Target speed: ~60px/s for comfortable, relaxed reading
                setTimeout(function () {
                    let totalPixelWidth = ($tickerEl[0] && $tickerEl[0].scrollWidth) ? $tickerEl[0].scrollWidth : 2000;
                    let targetPxPerSecond = 60; // Constant comfortable reading speed (60 px/s)
                    let durationSeconds = Math.max(25, Math.round(totalPixelWidth / targetPxPerSecond));
                    $tickerEl.css('animation-duration', durationSeconds + 's');
                }, 50);
            }
        });
    }

    // Reload ticker when running models change
    window.reloadDTCSummaryTicker = loadDTCSummaryTicker;


    let missingParamIds = new Set();
    window.missingCounts = { 'All': 0, 'CTQ': 0, 'CTP': 0, 'Time Check': 0, 'F/Proof': 0 };

    function loadMissingCounts() {
        let line = $('#filter-line').val() || '';
        let section = $('#filter-section').val() || '';
        let reqData = { line: line, section: section };

        if (typeof activeModelFilter !== 'undefined' && activeModelFilter) {
            reqData.model = activeModelFilter.model || '';
            if (activeModelFilter.line) reqData.line = activeModelFilter.line;
            if (activeModelFilter.section) reqData.section = activeModelFilter.section;
        } else if (typeof runningModelsList !== 'undefined' && runningModelsList && runningModelsList.length > 0) {
            let rMods = runningModelsList.map(m => m.model_name).filter(Boolean);
            if (rMods.length > 0) {
                reqData.running_models = JSON.stringify(rMods);
            }
        }

        $.ajax({
            url: 'Script/php/dtc/c_missing_data_daily.php',
            type: 'GET',
            data: reqData,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    missingParamIds.clear();
                    if (res.data) {
                        res.data.forEach(item => {
                            if (item.slots && item.slots.includes(0)) {
                                missingParamIds.add(item.parameter_id);
                            }
                        });
                    }
                    if (res.counts) {
                        window.missingCounts = res.counts;
                    }
                    updateTabCounts();
                }
            }
        });
    }

    function updateTabCounts() {
        let counts = Object.assign({}, window.missingCounts || { 'All': 0, 'CTQ': 0, 'CTP': 0, 'Time Check': 0, 'F/Proof': 0 });

        // Calculate/sync counts directly from loaded DataTable rows if present
        if (typeof table !== 'undefined' && table && table.rows) {
            let tableData = table.rows({ search: 'applied' }).data();
            if (tableData && tableData.length > 0) {
                let dtCounts = { 'All': 0, 'CTQ': 0, 'CTP': 0, 'Time Check': 0, 'F/Proof': 0 };
                let hasOverdue = false;
                for (let i = 0; i < tableData.length; i++) {
                    let row = tableData[i];
                    let overdue = parseInt(row.overdue_today_count || 0);
                    if (overdue > 0) {
                        hasOverdue = true;
                        dtCounts['All']++;
                        let type = (row.data_type || '').trim().toUpperCase();
                        if (type === 'CTQ') dtCounts['CTQ']++;
                        else if (type === 'CTP') dtCounts['CTP']++;
                        else if (type === 'TIME CHECK') dtCounts['Time Check']++;
                        else if (type === 'F/PROOF') dtCounts['F/Proof']++;
                    }
                }
                if (hasOverdue) {
                    counts = dtCounts;
                }
            }
        }

        $('.dtc-filter-tabs .filter-tab-btn, .filter-tab-btn[data-filter]').each(function () {
            let filter = $(this).attr('data-filter');
            if (filter === undefined) return; // Skip checkpoint tabs or buttons without data-filter
            let key = (filter === '' || filter === null) ? 'All' : filter;
            let count = counts[key] || 0;
            let text = (filter === '' || filter === null) ? 'All' : filter;

            if (count > 0) {
                $(this).addClass('has-notif');
                $(this).html(`${text} <span class="badge-notif-glow" style="font-size:10px; padding:2px 7px; border-radius:10px; margin-left:5px; font-weight:700;">${count}</span>`);
            } else {
                $(this).removeClass('has-notif');
                $(this).html(`${text} <span style="background:rgba(255,255,255,0.1); color:var(--text-muted); font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px;">0</span>`);
            }
        });
    }

    // 1.5 Filter Tabs Logic
    $('.dtc-filter-tabs .filter-tab-btn, .filter-tab-btn[data-filter]').on('click', function () {
        $('.dtc-filter-tabs .filter-tab-btn, .filter-tab-btn[data-filter]').removeClass('active');
        $(this).addClass('active');

        if (typeof table !== 'undefined' && table) {
            table.draw();
        }
    });

    // --- Running Model Management & Rendering ---

    // Custom filtering for Line, Section, Item Check, and Running Model
    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex, rowData) {
            if (settings.nTable.id !== 'dtc-table') return true;

            let filterLine = $('#filter-line').val();
            let filterSection = $('#filter-section').val();
            let filterItemCheck = $('#filter-item-check').val();

            // Extract line_name, section_name, item_check_name, model_name safely from original row object
            let rawRow = (settings.aoData && settings.aoData[dataIndex] && settings.aoData[dataIndex]._aData) ? settings.aoData[dataIndex]._aData : (rowData || {});
            let lineName = (rawRow.line_name || '').trim();
            let sectionName = (rawRow.section_name || '').trim();
            let itemCheckName = (rawRow.item_check_name || '').trim();
            let rowModel = (rawRow.model_name || '').trim();

            if (filterLine && lineName.toLowerCase() !== filterLine.trim().toLowerCase()) return false;
            if (filterSection && sectionName.toLowerCase() !== filterSection.trim().toLowerCase()) return false;
            if (filterItemCheck && itemCheckName.toLowerCase() !== filterItemCheck.trim().toLowerCase()) return false;

            // Running Model filter logic:
            if (activeModelFilter) {
                // Single model badge selected by user
                if (rowModel.toLowerCase() !== activeModelFilter.model.toLowerCase() ||
                    lineName.toLowerCase() !== activeModelFilter.line.toLowerCase() ||
                    sectionName.toLowerCase() !== activeModelFilter.section.toLowerCase()) {
                    return false;
                }
            } else if (runningModelsList && runningModelsList.length > 0) {
                // If running models exist, filter table to show ONLY rows belonging to active running models
                let isRunning = runningModelsList.some(m =>
                    m.model_name.trim().toLowerCase() === rowModel.toLowerCase() &&
                    m.line_name.trim().toLowerCase() === lineName.toLowerCase() &&
                    m.section_name.trim().toLowerCase() === sectionName.toLowerCase()
                );
                if (!isRunning) return false;
            }

            return true;
        }
    );

    let masterSpecsListDtc = [];

    function updateListSectionFilterOptions() {
        if (!masterSpecsListDtc || masterSpecsListDtc.length === 0) return;
        let selectedLine = $('#filter-line').val();
        let selectedSection = $('#filter-section').val();

        let filteredSpecs = masterSpecsListDtc;
        if (selectedLine) {
            filteredSpecs = filteredSpecs.filter(s => s.line_name === selectedLine);
        }

        let availableSections = [...new Set(filteredSpecs.map(s => s.section_name).filter(Boolean))].sort();

        let sectionOpts = '<option value="">All Sections</option>';
        availableSections.forEach(sec => {
            sectionOpts += `<option value="${sec}">${sec}</option>`;
        });
        $('#filter-section').html(sectionOpts);

        if (selectedSection && availableSections.includes(selectedSection)) {
            $('#filter-section').val(selectedSection);
        } else {
            $('#filter-section').val('');
        }

        updateListItemCheckFilterOptions();
    }

    function updateListItemCheckFilterOptions() {
        if (!masterSpecsListDtc || masterSpecsListDtc.length === 0) return;
        let selectedLine = $('#filter-line').val();
        let selectedSection = $('#filter-section').val();
        let selectedItemCheck = $('#filter-item-check').val();

        let filteredSpecs = masterSpecsListDtc;
        if (selectedLine) {
            filteredSpecs = filteredSpecs.filter(s => s.line_name === selectedLine);
        }
        if (selectedSection) {
            filteredSpecs = filteredSpecs.filter(s => s.section_name === selectedSection);
        }

        let availableItemChecks = [...new Set(filteredSpecs.map(s => s.item_check_name).filter(Boolean))].sort();

        let itemCheckOpts = '<option value="">All Item Checks</option>';
        availableItemChecks.forEach(ic => {
            itemCheckOpts += `<option value="${ic}">${ic}</option>`;
        });
        $('#filter-item-check').html(itemCheckOpts);

        if (selectedItemCheck && availableItemChecks.includes(selectedItemCheck)) {
            $('#filter-item-check').val(selectedItemCheck);
        } else {
            $('#filter-item-check').val('');
        }
    }

    $('#filter-line').on('change', function () {
        updateListSectionFilterOptions();
        if (table) table.draw();
    });

    $('#filter-section').on('change', function () {
        updateListItemCheckFilterOptions();
        if (table) table.draw();
    });

    $('#filter-item-check, #filter-oos-only').on('change', function () {
        if (table) table.draw();
    });

    function loadRunningModels() {
        let line = $('#filter-line').val() || '';
        let section = $('#filter-section').val() || '';
        let today = new Date();
        let yyyy = today.getFullYear();
        let mm = String(today.getMonth() + 1).padStart(2, '0');
        let currentMonth = `${yyyy}-${mm}`;

        $.ajax({
            url: 'Script/php/dtc/c_dtc_running_model.php',
            type: 'GET',
            cache: false,
            data: {
                action: 'get',
                month: currentMonth
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    let html = '';
                    runningModelsList = [];
                    if (res.data && res.data.length > 0) {
                        runningModelsList = res.data;
                        window.runningModelsList = res.data;

                        // Group running models by Line - Section
                        let grouped = {};
                        res.data.forEach(r => {
                            let secKey = r.section_name ? (r.line_name ? `${r.line_name} - ${r.section_name}` : r.section_name) : (r.line_name || 'General');
                            if (!grouped[secKey]) {
                                grouped[secKey] = {
                                    line_name: r.line_name,
                                    section_name: r.section_name,
                                    models: []
                                };
                            }
                            grouped[secKey].models.push(r);
                        });

                        let totalModels = res.data.length;
                        let totalSections = Object.keys(grouped).length;
                        if ($('#rm-count-text').length) {
                            $('#rm-count-text').text(`${totalModels} Model${totalModels > 1 ? 's' : ''} (${totalSections} Section${totalSections > 1 ? 's' : ''})`);
                        } else {
                            $('#rm-active-count-badge').text(`${totalModels} Model${totalModels > 1 ? 's' : ''} (${totalSections} Section${totalSections > 1 ? 's' : ''})`);
                        }

                        let rowsHtml = '';
                        Object.keys(grouped).forEach(secKey => {
                            let group = grouped[secKey];
                            let itemsHtml = '';
                            group.models.forEach(r => {
                                let isActive = (activeModelFilter && activeModelFilter.id === r.running_id) ? ' active-filter' : '';

                                let isAdmin = !!window.currentIsAdmin || ((window.userRole || '').toLowerCase().trim() === 'admin');
                                let userSec = (window.userSectionName || '').toLowerCase().trim();
                                let rmSec = (r.section_name || '').toLowerCase().trim();
                                let canDelete = isAdmin || (userSec !== '' && userSec === rmSec);

                                let deleteBtnHtml = canDelete
                                    ? `<i class="fa-solid fa-times btn-remove-rm" data-id="${r.running_id}" data-section="${r.section_name}" title="Remove Running Model"></i>`
                                    : '';

                                itemsHtml += `<span class="running-model-badge${isActive}" data-id="${r.running_id}" data-model="${r.model_name}" data-line="${r.line_name}" data-section="${r.section_name}" title="Click to filter table by ${r.model_name}">
                                            <i class="fa-solid fa-cube"></i> ${r.model_name}
                                            <i class="fa-solid fa-pen-to-square btn-bulk-input-rm" data-model="${r.model_name}" data-line="${r.line_name}" data-section="${r.section_name}" title="Input Pengukuran Model ${r.model_name}" style="margin-left: 4px; cursor: pointer; color: #60a5fa;"></i>
                                            ${deleteBtnHtml}
                                         </span>`;
                            });

                            rowsHtml += `<tr>
                                            <td style="padding: 10px 12px; vertical-align: middle;">
                                                <span class="rm-section-badge" title="Section: ${secKey}">
                                                    <i class="fa-solid fa-industry"></i> ${secKey}
                                                </span>
                                            </td>
                                            <td style="padding: 10px 12px; vertical-align: middle;">
                                                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                                    ${itemsHtml}
                                                </div>
                                            </td>
                                         </tr>`;
                        });
                        $('#running-model-table-body').html(rowsHtml);
                    } else {
                        if ($('#rm-count-text').length) {
                            $('#rm-count-text').text('0 Models Active');
                        } else {
                            $('#rm-active-count-badge').text('0 Models Active');
                        }
                        $('#running-model-table-body').html('<tr><td colspan="2" style="padding: 12px; text-align: center; color: #64748b; font-style: italic;">(No models running)</td></tr>');
                    }

                    // Redraw table with updated running model filter
                    if (table) table.draw();

                    // Refresh missing counts & summary ticker with new running models
                    loadMissingCounts();
                    if (typeof window.reloadDTCSummaryTicker === 'function') {
                        window.reloadDTCSummaryTicker();
                    }
                }
            }
        });
    }

    // Toggle expand/collapse of Running Model Table Panel
    $(document).on('click', '#toggle-running-model-panel', function (e) {
        if ($(e.target).closest('#btn-open-add-running-model, #btn-open-ctp-matrix, .running-model-badge').length > 0) return;
        $('#running-model-table-wrapper').slideToggle(200, function () {
            let isVisible = $(this).is(':visible');
            $('#rm-panel-chevron').css('transform', isVisible ? 'rotate(0deg)' : 'rotate(-180deg)');

            // Trigger screen resize & DataTables layout recalculation on expand/minimize
            $(window).trigger('resize');
            if (typeof table !== 'undefined' && table) {
                table.columns.adjust();
                if (table.responsive) {
                    table.responsive.recalc();
                }
            }
        });
    });

    // Initial load for running models
    loadRunningModels();

    // Reload running models when Line or Section filter changes
    $('#filter-line, #filter-section').on('change', function () {
        loadRunningModels();
    });

    // Quick filter by clicking Running Model badge
    $(document).on('click', '.running-model-badge', function (e) {
        if ($(e.target).hasClass('btn-remove-rm') || $(e.target).hasClass('btn-bulk-input-rm')) return;

        let runningId = $(this).data('id');
        let modelName = $(this).data('model');
        let lineName = $(this).data('line');
        let sectionName = $(this).data('section');

        if (activeModelFilter && activeModelFilter.id === runningId) {
            activeModelFilter = null;
            $('.running-model-badge').removeClass('active-filter');
            $('#btn-open-ctp-matrix').hide();
        } else {
            activeModelFilter = { id: runningId, model: modelName, line: lineName, section: sectionName };
            $('.running-model-badge').removeClass('active-filter');
            $(this).addClass('active-filter');
            $('#btn-open-ctp-matrix').show();
        }
        if (table) table.draw();
        loadMissingCounts();
    });

    $('#btn-open-ctp-matrix').on('click', function () {
        if (!activeModelFilter) return;
        let url = `index.php?page=dtc_matrix_qualitative&model=${encodeURIComponent(activeModelFilter.model)}&line=${encodeURIComponent(activeModelFilter.line)}&section=${encodeURIComponent(activeModelFilter.section)}&month=${encodeURIComponent(currentMonth)}`;
        window.location.href = url;
    });

    // Remove running model
    $(document).on('click', '.btn-remove-rm', function (e) {
        e.stopPropagation();
        let runningId = $(this).data('id');
        let rmSection = $(this).data('section') || '';

        let isAdmin = !!window.currentIsAdmin || ((window.userRole || '').toLowerCase().trim() === 'admin');
        let userSec = (window.userSectionName || '').toLowerCase().trim();
        let canDelete = isAdmin || (userSec !== '' && userSec === rmSection.toLowerCase().trim());

        if (!canDelete) {
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak',
                text: 'Hapus running model ini hanya dapat dilakukan oleh user dari section ' + rmSection + ' atau Admin.',
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        if (confirm("Remove this model from running list?")) {
            $.ajax({
                url: 'Script/php/dtc/c_dtc_running_model.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    running_id: runningId
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        loadRunningModels();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                    }
                }
            });
        }
    });

    // Modal Add Running Model
    $(document).on('click', '#btn-open-add-running-model', function () {
        $('#form-add-running-model')[0].reset();

        if (window.dtcLines) {
            let opts = '<option value="">-- Select Line --</option>';
            window.dtcLines.forEach(l => {
                opts += `<option value="${l.line_name}">${l.line_name}</option>`;
            });
            $('#rm_line_select').html(opts);
        }
        if (window.dtcSections) {
            let opts = '<option value="">-- Select Section --</option>';
            window.dtcSections.forEach(s => {
                opts += `<option value="${s.section_name}">${s.section_name}</option>`;
            });
            $('#rm_section_select').html(opts);
        }

        let currentLine = $('#filter-line').val();
        let currentSection = $('#filter-section').val();
        if (currentLine) $('#rm_line_select').val(currentLine);
        if (currentSection) $('#rm_section_select').val(currentSection);

        loadAvailableModelsForRM();

        $('#modal-add-running-model').css('display', 'flex');
    });

    $(document).on('click', '#btn-close-rm-modal, #btn-cancel-rm', function () {
        $('#modal-add-running-model').hide();
    });

    function loadAvailableModelsForRM() {
        let line = $('#rm_line_select').val() || '';
        let section = $('#rm_section_select').val() || '';
        let month = $('#form-add-running-model input[name="target_month"]').val() || '';

        $.ajax({
            url: 'Script/php/dtc/c_dtc_running_model.php',
            type: 'GET',
            cache: false,
            data: {
                action: 'get_available_models',
                line: line,
                section: section,
                month: month
            },
            dataType: 'json',
            success: function (res) {
                let opts = '<option value="">-- Select Model --</option>';
                if (res.status === 'success' && res.models) {
                    res.models.forEach(m => {
                        opts += `<option value="${m}">${m}</option>`;
                    });
                }
                $('#rm_model_select').html(opts);
            }
        });
    }

    $(document).on('change', '#rm_line_select, #rm_section_select', function () {
        loadAvailableModelsForRM();
    });

    $('#form-add-running-model').submit(function (e) {
        e.preventDefault();
        let lineVal = $('#rm_line_select').val();
        let sectionVal = $('#rm_section_select').val();
        let modelVal = $('#rm_model_select').val();

        if (!lineVal || !sectionVal || !modelVal) {
            Swal.fire({ icon: 'warning', title: 'Form Belum Lengkap!', text: 'Line, Section, and Model Name are required.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#3b82f6' });
            return;
        }

        let btn = $('#btn-save-rm');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'Script/php/dtc/c_dtc_running_model.php',
            type: 'POST',
            data: {
                action: 'add',
                target_month: $('#form-add-running-model input[name="target_month"]').val(),
                line_name: lineVal,
                section_name: sectionVal,
                model_name: modelVal
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Running Model');
                if (res.status === 'success') {
                    $('#modal-add-running-model').hide();

                    // Reload running models and redraw table
                    loadRunningModels();
                } else if (res.status === 'info') {
                    Swal.fire({ icon: 'info', title: 'Informasi', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#3b82f6' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message || "Failed to add model", background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Running Model');
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: "Server error occurred while saving running model: " + (xhr.responseJSON ? xhr.responseJSON.message : xhr.responseText), background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    });

    // 2. Modal Logic
    const modal = document.getElementById('modal-add-dtc');
    const btnAdd = document.getElementById('btn-add-dtc');
    const btnClose = document.getElementById('btn-close-modal');
    const btnCancel = document.getElementById('btn-cancel-add');
    const formAdd = document.getElementById('form-add-dtc');

    function loadSelectOptions() {
        $.ajax({
            url: 'Script/php/dtc/c_dtc_master_data.php',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.specs) {
                    window.dtcSpecs = res.specs;
                    masterSpecsListDtc = res.specs;
                    populateSpecs();
                }
                if (res.sections) {
                    window.dtcSections = res.sections;
                    let opts = '<option value="">-- Select Section --</option>';
                    let filterOpts = '<option value="">All Sections</option>';
                    res.sections.forEach(s => {
                        opts += `<option value="${s.section_name}">${s.section_name}</option>`;
                        filterOpts += `<option value="${s.section_name}">${s.section_name}</option>`;
                    });
                    $('#cs_section').html(opts);
                    $('#filter-section').html(filterOpts);
                }
                if (res.lines) {
                    window.dtcLines = res.lines;
                    let opts = '<option value="">-- Select Line --</option>';
                    let filterOpts = '<option value="">All Lines</option>';
                    res.lines.forEach(l => {
                        opts += `<option value="${l.line_name}">${l.line_name}</option>`;
                        filterOpts += `<option value="${l.line_name}">${l.line_name}</option>`;
                    });
                    $('#cs_line').html(opts);
                    $('#filter-line').html(filterOpts);
                }

                if (res.specs) {
                    updateListSectionFilterOptions();
                }

                // Apply any filters passed in URL parameters (e.g. from Missing Data Tracker)
                applyUrlFilters();
            }
        });
    }

    function applyUrlFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        const lineParam = urlParams.get('line');
        const sectionParam = urlParams.get('section');
        const modelParam = urlParams.get('model');
        const typeParam = urlParams.get('type');

        let filterChanged = false;

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

        if (modelParam) {
            window.activeModelFilter = { model: modelParam };
            if (lineParam) window.activeModelFilter.line = lineParam;
            if (sectionParam) window.activeModelFilter.section = sectionParam;
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

    // Call once on page load to populate the Line and Section filters
    loadSelectOptions();
    applyUrlFilters();

    function populateSpecs() {
        let opts = '<option value="">-- Select Master Spec --</option>';
        if (window.dtcSpecs) {
            window.dtcSpecs.forEach(s => {
                let subName = s.sub_item_check_name && s.sub_item_check_name !== '-' ? ` - ${s.sub_item_check_name}` : '';
                opts += `<option value="${s.spec_id}">[${s.model_name}] [${s.line_name} - ${s.section_name}] ${s.process_name} (${s.item_check_name}${subName}) - ${s.data_type}</option>`;
            });
        }
        $('#spec_id').html(opts);
        if ($.fn.select2) {
            $('#spec_id').select2({
                dropdownParent: $('#modal-add-dtc'),
                width: '100%',
                placeholder: "-- Select Master Spec --"
            });
        }
    }

    if (btnAdd && modal) {
        btnAdd.onclick = function () {
            if (formAdd) formAdd.reset();
            loadSelectOptions();

            let today = new Date();
            let yyyy = today.getFullYear();
            let mm = String(today.getMonth() + 1).padStart(2, '0');
            $('input[name="target_month"]').val(`${yyyy}-${mm}`);

            modal.style.display = 'flex';
        };
    }

    if (btnClose && modal) {
        btnClose.onclick = function () { modal.style.display = 'none'; };
    }
    if (btnCancel && modal) {
        btnCancel.onclick = function () { modal.style.display = 'none'; };
    }
    if (modal) {
        window.onclick = function (event) { if (event.target == modal) { modal.style.display = "none"; } };
    }

    $('#spec_id').change(function () {
        let opt = $(this).find('option:selected');
        let lsl = opt.data('lsl');
        let usl = opt.data('usl');
        let target = opt.data('target');
        $('#add_dtc_name').val(opt.data('dtc') || '');
        $('#add_lsl').val(lsl !== undefined && lsl !== "" ? lsl : '');
        $('#add_usl').val(usl !== undefined && usl !== "" ? usl : '');
        $('#add_target_value').val(target !== undefined && target !== "" ? target : '');
    });

    $('#form-add-dtc').submit(function (e) {
        e.preventDefault();

        let btn = $('#btn-save-dtc');
        btn.prop('disabled', true).text('Saving...');

        let formData = new FormData(this);

        $.ajax({
            url: 'Script/php/dtc/c_dtc_add.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save DTC');
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        background: 'var(--bg-card)',
                        color: 'var(--text-light)',
                        confirmButtonColor: 'var(--primary)'
                    });
                    modal.style.display = 'none';
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: response.message,
                        background: 'var(--bg-card)',
                        color: 'var(--text-light)',
                        confirmButtonColor: 'var(--danger)'
                    });
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save DTC');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to connect to server',
                    background: 'var(--bg-card)',
                    color: 'var(--text-light)',
                    confirmButtonColor: 'var(--danger)'
                });
            }
        });
    });

    // Image preview for Add form
    $('#add-ref-image-input').on('change', function () {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function (e) {
                $('#add-ref-image-thumb').attr('src', e.target.result);
                $('#add-ref-image-preview').show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#add-ref-image-preview').hide();
        }
    });

    // --- Import Excel Flow ---
    const modalImport = document.getElementById('modal-import-dtc');
    const modalPreview = document.getElementById('modal-preview-excel');
    let parsedExcelData = [];

    $('#btn-download-template').click(function () {
        if (typeof XLSX === 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error!', text: 'SheetJS library is not loaded.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            return;
        }

        let headerRow = ["Jam"];
        for (let i = 1; i <= 31; i++) headerRow.push(i.toString());
        let ws_data = [headerRow];
        let times = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
        for (let i = 0; i < times.length; i++) {
            let row = [times[i]];
            for (let d = 1; d <= 31; d++) row.push("");
            ws_data.push(row);
        }

        let wb = XLSX.utils.book_new();
        let currentYear = new Date().getFullYear();

        for (let m = 1; m <= 12; m++) {
            let ws = XLSX.utils.aoa_to_sheet(ws_data);
            let monthStr = String(m).padStart(2, '0');
            let sheetName = `${monthStr}-${currentYear}`;
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
        }

        XLSX.writeFile(wb, `DTC_Template_${currentYear}.xlsx`);
    });

    function populateImportSpecs() {
        let opts = '<option value="">-- Select Master Spec --</option>';
        if (window.dtcSpecs) {
            window.dtcSpecs.forEach(s => {
                if (s.measuring_item === 'Qualitative') return; // Only Quantitative
                let subName = s.sub_item_check_name && s.sub_item_check_name !== '-' ? ` - ${s.sub_item_check_name}` : '';
                opts += `<option value="${s.spec_id}">[${s.model_name}] [${s.line_name} - ${s.section_name}] ${s.process_name} (${s.item_check_name}${subName}) - ${s.measuring_item}</option>`;
            });
        }
        $('#import_spec_id').html(opts);
        if ($.fn.select2) {
            $('#import_spec_id').select2({
                dropdownParent: $('#modal-import-dtc'),
                width: '100%',
                placeholder: "-- Select Master Spec --"
            });
        }
    }

    $('#import_is_custom').change(function () {
        if ($(this).is(':checked')) {
            $('#import_spec_wrapper').hide();
            $('#import_spec_id').removeAttr('required');
        } else {
            $('#import_spec_wrapper').show();
            $('#import_spec_id').attr('required', 'required');
        }
    });

    $('#btn-open-import').click(function () {
        $('#input-excel-file').val('');
        $('#import_is_custom').prop('checked', false).trigger('change');
        let today = new Date();
        let yyyy = today.getFullYear();
        let mm = String(today.getMonth() + 1).padStart(2, '0');
        $('#import_target_month').val(`${yyyy}-${mm}`);

        if (!window.dtcSpecs) {
            $.ajax({
                url: 'Script/php/dtc/c_dtc_master_data.php',
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (res.specs) {
                        window.dtcSpecs = res.specs;
                        populateImportSpecs();
                    }
                    if (res.sections) {
                        window.dtcSections = res.sections;
                        let opts = '<option value="">-- Select Section --</option>';
                        res.sections.forEach(s => {
                            opts += `<option value="${s.section_name}">${s.section_name}</option>`;
                        });
                        $('#cs_section').html(opts);
                    }
                    if (res.lines) {
                        window.dtcLines = res.lines;
                        let opts = '<option value="">-- Select Line --</option>';
                        res.lines.forEach(l => {
                            opts += `<option value="${l.line_name}">${l.line_name}</option>`;
                        });
                        $('#cs_line').html(opts);
                    }
                }
            });
        } else {
            populateImportSpecs();
            if (window.dtcSections) {
                let opts = '<option value="">-- Select Section --</option>';
                window.dtcSections.forEach(s => {
                    opts += `<option value="${s.section_name}">${s.section_name}</option>`;
                });
                $('#cs_section').html(opts);
            }
            if (window.dtcLines) {
                let opts = '<option value="">-- Select Line --</option>';
                window.dtcLines.forEach(l => {
                    opts += `<option value="${l.line_name}">${l.line_name}</option>`;
                });
                $('#cs_line').html(opts);
            }
        }
        $(modalImport).css('display', 'flex');
    });

    $('#btn-close-import-modal, #btn-cancel-import').click(function () {
        $(modalImport).hide();
    });

    $('#btn-cancel-preview-excel').click(function () {
        $(modalPreview).hide();
    });

    $('#btn-preview-excel').click(function () {
        let month = $('#import_target_month').val();
        let specId = $('#import_spec_id').val();
        let isCustom = $('#import_is_custom').is(':checked');
        let fileInput = document.getElementById('input-excel-file');

        if (!month || (!isCustom && !specId) || !fileInput.files.length) {
            Swal.fire({ icon: 'warning', title: 'Form Belum Lengkap!', text: 'Please select Target Month, Master Data (or check Custom), and upload an Excel file.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#3b82f6' });
            return;
        }

        if (isCustom) {
            $('#custom-spec-form').show();
        } else {
            $('#custom-spec-form').hide();
        }

        const file = fileInput.files[0];
        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                let fallbackMonth = month;
                parsedExcelData = [];
                let tbodyHtml = '';

                // Helper to parse sheet name into YYYY-MM
                function parseSheetMonth(sheetName) {
                    const months = {
                        'jan': '01', 'januari': '01', 'january': '01',
                        'feb': '02', 'februari': '02', 'february': '02',
                        'mar': '03', 'maret': '03', 'march': '03',
                        'apr': '04', 'april': '04',
                        'mei': '05', 'may': '05',
                        'jun': '06', 'juni': '06', 'june': '06',
                        'jul': '07', 'juli': '07', 'july': '07',
                        'agu': '08', 'agustus': '08', 'august': '08', 'aug': '08',
                        'sep': '09', 'september': '09',
                        'okt': '10', 'oktober': '10', 'october': '10', 'oct': '10',
                        'nov': '11', 'november': '11',
                        'des': '12', 'desember': '12', 'december': '12', 'dec': '12'
                    };

                    let sn = sheetName.trim().toLowerCase();

                    let m = sn.match(/^(\d{4})-(\d{2})$/);
                    if (m) return `${m[1]}-${m[2]}`;

                    m = sn.match(/^(\d{2})-(\d{4})$/);
                    if (m) return `${m[2]}-${m[1]}`;

                    m = sn.match(/^([a-z]+)[\s-]+(\d{4})$/);
                    if (m) {
                        if (months[m[1]]) return `${m[2]}-${months[m[1]]}`;
                    }
                    return fallbackMonth;
                }

                workbook.SheetNames.forEach(sheetName => {
                    let sheetMonth = parseSheetMonth(sheetName);
                    const sheet = workbook.Sheets[sheetName];
                    const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false });

                    if (!rows || rows.length === 0) return;

                    let days = [];
                    for (let col = 1; col < rows[0].length; col++) {
                        let dayLabel = rows[0][col];
                        if (dayLabel !== undefined && dayLabel !== null && dayLabel !== '') {
                            days.push({ colIndex: col, day: parseInt(dayLabel) });
                        }
                    }

                    days.forEach(d => {
                        let testDate = new Date(`${sheetMonth}-${String(d.day).padStart(2, '0')}T00:00:00`);
                        if (isNaN(testDate.getTime()) || testDate.getDate() !== d.day) {
                            return; // Skip invalid dates like Feb 30
                        }

                        let s = [];
                        let hasData = false;
                        for (let r = 1; r <= 24; r++) {
                            if (rows[r]) {
                                let val = rows[r][d.colIndex];
                                if (val !== undefined && val !== null && val !== "") {
                                    val = val.toString().trim();
                                    if (val.includes(',')) {
                                        val = val.replace(/\./g, '').replace(',', '.');
                                    }
                                    s.push(val);
                                    if (val !== "") hasData = true;
                                } else {
                                    s.push("");
                                }
                            } else {
                                s.push("");
                            }
                        }

                        let dateStr = `${sheetMonth}-${String(d.day).padStart(2, '0')}`;
                        let rowDate = new Date(`${dateStr}T00:00:00`);
                        let todayObj = new Date();
                        todayObj.setHours(0, 0, 0, 0);

                        let shouldLock = false;
                        if (rowDate < todayObj) {
                            shouldLock = true; // Auto lock past dates
                        } else if (!hasData) {
                            shouldLock = true; // Auto lock empty present/future dates
                        }

                        parsedExcelData.push({
                            date: dateStr,
                            samples: s,
                            remarks: "",
                            is_closed: shouldLock ? 1 : 0
                        });

                        let trStyle = shouldLock ? "background: rgba(239, 68, 68, 0.1);" : "";
                        let lockIcon = shouldLock ? ' <i class="fa-solid fa-lock" style="color: var(--danger); font-size: 10px;" title="Auto Locked (Past Date or Empty)"></i>' : "";

                        tbodyHtml += `<tr style="${trStyle}">
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05);">${dateStr}${lockIcon}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[0]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[1]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[2]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[3]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[4]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[5]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[6]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[7]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[8]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">${s[9]}</td>
                        <td style="padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:center;">-</td>
                    </tr>`;
                    });
                });

                $('#preview-row-count').text(parsedExcelData.length + ' rows');
                $('#preview-excel-tbody').html(tbodyHtml);
                $(modalPreview).css('display', 'flex');
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal Membaca File Excel!', text: err.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        };
        reader.readAsArrayBuffer(file);
    });

    $('#btn-save-excel').click(function () {
        if (parsedExcelData.length === 0) return;

        let month = $('#import_target_month').val();
        let specId = $('#import_spec_id').val();
        let isCustom = $('#import_is_custom').is(':checked');
        let customSpec = {};

        if (isCustom) {
            let lslVal = parseFloat($('#cs_lsl').val() || 50);
            let uslVal = parseFloat($('#cs_usl').val() || 170);
            let targetVal = (lslVal + uslVal) / 2;

            customSpec = {
                line_name: $('#cs_line').val(),
                section_name: $('#cs_section').val(),
                process_name: $('#cs_process').val(),
                model_name: $('#cs_model').val(),
                item_check_name: $('#cs_item_check').val(),
                data_type: $('#cs_data_type').val(),
                measuring_item: $('#cs_measuring').val(),
                target_value: targetVal,
                lsl: lslVal,
                usl: uslVal,
                uom: $('#cs_uom').val()
            };

            if (!customSpec.line_name || !customSpec.item_check_name || !customSpec.measuring_item) {
                Swal.fire({ icon: 'warning', title: 'Form Belum Lengkap!', text: 'Please fill in at least Line Name, Item Check Name, and Measuring Item for the custom master data.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#3b82f6' });
                return;
            }
        }

        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'Script/php/dtc/c_dtc_measurement_import.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                spec_id: specId,
                target_month: month,
                rows: parsedExcelData,
                is_custom: isCustom,
                custom_spec: customSpec
            }),
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-upload"></i> Confirm & Save');
                try {
                    let result = typeof res === 'object' ? res : JSON.parse(res);
                    if (result.status === 'success') {
                        $(modalPreview).hide();
                        $(modalImport).hide();
                        Swal.fire({
                            icon: 'success',
                            title: 'Import Successful',
                            text: result.message,
                            background: '#1e293b',
                            color: '#f8fafc'
                        });
                        table.ajax.reload(null, false);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error Import!', text: result.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Format Tidak Valid!', text: 'Invalid server response.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-upload"></i> Confirm & Save');
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Server error during import.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    });
});

// Global function to handle delete action
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

// Modal Generate Bulan Ini
$(document).on('click', '#btn-generate-month-modal', function () {
    if (typeof window.currentIsAdmin !== 'undefined' && !window.currentIsAdmin) {
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak',
            text: 'Hanya Admin yang dapat menggunakan fitur Generate Bulan Ini.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    $('#modal-generate-month').css('display', 'flex');
});

$(document).on('click', '#btn-close-gen-modal, #btn-cancel-gen', function () {
    $('#modal-generate-month').hide();
});

$(document).on('submit', '#form-generate-month', function (e) {
    e.preventDefault();

    let sourceMonth = $('#gen_source_month').val();
    let targetMonth = $('#gen_target_month').val();

    if (!sourceMonth || !targetMonth) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Belum Lengkap',
            text: 'Bulan asal dan bulan tujuan wajib diisi.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    if (sourceMonth === targetMonth) {
        Swal.fire({
            icon: 'warning',
            title: 'Bulan Sama',
            text: 'Bulan asal (bulan lalu) dan bulan tujuan (bulan ini) tidak boleh sama.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    let btn = $('#btn-submit-gen');
    let origHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Mengenerate...');

    $.ajax({
        url: 'Script/php/dtc/c_dtc_generate_month.php',
        type: 'POST',
        data: {
            source_month: sourceMonth,
            target_month: targetMonth
        },
        dataType: 'json',
        success: function (res) {
            btn.prop('disabled', false).html(origHtml);
            if (res.status === 'success') {
                $('#modal-generate-month').hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Generate Berhasil!',
                    html: res.message.replace(/\n/g, '<br>'),
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#10b981'
                });
                if (typeof loadRunningModels === 'function') {
                    loadRunningModels();
                }
                if ($('#dtc-table').length && $.fn.DataTable.isDataTable('#dtc-table')) {
                    $('#dtc-table').DataTable().ajax.reload(null, false);
                }
            } else if (res.status === 'warning') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Perhatian',
                    text: res.message,
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#f59e0b'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: res.message,
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444'
                });
            }
        },
        error: function () {
            btn.prop('disabled', false).html(origHtml);
            Swal.fire({
                icon: 'error',
                title: 'Error Server!',
                text: 'Terjadi kesalahan sistem saat memproses request generate.',
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            });
        }
    });
});

// ==============================================================================
// BULK MEASUREMENT INPUT FOR ALL RUNNING MODELS (MASTER FULLSCREEN FORM)
// ==============================================================================

let bulkFormRefreshInterval = null;
let bulkAlarmLoopTimer = null;
let isBulkAlarmMuted = false;
window.currentBulkFilterModel = '';
window.currentBulkFilterLine = '';
window.currentBulkFilterSection = '';
window.currentBulkFilterParamId = 0;

window.openBulkInputModal = function (modelName, lineName, sectionName, paramId) {
    window.currentBulkFilterModel = modelName || '';
    window.currentBulkFilterLine = lineName || '';
    window.currentBulkFilterSection = sectionName || '';
    window.currentBulkFilterParamId = paramId || 0;

    // Set sound alarm state: Muted if on detail view (#matrix-container), Unmuted (Sound ON) if on DTC List page!
    if ($('#matrix-container').length > 0) {
        isBulkAlarmMuted = true;
        $('#mute-icon').attr('class', 'fa-solid fa-volume-xmark');
        $('#mute-btn-text').text('Unmute');
        $('#btn-toggle-bulk-mute').css({ 'background': 'rgba(100,116,139,0.3)', 'color': '#cbd5e1', 'border-color': 'rgba(255,255,255,0.2)' });
    } else {
        isBulkAlarmMuted = false;
        $('#mute-icon').attr('class', 'fa-solid fa-volume-high');
        $('#mute-btn-text').text('Mute Alarm');
        $('#btn-toggle-bulk-mute').css({ 'background': 'rgba(239,68,68,0.25)', 'color': '#f87171', 'border-color': 'rgba(239,68,68,0.4)' });
    }

    // CRITICAL FIX: Move modal to direct child of <body> to escape any parent CSS transform/zoom
    // (body.style.zoom or body.style.transform breaks position:fixed stacking context)
    let $modal = $('#modal-bulk-input');
    if ($modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }
    if ($('#bulk_date_input').length > 0) {
        $('#bulk_date_input').val(getManufacturingProdDateStr());
    }
    $modal.css({ 'display': 'flex', 'z-index': '99999' });
    loadBulkFormData();

    if (bulkFormRefreshInterval) clearInterval(bulkFormRefreshInterval);

    // Auto refresh form data every 1 minute (60,000 ms)
    bulkFormRefreshInterval = setInterval(function () {
        if ($('#modal-bulk-input').is(':visible')) {
            let isUserTyping = ($(':focus').hasClass('bulk-sample-quant-input') || $(':focus').hasClass('bulk-remarks-input'));
            if (!isUserTyping) {
                loadBulkFormData();
            } else {
                highlightUnfilledCellsAndAlarm();
            }
        } else {
            clearInterval(bulkFormRefreshInterval);
            bulkFormRefreshInterval = null;
        }
    }, 60000);
}

function getManufacturingProdDateStr() {
    let now = new Date();
    if (now.getHours() < 7) {
        now.setDate(now.getDate() - 1);
    }
    let y = now.getFullYear();
    let m = String(now.getMonth() + 1).padStart(2, '0');
    let d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

window.loadBulkFormData = function loadBulkFormData() {
    let dateVal = $('#bulk_date_input').val() || getManufacturingProdDateStr();
    let lineVal = window.currentBulkFilterLine || $('#filter-line').val() || '';
    let sectionVal = window.currentBulkFilterSection || $('#filter-section').val() || '';
    let modelVal = window.currentBulkFilterModel || '';
    let paramVal = window.currentBulkFilterParamId || 0;

    console.log('[DEBUG BULK] Loading Bulk Form Data with params:', { line: lineVal, section: sectionVal, model: modelVal, param_id: paramVal, date: dateVal });

    $('#bulk-empty-state').hide();
    $('#bulk-loading-state').show();
    $('#form-bulk-save').hide();

    $.ajax({
        url: 'Script/php/dtc/c_dtc_bulk_get.php',
        type: 'GET',
        cache: false,
        data: {
            line: lineVal,
            section: sectionVal,
            model: modelVal,
            param_id: paramVal,
            date: dateVal
        },
        dataType: 'json',
        success: function (res) {
            console.log('[DEBUG BULK] AJAX Response Received:', res);
            $('#bulk-loading-state').hide();

            if (res.status !== 'success' || !res.models || res.models.length === 0) {
                console.warn('[DEBUG BULK] Warning or No models found:', res.message);
                let warningMsg = res.message || 'Tidak ada Running Model aktif ditemukan.';
                $('#bulk-items-container').html(`
                    <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                        <i class="fa-solid fa-box-open fa-3x" style="margin-bottom:16px; display:block; opacity:0.5; color:#60a5fa;"></i>
                        <p style="font-size:15px; font-weight:600; color:#f8fafc; margin-bottom:8px;">Tidak Ada Data</p>
                        <p style="font-size:13px; color:#94a3b8;">${warningMsg}</p>
                        <p style="font-size:11px; color:#64748b; margin-top:12px;">Pastikan sudah ada Running Model aktif yang di-input untuk bulan ini dan sesuai dengan section/line Anda.</p>
                    </div>
                `);
                // Tampilkan form container agar pesan terlihat, tapi sembunyikan tombol save
                $('#form-bulk-save').show();
                $('#btn-submit-bulk-save').hide();
                return;
            }

            window.currentBulkModels = res.models || [];
            window.currentBulkTimeLabels = res.time_labels || [];
            window.currentIsAdmin = !!res.is_admin;

            console.log('[DEBUG BULK] Rendering models count:', res.models.length, 'Time labels:', res.time_labels);
            renderBulkAllModelsForm(res.models, res.time_labels);
            $('#form-bulk-save').show();
            $('#btn-submit-bulk-save').show();
            updateBulkSummaryCount();
            highlightUnfilledCellsAndAlarm();
        },
        error: function (xhr, status, error) {
            console.error('[DEBUG BULK] AJAX Error:', { status: status, error: error, responseText: xhr.responseText });
            $('#bulk-loading-state').hide();
            $('#bulk-empty-state').show();
            Swal.fire({
                icon: 'error',
                title: 'Error Server',
                text: 'Gagal memuat form pengukuran.',
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            });
        }
    });
}

function renderBulkAllModelsForm(models, timeLabels) {
    let container = $('#bulk-items-container');
    container.empty();

    if (!models || models.length === 0) {
        container.html('<div style="text-align:center; padding:40px; color:#94a3b8;">Tidak ada Running Model aktif ditemukan untuk periode ini.</div>');
        return;
    }

    let html = '';

    models.forEach((m, mIdx) => {
        let itemsList = m.parameters || m.items || [];
        if (!itemsList || itemsList.length === 0) return;

        // Group items by process_name
        let grouped = {};
        itemsList.forEach(item => {
            let proc = item.process_name || 'Umum';
            if (!grouped[proc]) grouped[proc] = [];
            grouped[proc].push(item);
        });

        let globalSeq = 1; for (let procName in grouped) {
            html += `
            <div class="bulk-process-block" style="background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                <!-- Integrated Compact Header (Model + Process) -->
                <div style="background: rgba(30,41,59,0.85); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 8px 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: rgba(59,130,246,0.25); color: #60a5fa; border: 1px solid rgba(59,130,246,0.4); padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 11px;">
                            <i class="fa-solid fa-cube"></i> MODEL: ${m.model_name}
                        </span>
                        <span style="font-size: 11px; color: #94a3b8;">(${m.line_name} &bull; ${m.section_name})</span>
                        <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #38bdf8; display: inline-flex; align-items: center; gap: 6px; margin-left: 6px;">
                            <i class="fa-solid fa-gears" style="color: #60a5fa;"></i> Process: ${procName}
                        </h4>
                    </div>
                    <span style="font-size: 10px; color: #94a3b8; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 10px;">
                        ${grouped[procName].length} Item
                    </span>
                </div>

                <div class="bulk-table-container" style="padding: 8px; overflow-x: auto; cursor: grab; user-select: none;">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; color: #f8fafc;">
                        <thead>
                            <tr style="background: rgba(15,23,42,0.95); border-bottom: 2px solid rgba(255,255,255,0.1); color: #94a3b8; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                                <th style="position: sticky; left: 0; top: 0; z-index: 25; background: #0f172a; padding: 10px 6px; text-align: center; width: 40px; min-width: 40px; border-right: 1px solid rgba(255,255,255,0.08); border-bottom: 2px solid rgba(255,255,255,0.1);">No</th>
                                <th style="position: sticky; left: 40px; top: 0; z-index: 25; background: #0f172a; padding: 10px; text-align: left; min-width: 280px; width: 280px; border-right: 1px solid rgba(255,255,255,0.08); border-bottom: 2px solid rgba(255,255,255,0.1);">Item Check & Kategori</th>
                                <th style="position: sticky; left: 320px; top: 0; z-index: 25; background: #0f172a; padding: 10px; text-align: left; min-width: 170px; width: 170px; border-right: 2px solid rgba(59, 130, 246, 0.4); box-shadow: 4px 0 10px rgba(0,0,0,0.5); border-bottom: 2px solid rgba(255,255,255,0.1);">Checkpoint & Spesifikasi</th>
            `;

            (timeLabels || []).forEach((lbl, idx) => {
                let due = isSlotDue(lbl);
                let nextDueIdx = timeLabels.findIndex(l => !isSlotDue(l));
                let currentSlotIdx = (nextDueIdx > 0) ? (nextDueIdx - 1) : (nextDueIdx === -1 ? timeLabels.length - 1 : 0);
                let isCurrentSlotHeader = (idx === currentSlotIdx);
                let thStyle = isCurrentSlotHeader
                    ? 'background: rgba(59, 130, 246, 0.35); border: 1px solid rgba(59, 130, 246, 0.8); box-shadow: 0 0 10px rgba(59,130,246,0.3);'
                    : 'background: rgba(30,41,59,0.5); border-left: 1px solid rgba(255,255,255,0.05);';

                html += `
                    <th style="position: sticky; top: 0; z-index: 15; padding: 8px 4px; text-align: center; min-width: 85px; border-bottom: 2px solid rgba(255,255,255,0.1); ${thStyle}">
                        S${idx + 1} ${isCurrentSlotHeader ? '<span style="color:#38bdf8; font-size:9px; font-weight:900;">● AKTIF</span>' : ''}<br><span style="font-size:10px; color:${isCurrentSlotHeader ? '#38bdf8' : '#60a5fa'}; font-weight:${isCurrentSlotHeader ? '900' : 'normal'}; text-transform:none;">${lbl}</span>
                    </th>
                `;
            });

            html += `
                            </tr>
                        </thead>
                        <tbody>
            `;

            grouped[procName].forEach(p => {
                let dtype = p.data_type || 'CTQ';
                let badgeBg = 'rgba(59, 130, 246, 0.2)';
                let badgeColor = '#60a5fa';
                if (dtype === 'CTP') { badgeBg = 'rgba(16, 185, 129, 0.2)'; badgeColor = '#34d399'; }
                if (dtype === 'Time Check') { badgeBg = 'rgba(245, 158, 11, 0.2)'; badgeColor = '#fbbf24'; }
                if (dtype === 'F/Proof') { badgeBg = 'rgba(236, 72, 153, 0.2)'; badgeColor = '#f472b6'; }

                p.checkpoints.forEach(cp => {
                    let isQuant = ((cp.checkpoint_type || p.measuring_item || p.data_type || '').toLowerCase() === 'quantitative')
                        || ((p.measuring_item || '').toLowerCase() === 'quantitative')
                        || (dtype === 'CTQ' || dtype === 'CTP');

                    let lslAttr = (cp.lsl !== null && cp.lsl !== undefined && cp.lsl !== '') ? cp.lsl : ((p.lsl !== null && p.lsl !== undefined && p.lsl !== '') ? p.lsl : '');
                    let uslAttr = (cp.usl !== null && cp.usl !== undefined && cp.usl !== '') ? cp.usl : ((p.usl !== null && p.usl !== undefined && p.usl !== '') ? p.usl : '');
                    let targetAttr = (cp.target_value !== null && cp.target_value !== undefined && cp.target_value !== '') ? cp.target_value : ((p.target_value !== null && p.target_value !== undefined && p.target_value !== '') ? p.target_value : '');

                    let specHtml = '';
                    if (isQuant) {
                        let limitsArr = [];
                        if (lslAttr !== '' && lslAttr !== null && lslAttr !== undefined) {
                            limitsArr.push(`LSL: <strong style="color:#38bdf8;">${lslAttr}</strong>`);
                        }
                        if (targetAttr !== '' && targetAttr !== null && targetAttr !== undefined) {
                            limitsArr.push(`Target: <strong style="color:#cbd5e1;">${targetAttr}</strong>`);
                        }
                        if (uslAttr !== '' && uslAttr !== null && uslAttr !== undefined) {
                            limitsArr.push(`USL: <strong style="color:#38bdf8;">${uslAttr}</strong>`);
                        }

                        if (limitsArr.length > 0) {
                            specHtml += `<div style="font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 4px;">${limitsArr.join(' | ')}</div>`;
                        }
                        if (cp.spec_value) {
                            specHtml += `<div style="font-size: 10px; color: #cbd5e1; margin-bottom: 4px;">Spec: <strong style="color:#e2e8f0;">${cp.spec_value}</strong> ${p.uom ? `(${p.uom})` : ''}</div>`;
                        }
                        if (limitsArr.length === 0 && !cp.spec_value) {
                            specHtml += `<div style="font-size: 11px; color: #94a3b8; margin-bottom: 4px;">Spec: -</div>`;
                        }
                    } else {
                        let specText = cp.spec_value || p.specification || '-';
                        specHtml = `<div style="font-size: 11px; color: #94a3b8; line-height: 1.35; margin-bottom: 6px;">Spec: <strong style="color:#e2e8f0;">${specText}</strong> ${p.uom ? `(${p.uom})` : ''}</div>`;
                    }

                    html += `
                    <tr class="bulk-item-row" data-model="${m.model_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-type="${isQuant ? 'Quantitative' : 'Qualitative'}" data-name="${m.model_name} - ${p.item_check_name} - ${cp.checkpoint_name}" style="border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(15,23,42,0.3);">
                        <td style="position: sticky; left: 0; z-index: 10; background: #0f172a; padding: 12px 6px; text-align: center; color: #64748b; font-weight: 700; vertical-align: middle; width: 40px; min-width: 40px; border-right: 1px solid rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.05);">${globalSeq++}</td>
                        
                        <!-- ITEM CHECK & KATEGORI (FIXED STICKY WITH LARGE REFERENCE IMAGE) -->
                        <td style="position: sticky; left: 40px; z-index: 10; background: #0f172a; padding: 12px 10px; vertical-align: middle; min-width: 280px; width: 280px; border-right: 1px solid rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                ${cp.reference_image ? `
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 4px; flex-shrink: 0; background: rgba(0,0,0,0.4); padding: 5px; border-radius: 8px; border: 1px solid rgba(59,130,246,0.4); box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                                        <img src="${cp.reference_image}" alt="Ref Image" class="btn-preview-bulk-img" data-img="${cp.reference_image}" data-title="${m.model_name} - ${p.item_check_name} (${cp.checkpoint_name})"
                                             style="width: 115px; height: 155px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(59,130,246,0.6); cursor: pointer; background: #0f172a; transition: transform 0.2s;"
                                             title="Klik untuk memperbesar gambar acuan">
                                        <span class="btn-preview-bulk-img" data-img="${cp.reference_image}" data-title="${m.model_name} - ${p.item_check_name} (${cp.checkpoint_name})" 
                                              style="font-size: 10px; color: #60a5fa; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; margin-top: 1px;">
                                            <i class="fa-solid fa-magnifying-glass-plus"></i> Acuan
                                        </span>
                                    </div>
                                ` : `
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 115px; height: 140px; flex-shrink: 0; background: rgba(15,23,42,0.5); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1); font-size: 10px; color: #64748b;" title="Tanpa Gambar Acuan">
                                        <i class="fa-regular fa-image" style="font-size: 20px; opacity: 0.4; margin-bottom: 4px;"></i>
                                        <span style="font-size: 9px;">Tanpa Gambar</span>
                                    </div>
                                `}
                                <div style="flex: 1; text-align: left;">
                                    <div style="font-weight: 700; color: #f8fafc; margin-bottom: 4px; font-size: 13px;">${p.item_check_name}</div>
                                    ${p.sub_item_check_name ? `<div style="font-size:11px; color:#cbd5e1; margin-bottom:4px;">Sub: ${p.sub_item_check_name}</div>` : ''}
                                    <span style="background: ${badgeBg}; color: ${badgeColor}; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; display: inline-block;">
                                        ${dtype}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- CHECKPOINT & SPESIFIKASI (FIXED STICKY WITH DIVIDER LINE) -->
                        <td style="position: sticky; left: 320px; z-index: 10; background: #0f172a; padding: 12px 10px; vertical-align: middle; min-width: 170px; width: 170px; border-right: 2px solid rgba(59, 130, 246, 0.4); box-shadow: 4px 0 10px rgba(0,0,0,0.5); border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 13px; font-weight: 700; color: #38bdf8; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                                <i class="fa-regular fa-circle-dot" style="font-size:10px; color: #60a5fa;"></i> ${cp.checkpoint_name}
                            </div>
                            ${specHtml}
                            <button type="button" class="btn-reset-row-null" title="Kosongkan/Reset seluruh slot jam sampel checkpoint ini ke NULL" style="padding: 3px 8px; font-size: 9.5px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.15); color: #f87171; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;">
                                <i class="fa-solid fa-rotate-left" style="font-size: 9px;"></i> Reset Checkpoint (NULL)
                            </button>
                        </td>
                    `;

                    let dateVal = $('#bulk_date_input').val() || (typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : '');

                    (timeLabels || []).forEach((lbl, slotIdx) => {
                        let slotData = (cp.slots && cp.slots[lbl]) ? cp.slots[lbl] : ((cp.measurements_by_slot && cp.measurements_by_slot[lbl]) ? cp.measurements_by_slot[lbl] : {});
                        let slotVal = (slotData.val !== undefined && slotData.val !== null) ? String(slotData.val).trim() : '';
                        let isFilled = (slotVal !== '');
                        let todayStr = typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : new Date().toISOString().slice(0, 10);
                        let isAdminUser = !!window.currentIsAdmin || !!window.isAdmin || ((window.userRole || '').toLowerCase().trim() === 'admin');

                        let isBeforeCreation = isSlotBeforeModelCreation(lbl, timeLabels, slotIdx, m.created_at, dateVal);
                        let isOpen = isSlotOpenForInput(lbl, timeLabels, slotIdx, m.created_at, dateVal);
                        let isLockedForUser = isFilled && !isAdminUser;

                        if (isAdminUser && dateVal < todayStr) {
                            isBeforeCreation = false;
                            isLockedForUser = false;
                            isOpen = true;
                        }

                        html += `<td style="padding: 6px 3px; text-align: center; vertical-align: middle; border-left: 1px solid rgba(255,255,255,0.03);">`;

                        if (isQuant) {
                            if (isBeforeCreation) {
                                let createdTimeDisplay = m.created_at ? m.created_at.substring(11, 16) : '';
                                html += `
                                    <input type="text" class="bulk-sample-quant-input slot-disabled-before-creation" disabled 
                                           data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" 
                                           value="-" title="Model baru di-add pada jam ${createdTimeDisplay}. Slot jam ${lbl} (jam sebelumnya) tidak perlu diisi." 
                                           style="width: 100%; max-width: 75px; padding: 5px 3px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.05); background: rgba(15,23,42,0.6); color: #64748b; font-size: 11px; font-weight: 600; text-align: center; cursor: not-allowed; opacity: 0.5;">
                                `;
                            } else if (isLockedForUser) {
                                html += `
                                    <input type="number" step="any" class="bulk-sample-quant-input slot-filled-locked" disabled 
                                           data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" 
                                           data-lsl="${lslAttr}" data-usl="${uslAttr}" data-target="${targetAttr}" 
                                           value="${slotVal}" title="Sudah terisi (${slotVal}). Hanya Admin yang dapat mengubah data." 
                                           style="width: 100%; max-width: 75px; padding: 5px 3px; border-radius: 5px; border: 1px solid rgba(16,185,129,0.4); background: rgba(16,185,129,0.18); color: #34d399; font-size: 11px; font-weight: 800; text-align: center; cursor: not-allowed;">
                                `;
                            } else if (!isOpen) {
                                html += `
                                    <input type="number" step="any" class="bulk-sample-quant-input slot-disabled" disabled 
                                           data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" 
                                           data-lsl="${lslAttr}" data-usl="${uslAttr}" data-target="${targetAttr}" 
                                           value="${slotVal}" placeholder="Belum" title="Slot jam ${lbl} belum waktunya diisi" 
                                           style="width: 100%; max-width: 75px; padding: 5px 3px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.08); background: rgba(15,23,42,0.4); color: #475569; font-size: 11px; font-weight: 600; text-align: center; cursor: not-allowed; opacity: 0.45;">
                                `;
                            } else {
                                let inputBg = isFilled ? 'rgba(16,185,129,0.2)' : 'rgba(15,23,42,0.8)';
                                let inputColor = isFilled ? '#34d399' : 'white';
                                let inputBorder = isFilled ? '1px solid rgba(16,185,129,0.5)' : '1px solid rgba(255,255,255,0.15)';
                                html += `
                                    <div style="display: inline-flex; align-items: center; gap: 2px; justify-content: center;">
                                        <input type="number" step="any" class="bulk-sample-quant-input" 
                                               data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" 
                                               data-lsl="${lslAttr}" data-usl="${uslAttr}" data-target="${targetAttr}" data-orig="${slotVal}" 
                                               value="${slotVal}" placeholder="-" 
                                               style="width: 100%; max-width: 70px; padding: 5px 3px; border-radius: 5px; border: ${inputBorder}; background: ${inputBg}; color: ${inputColor}; font-size: 11px; font-weight: 700; text-align: center;">
                                        <button type="button" class="btn-quant-sample-reset" title="Reset sample jam ${lbl} ke NULL" style="padding: 3px 4px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05); color: #f87171; cursor: pointer; display: ${isFilled ? 'inline-flex' : 'none'}; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-xmark" style="font-size: 8px;"></i>
                                        </button>
                                    </div>
                                `;
                            }
                        } else {
                            let isOk = (slotVal.toUpperCase() === 'OK');
                            let isNg = (slotVal.toUpperCase() === 'NG');

                            if (isBeforeCreation) {
                                let createdTimeDisplay = m.created_at ? m.created_at.substring(11, 16) : '';
                                html += `
                                    <div class="btn-group-mini-qual slot-disabled-before-creation" style="display: flex; gap: 3px; justify-content: center; opacity: 0.4; pointer-events: none;" title="Model baru di-add pada jam ${createdTimeDisplay}. Slot jam ${lbl} (jam sebelumnya) tidak perlu diisi.">
                                        <button type="button" disabled class="btn-mini-qual" style="padding: 3px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); background: rgba(15,23,42,0.4); color: #475569; cursor: not-allowed;">-</button>
                                        <input type="hidden" class="bulk-sample-qual-input slot-disabled-before-creation" data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" data-orig="" value="">
                                    </div>
                                `;
                            } else if (isLockedForUser) {
                                html += `
                                    <div class="btn-group-mini-qual slot-filled-locked" style="display: flex; gap: 3px; justify-content: center; opacity: 0.85;" title="Sudah terisi (${slotVal}). Hanya Admin yang dapat mengubah data.">
                                        ${isOk ? `
                                            <button type="button" disabled class="btn-mini-qual active-ok" data-val="OK" 
                                                    style="padding: 3px 8px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid #10b981; background: #10b981; color: #ffffff; cursor: not-allowed;">OK</button>
                                        ` : `
                                            <button type="button" disabled class="btn-mini-qual active-ng" data-val="NG" 
                                                    style="padding: 3px 8px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid #ef4444; background: #ef4444; color: #ffffff; cursor: not-allowed;">NG</button>
                                        `}
                                        <input type="hidden" class="bulk-sample-qual-input" data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" data-orig="${slotVal}" value="${slotVal}">
                                    </div>
                                `;
                            } else if (!isOpen) {
                                html += `
                                    <div class="btn-group-mini-qual slot-disabled" style="display: flex; gap: 3px; justify-content: center; opacity: 0.35; pointer-events: none;">
                                        <button type="button" disabled class="btn-mini-qual btn-mini-ok ${isOk ? 'active-ok' : ''}" data-val="OK" 
                                                style="padding: 3px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #64748b; cursor: not-allowed; ${isNg ? 'display:none;' : ''}">OK</button>
                                        <button type="button" disabled class="btn-mini-qual btn-mini-ng ${isNg ? 'active-ng' : ''}" data-val="NG" 
                                                style="padding: 3px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #64748b; cursor: not-allowed; ${isOk ? 'display:none;' : ''}">NG</button>
                                        <input type="hidden" class="bulk-sample-qual-input" data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" data-orig="${slotVal}" value="${slotVal}">
                                    </div>
                                `;
                            } else {
                                let okDisplay = isNg ? 'display: none;' : 'display: inline-flex;';
                                let ngDisplay = isOk ? 'display: none;' : 'display: inline-flex;';
                                let resetDisplay = (isOk || isNg) ? 'display: inline-flex;' : 'display: none;';

                                html += `
                                    <div class="btn-group-mini-qual" style="display: flex; gap: 2px; justify-content: center; align-items: center;">
                                        <button type="button" class="btn-mini-qual btn-mini-ok ${isOk ? 'active-ok' : ''}" data-val="OK" 
                                                style="padding: 3px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(16,185,129,0.4); background: ${isOk ? '#10b981' : 'rgba(16,185,129,0.15)'}; color: ${isOk ? '#ffffff' : '#34d399'}; cursor: pointer; ${okDisplay}">OK</button>
                                        <button type="button" class="btn-mini-qual btn-mini-ng ${isNg ? 'active-ng' : ''}" data-val="NG" 
                                                style="padding: 3px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(239,68,68,0.4); background: ${isNg ? '#ef4444' : 'rgba(239,68,68,0.15)'}; color: ${isNg ? '#ffffff' : '#f87171'}; cursor: pointer; ${ngDisplay}">NG</button>
                                        <button type="button" class="btn-mini-qual-reset" title="Reset sample jam ${lbl} ke NULL" 
                                                style="padding: 3px 4px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05); color: #f87171; cursor: pointer; ${resetDisplay} align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-xmark" style="font-size: 9px;"></i>
                                        </button>
                                        <input type="hidden" class="bulk-sample-qual-input" data-model="${m.model_name}" data-line="${m.line_name}" data-section="${m.section_name}" data-param="${p.parameter_id}" data-cp="${cp.checkpoint_id}" data-slot="${lbl}" data-orig="${slotVal}" value="${slotVal}">
                                    </div>
                                `;
                            }
                        }

                        html += `</td>`;
                    });

                    html += `</tr>`;
                });
            });

            html += `
                        </tbody>
                    </table>
                </div>
            </div>
            `;
        }
    });

    if (!html) {
        container.html('<div style="text-align:center; padding:40px; color:#94a3b8;"><i class="fa-solid fa-folder-open fa-2x" style="margin-bottom:10px; display:block; opacity:0.5;"></i><p style="font-size:14px; font-weight:600;">Tidak ada item parameter aktif yang ditemukan untuk filter section/line Anda saat ini.</p></div>');
    } else {
        container.html(html);

        // Auto-scroll to active slot column
        setTimeout(() => {
            $('.bulk-process-block').each(function () {
                let $procBlock = $(this);
                let $table = $procBlock.find('table');
                let $tableContainer = $procBlock.find('.bulk-table-container');

                if ($table.length > 0) {
                    let $activeTh = $table.find('th:contains("● AKTIF")');
                    if ($activeTh.length > 0) {
                        let activeLeft = $activeTh[0].offsetLeft;
                        let scrollTarget = Math.max(0, activeLeft - 350);
                        $tableContainer.scrollLeft(scrollTarget);
                    }
                }
            });
        }, 150);
    }
    $('.bulk-sample-quant-input').trigger('input');
    highlightUnfilledCellsAndAlarm();
    setTimeout(function () {
        let firstEmpty = $('.bulk-sample-quant-input:not(:disabled)').filter(function () {
            return !$(this).val() || $(this).val().trim() === '';
        }).first();
        if (firstEmpty.length) {
            firstEmpty.focus();
        }
    }, 150);
}

// --- Drag to Scroll (Mouse Click & Drag Horizontal Panning) ---
let isBulkDragging = false;
let bulkDragStartX = 0;
let bulkDragScrollLeft = 0;
let $activeDragContainer = null;

$(document).on('mousedown', '.bulk-table-container', function (e) {
    if ($(e.target).closest('input, button, a, select, textarea, .btn-preview-bulk-img').length > 0) {
        return;
    }
    isBulkDragging = true;
    $activeDragContainer = $(this);
    bulkDragStartX = e.pageX - $activeDragContainer.offset().left;
    bulkDragScrollLeft = $activeDragContainer.scrollLeft();
    $activeDragContainer.css('cursor', 'grabbing');
});

$(document).on('mousemove', function (e) {
    if (!isBulkDragging || !$activeDragContainer) return;
    let x = e.pageX - $activeDragContainer.offset().left;
    let walk = (x - bulkDragStartX) * 1.5;
    $activeDragContainer.scrollLeft(bulkDragScrollLeft - walk);
});

$(document).on('mouseup mouseleave', function () {
    if (isBulkDragging) {
        isBulkDragging = false;
        if ($activeDragContainer) {
            $activeDragContainer.css('cursor', 'grab');
            $activeDragContainer = null;
        }
    }
});

// Web Audio API Synthesizer for Bulk Input Alarm
function playBulkAlarmSound() {
    if (typeof isBulkAlarmMuted !== 'undefined' && isBulkAlarmMuted) return;
    try {
        let AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        let ctx = new AudioCtx();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        let now = ctx.currentTime;

        // Tone 1: Alert Beep
        let osc1 = ctx.createOscillator();
        let gain1 = ctx.createGain();
        osc1.type = 'sawtooth';
        osc1.frequency.setValueAtTime(880, now);
        gain1.gain.setValueAtTime(0.2, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.25);

        // Tone 2: Urgent High Beep
        let osc2 = ctx.createOscillator();
        let gain2 = ctx.createGain();
        osc2.type = 'sawtooth';
        osc2.frequency.setValueAtTime(1174.66, now + 0.28);
        gain2.gain.setValueAtTime(0.25, now + 0.28);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.28);
        osc2.stop(now + 0.55);

        // Tone 3: High Alarm Chime
        let osc3 = ctx.createOscillator();
        let gain3 = ctx.createGain();
        osc3.type = 'triangle';
        osc3.frequency.setValueAtTime(1396.91, now + 0.58);
        gain3.gain.setValueAtTime(0.3, now + 0.58);
        gain3.gain.exponentialRampToValueAtTime(0.001, now + 0.95);
        osc3.connect(gain3);
        gain3.connect(ctx.destination);
        osc3.start(now + 0.58);
        osc3.stop(now + 0.95);
    } catch (e) {
        console.warn('Web Audio Alarm error:', e);
    }
}

function parseSlotMinutes(slotLabel) {
    if (!slotLabel) return 0;
    let parts = slotLabel.trim().split(':');
    if (parts.length < 2) return 0;
    let h = parseInt(parts[0], 10);
    let m = parseInt(parts[1], 10);
    if (h === 24) h = 0;
    let mins = (h < 7 ? h + 24 : h) * 60 + m;
    return mins;
}

function isSlotBeforeModelCreation(slotLabel, timeLabels, slotIdx, createdAtStr, inspectionDateStr) {
    let isUserAdmin = window.currentIsAdmin || window.isAdmin || (typeof isAdmin !== 'undefined' && isAdmin);
    if (isUserAdmin) return false;

    if (!createdAtStr || !inspectionDateStr || !slotLabel) return false;

    let parts = createdAtStr.trim().split(' ');
    if (parts.length < 2) return false;
    let createdDate = parts[0];
    let createdTime = parts[1];

    if (createdDate < inspectionDateStr) return false;
    if (createdDate > inspectionDateStr) return true;

    let timeParts = createdTime.split(':');
    let cH = parseInt(timeParts[0] || '0', 10);
    let cM = parseInt(timeParts[1] || '0', 10);

    // If created before 07:00 AM on the same calendar day, it was created BEFORE shift 07:00 AM started -> active from start of shift!
    if (cH < 7) return false;

    let createdMinsFrom7 = (cH - 7) * 60 + cM;

    let nextSlotLabel = (timeLabels && timeLabels[slotIdx + 1]) ? timeLabels[slotIdx + 1] : null;
    let nextSlotMinsFrom7;
    if (nextSlotLabel) {
        let nParts = nextSlotLabel.trim().split(':');
        let nH = parseInt(nParts[0], 10);
        let nM = parseInt(nParts[1], 10);
        let nHShift = nH < 7 ? nH + 24 : nH;
        nextSlotMinsFrom7 = (nHShift - 7) * 60 + nM;
    } else {
        let cParts = slotLabel.trim().split(':');
        let sH = parseInt(cParts[0], 10);
        let sM = parseInt(cParts[1], 10);
        let sHShift = sH < 7 ? sH + 24 : sH;
        nextSlotMinsFrom7 = (sHShift - 7) * 60 + sM + 120;
    }

    return createdMinsFrom7 >= nextSlotMinsFrom7;
}

function isSlotOpenForInput(slotLabel, timeLabels, slotIdx, createdAtStr, inspectionDateStr) {
    if (!slotLabel) return false;

    if (isSlotBeforeModelCreation(slotLabel, timeLabels, slotIdx, createdAtStr, inspectionDateStr)) {
        return false;
    }

    let curSlotMins = parseSlotMinutes(slotLabel);

    let now = new Date();
    let curH = now.getHours();
    let curM = now.getMinutes();
    let nowMinutesFrom7 = (curH < 7 ? curH + 24 : curH) * 60 + curM;

    let todayStr = typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : new Date().toISOString().slice(0, 10);
    if (inspectionDateStr < todayStr) return true;
    if (inspectionDateStr > todayStr) return false;

    // Slot jam (seperti 14:40) HANYA terbuka 30 menit sebelum waktunya (mulai jam 14:10 ke atas)
    return nowMinutesFrom7 >= curSlotMins;
}

// Check if a time slot (e.g. "07:30", "09:40", "02:30") is due relative to current shift time
function isSlotDue(slotLabel) {
    if (!slotLabel) return false;
    let parts = slotLabel.trim().split(':');
    if (parts.length < 2) return false;

    let slotH = parseInt(parts[0], 10);
    let slotM = parseInt(parts[1], 10);

    let now = new Date();
    let curH = now.getHours();
    let curM = now.getMinutes();

    // Production day starts at 07:00 AM.
    // Hours < 7 (e.g. 02:30, 04:30) belong to the night shift overnight portion (next calendar day morning).
    let slotMinutes = (slotH < 7 ? slotH + 24 : slotH) * 60 + slotM;
    let curMinutes = (curH < 7 ? curH + 24 : curH) * 60 + curM;

    return slotMinutes <= curMinutes;
}

let lastUnfilledVoiceDetails = [];

// Indonesian Text-to-Speech Voice Alarm with Model, Line, and Section (Uninterrupted)
function speakBulkVoiceAlarm(unfilledDetails) {
    if (isBulkAlarmMuted) return;

    if (!('speechSynthesis' in window)) {
        playBulkAlarmSound();
        return;
    }

    // Do NOT cut off ongoing speech! Allow active sentence to finish completely naturally.
    if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
        return;
    }

    try {
        let parts = [];
        if (Array.isArray(unfilledDetails) && unfilledDetails.length > 0) {
            let itemsToSpeak = unfilledDetails.slice(0, 2);
            itemsToSpeak.forEach(item => {
                // Normalize model name for TTS: replace dots with " titik " so
                // "VT 10.11.12" is spoken as "10 titik 11 titik 12", not as time
                let rawModel = item.model || 'Model';
                let spokenModel = rawModel.replace(/\./g, ' titik ').replace(/\s+/g, ' ').trim();
                let modelStr = `Model ${spokenModel}`;
                let lineStr = item.line ? `, Line ${item.line}` : '';
                let secStr = item.section ? `, ${item.section}` : '';
                parts.push(`${modelStr}${lineStr}${secStr}, ada ${item.count} slot belum diisi.`);
            });
            if (unfilledDetails.length > 2) {
                parts.push(`Dan ${unfilledDetails.length - 2} model lainnya.`);
            }
        }

        let text = parts.length > 0 ? ('Peringatan. ' + parts.join(' ') + ' Harap segera diisi.') : 'Ada slot pengukuran yang belum diisi pada waktunya.';
        let msg = new SpeechSynthesisUtterance(text);
        msg.lang = 'id-ID';
        msg.rate = 1.0;
        msg.pitch = 1.0;
        msg.volume = 1.0;

        let voices = window.speechSynthesis.getVoices();
        let idVoice = voices.find(v => (v.lang && (v.lang.startsWith('id') || v.lang.startsWith('id-ID'))) || (v.name && v.name.toLowerCase().includes('indonesia')));
        if (idVoice) {
            msg.voice = idVoice;
        }

        window.speechSynthesis.speak(msg);
    } catch (e) {
        console.warn('Voice alarm error:', e);
        playBulkAlarmSound();
    }
}

function startBulkAlarmLoop() {
    if (bulkAlarmLoopTimer) clearInterval(bulkAlarmLoopTimer);

    let runAlarmAction = function () {
        if ($('#modal-bulk-input').is(':visible') && $('#bulk-alarm-badge').is(':visible')) {
            if (!isBulkAlarmMuted) {
                speakBulkVoiceAlarm(lastUnfilledVoiceDetails);
            }
        } else {
            stopBulkAlarmLoop();
        }
    };

    // Run first voice announcement immediately
    runAlarmAction();

    // Repeat voice alarm announcement every 9.5 seconds to give full time for sentence to finish
    bulkAlarmLoopTimer = setInterval(runAlarmAction, 9500);
}

function stopBulkAlarmLoop() {
    if (bulkAlarmLoopTimer) {
        clearInterval(bulkAlarmLoopTimer);
        bulkAlarmLoopTimer = null;
    }
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }
}

// Highlight unfilled due sample cells in red & trigger alarm voice loop
function highlightUnfilledCellsAndAlarm() {
    let unfilledDueCount = 0;
    let unfilledByModel = {};

    let dateVal = $('#bulk_date_input').val() || (typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : '');
    let todayStr = typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : new Date().toISOString().slice(0, 10);
    let isBackdate = dateVal && dateVal < todayStr;

    $('.bulk-sample-quant-input').each(function () {
        if ($(this).hasClass('slot-disabled-before-creation') || $(this).is(':disabled')) return;
        let val = $(this).val().trim();
        let slot = $(this).data('slot');
        let due = !isBackdate && isSlotDue(slot);

        if (val === '' && due) {
            unfilledDueCount++;
            let m = $(this).data('model') || 'Umum';
            let l = $(this).data('line') || '';
            let s = $(this).data('section') || '';
            let key = `${m}|${l}|${s}`;

            if (!unfilledByModel[key]) {
                unfilledByModel[key] = { model: m, line: l, section: s, count: 0 };
            }
            unfilledByModel[key].count++;

            $(this).css({
                'border': '1px dashed #ef4444',
                'background': 'rgba(239, 68, 68, 0.25)',
                'color': '#fca5a5'
            }).attr('placeholder', 'Belum!');
        } else if (val === '') {
            $(this).css({
                'border': '1px solid rgba(255,255,255,0.15)',
                'background': 'rgba(15,23,42,0.8)',
                'color': '#ffffff'
            }).attr('placeholder', '-');
        }
    });

    $('.bulk-sample-qual-input').each(function () {
        if ($(this).hasClass('slot-disabled-before-creation') || $(this).is(':disabled')) return;
        let val = $(this).val().trim();
        let slot = $(this).data('slot');
        let due = !isBackdate && isSlotDue(slot);
        let group = $(this).closest('.btn-group-mini-qual');

        if (val === '' && due) {
            unfilledDueCount++;
            let m = $(this).data('model') || 'Umum';
            let l = $(this).data('line') || '';
            let s = $(this).data('section') || '';
            let key = `${m}|${l}|${s}`;

            if (!unfilledByModel[key]) {
                unfilledByModel[key] = { model: m, line: l, section: s, count: 0 };
            }
            unfilledByModel[key].count++;

            group.css({
                'border': '1px dashed #ef4444',
                'border-radius': '4px',
                'padding': '2px',
                'background': 'rgba(239, 68, 68, 0.2)'
            });
        } else {
            group.css({
                'border': 'none',
                'padding': '0',
                'background': 'transparent'
            });
        }
    });

    lastUnfilledVoiceDetails = Object.values(unfilledByModel);

    if (unfilledDueCount > 0) {
        $('#bulk-alarm-badge').show();
        $('#unfilled-count-text').text(unfilledDueCount);
        if (!bulkAlarmLoopTimer) {
            startBulkAlarmLoop();
        }
    } else {
        $('#bulk-alarm-badge').hide();
        stopBulkAlarmLoop();
    }
}

function updateBulkSummaryCount() {
    let totalCells = 0;
    let filledCells = 0;

    $('.bulk-sample-quant-input').each(function () {
        if (!$(this).hasClass('slot-disabled-before-creation')) {
            totalCells++;
            let val = $(this).val().trim();
            if (val !== '' && val !== '-') filledCells++;
        }
    });

    $('.bulk-sample-qual-input').each(function () {
        if (!$(this).hasClass('slot-disabled-before-creation')) {
            totalCells++;
            let val = $(this).val().trim();
            if (val !== '' && val !== '-') filledCells++;
        }
    });

    $('#bulk-total-count').text(totalCells);
    $('#bulk-filled-count').text(filledCells);
}

// Event handlers for Bulk Input Modal
$(document).on('click', '#btn-open-bulk-input-modal', function (e) {
    e.stopPropagation();
    if (typeof window.openBulkInputModal === 'function') {
        window.openBulkInputModal();
    }
});

$(document).on('click', '.btn-bulk-input-rm', function (e) {
    e.stopPropagation();
    let m = $(this).data('model') || $(this).attr('data-model') || '';
    let l = $(this).data('line') || $(this).attr('data-line') || '';
    let s = $(this).data('section') || $(this).attr('data-section') || '';
    if (typeof window.openBulkInputModal === 'function') {
        window.openBulkInputModal(m, l, s);
    }
});

$(document).on('click', '#btn-close-bulk-modal, #btn-cancel-bulk-modal', function () {
    stopBulkAlarmLoop();
    if (bulkFormRefreshInterval) {
        clearInterval(bulkFormRefreshInterval);
        bulkFormRefreshInterval = null;
    }
    window.currentBulkFilterModel = '';
    window.currentBulkFilterLine = '';
    window.currentBulkFilterSection = '';
    $('#modal-bulk-input').hide();
});

$(document).on('change', '#bulk_date_input', function () {
    let dateVal = $(this).val();
    if (!dateVal) return;

    let todayStr = typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : new Date().toISOString().slice(0, 10);
    if (dateVal > todayStr) {
        Swal.fire({
            icon: 'warning',
            title: 'Tanggal Tidak Valid',
            text: 'Pengisian data tidak boleh untuk tanggal di masa depan (maksimal hari ini: ' + todayStr + ').',
            confirmColor: '#3085d6'
        });
        $(this).val(todayStr);
    }
    loadBulkFormData();
});

$(document).on('click', '#btn-reload-bulk-data', function () {
    loadBulkFormData();
});

$(document).on('click', '#btn-toggle-bulk-mute', function () {
    isBulkAlarmMuted = !isBulkAlarmMuted;
    if (isBulkAlarmMuted) {
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        $('#mute-icon').attr('class', 'fa-solid fa-volume-xmark');
        $('#mute-btn-text').text('Unmute');
        $(this).css({ 'background': 'rgba(100,116,139,0.3)', 'color': '#cbd5e1', 'border-color': 'rgba(255,255,255,0.2)' });
    } else {
        $('#mute-icon').attr('class', 'fa-solid fa-volume-high');
        $('#mute-btn-text').text('Mute Alarm');
        $(this).css({ 'background': 'rgba(239,68,68,0.25)', 'color': '#f87171', 'border-color': 'rgba(239,68,68,0.4)' });
        if ($('#bulk-alarm-badge').is(':visible')) {
            let countText = $('#unfilled-count-text').text() || '1';
            speakBulkVoiceAlarm(parseInt(countText, 10) || 1);
        }
    }
});

$(document).on('click', '#bulk-alarm-badge', function () {
    playBulkAlarmSound();
    highlightUnfilledCellsAndAlarm();
    Swal.fire({
        icon: 'warning',
        title: 'Alarm DTC Pengukuran',
        text: 'Terdapat slot jam yang belum diisi! Silakan lengkapi kolom bergaris merah.',
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#ef4444',
        timer: 1800
    });
});

// Reference Image Zoom Preview Modal Trigger
$(document).on('click', '.btn-preview-bulk-img', function (e) {
    e.preventDefault();
    let imgUrl = $(this).data('img');
    let title = $(this).data('title') || 'Gambar Acuan Bagian Inspeksi';
    if (!imgUrl) return;

    Swal.fire({
        title: `<span style="font-size:15px; font-weight:700; color:#38bdf8;"><i class="fa-solid fa-image"></i> ${title}</span>`,
        html: `
            <div style="text-align:center; padding:10px 0;">
                <img src="${imgUrl}" alt="${title}" style="max-width:100%; max-height:480px; border-radius:8px; border:1.5px solid rgba(59,130,246,0.4); box-shadow:0 10px 30px rgba(0,0,0,0.8);">
                <div style="font-size:11px; color:#cbd5e1; margin-top:12px; font-weight:600;">
                    <i class="fa-solid fa-circle-info" style="color:#60a5fa; margin-right:4px;"></i> Acuan visual lokasi & bagian fisik produk yang wajib diinspeksi.
                </div>
            </div>
        `,
        background: '#0f172a',
        color: '#f8fafc',
        showCloseButton: true,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Tutup Preview'
    });
});

// Quantitative cell live validation
$(document).on('input', '.bulk-sample-quant-input', function () {
    let valStr = $(this).val().trim();
    let lsl = $(this).data('lsl');
    let usl = $(this).data('usl');
    let slot = $(this).data('slot');
    let resetBtn = $(this).siblings('.btn-quant-sample-reset');

    if (valStr === '') {
        resetBtn.hide();
        if (isSlotDue(slot)) {
            $(this).css({ 'border': '1px dashed #ef4444', 'background': 'rgba(239,68,68,0.25)', 'color': '#fca5a5' });
        } else {
            $(this).css({ 'border-color': 'rgba(255,255,255,0.15)', 'background': 'rgba(15,23,42,0.8)', 'color': '#ffffff' });
        }
        updateBulkSummaryCount();
        return;
    }

    resetBtn.show();

    let val = parseFloat(valStr);
    if (isNaN(val)) {
        $(this).css({ 'border-color': '#ef4444', 'background': 'rgba(239,68,68,0.25)' });
        updateBulkSummaryCount();
        return;
    }

    let isOos = false;
    if (lsl !== null && lsl !== '' && val < parseFloat(lsl)) {
        isOos = true;
    } else if (usl !== null && usl !== '' && val > parseFloat(usl)) {
        isOos = true;
    }

    if (isOos) {
        $(this).css({ 'border-color': '#ef4444', 'background': 'rgba(239,68,68,0.3)', 'color': '#ffffff' });
    } else {
        $(this).css({ 'border-color': '#10b981', 'background': 'rgba(16,185,129,0.15)', 'color': '#ffffff' });
    }
    updateBulkSummaryCount();
});

// Quantitative per-sample reset button
$(document).on('click', '.btn-quant-sample-reset', function (e) {
    e.preventDefault();
    e.stopPropagation();
    let wrapper = $(this).closest('div');
    let quantInput = wrapper.find('.bulk-sample-quant-input');
    quantInput.val('').data('cleared', 1).attr('data-cleared', '1').trigger('input');
    $(this).hide();
});

function resetQualButtonGroup(group) {
    group.css({ 'border': 'none', 'padding': '0', 'background': 'transparent' });
    group.find('.btn-mini-qual').removeClass('active-ok active-ng');
    group.find('.btn-mini-ok').css({ 'background': 'rgba(16,185,129,0.15)', 'color': '#34d399', 'border-color': 'rgba(16,185,129,0.4)', 'display': 'inline-flex' });
    group.find('.btn-mini-ng').css({ 'background': 'rgba(239,68,68,0.15)', 'color': '#f87171', 'border-color': 'rgba(239,68,68,0.4)', 'display': 'inline-flex' });
    let input = group.find('.bulk-sample-qual-input');
    input.val('').data('cleared', 1).attr('data-cleared', '1');
    group.find('.btn-mini-qual-reset').hide();
}

// Qualitative mini toggle buttons
$(document).on('click', '.btn-mini-qual', function () {
    let group = $(this).closest('.btn-group-mini-qual');
    let hiddenInput = group.find('.bulk-sample-qual-input');
    let clickedVal = $(this).data('val');
    let resetBtn = group.find('.btn-mini-qual-reset');
    let isAlreadyActive = $(this).hasClass('active-ok') || $(this).hasClass('active-ng');

    if (isAlreadyActive) {
        resetQualButtonGroup(group);
    } else {
        group.css({ 'border': 'none', 'padding': '0', 'background': 'transparent' });
        group.find('.btn-mini-qual').removeClass('active-ok active-ng');

        if (clickedVal === 'OK') {
            group.find('.btn-mini-ok').addClass('active-ok').css({ 'background': '#10b981', 'color': '#ffffff', 'display': 'inline-flex' });
            group.find('.btn-mini-ng').hide();
        } else {
            group.find('.btn-mini-ng').addClass('active-ng').css({ 'background': '#ef4444', 'color': '#ffffff', 'display': 'inline-flex' });
            group.find('.btn-mini-ok').hide();
        }
        hiddenInput.val(clickedVal).data('cleared', 0).attr('data-cleared', '0');
        resetBtn.css({ 'color': '#f87171', 'display': 'inline-flex' });
    }
    updateBulkSummaryCount();
});

// Qualitative per-sample reset button (NULL)
$(document).on('click', '.btn-mini-qual-reset', function (e) {
    e.preventDefault();
    e.stopPropagation();
    let group = $(this).closest('.btn-group-mini-qual');
    resetQualButtonGroup(group);
    updateBulkSummaryCount();
});

// Reset Checkpoint (NULL) for all sample slots in a row
$(document).on('click', '.btn-reset-row-null', function (e) {
    e.preventDefault();
    let row = $(this).closest('tr');
    let cpName = row.data('name') || 'checkpoint ini';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Reset Checkpoint ke NULL?',
            text: `Apakah Anda yakin ingin mengosongkan/meng-reset seluruh nilai sampel untuk "${cpName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Reset NULL',
            cancelButtonText: 'Batal',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                executeRowResetNull(row);
            }
        });
    } else {
        if (confirm(`Apakah Anda yakin ingin mengosongkan seluruh nilai sampel untuk "${cpName}"?`)) {
            executeRowResetNull(row);
        }
    }
});

function executeRowResetNull(row) {
    row.find('.bulk-sample-quant-input:not(:disabled)').each(function () {
        $(this).val('').data('cleared', 1).attr('data-cleared', '1').trigger('input');
    });

    row.find('.btn-group-mini-qual:not(.slot-disabled):not(.slot-disabled-before-creation):not(.slot-filled-locked)').each(function () {
        resetQualButtonGroup($(this));
    });

    if (typeof updateBulkSummaryCount === 'function') {
        updateBulkSummaryCount();
    }
}

// Submit Bulk Save for ALL Models and ALL Slots with OOS Confirmation Modal
$(document).on('click', '#btn-submit-bulk-save', function () {
    let dateVal = $('#bulk_date_input').val() || getManufacturingProdDateStr();

    if (!dateVal) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Belum Lengkap',
            text: 'Harap pilih Tanggal Inspeksi.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    let itemsToSave = [];
    let oosItemsList = [];

    $('.bulk-sample-quant-input').each(function () {
        let val = $(this).val().trim();
        let orig = String($(this).data('orig') || $(this).attr('data-orig') || $(this).attr('value') || '').trim();
        let userCleared = $(this).data('cleared') == 1 || $(this).attr('data-cleared') === '1';
        let isFilled = (val !== '' && val !== '-');
        let isChanged = (val !== orig) || userCleared;

        if (isFilled || isChanged) {
            let sendVal = !isFilled ? '__DELETE__' : val;
            let row = $(this).closest('tr');
            let cpName = row.data('name') || $(this).data('model') || 'Item Check';
            let slot = $(this).data('slot') || '';

            itemsToSave.push({
                model_name: $(this).data('model') || row.data('model'),
                parameter_id: $(this).data('param'),
                checkpoint_id: $(this).data('cp'),
                checkpoint_type: 'Quantitative',
                sample_label: slot,
                name: cpName,
                value: sendVal,
                remarks: ''
            });

            // Check if value is Out of Spec (OOS)
            if (isFilled) {
                let numVal = parseFloat(val);
                if (!isNaN(numVal)) {
                    let lsl = $(this).data('lsl');
                    let usl = $(this).data('usl');
                    let lslVal = (lsl !== null && lsl !== undefined && lsl !== '') ? parseFloat(lsl) : null;
                    let uslVal = (usl !== null && usl !== undefined && usl !== '') ? parseFloat(usl) : null;
                    let isOos = false;

                    if (lslVal !== null && numVal < lslVal) isOos = true;
                    if (uslVal !== null && numVal > uslVal) isOos = true;

                    if (isOos) {
                        let specText = (lslVal !== null ? `LSL: ${lslVal}` : '') + ((lslVal !== null && uslVal !== null) ? ' | ' : '') + (uslVal !== null ? `USL: ${uslVal}` : '');
                        oosItemsList.push({
                            cpName: cpName,
                            slot: slot,
                            val: numVal,
                            specText: specText || 'Limits',
                            type: 'Kuantitatif'
                        });
                    }
                }
            }
        }
    });

    $('.bulk-sample-qual-input').each(function () {
        let val = $(this).val().trim();
        let orig = String($(this).data('orig') || $(this).attr('data-orig') || $(this).attr('value') || '').trim();
        let userCleared = $(this).data('cleared') == 1 || $(this).attr('data-cleared') === '1';
        let isFilled = (val !== '' && val !== '-');
        let isChanged = (val !== orig) || userCleared;

        if (isFilled || isChanged) {
            let sendVal = !isFilled ? '__DELETE__' : val;
            let row = $(this).closest('tr');
            let cpName = row.data('name') || $(this).data('model') || 'Item Check';
            let slot = $(this).data('slot') || '';

            itemsToSave.push({
                model_name: $(this).data('model') || row.data('model'),
                parameter_id: $(this).data('param'),
                checkpoint_id: $(this).data('cp'),
                checkpoint_type: 'Qualitative',
                sample_label: slot,
                name: cpName,
                value: sendVal,
                remarks: ''
            });

            // Check if value is NG (Qualitative OOS)
            if (isFilled && val.toUpperCase() === 'NG') {
                oosItemsList.push({
                    cpName: cpName,
                    slot: slot,
                    val: 'NG',
                    specText: 'Spec: OK',
                    type: 'Kualitatif'
                });
            }
        }
    });

    if (itemsToSave.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Masih Kosong',
            text: 'Harap isi minimal 1 nilai sampel pengukuran sebelum menyimpan.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }

    let executeBulkSave = function () {
        let btn = $('#btn-submit-bulk-save');
        let origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Bulk Data...');

        $.ajax({
            url: 'Script/php/dtc/c_dtc_bulk_save.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                inspection_date: dateVal,
                time_label: 'ALL',
                items: itemsToSave
            }),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.status === 'success') {
                    if ($('#matrix-container').length > 0) {
                        location.reload();
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Simpan Bulk Berhasil!',
                        text: res.message,
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#10b981'
                    });

                    loadBulkFormData();
                    if ($('#dtc-table').length && $.fn.DataTable.isDataTable('#dtc-table')) {
                        $('#dtc-table').DataTable().ajax.reload(null, false);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Simpan!',
                        text: res.message,
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origHtml);
                Swal.fire({
                    icon: 'error',
                    title: 'Error Server!',
                    text: 'Terjadi kesalahan sistem saat menyimpan bulk data.',
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    };

    if (oosItemsList.length > 0) {
        let oosHtml = `<div style="text-align: left; max-height: 260px; overflow-y: auto; background: rgba(15, 23, 42, 0.95); padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(239,68,68,0.4); margin-top: 12px; font-size: 12px;">`;
        oosHtml += `<div style="color: #f87171; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-triangle-exclamation" style="font-size: 15px;"></i> Terdeteksi ${oosItemsList.length} Nilai Pengukuran Out of Spec (OOS / NG):</div>`;
        oosHtml += `<table style="width: 100%; border-collapse: collapse; color: #cbd5e1;">`;
        oosHtml += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.15); font-weight: 700; color: #fca5a5; font-size: 11.5px;">
                        <td style="padding: 6px 8px;">Checkpoint</td>
                        <td style="padding: 6px 8px; text-align: center; white-space: nowrap; width: 80px;">Jam</td>
                        <td style="padding: 6px 8px; text-align: center; white-space: nowrap; width: 80px;">Input</td>
                        <td style="padding: 6px 8px; text-align: right; white-space: nowrap; width: 140px;">Spesifikasi</td>
                    </tr>`;

        oosItemsList.forEach(item => {
            oosHtml += `<tr style="border-bottom: 1px dashed rgba(255,255,255,0.08);">
                            <td style="padding: 6px 8px; font-weight: 600; color: #f8fafc;">${item.cpName}</td>
                            <td style="padding: 6px 8px; text-align: center; color: #60a5fa; font-weight: 700; white-space: nowrap;">${item.slot}</td>
                            <td style="padding: 6px 8px; text-align: center; white-space: nowrap;"><span style="color: #ffffff; background: #ef4444; padding: 3px 10px; border-radius: 4px; font-weight: 800; font-size: 11.5px; display: inline-block;">${item.val}</span></td>
                            <td style="padding: 6px 8px; text-align: right; color: #94a3b8; white-space: nowrap; font-size: 11px;">${item.specText}</td>
                        </tr>`;
        });
        oosHtml += `</table></div>`;

        Swal.fire({
            title: '<span style="color:#f87171; font-size:18px;"><i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Simpan Data Out of Spec (OOS)</span>',
            html: `<div style="font-size: 13px; color: #cbd5e1;">Terdapat nilai pengukuran yang berada di luar batas spesifikasi (Out of Spec / NG).</div>${oosHtml}<div style="margin-top:14px; color:#fbbf24; font-weight:700; font-size:13px;">Apakah Anda yakin ingin tetap menyimpan seluruh data ini?</div>`,
            icon: 'warning',
            width: '780px',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fa-solid fa-floppy-disk"></i> Ya, Tetap Simpan Data OOS',
            cancelButtonText: '<i class="fa-solid fa-xmark"></i> Periksa Kembali Data',
            background: '#0f172a',
            color: '#f8fafc',
            didOpen: () => {
                $('.swal2-container').css('z-index', '9999999');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                executeBulkSave();
            }
        });
    } else {
        executeBulkSave();
    }
});

// Shop-Floor Operator Fast Keyboard Navigation & Ctrl+S Shortcut
$(document).on('keydown', '.bulk-sample-quant-input', function (e) {
    let input = $(this);
    let td = input.closest('td');
    let tr = input.closest('tr');
    let table = input.closest('table');
    let colIdx = td.index();

    if (e.key === 'Enter' || e.key === 'ArrowDown') {
        e.preventDefault();
        let nextTr = tr.nextAll('.bulk-item-row').first();
        if (nextTr.length) {
            let target = nextTr.children('td').eq(colIdx).find('.bulk-sample-quant-input:not(:disabled)');
            if (target.length) target.focus().select();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        let prevTr = tr.prevAll('.bulk-item-row').first();
        if (prevTr.length) {
            let target = prevTr.children('td').eq(colIdx).find('.bulk-sample-quant-input:not(:disabled)');
            if (target.length) target.focus().select();
        }
    }
});

// Global Ctrl+S / Cmd+S Shortcut to Save Bulk Form Fast
$(document).on('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        if ($('#modal-bulk-input').is(':visible')) {
            e.preventDefault();
            $('#btn-submit-bulk-save').click();
        }
    }
});



