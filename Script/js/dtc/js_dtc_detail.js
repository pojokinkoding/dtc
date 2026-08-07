// js_dtc_detail.js

$(document).ready(function () {
    // Retrieve specs dynamically from hidden inputs set by PHP
    const LSL = parseFloat(document.getElementById('spec_lsl').value) || 0;
    const USL = parseFloat(document.getElementById('spec_usl').value) || 0;

    // Helper to get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const paramId = urlParams.get('param_id') || 1;
    let currentMonth = urlParams.get('month');

    if (!currentMonth) {
        // If they came from an old link with date=2026-06-30, extract month
        const dateParam = urlParams.get('date');
        if (dateParam) {
            currentMonth = dateParam.substring(0, 7);
        } else {
            const today = new Date();
            currentMonth = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, '0');
        }
    }

    // Calculate days in the current month
    const [cmYear, cmMonth] = currentMonth.split('-');
    const daysInMonth = new Date(parseInt(cmYear), parseInt(cmMonth), 0).getDate();

    function loadData() {
        if (typeof isQualitative !== 'undefined' && isQualitative) {
            return; // Qualitative matrix uses js_dtc_qualitative.js instead
        }
        // 1. Fetch and Generate HTML Matrix & Charts
        $.ajax({
            url: `Script/php/dtc/c_dtc_matrix.php?param_id=${paramId}&month=${currentMonth}`,
            type: "GET",
            cache: false,
            dataType: "json",
            success: function (response) {
                if (response.error) {
                    $("#matrix-body").empty().append(`<tr><td colspan="32" style="text-align: center; padding: 20px; color: red;">Error: ${response.error}</td></tr>`);
                    return;
                }

                let data = response.matrix || [];
                let chartData = response.charts || {};

                // If server found data in a different month (fallback), use that month
                const displayMonth = response.actual_month || currentMonth;
                const [dmYear, dmMonth] = displayMonth.split('-');
                const activeDaysInMonth = new Date(parseInt(dmYear), parseInt(dmMonth), 0).getDate();

                let headerRow = $("#matrix-header");
                let tbody = $("#matrix-body");

                // Clear previous data for reload
                headerRow.find("th:not(.sticky-col)").remove();
                tbody.empty();

                // Build day headers
                for (let i = 1; i <= activeDaysInMonth; i++) {
                    let d = new Date(parseInt(dmYear), parseInt(dmMonth) - 1, i);
                    let dayOfWeek = d.getDay();
                    let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                    let bgStyle = isWeekend ? 'background-color: rgba(100,100,100,0.35); color: #777;' : '';
                    headerRow.append(`<th style="min-width: 80px; ${bgStyle}">${i}</th>`);
                }

                if (data.length === 0) {
                    tbody.append(`<tr><td colspan="32" style="text-align: center; padding: 20px;">No records available for this month</td></tr>`);
                    return;
                }

                // Build data rows
                data.forEach(function (row) {
                    let isSummary = (row.jam === "Max Data" || row.jam === "Min Data");
                    let trClass = isSummary ? "summary-row" : "";

                    let tr = `<tr class="${trClass}">`;
                    tr += `<td class="sticky-col">${row.jam}</td>`;

                    for (let i = 1; i <= activeDaysInMonth; i++) {
                        let d = new Date(parseInt(dmYear), parseInt(dmMonth) - 1, i);
                        let dayOfWeek = d.getDay();
                        let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

                        let val = row["day_" + i];
                        let displayVal = val !== null ? parseFloat(val).toFixed(2) : "";

                        let cellClass = "";
                        let bgStyle = isWeekend ? 'background-color: rgba(100,100,100,0.15); color: #555;' : '';

                        if (!isSummary && val !== null) {
                            if (val < LSL || val > USL) {
                                cellClass = "oos-cell";
                            }
                        }

                        tr += `<td class="${cellClass}" style="${bgStyle}">${displayVal}</td>`;
                    }
                    tr += `</tr>`;
                    tbody.append(tr);
                });

                // Generate Categories for Charts
                let dayCategories = [];
                for (let i = 1; i <= activeDaysInMonth; i++) dayCategories.push(i.toString());

                // Calculate Means for X-Bar CL and R-Chart CL
                let xbarMean = null;
                let validXbar = chartData.xbar.filter(v => v !== null);
                if (validXbar.length > 0) xbarMean = validXbar.reduce((a, b) => a + b, 0) / validXbar.length;

                let rMean = null;
                let validR = chartData.r.filter(v => v !== null);
                if (validR.length > 0) rMean = validR.reduce((a, b) => a + b, 0) / validR.length;

                let r_n = data.filter(r => r.is_sample).length || 5;
                let D3 = 0, D4 = 2.114; // Default n=5
                if (r_n === 2) { D3 = 0; D4 = 3.267; }
                else if (r_n === 3) { D3 = 0; D4 = 2.574; }
                else if (r_n === 4) { D3 = 0; D4 = 2.282; }
                else if (r_n === 5) { D3 = 0; D4 = 2.114; }
                else if (r_n === 6) { D3 = 0; D4 = 2.004; }
                else if (r_n === 7) { D3 = 0.076; D4 = 1.924; }
                else if (r_n === 8) { D3 = 0.136; D4 = 1.864; }
                else if (r_n === 9) { D3 = 0.184; D4 = 1.816; }
                else if (r_n >= 10) { D3 = 0.223; D4 = 1.777; }

                let UCL_R = D4 * (rMean || 0);
                let LCL_R = D3 * (rMean || 0);

                // Get dynamic colors from CSS variables
                const docStyle = getComputedStyle(document.body);
                const colPrimary = docStyle.getPropertyValue('--primary').trim() || '#3b82f6';
                const colDanger = docStyle.getPropertyValue('--danger').trim() || '#ef4444';
                const colAccent = docStyle.getPropertyValue('--accent').trim() || '#10b981';
                const colPurple = docStyle.getPropertyValue('--purple').trim() || '#8b5cf6';

                // Inject dynamic CSS for glowing lines
                let glowStyle = `
                    <style id="dynamic-glow-style">
                        #chart-xbar svg path[stroke="${colPrimary}"] {
                            filter: drop-shadow(0px 0px 6px ${colPrimary});
                        }
                        #chart-r svg path[stroke="${colPurple}"] {
                            filter: drop-shadow(0px 0px 6px ${colPurple});
                        }
                        #chart-capability svg path[stroke="${colPurple}"] {
                            filter: drop-shadow(0px 0px 6px ${colPurple});
                        }
                    </style>
                `;
                $('#dynamic-glow-style').remove();
                $('head').append(glowStyle);

                // Calculate dynamic Y-axis min/max for X-Bar Chart
                let allXbarVals = [LSL, USL, xbarMean, ...validXbar].filter(v => v !== null && v !== undefined && !isNaN(v));
                let xbarMinVal = allXbarVals.length > 0 ? Math.min(...allXbarVals) : 0;
                let xbarMaxVal = allXbarVals.length > 0 ? Math.max(...allXbarVals) : 10;
                let xbarSpan = xbarMaxVal - xbarMinVal;
                let xbarPad = (xbarSpan === 0) ? (Math.abs(xbarMaxVal) > 0 ? Math.abs(xbarMaxVal) * 0.1 : 0.5) : Math.max(xbarSpan * 0.25, 0.1);
                let axisMinXbar = xbarMinVal - xbarPad;
                let axisMaxXbar = xbarMaxVal + xbarPad;
                let formatXbar = (xbarSpan < 2) ? "{0:n2}" : "{0:n1}";

                // 2. Initialize X-Bar Chart
                $("#chart-xbar").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    legend: { position: "bottom", labels: { color: "#94a3b8", font: "11px Inter, sans-serif" } },
                    series: [{
                        type: "line",
                        data: chartData.xbar,
                        name: "Avg(X-Bar)",
                        color: colPrimary,
                        markers: { size: 5, background: colPrimary, border: { color: colPrimary, width: 2 } }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(USL),
                        name: "USL",
                        color: colDanger,
                        dashType: "dash",
                        markers: { visible: false }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(xbarMean),
                        name: "CL (Mean)",
                        color: colAccent,
                        dashType: "longDash",
                        markers: { visible: false }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(LSL),
                        name: "LSL",
                        color: colDanger,
                        dashType: "dash",
                        markers: { visible: false }
                    }],
                    categoryAxis: {
                        categories: dayCategories,
                        labels: { visible: true, color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                        majorGridLines: { visible: false },
                        justified: true,
                        title: { text: "Date (Day of Month)", color: "#64748b", font: "10px Inter, sans-serif" }
                    },
                    valueAxis: {
                        min: axisMinXbar,
                        max: axisMaxXbar,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif", format: formatXbar },
                        plotBands: [
                            { from: LSL, to: USL, color: "rgba(16, 185, 129, 0.1)" }
                        ]
                    },
                    tooltip: {
                        visible: true,
                        shared: true,
                        sharedTemplate: "<div><strong>Tanggal #= category #</strong></div>" +
                            "<table>" +
                            "# for (var i = 0; i < points.length; i++) { #" +
                            "<tr><td style='padding-right:8px;'><span style='color: #= points[i].series.color #'>#= points[i].series.name #</span>: </td>" +
                            "<td><b>#= points[i].value !== null ? kendo.toString(points[i].value, 'n2') : 'N/A' #</b></td></tr>" +
                            "# } #" +
                            "</table>"
                    }
                });

                // Calculate dynamic Y-axis min/max for R Chart
                let validRData = (chartData.r || []).filter(v => v !== null && v !== undefined && !isNaN(v));
                let allRVals = [LCL_R, UCL_R, rMean, ...validRData].filter(v => v !== null && v !== undefined && !isNaN(v));
                let rMinVal = allRVals.length > 0 ? Math.min(...allRVals) : 0;
                let rMaxVal = allRVals.length > 0 ? Math.max(...allRVals) : 1;
                let rSpan = rMaxVal - rMinVal;
                let rPad = (rSpan === 0) ? (Math.abs(rMaxVal) > 0 ? Math.abs(rMaxVal) * 0.2 : 0.05) : Math.max(rSpan * 0.25, 0.02);
                let axisMinR = Math.max(0, rMinVal - rPad);
                let axisMaxR = rMaxVal + rPad;
                let formatR = (rSpan < 2) ? "{0:n2}" : "{0:n1}";

                // 3. Initialize R Chart (Range)
                $("#chart-r").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    legend: { position: "bottom", labels: { color: "#94a3b8", font: "11px Inter, sans-serif" } },
                    series: [{
                        type: "line",
                        data: chartData.r,
                        name: "Range",
                        color: colPurple,
                        markers: { size: 5, background: colPurple, border: { color: colPurple, width: 2 } }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(UCL_R),
                        name: "UCL",
                        color: colDanger,
                        dashType: "dash",
                        markers: { visible: false }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(rMean),
                        name: "CL (Mean)",
                        color: colAccent,
                        dashType: "longDash",
                        markers: { visible: false }
                    }, {
                        type: "line",
                        data: Array(daysInMonth).fill(LCL_R),
                        name: "LCL",
                        color: colDanger,
                        dashType: "dash",
                        markers: { visible: false }
                    }],
                    categoryAxis: {
                        categories: dayCategories,
                        labels: { visible: true, color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                        majorGridLines: { visible: false },
                        justified: true,
                        title: { text: "Date (Day of Month)", color: "#64748b", font: "10px Inter, sans-serif" }
                    },
                    valueAxis: {
                        min: axisMinR,
                        max: axisMaxR,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif", format: formatR },
                        plotBands: [
                            { from: LCL_R, to: UCL_R, color: "rgba(16, 185, 129, 0.1)" }
                        ]
                    },
                    tooltip: {
                        visible: true,
                        shared: true,
                        sharedTemplate: "<div><strong>Tanggal #= category #</strong></div>" +
                            "<table>" +
                            "# for (var i = 0; i < points.length; i++) { #" +
                            "<tr><td style='padding-right:8px;'><span style='color: #= points[i].series.color #'>#= points[i].series.name #</span>: </td>" +
                            "<td><b>#= points[i].value !== null ? kendo.toString(points[i].value, 'n2') : 'N/A' #</b></td></tr>" +
                            "# } #" +
                            "</table>"
                    }
                });

                // 4. Initialize Process Capability Curve (Bell Curve)
                let allSamples = [];
                data.forEach(row => {
                    if (row.is_sample) {
                        for (let i = 1; i <= daysInMonth; i++) {
                            let val = row["day_" + i];
                            if (val !== null && val !== undefined && val !== "") {
                                allSamples.push(parseFloat(val));
                            }
                        }
                    }
                });

                let capabilityData = [];
                let c_mean = null;
                let c_minX = null;
                let c_maxX = null;

                let centerSpec = (USL + LSL) / 2;
                $("#summ-center").text(kendo.toString(centerSpec, "n2"));

                if (allSamples.length > 1) {
                    let sum = allSamples.reduce((a, b) => a + b, 0);
                    let mean = sum / allSamples.length;
                    c_mean = mean;
                    let sumSq = allSamples.reduce((a, b) => a + Math.pow(b - mean, 2), 0);
                    let std = Math.sqrt(sumSq / (allSamples.length - 1));


                    if (std > 0) {
                        let cpu = (USL - mean) / (3 * std);
                        let cpl = (mean - LSL) / (3 * std);
                        let cpk = Math.min(cpu, cpl);

                        // --- 4 BLOCK DIAGRAM (Zst vs Z-shift) ---
                        let dailyStds = [];
                        data.forEach(row => {
                            if (row.jam === "Std Dev") {
                                for (let i = 1; i <= 31; i++) {
                                    let v = row["day_" + i];
                                    if (v && v > 0) dailyStds.push(parseFloat(v));
                                }
                            }
                        });

                        let stdDevToUse = std; // Using overall std as in Excel

                        let cp = (USL - LSL) / (6 * stdDevToUse);
                        let zltTrue = 3 * cpk;
                        let zstTrue = 3 * cp;
                        let zShift = zstTrue - zltTrue;

                        let maxData = Math.max(...allSamples);
                        let minData = Math.min(...allSamples);

                        $("#summ-n").text(allSamples.length);
                        $("#summ-max").text(kendo.toString(maxData, "n2"));
                        $("#summ-min").text(kendo.toString(minData, "n2"));
                        $("#summ-avg").text(kendo.toString(mean, "n2"));
                        $("#summ-std").text(kendo.toString(stdDevToUse, "n2"));
                        $("#summ-cp").text(kendo.toString(cp, "n2"));
                        $("#summ-cpk").text(kendo.toString(cpk, "n2"));
                        $("#summ-zst").text(kendo.toString(zstTrue, "n2"));
                        $("#summ-zlt").text(kendo.toString(zltTrue, "n2"));

                        // Coloring UX for Cp and Cpk
                        if (cp < 1.0) { $("#summ-cp").css("color", "var(--danger)"); }
                        else if (cp < 1.33) { $("#summ-cp").css("color", "#f59e0b"); }
                        else { $("#summ-cp").css("color", "var(--accent)"); }

                        if (cpk < 1.0) { $("#summ-cpk").css("color", "var(--danger)"); }
                        else if (cpk < 1.33) { $("#summ-cpk").css("color", "#f59e0b"); }
                        else { $("#summ-cpk").css("color", "var(--accent)"); }

                        // Coloring for Zst and Zlt
                        $("#summ-zst").css({ "color": "var(--primary)", "font-weight": "bold" });
                        $("#summ-zlt").css({ "color": "var(--primary)", "font-weight": "bold" });

                        // User requested capping logic
                        let cappedZst = zstTrue > 6.0 ? 6.0 : zstTrue;
                        let cappedZShift = zShift > 3.0 ? 3.0 : zShift;

                        let maxZstAxis = 6.5;
                        let maxZShiftAxis = 3.5;

                        // Analyze Outliers
                        let oosPoints = [];
                        allSamples.forEach(val => {
                            if (val < LSL || val > USL) {
                                oosPoints.push(val);
                            }
                        });
                        let oosStr = "";
                        if (oosPoints.length > 0) {
                            // Get unique outliers and sort them, show max 3
                            let uniqueOos = [...new Set(oosPoints)].sort((a, b) => a - b);
                            let sampleStr = uniqueOos.slice(0, 3).map(v => kendo.toString(v, "n2")).join(", ");
                            if (uniqueOos.length > 3) sampleStr += " etc.";
                            oosStr = ` <span style="color: var(--danger);">AI detected <strong>${oosPoints.length} outlier(s)</strong> (e.g., ${sampleStr}) outside the spec limits (${LSL} - ${USL}). Focus on eliminating these extreme variations.</span>`;
                        }

                        // AI Insight Generation (Prompt Simulation)
                        let aiText = "";
                        if (cpk < 1.0) {
                            aiText = `🚨 <strong>Process is Unstable.</strong> Cpk (${kendo.toString(cpk, "n2")}) is below 1.0, indicating high variation or shifting off-target.${oosStr}`;
                        } else if (cpk < 1.33) {
                            aiText = `⚠️ <strong>Process Needs Improvement.</strong> With a Cpk of ${kendo.toString(cpk, "n2")}, the process is capable but lacks safety margins against shifts.${oosStr ? oosStr : " Optimization recommended."}`;
                        } else {
                            aiText = `✅ <strong>Process is Stable & Capable.</strong> Cpk (${kendo.toString(cpk, "n2")}) indicates excellent control. The X-bar averages are tightly centered. Maintain current parameters.`;
                        }
                        if ($("#ai-insight-box").length) {
                            $("#ai-insight-box").html(aiText).css("color", "var(--text-light)");
                        }

                        let screenW = $(window).width();
                        let responsiveFont = screenW < 768 ? "bold 9px 'Inter', sans-serif" : (screenW < 1200 ? "bold 11px 'Inter', sans-serif" : "bold 13px 'Inter', sans-serif");

                        let rightCenterX = 4.75; // 3.0 + (6.5 - 3.0) / 2
                        let leftCenterX = 1.5; // 0 + 3.0 / 2
                        let topCenterY = 2.5; // 1.5 + (3.5 - 1.5) / 2

                        $("#chart-4block").kendoChart({
                            theme: "sass",
                            chartArea: { background: "transparent" },
                            series: [
                                {
                                    type: "scatter",
                                    data: [{ x: cappedZst, y: cappedZShift }],
                                    xField: "x",
                                    yField: "y",
                                    markers: { size: 16, type: "circle", background: "#f97316", border: { color: "white", width: 3 } },
                                    tooltip: { visible: true, template: "<b>Current Performance</b><br>Technology (Zst): #= kendo.toString(" + zstTrue + ", 'n2') #<br>Control (Z-Shift): #= kendo.toString(" + zShift + ", 'n2') #" }
                                },
                                {
                                    type: "scatter",
                                    data: [
                                        { x: leftCenterX, y: topCenterY, label: "Poor Tech & Control\n(Needs overhaul)", color: "rgba(239, 68, 68, 0.4)" },
                                        { x: rightCenterX, y: topCenterY, label: "Off Target\n(Needs centering)", color: "rgba(245, 158, 11, 0.4)" },
                                        { x: leftCenterX, y: 0.75, label: "Poor Technology\n(Needs variance reduction)", color: "rgba(245, 158, 11, 0.4)" },
                                        { x: rightCenterX, y: 0.75, label: "IDEAL STATE\n(World Class)", color: "rgba(16, 185, 129, 0.4)" }
                                    ],
                                    xField: "x",
                                    yField: "y",
                                    labels: {
                                        visible: true,
                                        template: "#= dataItem.label #",
                                        font: responsiveFont,
                                        color: function (e) { return e.dataItem.color; },
                                        position: "center"
                                    },
                                    markers: { visible: false },
                                    tooltip: { visible: false }
                                }
                            ],
                            xAxis: {
                                min: 0, max: maxZstAxis,
                                title: { text: "Technology / Capability (Zst)", color: "#94a3b8", font: "11px 'Inter', sans-serif" },
                                labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                                plotBands: [{ from: 2.98, to: 3.02, color: "rgba(148, 163, 184, 0.5)" }] // Vertical divider centered at 3
                            },
                            yAxis: {
                                min: 0, max: maxZShiftAxis,
                                title: { text: "Control / Stability (Z shift)", color: "#94a3b8", font: "11px 'Inter', sans-serif" },
                                labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                                plotBands: [{ from: 1.48, to: 1.52, color: "rgba(148, 163, 184, 0.5)" }] // Horizontal divider
                            }
                        });

                        let minX = Math.min(LSL - (0.1 * LSL), mean - 4 * std);
                        let maxX = Math.max(USL + (0.1 * USL), mean + 4 * std);
                        c_minX = minX;
                        c_maxX = maxX;
                        let step = (maxX - minX) / 100;

                        for (let x = minX; x <= maxX; x += step) {
                            let exponent = Math.exp(-0.5 * Math.pow((x - mean) / std, 2));
                            let y = (1 / (std * Math.sqrt(2 * Math.PI))) * exponent;
                            capabilityData.push({ x: x, y: y });
                        }
                    } else {
                        // std = 0 : all values are constant, show stats but mark Z/Cp as ∞
                        let maxData = Math.max(...allSamples);
                        let minData = Math.min(...allSamples);
                        $("#summ-n").text(allSamples.length);
                        $("#summ-max").text(kendo.toString(maxData, "n2"));
                        $("#summ-min").text(kendo.toString(minData, "n2"));
                        $("#summ-avg").text(kendo.toString(mean, "n2"));
                        $("#summ-std").text("0.00");
                        $("#summ-cp").text("∞").css({ "color": "var(--accent)", "font-weight": "bold" });
                        $("#summ-cpk").text("∞").css({ "color": "var(--accent)", "font-weight": "bold" });
                        $("#summ-zst").text("∞").css({ "color": "var(--primary)", "font-weight": "bold" });
                        $("#summ-zlt").text("∞").css({ "color": "var(--primary)", "font-weight": "bold" });
                        $("#chart-4block").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#10b981;font-size:13px;flex-direction:column;gap:8px;"><i class="fa-solid fa-infinity" style="font-size:28px;opacity:0.8;"></i><span>Semua data seragam (Std Dev = 0)<br>Process Capability = ∞ (Perfect)</span></div>');
                        if ($("#ai-insight-box").length) {
                            $("#ai-insight-box").html(`✅ <strong>Data Konstan / Zero Variance.</strong> Semua nilai pengukuran identik (= ${kendo.toString(mean, "n2")}). Standar deviasi = 0, sehingga Cp, Cpk, Zst, dan Zlt bernilai tak terhingga (∞) — yang secara teori menunjukkan proses sempurna. Pastikan data yang diinput sudah benar dan mencerminkan variasi aktual proses.`).css("color", "var(--text-light)");
                        }
                    }

                } else {
                    $("#summ-n").text("0");
                    $("#summ-max").text("-");
                    $("#summ-min").text("-");
                    $("#summ-avg").text("-");
                    $("#summ-std").text("-");
                    $("#summ-cp").text("-");
                    $("#summ-cpk").text("-");
                    $("#summ-zst").text("-");
                    $("#summ-zlt").text("-");
                    if ($("#ai-insight-box").length) {
                        $("#ai-insight-box").html(`<span style="color: #cbd5e1; font-style: normal;"><i class="fa-solid fa-circle-info" style="color: #38bdf8; margin-right: 6px;"></i> Belum ada data pengisian pengukuran pada bulan ini. Silakan klik <b>Input / Update Data</b> untuk mengisi data.</span>`);
                    }
                    $("#chart-4block").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:12px;"><i class="fa-solid fa-chart-pie" style="margin-right:6px;opacity:0.5;"></i> Belum ada data untuk 4-Block Diagram</div>');
                    $("#chart-capability").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:12px;"><i class="fa-solid fa-chart-line" style="margin-right:6px;opacity:0.5;"></i> Belum ada data untuk Process Capability Curve</div>');
                }

                let xAxisNotesData = [
                    { value: LSL, label: { text: "LSL: " + LSL, background: colDanger }, line: { color: colDanger, length: 70 } },
                    { value: USL, label: { text: "USL: " + USL, background: colDanger }, line: { color: colDanger, length: 70 } }
                ];
                if (c_mean !== null) {
                    xAxisNotesData.push({
                        value: c_mean,
                        label: { text: "Mean: " + kendo.toString(c_mean, 'n2'), background: colAccent, color: "#0f172a" },
                        line: { color: colAccent, length: 70 }
                    });
                }

                $("#chart-capability").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent", margin: { top: 40 } },
                    legend: { visible: false },
                    series: [{
                        type: "scatterLine",
                        style: "smooth",
                        data: capabilityData,
                        xField: "x",
                        yField: "y",
                        name: "Normal Distribution",
                        color: colPurple,
                        markers: { visible: false }
                    }],
                    xAxis: {
                        min: c_minX,
                        max: c_maxX,
                        labels: { visible: false },
                        majorGridLines: { visible: false },
                        majorTicks: { visible: false },
                        plotBands: [
                            { from: LSL, to: USL, color: "rgba(16, 185, 129, 0.1)" }
                        ],
                        notes: {
                            label: { color: "white", font: "10px Inter, sans-serif" },
                            data: xAxisNotesData
                        }
                    },
                    yAxis: {
                        labels: { visible: false },
                        majorGridLines: { visible: false }
                    },
                    tooltip: {
                        visible: true,
                        template: "Z-Value: <b>#= kendo.toString(value.x, 'n3') #</b><br>Density: <b>#= kendo.toString(value.y, 'n3') #</b>"
                    }
                });

                // 5. Daily Raw Samples Chart (Clustered Columns per day)
                let dailySeries = [];
                let sampleRows = data.filter(r => r.is_sample);

                sampleRows.forEach((row, index) => {
                    let sampleData = [];
                    for (let i = 1; i <= daysInMonth; i++) {
                        let val = row["day_" + i];
                        sampleData.push(val ? parseFloat(val) : null);
                    }
                    dailySeries.push({
                        type: "column",
                        name: "Sample " + (index + 1),
                        data: sampleData
                    });
                });

                $("#chart-histogram").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    series: dailySeries,
                    categoryAxis: {
                        categories: dayCategories,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" }
                    },
                    valueAxis: {
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                        plotBands: [
                            { from: LSL, to: USL, color: "rgba(16, 185, 129, 0.1)" }
                        ]
                    },
                    tooltip: { visible: true, format: "{0:n2}" }
                });

            },
            error: function (err) {
                console.error("Failed to fetch matrix data", err);
            }
        });

        // 4. Fetch and Generate Monthly Z-Trend (Jan - Dec)
        let currentYear = currentMonth.split('-')[0];
        $.ajax({
            url: `Script/php/dtc/c_dtc_ztrend.php?param_id=${paramId}&year=${currentYear}`,
            type: "GET",
            dataType: "json",
            success: function (trendData) {
                const monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

                let series = [];

                // Check if there is any actual data
                let hasActual = trendData.zst_actual && trendData.zst_actual.some(v => v !== null);

                let displayLabels = monthLabels;
                if (hasActual) {
                    let lastIndex = -1;
                    for (let i = 0; i < 12; i++) {
                        if (trendData.zst_actual[i] !== null) lastIndex = i;
                    }
                    if (lastIndex >= 0) {
                        trendData.zst_actual = trendData.zst_actual.slice(0, lastIndex + 1);
                        trendData.zlt_actual = trendData.zlt_actual.slice(0, lastIndex + 1);
                        displayLabels = monthLabels.slice(0, lastIndex + 1);
                    }

                    series.push({
                        type: "column",
                        data: trendData.zst_actual,
                        name: "ZST (Actual)",
                        color: "#10b981",
                        opacity: 1
                    });
                    series.push({
                        type: "column",
                        data: trendData.zlt_actual,
                        name: "ZLT (Actual)",
                        color: "#f59e0b",
                        opacity: 1
                    });
                }

                // No forecast logic

                // If no actual data at all, show empty state
                if (!hasActual) {
                    $("#chart-ztrend").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:12px;"><i class="fa-solid fa-chart-line" style="margin-right:6px;opacity:0.5;"></i> Belum ada data trend untuk tahun ini</div>');
                    if ($("#trend-insight-box").length) {
                        $("#trend-insight-box").html('<span style="color:#cbd5e1; font-style:normal;"><i class="fa-solid fa-circle-info" style="color:#38bdf8; margin-right:6px;"></i> Belum ada data trend untuk tahun ini.</span>');
                    }
                    return;
                }

                // Update Trend Insight UI
                let conclusionDiv = $("#trend-insight-box");
                if (conclusionDiv.length && trendData.forecast_conclusion) {
                    let cText = trendData.forecast_conclusion;
                    if (cText.includes('Kritis')) {
                        conclusionDiv.css({ 'background-color': 'rgba(239, 68, 68, 0.1)', 'color': '#ef4444', 'border': '1px solid rgba(239, 68, 68, 0.3)' });
                    } else if (cText.includes('Waspada')) {
                        conclusionDiv.css({ 'background-color': 'rgba(245, 158, 11, 0.1)', 'color': '#f59e0b', 'border': '1px solid rgba(245, 158, 11, 0.3)' });
                    } else if (cText.includes('Positif') || cText.includes('Stabil')) {
                        conclusionDiv.css({ 'background-color': 'rgba(16, 185, 129, 0.1)', 'color': '#10b981', 'border': '1px solid rgba(16, 185, 129, 0.3)' });
                    } else {
                        conclusionDiv.css({ 'background-color': 'rgba(59, 130, 246, 0.1)', 'color': '#3b82f6', 'border': '1px solid rgba(59, 130, 246, 0.3)' });
                    }
                    conclusionDiv.html(cText);
                } else if (conclusionDiv.length) {
                    conclusionDiv.html('Data tidak cukup untuk menyimpulkan tren.');
                }

                $("#chart-ztrend").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    series: series,
                    seriesDefaults: { gap: 0.3 },
                    categoryAxis: {
                        categories: displayLabels,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" }
                    },
                    valueAxis: {
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                        majorUnit: 40,
                        plotBands: [{ from: 0, to: 3, color: "rgba(239, 68, 68, 0.1)" }]
                    },
                    legend: {
                        visible: true,
                        position: "bottom",
                        labels: { color: "#94a3b8", font: "11px Inter, sans-serif" }
                    },
                    tooltip: {
                        visible: true,
                        shared: true,
                        format: "{0:n2}"
                    }
                });
            },
            error: function (err) {
                console.error("Failed to fetch ztrend data", err);
            }
        });
    }
    // Initial Load
    loadData();

    const resetSampleColors = () => {
        $('.sample-input').css({
            'border-color': 'rgba(255,255,255,0.1)',
            'background-color': 'rgba(15,23,42,0.5)',
            'color': 'white',
            'opacity': '1',
            'cursor': 'text'
        }).prop("readonly", false);
    };

    let hasSpokenForCurrentModal = false;

    // --- Modal Logic ---
    const modalInput = document.getElementById("modal-input-data");

    $("#btn-input-data").click(function () {
        modalInput.style.display = "flex";
        resetSampleColors();
        hasSpokenForCurrentModal = false;
        $('#voice-alarm-alert-banner').hide();

        if ('speechSynthesis' in window && window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }

        // Auto-load existing data if date is already pre-filled (default to today)
        const dateVal = $("#input_inspection_date").val();
        if (dateVal) {
            $("#input_inspection_date").trigger('change');
        }
    });

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

    // Auto-open pop-up input modal for today's measurement on detail page load (Current Month Only)
    let noPopup = urlParams.get('no_popup');
    let isPastMonthDetail = currentMonth < new Date().toISOString().slice(0, 7);
    if (!noPopup && !isPastMonthDetail && typeof isQualitative !== 'undefined' && !isQualitative) {
        let todayStr = getManufacturingProdDateStr();
        let targetDate = urlParams.get('auto_add') || urlParams.get('date') || todayStr;
        
        setTimeout(() => {
            if ($("#input_inspection_date").length > 0 && modalInput) {
                if (!$("#input_inspection_date").val()) {
                    $("#input_inspection_date").val(targetDate);
                }
                $("#btn-input-data").click();
            }
        }, 350);
    }

    $("#close-modal-input, #btn-cancel-input").click(function () {
        modalInput.style.display = "none";
        $("#form-input-data")[0].reset();
        resetSampleColors();
        $('#voice-alarm-alert-banner').hide();
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    });

    // Close on outside click
    window.onclick = function (event) {
        if (event.target == modalInput) {
            modalInput.style.display = "none";
            $("#form-input-data")[0].reset();
            resetSampleColors();
            $('#voice-alarm-alert-banner').hide();
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        }
    }

    function isTimeSlotFutureDetail(dateStr, labelText) {
        if (!dateStr || !labelText) return false;
        let timeParts = labelText.match(/^(\d{1,2}):(\d{2})$/);
        if (!timeParts) return false;

        let hours = parseInt(timeParts[1], 10);
        let minutes = parseInt(timeParts[2], 10);
        let offsetDay = 0;

        if (hours >= 24) {
            offsetDay = Math.floor(hours / 24);
            hours = hours % 24;
        } else if (hours < 7) {
            offsetDay = 1;
        }

        let targetDate = new Date(dateStr + 'T00:00:00');
        if (isNaN(targetDate.getTime())) return false;

        if (offsetDay > 0) {
            targetDate.setDate(targetDate.getDate() + offsetDay);
        }
        targetDate.setHours(hours, minutes, 0, 0);

        let now = new Date();
        return targetDate.getTime() > now.getTime();
    }

    $(document).on('input keyup change', ".sample-input, input[name^='sample_']", function () {
        let val = $(this).val();
        if (val !== null && val !== undefined && String(val).trim() !== '') {
            $(this).removeClass('slot-overdue-glowing');
        }
    });

    // --- Auto Load Data on Date Change ---
    $("#input_inspection_date").change(function () {
        let selectedDate = $(this).val();
        if (!selectedDate) return;

        // Clear previous sample values before loading
        $("input[name^='sample_']").val("").prop("readonly", false).css({ 'opacity': '1', 'cursor': 'text' }).removeAttr('title').removeClass('slot-overdue-glowing');
        $("input[name='remarks']").val("").prop("readonly", false);
        $("#btn-save-input").show();
        $("#btn-close-data").hide();

        $.ajax({
            url: `Script/php/dtc/c_dtc_measurement_get.php?parameter_id=${paramId}&date=${selectedDate}`,
            type: "GET",
            cache: false,
            dataType: "json",
            success: function (response) {
                let d = (response.status === "found") ? response.data : {};
                let hasLoadedOOS = false;
                let isClosed = (response.status === "found" && parseInt(d.is_closed) === 1);
                let unfilledOverdueCount = 0;
                let totalSlotsCount = $("input[name^='sample_']").length || 11;

                for (let i = 1; i <= totalSlotsCount; i++) {
                    let sampleVal = d["sample_" + i];
                    let $input = $("input[name='sample_" + i + "']");
                    if ($input.length === 0) continue;

                    $input.removeClass('slot-overdue-glowing');
                    let labelText = $("#label_sample_" + i).text().trim();
                    let isFuture = isTimeSlotFutureDetail(selectedDate, labelText);

                    if (isFuture) {
                        $input.val("").prop("readonly", true).css({
                            'border': '1px dashed rgba(255,255,255,0.15)',
                            'box-shadow': 'none',
                            'background-color': 'rgba(15, 23, 42, 0.4)',
                            'color': 'rgba(255,255,255,0.3)',
                            'opacity': '0.5',
                            'cursor': 'not-allowed'
                        }).attr('title', 'Belum masuk waktu pengisian (slot jam di masa depan)');
                    } else if (sampleVal !== null && sampleVal !== undefined && sampleVal !== "" && String(sampleVal).trim() !== "") {
                        let val = parseFloat(sampleVal);
                        $input.val(val).removeAttr('title');

                        // LOCK ALREADY INPUTTED DATA (UNLESS ADMIN)
                        if (!isAdmin) {
                            $input.prop("readonly", true).css({
                                'opacity': '0.7',
                                'cursor': 'not-allowed',
                                'background-color': 'rgba(255,255,255,0.05)'
                            });
                        } else {
                            $input.prop("readonly", false).css({
                                'opacity': '1',
                                'cursor': 'text',
                                'background-color': 'rgba(15,23,42,0.5)'
                            });
                        }

                        if (!isNaN(val) && (val < LSL || val > USL)) {
                            hasLoadedOOS = true;
                        }
                    } else {
                        $input.val("").removeAttr('title');
                        // KEEP EMPTY SLOTS EDITABLE IF NOT CLOSED
                        $input.prop("readonly", false).css({
                            'opacity': '1',
                            'cursor': 'text',
                            'background-color': 'rgba(15,23,42,0.5)'
                        });

                        // Glowing Yellow effect for overdue unfilled slots if not closed
                        if (!isClosed) {
                            $input.addClass('slot-overdue-glowing');
                            unfilledOverdueCount++;
                        }
                    }
                }
                if (d && d.remarks) {
                    $("input[name='remarks']").val(d.remarks);
                }

                if (isClosed) {
                    if (!isAdmin) {
                        // Lock everything if closed and not admin
                        $("input[name^='sample_']").prop("readonly", true).css({ 'opacity': '0.6', 'cursor': 'not-allowed' });
                        $("input[name='remarks']").prop("readonly", true);
                        $("#btn-save-input").hide();
                        $("#btn-close-data").hide();
                    } else {
                        // Admin can edit even if closed
                        $("#btn-save-input").show();
                        $("#btn-close-data").hide();
                    }
                } else {
                    // Allow edits but show Close button since data exists
                    $("#btn-close-data").show();
                    $("#btn-save-input").show();
                }

                if (hasLoadedOOS && typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Peringatan OOS!',
                        text: 'Data pada tanggal ini mengandung nilai pengukuran yang Out of Spec. Mohon lakukan pengukuran ulang jika diperlukan!',
                        icon: 'warning',
                        confirmButtonColor: '#3b82f6',
                        confirmButtonText: 'Mengerti',
                        background: '#1e293b',
                        color: '#f8fafc'
                    });
                }

                // Auto-focus on the first active/editable empty sample input
                setTimeout(() => {
                    let $firstEmpty = $("input[name^='sample_']:not([readonly]):filter(function() { return $(this).val() === ''; }).first()");
                    if ($firstEmpty.length > 0) {
                        $firstEmpty.focus();
                    } else {
                        $("input[name^='sample_']:not([readonly])").first().focus();
                    }
                }, 150);
            }
        });
});

