$(document).ready(function () {
    let currentDetailTable = null;
    let rawApiResponse = null;
    let currentViewMode = 'summary';
    let autoRefreshInterval = null;

    // Register DataTables search filter ONLY for missing-data-table safely
    function missingDataSearchFilter(settings, data, dataIndex) {
        if (!settings || !settings.nTable || settings.nTable.id !== 'missing-data-table') return true;
        if (!rawApiResponse || !rawApiResponse.data) return true;

        let activeType = $('.filter-tab-btn.active').data('filter') || '';
        let lineFilter = $('#filter_line_name').val() || '';
        let sectionFilter = $('#filter_section_name').val() || '';

        let paramObj = rawApiResponse.data[dataIndex];
        if (!paramObj) return true;

        let mType = !activeType || String(paramObj.data_type).toLowerCase() === String(activeType).toLowerCase();
        let mLine = !lineFilter || paramObj.line_name === lineFilter;
        let mSec = !sectionFilter || paramObj.section_name === sectionFilter;

        return mType && mLine && mSec;
    }

    if (!$.fn.dataTable.ext.search.includes(missingDataSearchFilter)) {
        $.fn.dataTable.ext.search.push(missingDataSearchFilter);
    }

    function reloadData(isSilent = false) {
        // Stop timer if page element is no longer present in DOM
        if ($('#summary-section-container').length === 0) {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            return;
        }

        let selectedMonth = $('#filter_month').val() || new Date().toISOString().slice(0, 7);

        if (!isSilent) {
            $('#summary-cards-container').html('<div style="text-align:center; padding:30px; color:var(--text-muted);"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p style="margin-top:10px;">Calculating monitoring control statistics...</p></div>');
            $('#table-container').html('<div style="text-align: center; padding: 50px; color: var(--text-muted);"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p style="margin-top: 10px;">Loading parameter matrix...</p></div>');
        }

        $.ajax({
            url: 'Script/php/dtc/c_missing_data.php',
            type: 'GET',
            cache: false,
            data: { month: selectedMonth },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    rawApiResponse = res;
                    populateLineDropdown(res.data);
                    populateSectionDropdown(res.data);
                    if (!isSilent || !currentDetailTable) {
                        renderDetailMatrixTable(res.data, res.days_count, res.month);
                    }
                    applyFilters();

                    // Update Live Refresh Badge timestamp
                    let timeStr = new Date().toLocaleTimeString('en-US', { hour12: false });
                    $('#live-text').text(`LIVE (5s) • ${timeStr}`);
                    $('#live-refresh-badge').addClass('live-pulse');
                    setTimeout(() => { $('#live-refresh-badge').removeClass('live-pulse'); }, 1200);

                    if (window.triggerFitScreen) {
                        window.triggerFitScreen(false);
                    }
                } else if (!isSilent) {
                    $('#summary-cards-container').html(`<div style="color:var(--danger); text-align:center; padding:20px;">Error: ${res.message}</div>`);
                }
            },
            error: function () {
                if (!isSilent) {
                    $('#summary-cards-container').html(`<div style="color:var(--danger); text-align:center; padding:20px;">Failed to load monitoring data.</div>`);
                }
            }
        });
    }

    function updateMissingTabCounts() {
        if (!rawApiResponse || !rawApiResponse.data) return;

        let counts = { '': 0, 'CTQ': 0, 'CTP': 0, 'Time Check': 0, 'F/Proof': 0 };
        let lineFilter = $('#filter_line_name').val() || '';
        let sectionFilter = $('#filter_section_name').val() || '';

        rawApiResponse.data.forEach(item => {
            if (lineFilter && item.line_name !== lineFilter) return;
            if (sectionFilter && item.section_name !== sectionFilter) return;

            counts['']++;
            let type = (item.data_type || '').trim().toUpperCase();
            if (type === 'CTQ') counts['CTQ']++;
            else if (type === 'CTP') counts['CTP']++;
            else if (type === 'TIME CHECK') counts['Time Check']++;
            else if (type === 'F/PROOF') counts['F/Proof']++;
        });

        $('.dtc-filter-tabs .filter-tab-btn').each(function () {
            let filter = $(this).data('filter');
            if (filter === undefined) filter = '';
            let count = counts[filter] || 0;
            let text = filter === '' ? 'All Types' : filter;

            if (count > 0) {
                $(this).addClass('has-notif');
                $(this).html(`${text} <span class="badge-notif-glow" style="font-size:10px; padding:2px 7px; border-radius:10px; margin-left:5px; font-weight:700;">${count}</span>`);
            } else {
                $(this).removeClass('has-notif');
                $(this).html(`${text} <span style="background:rgba(255,255,255,0.1); color:var(--text-muted); font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px;">0</span>`);
            }
        });
    }

    function applyFilters() {
        if (!rawApiResponse || !rawApiResponse.data) return;
        updateMissingTabCounts();

        let activeType = $('.filter-tab-btn.active').data('filter') || '';
        let lineFilter = $('#filter_line_name').val() || '';
        let sectionFilter = $('#filter_section_name').val() || '';

        // Filter Raw Parameters and Calculate Statistics
        let filteredParams = rawApiResponse.data.filter(param => {
            let matchType = !activeType || String(param.data_type).toLowerCase() === String(activeType).toLowerCase();
            let matchLine = !lineFilter || param.line_name === lineFilter;
            let matchSec = !sectionFilter || param.section_name === sectionFilter;
            return matchType && matchLine && matchSec;
        });

        // Re-compute Summary per Section per Line according to closed/empty session rules
        let summaryMap = {};
        let totalParamsAll = filteredParams.length;
        let totalExpectedSessionsAll = 0;
        let totalClosedSessionsAll = 0;
        let totalUnclosedSessionsAll = 0;
        let totalExpectedSlotsAll = 0;
        let totalOverdueSlotsAll = 0;

        let now = new Date();
        let todayDay = now.getDate();
        if (now.getHours() < 7) {
            let yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            todayDay = yesterday.getDate();
        }

        let currentMonthStr = now.toISOString().slice(0, 7);
        let isCurrentMonth = (rawApiResponse.month === currentMonthStr);

        let daysUpToToday = rawApiResponse.days_count;
        if (isCurrentMonth) {
            daysUpToToday = Math.min(todayDay, rawApiResponse.days_count);
        } else if (rawApiResponse.month > currentMonthStr) {
            daysUpToToday = 0;
        }

        filteredParams.forEach(param => {
            let line = param.line_name || 'REF 01';
            let section = param.section_name || 'DEFAULT';
            let key = line + '___' + section;

            if (!summaryMap[key]) {
                summaryMap[key] = {
                    line_name: line,
                    section_name: section,
                    total_parameters: 0,
                    total_expected_sessions: 0,
                    closed_sessions: 0,
                    unclosed_sessions: 0,
                    not_overdue_sessions: 0,
                    overdue_sessions: 0,
                    total_expected_slots: 0,
                    overdue_slots: 0
                };
            }

            summaryMap[key].total_parameters++;

            let startDay = isCurrentMonth ? todayDay : 1;
            let endDay = isCurrentMonth ? todayDay : daysUpToToday;

            for (let i = startDay; i <= endDay; i++) {
                let status = param[`day_${i}`];
                if (status === 3) continue; // Skip Weekend

                summaryMap[key].total_expected_sessions++;
                totalExpectedSessionsAll++;

                // A session is CLOSED ONLY if status === 2 (explicitly closed).
                // Status 0 (empty) or Status 1 (partially filled/unclosed) is UNCLOSED.
                let isClosed = (status === 2);

                if (isClosed) {
                    summaryMap[key].closed_sessions++;
                    totalClosedSessionsAll++;
                } else {
                    summaryMap[key].unclosed_sessions++;
                    totalUnclosedSessionsAll++;
                }

                let slotsPerDay = (isCurrentMonth && param.expected_slots_today !== undefined) ? param.expected_slots_today : (param.slots_per_day || 10);
                summaryMap[key].total_expected_slots += slotsPerDay;
                totalExpectedSlotsAll += slotsPerDay;

                let isSessionOverdue = false;
                // Overdue slots occur if NOT closed
                if (!isClosed) {
                    let missingSlotsCount = (isCurrentMonth && param.overdue_slots_today !== undefined) ? param.overdue_slots_today : ((param[`day_${i}_missing_slots`] !== undefined) ? param[`day_${i}_missing_slots`] : (status === 0 ? slotsPerDay : 1));

                    if (missingSlotsCount > 0) {
                        if (isCurrentMonth && i < todayDay) {
                            summaryMap[key].overdue_slots += missingSlotsCount;
                            totalOverdueSlotsAll += missingSlotsCount;
                            isSessionOverdue = true;
                        } else if (!isCurrentMonth && rawApiResponse.month < currentMonthStr) {
                            summaryMap[key].overdue_slots += missingSlotsCount;
                            totalOverdueSlotsAll += missingSlotsCount;
                            isSessionOverdue = true;
                        } else if (isCurrentMonth && i === todayDay) {
                            summaryMap[key].overdue_slots += missingSlotsCount;
                            totalOverdueSlotsAll += missingSlotsCount;
                            isSessionOverdue = true;
                        }
                    }
                }

                if (isSessionOverdue) {
                    summaryMap[key].overdue_sessions++;
                } else {
                    summaryMap[key].not_overdue_sessions++;
                }
            }
        });

        // Format filtered summary list
        let summaryList = [];
        Object.keys(summaryMap).sort().forEach(key => {
            let item = summaryMap[key];
            let totExpSess = maxVal(item.total_expected_sessions, 1);
            let pctUnclosed = Math.round((item.unclosed_sessions / totExpSess) * 1000) / 10;
            let pctOverdueSess = Math.round((item.overdue_sessions / totExpSess) * 100);

            let totExpSlots = maxVal(item.total_expected_slots, 1);
            let pctOverdue = Math.round((item.overdue_slots / totExpSlots) * 1000) / 10;

            let statusLevel = 'OK';
            if (pctOverdue > 10 || pctUnclosed > 30) {
                statusLevel = 'CRITICAL';
            } else if (pctOverdue > 5 || pctUnclosed > 15) {
                statusLevel = 'WARNING';
            }

            summaryList.push({
                line_name: item.line_name,
                section_name: item.section_name,
                total_parameters: item.total_parameters,
                total_expected_sessions: item.total_expected_sessions,
                closed_sessions: item.closed_sessions,
                unclosed_sessions: item.unclosed_sessions,
                not_overdue_sessions: item.not_overdue_sessions,
                overdue_sessions: item.overdue_sessions,
                pct_unclosed: pctUnclosed,
                pct_overdue_sess: pctOverdueSess,
                total_expected_slots: item.total_expected_slots,
                overdue_slots: item.overdue_slots,
                pct_overdue: pctOverdue,
                status_level: statusLevel
            });
        });

        // Render Filtered Overall KPIs
        let overallTotExpSess = maxVal(totalExpectedSessionsAll, 1);
        let overallTotExpSlots = maxVal(totalExpectedSlotsAll, 1);
        let overallKpi = {
            total_parameters: totalParamsAll,
            total_expected_sessions: totalExpectedSessionsAll,
            closed_sessions: totalClosedSessionsAll,
            unclosed_sessions: totalUnclosedSessionsAll,
            pct_unclosed: Math.round((totalUnclosedSessionsAll / overallTotExpSess) * 1000) / 10,
            total_expected_slots: totalExpectedSlotsAll,
            overdue_slots: totalOverdueSlotsAll,
            pct_overdue: Math.round((totalOverdueSlotsAll / overallTotExpSlots) * 1000) / 10,
            pct_closed: Math.round((totalClosedSessionsAll / overallTotExpSess) * 1000) / 10
        };

        renderOverallKpis(overallKpi, daysUpToToday, rawApiResponse.days_count, rawApiResponse.month, summaryList);
        renderSummaryTable(summaryList);

        if (currentDetailTable) {
            currentDetailTable.draw(false);
        }
    }

    function maxVal(val, min) {
        return val > min ? val : min;
    }

    function renderOverallKpis(kpi, daysUpToToday, daysCount, month, summaryList) {
        if (!kpi) return;

        let uniqueLines = Array.from(new Set((rawApiResponse.data || []).map(d => d.line_name).filter(Boolean))).sort();

        // Compute per-line statistics breakdown
        let lineStatsMap = {};
        if (Array.isArray(summaryList)) {
            summaryList.forEach(item => {
                let lName = item.line_name || 'Other Line';
                if (!lineStatsMap[lName]) {
                    lineStatsMap[lName] = {
                        total_parameters: 0,
                        total_expected_sessions: 0,
                        closed_sessions: 0,
                        total_expected_slots: 0,
                        overdue_slots: 0
                    };
                }
                lineStatsMap[lName].total_parameters += (item.total_parameters || 0);
                lineStatsMap[lName].total_expected_sessions += (item.total_expected_sessions || 0);
                lineStatsMap[lName].closed_sessions += (item.closed_sessions || 0);
                lineStatsMap[lName].total_expected_slots += (item.total_expected_slots || 0);
                lineStatsMap[lName].overdue_slots += (item.overdue_slots || 0);
            });
        }

        let sortedLines = Object.keys(lineStatsMap).sort();
        let banner = $('#global-kpi-banner');
        banner.empty();

        // 1. MONITORED PARAMS Cards (Split Per Line)
        sortedLines.forEach(l => {
            let stats = lineStatsMap[l];
            banner.append(`
                <div class="line-kpi-card-clickable" data-line="${l}" title="Klik untuk lihat data LINE ${l} di DTC List" style="background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,41,59,0.9)); border: 1px solid rgba(16,185,129,0.35); border-left: 5px solid #10b981; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer;">
                    <div style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fa-solid fa-microchip" style="color: #34d399; margin-right: 4px;"></i> PARAMS ${l}</span>
                        <span style="font-size: 9px; color: #34d399; background: rgba(16,185,129,0.2); padding: 1px 6px; border-radius: 4px; font-weight:700;">${l}</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 900; color: #34d399; margin-top: 4px; line-height: 1;">
                        ${stats.total_parameters.toLocaleString()}
                    </div>
                    <div style="font-size: 11px; color: #cbd5e1; margin-top: 4px; font-weight: 600;">
                        ${stats.total_parameters.toLocaleString()} Parameters Monitored
                    </div>
                </div>
            `);
        });

        // 3. CLOSED RATE Cards (Split Per Line)
        sortedLines.forEach(l => {
            let stats = lineStatsMap[l];
            let totExp = maxVal(stats.total_expected_sessions, 1);
            let closed = stats.closed_sessions;
            let pct = Math.round((closed / totExp) * 1000) / 10;
            banner.append(`
                <div class="line-kpi-card-clickable" data-line="${l}" title="Klik untuk lihat data LINE ${l} di DTC List" style="background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,41,59,0.9)); border: 1px solid rgba(6,182,212,0.35); border-left: 5px solid #06b6d4; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer;">
                    <div style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fa-solid fa-chart-pie" style="color: #22d3ee; margin-right: 4px;"></i> CLOSED RATE ${l}</span>
                        <span style="font-size: 9px; color: #22d3ee; background: rgba(6,182,212,0.2); padding: 1px 6px; border-radius: 4px; font-weight:700;">${l}</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 900; color: #22d3ee; margin-top: 4px; line-height: 1;">
                        ${pct}%
                    </div>
                    <div style="font-size: 11px; color: #cbd5e1; margin-top: 4px; font-weight: 600;">
                        ${closed.toLocaleString()} / ${totExp.toLocaleString()} item check
                    </div>
                </div>
            `);
        });

        // 4. OVERDUE SESSIONS Cards (Split Per Line)
        sortedLines.forEach(l => {
            let stats = lineStatsMap[l];
            let totExpSlots = maxVal(stats.total_expected_slots, 1);
            let overdue = stats.overdue_slots;
            let pct = Math.round((overdue / totExpSlots) * 1000) / 10;
            let bdgColor = pct > 10 ? '#f87171' : (pct > 0 ? '#fbbf24' : '#34d399');
            let bdgBorder = pct > 10 ? '#ef4444' : (pct > 0 ? '#f59e0b' : '#10b981');
            let bdgBg = pct > 10 ? 'rgba(239,68,68,0.25)' : (pct > 0 ? 'rgba(245,158,11,0.25)' : 'rgba(16,185,129,0.2)');
            let labelText = pct > 10 ? 'CRITICAL' : (pct > 0 ? 'WARNING' : 'NORMAL');

            banner.append(`
                <div class="line-kpi-card-clickable" data-line="${l}" title="Klik untuk lihat data LINE ${l} di DTC List" style="background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,41,59,0.9)); border: 1px solid rgba(255,255,255,0.1); border-left: 5px solid ${bdgBorder}; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer;">
                    <div style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fa-solid fa-triangle-exclamation" style="color: ${bdgColor}; margin-right: 4px;"></i> OVERDUE ${l}</span>
                        <span style="font-size: 9px; color: ${bdgColor}; background: ${bdgBg}; padding: 1px 6px; border-radius: 4px; font-weight: 800;">${labelText}</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 900; color: ${bdgColor}; margin-top: 4px; line-height: 1;">
                        ${pct}%
                    </div>
                    <div style="font-size: 11px; color: #cbd5e1; margin-top: 4px; font-weight: 600;">
                        ${overdue.toLocaleString()} / ${totExpSlots.toLocaleString()} slots overdue
                    </div>
                </div>
            `);
        });

        let currentMonth = new Date().toISOString().slice(0, 7);
        if (month === currentMonth) {
            $('#active-day-badge').text(`Shift Hari Ini`).show();
            $('#header-scope-label').text(`Monitoring unclosed sessions & overdue input percentage per Section per Line (Shift Hari Ini Fokus)`);
        } else {
            $('#active-day-badge').text(`Full Month (${daysCount} Days)`).show();
            $('#header-scope-label').text(`Monitoring unclosed sessions & overdue input percentage per Section per Line (Full Month Audit)`);
        }
    }

    let currentCardPages = {};
    let lastSummaryList = null;
    let carouselAutoTimer = null;
    let isCarouselHovered = false;

    // Bulletproof Carousel Auto-Slide Timer (Rotates cards every 3 seconds permanently)
    function initCarouselAutoTimer() {
        if (carouselAutoTimer) return; // Keep existing timer running safely
        carouselAutoTimer = setInterval(function () {
            if (isCarouselHovered || !lastSummaryList || $('#summary-section-container:visible').length === 0) {
                return;
            }

            // Group data by line to check total pages per line (1 summary card + N section cards = 12 cards max per 6x2 slide)
            let lineGroups = {};
            lastSummaryList.forEach(item => {
                let line = item.line_name || 'Other Line';
                if (!lineGroups[line]) lineGroups[line] = [];
                lineGroups[line].push(item);
            });

            let changed = false;
            Object.keys(lineGroups).forEach(line => {
                let totalCards = (lineGroups[line].length || 0);
                let totalPages = Math.ceil(totalCards / 12);
                if (totalPages > 1) {
                    currentCardPages[line] = ((currentCardPages[line] || 0) + 1) % totalPages;
                    changed = true;
                }
            });

            if (changed) {
                renderSummaryTable(lastSummaryList);
            }
        }, 3000);
    }

    $(document).on('mouseenter', '#summary-cards-container', function () {
        isCarouselHovered = true;
    }).on('mouseleave', '#summary-cards-container', function () {
        isCarouselHovered = false;
    });

    $(document).on('click', '.btn-card-prev', function (e) {
        e.preventDefault();
        let line = $(this).data('line');
        if (!line || !lastSummaryList) return;

        let lineItems = lastSummaryList.filter(it => (it.line_name || 'Other Line') === line);
        let totalPages = Math.ceil((lineItems.length + 1) / 12);
        if (totalPages <= 1) return;

        let cur = currentCardPages[line] || 0;
        currentCardPages[line] = (cur - 1 + totalPages) % totalPages;
        renderSummaryTable(lastSummaryList);
    });

    $(document).on('click', '.btn-card-next', function (e) {
        e.preventDefault();
        let line = $(this).data('line');
        if (!line || !lastSummaryList) return;

        let lineItems = lastSummaryList.filter(it => (it.line_name || 'Other Line') === line);
        let totalPages = Math.ceil((lineItems.length + 1) / 12);
        if (totalPages <= 1) return;

        let cur = currentCardPages[line] || 0;
        currentCardPages[line] = (cur + 1) % totalPages;
        renderSummaryTable(lastSummaryList);
    });

    $(document).on('click', '.card-dot-btn', function (e) {
        e.preventDefault();
        let line = $(this).data('line');
        let page = parseInt($(this).data('page'));
        if (line === undefined || isNaN(page) || !lastSummaryList) return;

        currentCardPages[line] = page;
        renderSummaryTable(lastSummaryList);
    });

    function renderSummaryTable(summaryData) {
        lastSummaryList = summaryData;

        if (!summaryData || summaryData.length === 0) {
            $('#summary-cards-container').html(`
                <div style="text-align:center; padding:30px; color:var(--text-muted);">
                    <i class="fa-solid fa-folder-open fa-2x"></i>
                    <p style="margin-top:10px;">No missing data matching your selected filters.</p>
                </div>
            `);
            $('#summary-count-label').text('0 Monitoring Cards');
            return;
        }

        initCarouselAutoTimer();

        // Map section model names from raw data
        let sectionModelsMap = {};
        if (rawApiResponse && rawApiResponse.data) {
            rawApiResponse.data.forEach(p => {
                let k = (p.line_name || '') + '___' + (p.section_name || '');
                if (!sectionModelsMap[k]) sectionModelsMap[k] = new Set();
                if (p.model_name) sectionModelsMap[k].add(p.model_name);
            });
        }

        // Group summary data by line_name
        let lineGroups = {};
        summaryData.forEach(item => {
            let line = item.line_name || 'Other Line';
            if (!lineGroups[line]) lineGroups[line] = [];
            lineGroups[line].push(item);
        });

        let lineNames = Object.keys(lineGroups).sort();
        $('#summary-count-label').text(`${lineNames.length} Line Array(s) • ${summaryData.length} Section Cards (+1 Summary Card/Line)`);

        let html = '';

        lineNames.forEach((lineName) => {
            let items = lineGroups[lineName];

            // Calculate Line Aggregates
            let lineParams = 0;
            let lineExpSess = 0;
            let lineClosedSess = 0;
            let lineUnclosedSess = 0;
            let lineNotOverdueSess = 0;
            let lineOverdueSess = 0;
            let lineExpSlots = 0;
            let lineOverdueSlots = 0;

            items.forEach(it => {
                lineParams += it.total_parameters || 0;
                lineExpSess += it.total_expected_sessions || 0;
                lineClosedSess += it.closed_sessions || 0;
                lineUnclosedSess += it.unclosed_sessions || 0;
                lineNotOverdueSess += it.not_overdue_sessions || 0;
                lineOverdueSess += it.overdue_sessions || 0;
                lineExpSlots += it.total_expected_slots || 0;
                lineOverdueSlots += it.overdue_slots || 0;
            });

            let totExpSess = maxVal(lineExpSess, 1);
            let pctUnclosed = Math.round((lineUnclosedSess / totExpSess) * 1000) / 10;
            let totExpSlots = maxVal(lineExpSlots, 1);
            let pctOverdue = Math.round((lineOverdueSlots / totExpSlots) * 1000) / 10;
            let pctClosed = Math.round((lineClosedSess / totExpSess) * 1000) / 10;

            let isRef01 = lineName.toUpperCase().includes('REF 01') || lineName.toUpperCase().includes('REF1');
            let lineBadgeBg = isRef01 ? 'linear-gradient(135deg, #3b82f6, #1d4ed8)' : 'linear-gradient(135deg, #06b6d4, #0e7490)';

            let lineStatusBadge = '<span style="background:rgba(16,185,129,0.25); color:#34d399; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:900; border:1px solid rgba(16,185,129,0.4);"><i class="fa-solid fa-circle-check"></i> CONTROL OK</span>';
            if (pctOverdue > 10 || pctUnclosed > 30) {
                lineStatusBadge = '<span style="background:rgba(239,68,68,0.25); color:#f87171; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:900; border:1px solid rgba(239,68,68,0.4);" class="blinking-outline"><i class="fa-solid fa-triangle-exclamation"></i> CRITICAL OVERDUE</span>';
            } else if (pctOverdue > 5 || pctUnclosed > 15) {
                lineStatusBadge = '<span style="background:rgba(245,158,11,0.25); color:#fbbf24; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:900; border:1px solid rgba(245,158,11,0.4);"><i class="fa-solid fa-circle-exclamation"></i> NEEDS ATTENTION</span>';
            }

            let allCards = items;

            // Slice items for 6x2 Grid Pagination (12 Cards per Page/Slide)
            let totalPages = Math.ceil(allCards.length / 12);
            let curPage = currentCardPages[lineName] || 0;
            if (curPage >= totalPages) curPage = 0;
            let displayItems = allCards.slice(curPage * 12, (curPage + 1) * 12);

            let pageControlsHtml = '';
            if (totalPages > 1) {
                let dotsHtml = '';
                for (let p = 0; p < totalPages; p++) {
                    let activeStyle = (p === curPage) ? 'background:#38bdf8; width:16px;' : 'background:rgba(255,255,255,0.3); width:6px;';
                    dotsHtml += `<button class="card-dot-btn" data-line="${lineName}" data-page="${p}" style="height:6px; ${activeStyle} border-radius:3px; border:none; padding:0; margin:0 2px; cursor:pointer; transition:all 0.3s ease;"></button>`;
                }
                pageControlsHtml = `
                <div style="display:flex; align-items:center; gap:6px; background:rgba(0,0,0,0.4); padding:3px 8px; border-radius:6px; border:1px solid rgba(255,255,255,0.15);">
                    <button class="btn-card-prev" data-line="${lineName}" style="background:transparent; border:none; color:#cbd5e1; font-size:11px; cursor:pointer; padding:1px 4px;" title="Previous Slide (6x2)"><i class="fa-solid fa-chevron-left"></i></button>
                    <div style="display:flex; align-items:center;">${dotsHtml}</div>
                    <button class="btn-card-next" data-line="${lineName}" style="background:transparent; border:none; color:#cbd5e1; font-size:11px; cursor:pointer; padding:1px 4px;" title="Next Slide (6x2)"><i class="fa-solid fa-chevron-right"></i></button>
                    <span style="font-size:10px; color:#94a3b8; font-weight:700; margin-left:2px;">Pg ${curPage + 1}/${totalPages}</span>
                </div>`;
            }

            html += `
            <div style="margin-bottom: 16px;">
                <!-- Line Monitoring Header (Compact & Sleek) -->
                <div style="background: rgba(15, 23, 42, 0.95); border-left: 4px solid ${isRef01 ? '#3b82f6' : '#06b6d4'}; border-radius: 8px; padding: 6px 14px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: ${lineBadgeBg}; color: #fff; padding: 3px 10px; border-radius: 6px; font-weight: 800; font-size: 13px; letter-spacing: 0.5px; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                            <i class="fa-solid fa-industry" style="margin-right: 6px;"></i> LINE ${lineName}
                        </span>
                        <span style="color: #cbd5e1; font-size: 12px; font-weight: 600;">
                            <strong style="color: #f8fafc; font-size: 13px;">${lineParams}</strong> Active Params &bull; <strong style="color: #f8fafc; font-size: 13px;">${items.length}</strong> Sections
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        ${pageControlsHtml}
                        <div style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;" title="Line Total Belum Overdue">
                            <i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i> Belum Overdue: <span style="font-weight:900; font-size:12px;">${lineNotOverdueSess.toLocaleString()}/${lineExpSess.toLocaleString()}</span> (${lineOverdueSess.toLocaleString()} overdue)
                        </div>
                        <div style="background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;" title="Line Total Overdue">
                            <i class="fa-solid fa-clock-rotate-left" style="margin-right: 4px;"></i> Overdue: <span style="font-weight:900; font-size:12px;">${pctOverdue}%</span> (${lineOverdueSlots.toLocaleString()}/${lineExpSlots.toLocaleString()})
                        </div>
                        ${lineStatusBadge}
                    </div>
                </div>`;

            // Section Grid Tiles (Strict 6x2 Fixed Grid Layout)
            html += `<div class="section-grid-6x2">`;

            function getGradientColorStyle(pct) {
                if (pct <= 0) {
                    return {
                        text: '#34d399',
                        border: 'rgba(16, 185, 129, 0.5)',
                        bg: 'rgba(16, 185, 129, 0.15)',
                        barGradient: 'linear-gradient(90deg, #10b981, #34d399)',
                        displayText: 'GOOD'
                    };
                } else if (pct <= 15) {
                    return {
                        text: '#fbbf24',
                        border: 'rgba(245, 158, 11, 0.5)',
                        bg: 'rgba(245, 158, 11, 0.15)',
                        barGradient: 'linear-gradient(90deg, #f59e0b, #fbbf24)',
                        displayText: pct + '%'
                    };
                } else if (pct <= 40) {
                    return {
                        text: '#fb923c',
                        border: 'rgba(251, 146, 60, 0.6)',
                        bg: 'rgba(251, 146, 60, 0.15)',
                        barGradient: 'linear-gradient(90deg, #ea580c, #fb923c)',
                        displayText: pct + '%'
                    };
                } else {
                    return {
                        text: '#f87171',
                        border: 'rgba(239, 68, 68, 0.7)',
                        bg: 'rgba(239, 68, 68, 0.15)',
                        barGradient: 'linear-gradient(90deg, #ef4444, #f87171)',
                        displayText: pct + '%'
                    };
                }
            }

            displayItems.forEach(item => {
                let mainOverdueStyle = getGradientColorStyle(item.pct_overdue);
                let sessOverdueStyle = getGradientColorStyle(item.pct_overdue_sess);

                let tileClass = '';
                let dotColor = mainOverdueStyle.text;

                if (item.status_level === 'CRITICAL') {
                    tileClass = 'cctv-tile-critical';
                    dotColor = '#ef4444';
                } else if (item.status_level === 'WARNING') {
                    dotColor = '#fbbf24';
                }

                let lineTagColor = isRef01 ? '#60a5fa' : '#22d3ee';
                let lineTagBg = isRef01 ? 'rgba(59, 130, 246, 0.2)' : 'rgba(6, 182, 212, 0.2)';
                let lineTagBorder = isRef01 ? 'rgba(59, 130, 246, 0.35)' : 'rgba(6, 182, 212, 0.35)';

                let secKeyMap = (item.line_name || '') + '___' + (item.section_name || '');
                let modelsSet = sectionModelsMap[secKeyMap] || new Set();
                let modelList = Array.from(modelsSet);
                let modelCount = modelList.length;
                let modelDisplay = modelCount === 1 ? modelList[0] : (modelCount > 0 ? `${modelCount} Models` : '-');
                let modelTooltip = modelList.length > 0 ? `Active Running Models: ${modelList.join(', ')}` : 'No active running models';

                html += `
                <div class="section-card-clickable ${tileClass}" data-line="${item.line_name}" data-section="${item.section_name}" title="Klik untuk lihat data ${item.line_name} - ${item.section_name} di DTC List" style="background: rgba(15, 23, 42, 0.95); border: 2px solid ${mainOverdueStyle.border}; border-radius: 12px; padding: 10px 12px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 6px 20px rgba(0,0,0,0.5); position: relative; overflow: hidden; animation: fadeIn 0.4s ease; cursor: pointer;">
                    <!-- Top Section Card Header (Line Badge & Actions) -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.12); padding-bottom: 6px; margin-bottom: 6px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: ${lineTagBg}; color: ${lineTagColor}; border: 1px solid ${lineTagBorder}; font-size: 11px; font-weight: 900; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.5px; white-space: nowrap;">${item.line_name}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            <button type="button" class="btn-download-section-report" data-line="${item.line_name}" data-section="${item.section_name}" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; cursor: pointer;" title="Download Laporan Performance Bulanan ${item.section_name}"><i class="fa-solid fa-file-excel"></i> Export</button>
                            ${(item.pct_overdue > 0 || item.pct_unclosed > 0) ? `<button type="button" class="btn-push-alert" data-line="${item.line_name}" data-section="${item.section_name}" data-overdue="${item.pct_overdue}" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; cursor: pointer;" title="Push Follow-up Alert ke Foreman"><i class="fa-solid fa-bell"></i> Alert</button>` : `<span style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px;"><i class="fa-solid fa-circle-check"></i> OK</span>`}
                            <span class="live-dot-pulse" style="width: 8px; height: 8px; border-radius: 50%; background: ${dotColor}; display: inline-block;"></span>
                        </div>
                    </div>

                    <!-- Prominent Full-Width Section Name Title (Above Overdue Box) -->
                    <div style="font-size: 14px; font-weight: 900; color: #f8fafc; margin-bottom: 6px; text-align: center; background: rgba(255,255,255,0.06); padding: 4px 6px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${item.section_name}">
                        <i class="fa-solid fa-layer-group" style="color: #38bdf8; margin-right: 5px; font-size: 12px;"></i>${item.section_name}
                    </div>

                    <!-- Main Overdue Rate KPI Display -->
                    <div style="background: rgba(0,0,0,0.45); padding: 10px 8px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); text-align: center; margin-bottom: 8px;">
                        <div style="font-size: 10px; color: #94a3b8; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">
                            <i class="fa-solid fa-clock-rotate-left" style="color: ${mainOverdueStyle.text}; margin-right: 3px;"></i> OVERDUE RATE
                        </div>
                        <div style="font-size: ${item.pct_overdue === 0 ? '30px' : '38px'}; font-weight: 900; color: ${mainOverdueStyle.text}; line-height: 1; letter-spacing: -1px; margin: 2px 0;">
                            ${mainOverdueStyle.displayText}
                        </div>
                        <div style="font-size: 11px; color: #f1f5f9; margin-top: 3px; font-weight: 800; white-space: nowrap;">
                            <strong style="color: ${mainOverdueStyle.text}; font-size: 13px;">${item.overdue_slots.toLocaleString()}</strong> <span style="color:#64748b;">/</span> ${item.total_expected_slots.toLocaleString()} overdue
                        </div>

                        <!-- Visual Progress Bar -->
                        <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.12); border-radius: 3px; overflow: hidden; margin-top: 6px;">
                            <div style="width: ${Math.min(item.pct_overdue, 100)}%; height: 100%; background: ${mainOverdueStyle.barGradient}; transition: width 0.5s ease;"></div>
                        </div>
                    </div>

                    <!-- Metrics Grid (2 Columns) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                        <div style="background: ${sessOverdueStyle.bg}; border: 1px solid ${sessOverdueStyle.border}; padding: 6px 4px; border-radius: 6px; text-align: center;" title="${item.overdue_sessions} Sesi Overdue dari total ${item.total_expected_sessions} sesi">
                            <div style="font-size: 10px; color: ${sessOverdueStyle.text}; font-weight: 900; text-transform: uppercase; letter-spacing: 0.3px;">OVERDUE</div>
                            <div style="font-size: ${item.pct_overdue_sess === 0 ? '18px' : '22px'}; font-weight: 900; color: ${sessOverdueStyle.text}; margin-top: 1px; line-height: 1; letter-spacing: -0.5px;">${sessOverdueStyle.displayText}</div>
                            <div style="font-size: 11px; color: #cbd5e1; font-weight: 800; margin-top: 2px;">${item.overdue_sessions}/${item.total_expected_sessions} item check</div>
                        </div>
                        <div style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); padding: 6px 4px; border-radius: 6px; text-align: center;" title="${modelTooltip}">
                            <div style="font-size: 10px; color: #60a5fa; font-weight: 900; text-transform: uppercase; letter-spacing: 0.3px;">RUNNING MODEL</div>
                            <div style="font-size: ${modelCount === 1 && modelDisplay.length > 10 ? '14px' : (modelCount === 1 ? '16px' : '20px')}; font-weight: 900; color: #60a5fa; margin-top: 1px; line-height: 1.2; letter-spacing: -0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${modelTooltip}">${modelDisplay}</div>
                            <div style="font-size: 10px; color: #cbd5e1; font-weight: 800; margin-top: 2px;">${item.total_parameters} Params</div>
                        </div>
                    </div>
                </div>`;
            });

            html += `</div>
            </div>`;
        });

        $('#summary-cards-container').html(html);
    }

    function renderDetailMatrixTable(data, daysCount, month) {
        let hasTodaySlots = data.length > 0 && Array.isArray(data[0].slots);
        let defaultTimeLabels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];

        let html = `
            <div style="width:100%; overflow-x:auto; padding-bottom:10px;">
            <table id="missing-data-table" class="display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th style="min-width: 250px;">Parameter (Running Model)</th>
        `;

        if (hasTodaySlots) {
            for (let s = 1; s <= 11; s++) {
                let lbl = defaultTimeLabels[s - 1] || `S${s}`;
                html += `<th class="day-col" title="Shift Slot ${s} (${lbl})" style="color: #60a5fa; font-size:11px;">
                            S${s}<br>
                            <span style="font-size: 10px; font-weight: normal; opacity: 0.9;">${lbl}</span>
                         </th>`;
            }
        } else {
            for (let i = 1; i <= daysCount; i++) {
                let dateStr = `${month}-${String(i).padStart(2, '0')}`;
                let d = new Date(dateStr);
                let dayName = isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-US', { weekday: 'short' });
                let isWeekend = (d.getDay() === 0 || d.getDay() === 6);
                let headerStyle = isWeekend ? 'color: var(--danger);' : '';

                html += `<th class="day-col" title="${i} ${month}" style="${headerStyle}">
                            ${i}<br>
                            <span style="font-size: 10px; font-weight: normal; opacity: 0.8;">${dayName}</span>
                         </th>`;
            }
        }

        html += `
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(row => {
            html += `<tr>`;
            let subName = row.sub_item_check_name && row.sub_item_check_name !== '-' ? ` - ${row.sub_item_check_name}` : '';
            html += `
                <td>
                    <div style="font-weight: 600; color: var(--accent);">${row.item_check_name}${subName} <span style="font-size:10px; color:var(--text-muted); font-weight:normal;">[${row.data_type}]</span></div>
                    <div style="font-size: 11px; color: #e2e8f0; font-weight: 500; margin-top: 2px; margin-bottom: 3px;"><i class="fa-solid fa-cube" style="color: #f59e0b; margin-right: 4px;"></i> ${row.model_name || '-'}</div>
                    <div style="font-size: 11px; color: var(--text-muted);">
                        <span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 1px 4px; border-radius: 3px; margin-right: 4px;">${row.line_name}</span>
                        <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 1px 4px; border-radius: 3px; margin-right: 4px;">${row.section_name}</span>
                        <span style="color: #94a3b8;">${row.process_name}</span>
                    </div>
                </td>
            `;

            if (hasTodaySlots) {
                let slotsArr = row.slots || [];
                let tLabels = row.time_labels || defaultTimeLabels;
                for (let s = 1; s <= 11; s++) {
                    let status = slotsArr[s - 1] !== undefined ? slotsArr[s - 1] : 4;
                    let slotTime = tLabels[s - 1] || defaultTimeLabels[s - 1] || '';
                    let blockClass, blockTitle, blockIcon;

                    if (status === 4) {
                        blockClass = 'block-na';
                        blockTitle = 'N/A (Exceeds max slots)';
                        blockIcon = '<i class="fa-solid fa-ban" style="color: #64748b; font-size: 10px; line-height: 22px;"></i>';
                    } else if (status === 2) {
                        blockClass = 'block-closed';
                        blockTitle = 'Shift Session Closed';
                        blockIcon = '<i class="fa-solid fa-lock" style="color: #fff; font-size: 10px; line-height: 22px;"></i>';
                    } else if (status === 1) {
                        blockClass = 'block-filled';
                        blockTitle = 'Filled (Recorded)';
                        blockIcon = '<i class="fa-solid fa-check" style="color: #fff; font-size: 11px; line-height: 22px;"></i>';
                    } else if (status === 3) {
                        blockClass = 'block-weekend';
                        blockTitle = 'Weekend / Future Slot (' + slotTime + ')';
                        blockIcon = `<span style="font-size: 9px; color: #94a3b8; font-weight: bold; line-height: 22px;">${slotTime}</span>`;
                    } else {
                        // Status 0: Missing / Overdue
                        blockClass = 'block-empty blinking-outline';
                        blockTitle = 'Overdue / Belum Terisi (' + slotTime + ')';
                        blockIcon = `<span style="font-size: 9px; color: white; font-weight: bold; line-height: 22px;">${slotTime}</span>`;
                    }

                    let link = `index.php?page=dtc_detail&param_id=${row.parameter_id}&month=${month}`;
                    html += `
                        <td class="day-col">
                            <a href="${link}" style="text-decoration:none;">
                                <div class="block-cell ${blockClass}">
                                    ${blockIcon}
                                    <span class="block-tooltip">Slot ${s} (${slotTime}) - ${blockTitle}</span>
                                </div>
                            </a>
                        </td>
                    `;
                }
            } else {
                for (let i = 1; i <= daysCount; i++) {
                    let status = row[`day_${i}`];
                    let timeLabel = row[`day_${i}_label`] || '';
                    let blockClass, blockTitle, blockIcon;

                    if (status === 3) {
                        blockClass = 'block-weekend';
                        blockTitle = 'Weekend / Off';
                        blockIcon = '<i class="fa-solid fa-minus" style="color: #94a3b8; font-size: 10px; line-height: 22px;"></i>';
                    } else if (status === 2) {
                        blockClass = 'block-closed';
                        blockTitle = 'Closed';
                        blockIcon = '<i class="fa-solid fa-lock" style="color: #fff; font-size: 10px; line-height: 22px;"></i>';
                    } else if (status === 1) {
                        blockClass = 'block-filled';
                        blockTitle = 'Draft (Next: ' + timeLabel + ')';
                        blockIcon = timeLabel ? `<span style="font-size: 9px; color: white; font-weight: bold; line-height: 22px; letter-spacing: -0.5px;">${timeLabel}</span>` : '<i class="fa-solid fa-pen" style="color: #fff; font-size: 10px; line-height: 22px;"></i>';
                    } else {
                        blockClass = 'block-empty';
                        blockTitle = 'Missing (Next: ' + timeLabel + ')';
                        blockIcon = timeLabel ? `<span style="font-size: 9px; color: white; font-weight: bold; line-height: 22px; letter-spacing: -0.5px;">${timeLabel}</span>` : '<i class="fa-solid fa-xmark" style="color: #fff; font-size: 12px; line-height: 22px;"></i>';
                    }

                    let dateParam = `${month}-${String(i).padStart(2, '0')}`;
                    let link = `index.php?page=dtc_detail&param_id=${row.parameter_id}&month=${month}&auto_add=${dateParam}`;
                    html += `
                        <td class="day-col">
                            <a href="${link}" style="text-decoration:none;">
                                <div class="block-cell ${blockClass}">
                                    ${blockIcon}
                                    <span class="block-tooltip">Day ${i} - ${blockTitle}</span>
                                </div>
                            </a>
                        </td>
                    `;
                }
            }

            html += `</tr>`;
        });

        html += `
                </tbody>
            </table>
            </div>
        `;

        $('#table-container').html(html);

        if (currentDetailTable) {
            currentDetailTable.destroy();
        }

        currentDetailTable = $('#missing-data-table').DataTable({
            responsive: false,
            scrollX: false,
            pageLength: 10,
            lengthChange: false,
            ordering: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search parameters..."
            }
        });
    }

    function populateLineDropdown(data) {
        if ($('#filter_line_name option').length > 1) return;

        let uniqueLines = new Set();
        data.forEach(row => {
            if (row.line_name) uniqueLines.add(row.line_name);
        });

        let currentVal = $('#filter_line_name').val();
        let html = '<option value="">-- All Lines --</option>';
        Array.from(uniqueLines).sort().forEach(line => {
            let selected = (line === currentVal) ? 'selected' : '';
            html += `<option value="${line}" ${selected}>${line}</option>`;
        });
        $('#filter_line_name').html(html);
    }

    function populateSectionDropdown(data) {
        if ($('#filter_section_name option').length > 1) return;

        let uniqueSections = new Set();
        data.forEach(row => {
            if (row.section_name) uniqueSections.add(row.section_name);
        });

        let currentVal = $('#filter_section_name').val();
        let html = '<option value="">-- All Sections --</option>';
        Array.from(uniqueSections).sort().forEach(sec => {
            let selected = (sec === currentVal) ? 'selected' : '';
            html += `<option value="${sec}" ${selected}>${sec}</option>`;
        });
        $('#filter_section_name').html(html);
    }

    // View Mode Toggle Events
    $('.view-mode-btn').on('click', function () {
        $('.view-mode-btn').removeClass('active').css({ 'background': 'transparent', 'color': 'var(--text-muted)' });
        $(this).addClass('active').css({ 'background': 'var(--primary)', 'color': 'white' });

        currentViewMode = $(this).data('mode');
        if (currentViewMode === 'summary') {
            $('#summary-section-container').show();
            $('#detail-section-container').hide();
        } else {
            $('#summary-section-container').hide();
            $('#detail-section-container').show();
        }

        if (window.triggerFitScreen) {
            setTimeout(() => { window.triggerFitScreen(true); }, 50);
        }
    });

    // Event Listeners
    $('#filter_line_name, #filter_section_name').on('change', function () {
        applyFilters();
    });

    $(document).on('click', '.filter-tab-btn', function () {
        $('.filter-tab-btn').removeClass('active');
        $(this).addClass('active');
        applyFilters();
    });

    // Full Mode (Hide Topbar & Sidebar) Toggle Handler
    $(document).on('click', '#btn-wall-fullscreen', function () {
        let isFull = $('body').hasClass('wall-display-focus');

        if (!isFull) {
            $('body').addClass('wall-display-focus');
            $(this).html('<i class="fa-solid fa-compress"></i> Normal Mode')
                .css({ 'background': 'linear-gradient(135deg, #059669, #047857)', 'border-color': 'rgba(52, 211, 153, 0.5)', 'box-shadow': '0 0 12px rgba(16, 185, 129, 0.5)' });
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function (err) { });
            }
        } else {
            $('body').removeClass('wall-display-focus');
            $(this).html('<i class="fa-solid fa-expand"></i> Full Mode')
                .css({ 'background': 'linear-gradient(135deg, #0284c7, #0369a1)', 'border-color': 'rgba(56, 189, 248, 0.5)', 'box-shadow': '0 0 12px rgba(2, 132, 199, 0.4)' });
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(function (err) { });
            }
        }
    });

    // Detect ESC key or native exit fullscreen to sync button state
    $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function () {
        let isNativeFull = !!(document.fullscreenElement || document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement);
        if (!isNativeFull && $('body').hasClass('wall-display-focus')) {
            $('body').removeClass('wall-display-focus');
            $('#btn-wall-fullscreen').html('<i class="fa-solid fa-expand"></i> Full Mode')
                .css({ 'background': 'linear-gradient(135deg, #0284c7, #0369a1)', 'border-color': 'rgba(56, 189, 248, 0.5)', 'box-shadow': '0 0 12px rgba(2, 132, 199, 0.4)' });
        }
    });

    // Push Alert / Follow-Up Action Handler for Admin Control Tower
    $(document).on('click', '.btn-push-alert', function (e) {
        e.stopPropagation();
        let line = $(this).data('line') || 'REF 01';
        let section = $(this).data('section') || 'All Sections';
        let overdue = $(this).data('overdue') || '0';

        Swal.fire({
            title: `<span style="color:#ef4444;"><i class="fa-solid fa-bell"></i> Push Follow-Up Alert</span>`,
            html: `
                <div style="text-align:left; font-size:13px; color:#cbd5e1; padding:6px 0;">
                    <p style="margin-bottom:10px;">Kirimkan peringatan pengisian data terlewat untuk <strong>LINE ${line}</strong> (${section}) dengan status Overdue: <strong style="color:#f87171;">${overdue}%</strong>.</p>
                    <label style="font-size:11px; color:#94a3b8; font-weight:700; display:block; margin-bottom:5px;">Pesan Peringatan / Catatan Follow-Up Admin:</label>
                    <textarea id="alert-push-msg" style="width:100%; height:75px; background:rgba(15,23,42,0.8); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:6px; padding:8px; font-size:12px;" placeholder="Harap segera lengkapi slot data terlewat pada shift ini..."></textarea>
                </div>
            `,
            background: '#0f172a',
            color: '#f8fafc',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fa-solid fa-paper-plane"></i> Kirim Alert',
            cancelButtonText: 'Batal'
        }).then((res) => {
            if (res.isConfirmed) {
                let msg = $('#alert-push-msg').val() || 'Harap segera lengkapi slot data terlewat pada shift ini.';
                Swal.fire({
                    icon: 'success',
                    title: 'Alert Berhasil Terkirim!',
                    text: `Peringatan untuk LINE ${line} (${section}) telah dicatat & dipush: "${msg}"`,
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#10b981'
                });
            }
        });
    });

    // Navigate to DTC List or DTC History filtered by selected Section & Line when clicking Section Card
    $(document).on('click', '.section-card-clickable', function (e) {
        if ($(e.target).closest('.btn-push-alert').length) return; // Ignore push alert button clicks
        let line = $(this).data('line') || '';
        let section = $(this).data('section') || '';
        let activeType = $('.filter-tab-btn.active').data('filter') || '';

        let currentMonthStr = new Date().toISOString().slice(0, 7);
        let selectedMonth = $('#filter_month').val() || (rawApiResponse ? rawApiResponse.month : currentMonthStr);
        let isPastMonth = (selectedMonth && selectedMonth < currentMonthStr);

        let targetPage = isPastMonth ? 'dtc_history' : 'dtc_list';
        let url = `index.php?page=${targetPage}`;
        let params = [];

        if (isPastMonth) params.push(`month=${encodeURIComponent(selectedMonth)}`);
        if (line) params.push(`line=${encodeURIComponent(line)}`);
        if (section) params.push(`section=${encodeURIComponent(section)}`);
        if (activeType) params.push(`type=${encodeURIComponent(activeType)}`);

        if (params.length > 0) url += `&` + params.join('&');
        window.location.href = url;
    });

    // Navigate to DTC List or DTC History filtered by selected Line when clicking top Line KPI Banner Cards
    $(document).on('click', '.line-kpi-card-clickable', function () {
        let line = $(this).data('line') || '';
        let activeType = $('.filter-tab-btn.active').data('filter') || '';

        let currentMonthStr = new Date().toISOString().slice(0, 7);
        let selectedMonth = $('#filter_month').val() || (rawApiResponse ? rawApiResponse.month : currentMonthStr);
        let isPastMonth = (selectedMonth && selectedMonth < currentMonthStr);

        let targetPage = isPastMonth ? 'dtc_history' : 'dtc_list';
        let url = `index.php?page=${targetPage}`;
        let params = [];

        if (isPastMonth) params.push(`month=${encodeURIComponent(selectedMonth)}`);
        if (line) params.push(`line=${encodeURIComponent(line)}`);
        if (activeType) params.push(`type=${encodeURIComponent(activeType)}`);

        if (params.length > 0) url += `&` + params.join('&');
        window.location.href = url;
    });

    // Monthly Station Performance Report Modal & Download Handlers
    let activeExportSection = '';
    let activeExportLine = '';

    function openMonthlyPerfModal(section = '', line = '') {
        activeExportSection = section;
        activeExportLine = line;

        let month = $('#report_month').val() || new Date().toISOString().slice(0, 7);
        let titleText = section ? `Laporan Performance Bulanan - ${line} (${section})` : `Laporan Performance Bulanan (Semua Stasiun)`;
        $('#perf-modal-title').text(titleText);
        $('#perf-modal-info').text(`Report Month: ${month} | Station Scope: ${section || 'All Assigned Stations'}`);
        $('#modal-monthly-performance').css('display', 'flex');

        $('#perf-modal-body').html('<div style="text-align: center; padding: 40px; color: var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p style="margin-top: 10px;">Generating performance report preview...</p></div>');

        $.ajax({
            url: 'Script/php/dtc/c_missing_data_monthly_report.php',
            type: 'GET',
            data: { month: month, section_name: section, line_name: line, format: 'json' },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success' && res.data) {
                    renderPerfModalBody(res);
                } else {
                    $('#perf-modal-body').html(`<div style="color: var(--danger); text-align: center; padding: 20px;">Failed to generate preview: ${res.message || 'Unknown error'}</div>`);
                }
            },
            error: function () {
                $('#perf-modal-body').html('<div style="color: var(--danger); text-align: center; padding: 20px;">Failed to connect to report server.</div>');
            }
        });
    }

    function renderPerfModalBody(res) {
        let html = '';
        let sections = res.data || [];

        if (sections.length === 0) {
            html = '<div style="text-align:center; padding:30px; color:var(--text-muted);">Tidak ada data stasiun pada bulan ini.</div>';
        } else {
            sections.forEach(sec => {
                let rateVal = sec.monthly_compliance_rate !== undefined ? sec.monthly_compliance_rate : 100;
                let rateColor = rateVal >= 90 ? '#34d399' : '#f87171';

                html += `
                <div style="background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 10px;">
                        <div>
                            <span style="background: rgba(59,130,246,0.2); color: #60a5fa; padding: 2px 8px; border-radius: 4px; font-weight: 800; font-size: 11px;">LINE ${sec.line_name}</span>
                            <span style="font-size: 16px; font-weight: 800; color: #38bdf8; margin-left: 8px;">${sec.section_name}</span>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center; font-size: 12px;">
                            <span style="color: #cbd5e1;">Compliance Bulanan: <strong style="color: ${rateColor}; font-size: 16px; font-weight: 900;">${rateVal}%</strong></span>
                            <span style="color: #cbd5e1;">Hari Tidak Full: <strong style="color: #f87171; font-size: 15px;">${sec.total_days_incomplete} Hari</strong></span>
                        </div>
                    </div>

                    <!-- Summary KPI Cards Row -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px;">
                        <div style="background: rgba(255,255,255,0.05); padding: 8px; border-radius: 6px; text-align: center; border: 1px solid rgba(255,255,255,0.08);">
                            <div style="font-size: 10px; color: #94a3b8; font-weight: 700;">TOTAL EXPECTED SLOTS</div>
                            <div style="font-size: 18px; font-weight: 900; color: #f8fafc;">${(sec.total_expected_month || 0).toLocaleString()}</div>
                        </div>
                        <div style="background: rgba(16,185,129,0.1); padding: 8px; border-radius: 6px; text-align: center; border: 1px solid rgba(16,185,129,0.2);">
                            <div style="font-size: 10px; color: #34d399; font-weight: 700;">TOTAL SLOTS DIISI</div>
                            <div style="font-size: 18px; font-weight: 900; color: #34d399;">${(sec.total_filled_month || 0).toLocaleString()}</div>
                        </div>
                        <div style="background: rgba(239,68,68,0.1); padding: 8px; border-radius: 6px; text-align: center; border: 1px solid rgba(239,68,68,0.2);">
                            <div style="font-size: 10px; color: #f87171; font-weight: 700;">SLOTS KOSONG</div>
                            <div style="font-size: 18px; font-weight: 900; color: #f87171;">${(sec.total_missing_month || 0).toLocaleString()}</div>
                        </div>
                        <div style="background: rgba(56,189,248,0.1); padding: 8px; border-radius: 6px; text-align: center; border: 1px solid rgba(56,189,248,0.2);">
                            <div style="font-size: 10px; color: #38bdf8; font-weight: 700;">PERSENTASE COMPLIANCE</div>
                            <div style="font-size: 18px; font-weight: 900; color: ${rateColor};">${rateVal}%</div>
                        </div>
                    </div>

                    <div style="font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 6px;">1. Persentase Pengisian Time Check Harian (% Daily Filling)</div>
                    <div style="overflow-x: auto; margin-bottom: 12px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center;">
                            <thead>
                                <tr style="background: rgba(30,41,59,0.9); color: #94a3b8;">
                                    <th style="padding: 6px; border: 1px solid rgba(255,255,255,0.1);">Tanggal</th>
                                    <th style="padding: 6px; border: 1px solid rgba(255,255,255,0.1);">Expected</th>
                                    <th style="padding: 6px; border: 1px solid rgba(255,255,255,0.1);">Filled</th>
                                    <th style="padding: 6px; border: 1px solid rgba(255,255,255,0.1);">Completion Rate (%)</th>
                                    <th style="padding: 6px; border: 1px solid rgba(255,255,255,0.1);">Status</th>
                                </tr>
                            </thead>
                            <tbody>`;

                Object.keys(sec.days || {}).sort().forEach(dStr => {
                    let d = sec.days[dStr];
                    let bgStr = d.is_weekend ? 'rgba(255,255,255,0.03)' : (d.is_full ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.15)');
                    let rateColor = d.is_full ? '#34d399' : '#f87171';
                    let statusLabel = d.is_weekend ? 'Weekend' : (d.is_full ? '<span style="color:#34d399; font-weight:800;">FULL (100%)</span>' : '<span style="color:#f87171; font-weight:800;">INCOMPLETE</span>');

                    html += `
                        <tr style="background: ${bgStr};">
                            <td style="padding: 5px; border: 1px solid rgba(255,255,255,0.05); font-weight: 700;">${dStr}</td>
                            <td style="padding: 5px; border: 1px solid rgba(255,255,255,0.05);">${d.expected_slots}</td>
                            <td style="padding: 5px; border: 1px solid rgba(255,255,255,0.05);">${d.filled_slots}</td>
                            <td style="padding: 5px; border: 1px solid rgba(255,255,255,0.05); font-weight: 800; color: ${rateColor};">${d.completion_rate}%</td>
                            <td style="padding: 5px; border: 1px solid rgba(255,255,255,0.05);">${statusLabel}</td>
                        </tr>`;
                });

                html += `
                            </tbody>
                        </table>
                    </div>

                    <div style="font-size: 12px; font-weight: 700; color: #f87171; margin-top: 10px; margin-bottom: 6px;">2. Rincian Item Check / Parameter yang Tidak Diisi (Slot Kosong)</div>`;

                let missedDates = Object.keys(sec.missed_items_by_date || {}).sort();
                if (missedDates.length > 0) {
                    html += `
                    <div style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.3); border: 1px solid rgba(239,68,68,0.2); border-radius: 6px; padding: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <thead>
                                <tr style="color: #f87171; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <th style="padding: 4px;">Tanggal</th>
                                    <th style="padding: 4px;">Model</th>
                                    <th style="padding: 4px;">Process</th>
                                    <th style="padding: 4px;">Item Check Name</th>
                                    <th style="padding: 4px; text-align: center;">Slot Kosong</th>
                                </tr>
                            </thead>
                            <tbody>`;
                    missedDates.forEach(dStr => {
                        (sec.missed_items_by_date[dStr] || []).forEach(item => {
                            html += `
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 4px; font-weight: 700; color: #cbd5e1;">${dStr}</td>
                                    <td style="padding: 4px;">${item.model_name || '-'}</td>
                                    <td style="padding: 4px; color: #94a3b8;">${item.process_name}</td>
                                    <td style="padding: 4px; color: #f8fafc; font-weight: 700;">${item.item_check_name}</td>
                                    <td style="padding: 4px; text-align: center; color: #f87171; font-weight: 800;">${item.missing_slots} Slot Kosong</td>
                                </tr>`;
                        });
                    });
                    html += `
                            </tbody>
                        </table>
                    </div>`;
                } else {
                    html += `<p style="font-size: 11px; color: #34d399; margin: 4px 0; font-style: italic;">Sempurna! Semua item check di stasiun ini terisi 100% full.</p>`;
                }

                html += `</div>`;
            });
        }

        $('#perf-modal-body').html(html);
    }

    $(document).on('click', '.btn-download-section-report', function (e) {
        e.stopPropagation();
        let section = $(this).data('section') || '';
        let line = $(this).data('line') || '';
        openMonthlyPerfModal(section, line);
    });

    $(document).on('click', '#btn-export-monthly-report', function () {
        openMonthlyPerfModal('', '');
    });

    $(document).on('click', '#btn-download-modal-excel', function () {
        let month = $('#report_month').val() || new Date().toISOString().slice(0, 7);
        let url = `Script/php/dtc/c_missing_data_monthly_report.php?month=${month}&section_name=${encodeURIComponent(activeExportSection)}&line_name=${encodeURIComponent(activeExportLine)}&export=excel`;
        window.location.href = url;
    });

    $(document).on('click', '#btn-close-perf-modal, #btn-cancel-perf-modal', function () {
        $('#modal-monthly-performance').css('display', 'none');
    });

    // Initial Data Load
    reloadData(false);

    // Auto-Refresh API polling disabled per user preference
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
});