// --- Close Measurement Action ---
$("#btn-close-data").click(function () {
    const dateVal = $("#input_inspection_date").val();
    if (!dateVal) return;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Close Measurement?',
            text: 'Setelah ditutup, data pada tanggal ini tidak bisa di-edit lagi. Lanjutkan?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Tutup Data',
            cancelButtonText: 'Batal',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                executeCloseMeasurement(dateVal);
            }
        });
    } else {
        if (confirm("Setelah ditutup, data tidak bisa di-edit. Lanjutkan?")) {
            executeCloseMeasurement(dateVal);
        }
    }
});

function executeCloseMeasurement(dateVal) {
    $.ajax({
        url: "Script/php/dtc/c_dtc_measurement_close.php",
        type: "POST",
        data: { parameter_id: paramId, inspection_date: dateVal },
        dataType: "json",
        success: function (res) {
            if (res.status === 'success') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: 'Data Closed', text: res.message,
                        showConfirmButton: false, timer: 3000,
                        background: '#1e293b', color: '#f8fafc'
                    });
                }
                // Trigger reload of data in form
                $("#input_inspection_date").trigger('change');
                // Reload matrix
                if (typeof isQualitative !== 'undefined' && isQualitative) {
                    if (typeof window.loadMatrixData === 'function') {
                        window.loadMatrixData();
                    }
                } else {
                    loadData();
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        },
        error: function (xhr, status, err) {
            Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: "Server error: " + err, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
        }
    });
}

// --- Form Submit via AJAX ---
$("#form-input-data").submit(function (e) {
    e.preventDefault();

    const dateVal = $("#input_inspection_date").val();
    if (!dateVal) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Tanggal Harus Diisi!',
            text: 'Silakan pilih tanggal inspeksi terlebih dahulu.',
            showConfirmButton: false,
            timer: 3000,
            background: '#1e293b',
            color: '#f8fafc'
        });
        return;
    }

    let hasData = false;
    $('.sample-input').each(function () {
        if ($(this).val().trim() !== "") {
            hasData = true;
        }
    });

    if (!hasData) {
        Swal.fire({
            title: 'Data Kosong!',
            text: 'Minimal harus ada satu data pengukuran yang diisi sebelum menyimpan.',
            icon: 'warning',
            confirmButtonColor: '#3b82f6',
            background: '#1e293b',
            color: '#f8fafc'
        });
        return;
    }

    let isFutureTime = false;
    let futureLabel = "";
    let now = new Date();

    $('.sample-input').each(function (index) {
        let val = $(this).val().trim();
        // Only check inputs that have a value AND are not locked
        if (val !== "" && !$(this).prop("readonly")) {
            let seq = index + 1;
            let labelText = $("#label_sample_" + seq).text().trim();

            // Construct Date object for this input
            let timeParts = labelText.match(/^(\d{1,2}):(\d{2})$/);
            if (timeParts) {
                let inputDateStr = $("#input_inspection_date").val();
                let dateParts = inputDateStr.split('-');
                if (dateParts.length === 3) {
                    let year = parseInt(dateParts[0], 10);
                    let month = parseInt(dateParts[1], 10) - 1; // 0-indexed
                    let day = parseInt(dateParts[2], 10);
                    let hours = parseInt(timeParts[1], 10);
                    let minutes = parseInt(timeParts[2], 10);

                    let offsetDay = 0;
                    if (hours >= 24) {
                        offsetDay = Math.floor(hours / 24);
                        hours = hours % 24;
                    } else if (hours < 7) {
                        // Assuming shift starts at 07:00. Times between 00:00 and 06:59 belong to the next day.
                        offsetDay = 1;
                    }

                    let inputDateTime = new Date(year, month, day + offsetDay, hours, minutes, 0);
                    if (inputDateTime > now) {
                        isFutureTime = true;
                        futureLabel = labelText;
                        return false; // break .each loop
                    }
                }
            }
        }
    });

    if (isFutureTime) {
        Swal.fire({
            title: 'Waktu Input Belum Masuk!',
            text: `Anda tidak dapat menginput data untuk sampel jam ${futureLabel} karena waktu saat ini belum mencapai jadwal tersebut.`,
            icon: 'warning',
            confirmButtonColor: '#3b82f6',
            background: '#1e293b',
            color: '#f8fafc'
        });
        return;
    }

    let hasOOS = false;
    $('.sample-input').each(function () {
        let val = parseFloat($(this).val());
        if (!isNaN(val)) {
            if (val < LSL || val > USL) {
                hasOOS = true;
            }
        }
    });

    const proceedSave = () => {
        const btn = $("#btn-save-input");
        btn.prop("disabled", true).text("Saving...");
        let formData = $("#form-input-data").serialize();

        $.ajax({
            url: "Script/php/dtc/c_dtc_measurement_save.php",
            type: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                btn.prop("disabled", false).text("Save Data");
                if (response.status === "success") {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Sukses!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 3000,
                        background: '#1e293b',
                        color: '#f8fafc'
                    });
                    modalInput.style.display = "none";
                    $("#form-input-data")[0].reset();
                    resetSampleColors();
                    // Reload Data!
                    loadData();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message,
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function (xhr, status, error) {
                btn.prop("disabled", false).text("Save Data");
                let errorMsg = error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        let parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.message) errorMsg = parsed.message;
                    } catch (e) {
                        let cleanText = xhr.responseText.replace(/<[^>]*>?/gm, '').trim();
                        if (cleanText) errorMsg = cleanText;
                    }
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: "Server error occurred while saving: " + errorMsg,
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    };

    if (hasOOS && typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Data Out of Spec!',
            text: 'Beberapa nilai sampel yang Anda masukkan berada di luar batas spesifikasi. Yakin ingin tetap menyimpannya?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                proceedSave();
            }
        });
    } else if (hasOOS) {
        if (confirm("Beberapa nilai sampel berada di luar batas spesifikasi. Yakin ingin tetap menyimpannya?")) {
            proceedSave();
        }
    } else {
        proceedSave();
    }
});

// --- Edit Header Modal Logic ---
const modalEditHeader = document.getElementById("modal-edit-header");

function loadEditSelectOptions() {
    $.ajax({
        url: 'Script/php/dtc/c_dtc_master_data.php',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.specs) {
                window.editDtcSpecs = res.specs;
                populateEditSpecs();
            }
        }
    });
}

function populateEditSpecs() {
    let opts = '<option value="">-- Select Master Data --</option>';
    if (window.editDtcSpecs) {
        window.editDtcSpecs.forEach(s => {
            let sel = (s.spec_id == $('#edit_spec_id').data('val')) ? 'selected' : '';

            let subName = s.sub_item_check_name && s.sub_item_check_name !== '-' ? ` - ${s.sub_item_check_name}` : '';
            let display = `[${s.model_name}] [${s.line_name} - ${s.section_name}] ${s.process_name} (${s.item_check_name}${subName}) - ${s.data_type}`;

            opts += `<option value="${s.spec_id}" 
                    data-model="${s.model_name}"
                    data-line="${s.line_name}"
                    data-section="${s.section_name}"
                    data-process="${s.process_name}"
                    data-lsl="${s.lsl}" 
                    data-usl="${s.usl}" 
                    data-target="${s.target_value}" 
                    ${sel}>${display}</option>`;
        });
    }
    $('#edit_spec_id').html(opts);

    if ($.fn.select2) {
        $('#edit_spec_id').select2({
            dropdownParent: $('#modal-edit-header'),
            width: '100%',
            placeholder: "-- Select Master Data --"
        });
    }

    // Trigger change to autofill initially if there is a selected value
    if ($('#edit_spec_id').val()) {
        $('#edit_spec_id').trigger('change');
    }
}

$('#edit_spec_id').on('change', function () {
    let opt = $(this).find('option:selected');
    if (!opt.val()) return;

    $('#edit_model_name').val(opt.data('model') || '');
    $('#edit_line_name').val(opt.data('line') || '');
    $('#edit_section_name').val(opt.data('section') || '');
    $('#edit_process_name').val(opt.data('process') || '');

    let lsl = opt.data('lsl');
    let usl = opt.data('usl');
    let target = opt.data('target');
    $('#edit_lsl').val(lsl !== undefined && lsl !== "" ? lsl : '');
    $('#edit_usl').val(usl !== undefined && usl !== "" ? usl : '');
    $('#edit_target_value').val(target !== undefined && target !== "" ? target : '');
});

$("#btn-edit-header").click(function () {
    loadEditSelectOptions();
    modalEditHeader.style.display = "flex";
});

$("#btn-cancel-edit-header").click(function () {
    modalEditHeader.style.display = "none";
});

window.addEventListener("click", function (event) {
    if (event.target == modalEditHeader) {
        modalEditHeader.style.display = "none";
    }
});

$("#form-edit-header").submit(function (e) {
    e.preventDefault();
    const btn = $("#btn-save-edit-header");
    btn.prop("disabled", true).text("Saving...");

    let formData = new FormData(this);

    $.ajax({
        url: "Script/php/dtc/c_dtc_header_update.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
            btn.prop("disabled", false).html('<i class="fa-solid fa-floppy-disk"></i> Save Changes');
            if (response.status === "success") {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses!',
                        text: 'Header updated! Reloading page...',
                        background: '#1e293b',
                        color: '#f8fafc',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    alert("Header updated!");
                    location.reload();
                }
                modalEditHeader.style.display = "none";
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message,
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#ef4444'
                    });
                } else {
                    alert("Error: " + response.message);
                }
            }
        },
        error: function (xhr, status, error) {
                btn.prop("disabled", false).html('<i class="fa-solid fa-floppy-disk"></i> Save Changes');
                let errorMsg = error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        let parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.message) errorMsg = parsed.message;
                    } catch (e) {
                        let cleanText = xhr.responseText.replace(/<[^>]*>?/gm, '').trim();
                        if (cleanText) errorMsg = cleanText;
                    }
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: "Server error: " + errorMsg,
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
});

// Image preview for Edit form
$('#edit-ref-image-input').on('change', function () {
    let file = this.files[0];
    if (file) {
        let reader = new FileReader();
        reader.onload = function (e) {
            $('#edit-ref-image-thumb').attr('src', e.target.result);
            $('#edit-ref-image-preview').show();
        };
        reader.readAsDataURL(file);
    }
});

// --- Train AI Model Button ---
$("#btn-train-ai").click(function () {
    const btn = $(this);
    const specId = btn.data('spec-id');
    if (!specId) {
        Swal.fire({ icon: 'error', title: 'Error!', text: 'Spec ID not found.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
        return;
    }
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Training...');

    $.ajax({
        url: 'Script/php/dtc/c_dtc_ai_train.php',
        type: 'POST',
        data: { spec_id: specId },
        dataType: 'json',
        success: function (res) {
            btn.prop('disabled', false).html('<i class="fa-solid fa-brain"></i> Train AI Model');
            if (res.status === 'success') {
                btn.css({ 'border-color': '#10b981', 'color': '#10b981' });
                Swal.fire({
                    icon: 'success',
                    title: 'Pelatihan AI Berhasil!',
                    text: res.message + ' Memperbarui grafik Z-Trend...',
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#10b981'
                });
                loadData(); // Reload charts to use new model
            } else {
                Swal.fire({ icon: 'error', title: 'Pelatihan Gagal!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        },
        error: function () {
            btn.prop('disabled', false).html('<i class="fa-solid fa-brain"></i> Train AI Model');
            Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Server error during AI training.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
        }
    });
});
// Legacy code removed. The edit modal is now fully dynamically populated.

// Extra Samples are now always visible by default

// Real-time OOS validation (Color change on typing)
$('.sample-input').on('input', function () {
    let val = parseFloat($(this).val());
    if (!isNaN(val)) {
        if (val < LSL || val > USL) {
            $(this).css({
                'border-color': '#ef4444',
                'background-color': 'rgba(239,68,68,0.15)',
                'color': '#f87171'
            });
        } else {
            $(this).css({
                'border-color': '#10b981',
                'background-color': 'rgba(16,185,129,0.15)',
                'color': '#34d399'
            });
        }
    } else {
        $(this).css({
            'border-color': 'rgba(255,255,255,0.1)',
            'background-color': 'rgba(15,23,42,0.5)',
            'color': 'white'
        });
    }
});

// Alarm Warning (Toast popup on finish typing)
$('.sample-input').on('change', function () {
    let val = parseFloat($(this).val());
    if (!isNaN(val)) {
        if (val < LSL || val > USL) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: `Out of Spec Warning!`,
                text: `Nilai ${val} berada di luar batas spek (LSL: ${LSL}, USL: ${USL})`,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#1e293b',
                color: '#f8fafc',
                iconColor: '#ef4444'
            });
        }
    }
});

// Make all Kendo charts responsive on window resize
let resizeTimer;
$(window).on("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        if (typeof kendo !== "undefined") {
            let w = $(window).width();
            let fSize = w < 768 ? "9px" : (w < 1200 ? "10px" : "11px");
            let fontStr = fSize + " Inter, sans-serif";

            $(".k-chart").each(function () {
                let chart = $(this).data("kendoChart");
                if (chart) {
                    // valueAxis might be an array or object, Kendo setOptions handles merging
                    chart.setOptions({
                        categoryAxis: { labels: { font: fontStr } },
                        valueAxis: { labels: { font: fontStr } },
                        legend: { labels: { font: fontStr } }
                    });
                }
            });
            kendo.resize($(".k-chart"));
        }
    }, 150);
});

// Handle Theme Switcher dynamic reload
document.addEventListener('themeChanged', function () {
    if (typeof loadData === 'function') {
        loadData();
    }
});

});
