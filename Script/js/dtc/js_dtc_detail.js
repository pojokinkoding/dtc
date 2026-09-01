// js_dtc_detail.js

$(document).ready(function () {
    let LSL = null;
    let USL = null;

    // Helper to safely get LSL / USL specs
    function getSpecLSL() {
        const el = document.getElementById('spec_lsl');
        if (el && el.value !== null && el.value !== '') {
            const val = parseFloat(el.value);
            if (!isNaN(val)) return val;
        }
        return null;
    }

    function getSpecUSL() {
        const el = document.getElementById('spec_usl');
        if (el && el.value !== null && el.value !== '') {
            const val = parseFloat(el.value);
            if (!isNaN(val)) return val;
        }
        return null;
    }

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

                // Determine LSL & USL specs safely
                let specLSL = getSpecLSL();
                if (specLSL === null && response.lsl !== undefined && response.lsl !== null && response.lsl !== '') {
                    specLSL = parseFloat(response.lsl);
                }
                let specUSL = getSpecUSL();
                if (specUSL === null && response.usl !== undefined && response.usl !== null && response.usl !== '') {
                    specUSL = parseFloat(response.usl);
                }

                LSL = specLSL;
                USL = specUSL;

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
                    let isSummary = (row.jam === "Max Data" || row.jam === "Min Data" || row.jam === "Zst" || row.jam === "Zlt");
                    let trClass = isSummary ? "summary-row" : "";

                    let tr = `<tr class="${trClass}">`;
                    tr += `<td class="sticky-col">${row.jam}</td>`;

                    for (let i = 1; i <= activeDaysInMonth; i++) {
                        let d = new Date(parseInt(dmYear), parseInt(dmMonth) - 1, i);
                        let dayOfWeek = d.getDay();
                        let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

                        let val = row["day_" + i];
                        let isUnmeasured = (val === null || val === undefined || val === "");
                        let displayVal = (!isSummary && isUnmeasured) ? "" : (val !== null && val !== undefined && val !== "" ? parseFloat(val).toFixed(2) : "");

                        let cellClass = "";
                        let bgStyle = isWeekend ? 'background-color: rgba(100,100,100,0.15); color: #555;' : '';

                        if (!isSummary && !isUnmeasured) {
                            let parsedVal = parseFloat(val);
                            if (!isNaN(parsedVal)) {
                                if ((specLSL !== null && !isNaN(specLSL) && parsedVal < specLSL) ||
                                    (specUSL !== null && !isNaN(specUSL) && parsedVal > specUSL)) {
                                    cellClass = "oos-cell";
                                }
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
                        // std = 0 : all values are constant. Check if constant value is IN SPEC or OUT OF SPEC!
                        let maxData = Math.max(...allSamples);
                        let minData = Math.min(...allSamples);
                        $("#summ-n").text(allSamples.length);
                        $("#summ-max").text(kendo.toString(maxData, "n2"));
                        $("#summ-min").text(kendo.toString(minData, "n2"));
                        $("#summ-avg").text(kendo.toString(mean, "n2"));
                        $("#summ-std").text("0.00");

                        let isConstOos = false;
                        if (specLSL !== null && !isNaN(specLSL) && mean < specLSL) isConstOos = true;
                        if (specUSL !== null && !isNaN(specUSL) && mean > specUSL) isConstOos = true;

                        if (isConstOos) {
                            $("#summ-cp").text("0.00").css({ "color": "#f87171", "font-weight": "bold" });
                            $("#summ-cpk").text("0.00").css({ "color": "#f87171", "font-weight": "bold" });
                            $("#summ-zst").text("0.00").css({ "color": "#f87171", "font-weight": "bold" });
                            $("#summ-zlt").text("0.00").css({ "color": "#f87171", "font-weight": "bold" });
                            $("#chart-4block").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#f87171;font-size:13px;flex-direction:column;gap:8px;text-align:center;padding:10px;"><i class="fa-solid fa-triangle-exclamation" style="font-size:28px;"></i><span>Data Konstan di Luar Spesifikasi (OOS/NG)<br>Rata-rata (' + kendo.toString(mean, "n2") + ') di luar batas spec.<br>Process Capability = 0.00 (Kritis)</span></div>');
                            if ($("#ai-insight-box").length) {
                                $("#ai-insight-box").html(`🚨 <strong>Out of Spec (OOS / NG).</strong> Seluruh data sampel bernilai ${kendo.toString(mean, "n2")} di luar batas spesifikasi (${specLSL !== null ? specLSL : '-'} — ${specUSL !== null ? specUSL : '-'}). Performa proses tidak memenuhi syarat (Cp = 0.00, Cpk = 0.00).`).css("color", "#f87171");
                            }
                            if ($("#trend-insight-box").length) {
                                $("#trend-insight-box").html(`🚨 <strong>Kritis:</strong> Data bulan ini terdeteksi Out of Spec (OOS/NG). Segera lakukan tindakan korektif!`).css("color", "#f87171");
                            }
                        } else {
                            $("#summ-cp").text("∞").css({ "color": "var(--accent)", "font-weight": "bold" });
                            $("#summ-cpk").text("∞").css({ "color": "var(--accent)", "font-weight": "bold" });
                            $("#summ-zst").text("∞").css({ "color": "var(--primary)", "font-weight": "bold" });
                            $("#summ-zlt").text("∞").css({ "color": "var(--primary)", "font-weight": "bold" });
                            $("#chart-4block").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#10b981;font-size:13px;flex-direction:column;gap:8px;"><i class="fa-solid fa-infinity" style="font-size:28px;opacity:0.8;"></i><span>Semua data seragam (Std Dev = 0)<br>Process Capability = ∞ (Perfect)</span></div>');
                            if ($("#ai-insight-box").length) {
                                $("#ai-insight-box").html(`✅ <strong>Data Konstan / Zero Variance.</strong> Semua nilai pengukuran identik (= ${kendo.toString(mean, "n2")}) dan berada di dalam batas spesifikasi.`).css("color", "var(--text-light)");
                            }
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

                let zstSeriesData = [];
                let zltSeriesData = [];
                let hasData = false;

                for (let i = 0; i < 12; i++) {
                    let actualZst = (trendData.zst_actual && trendData.zst_actual[i] !== null && trendData.zst_actual[i] !== undefined) ? trendData.zst_actual[i] : null;
                    let forecastZst = (trendData.zst_forecast && trendData.zst_forecast[i] !== null && trendData.zst_forecast[i] !== undefined) ? trendData.zst_forecast[i] : null;
                    let isForecastZst = (actualZst === null) && (forecastZst !== null);
                    let valZst = (actualZst !== null) ? actualZst : forecastZst;

                    zstSeriesData.push({
                        val: valZst,
                        isForecast: isForecastZst
                    });

                    let actualZlt = (trendData.zlt_actual && trendData.zlt_actual[i] !== null && trendData.zlt_actual[i] !== undefined) ? trendData.zlt_actual[i] : null;
                    let forecastZlt = (trendData.zlt_forecast && trendData.zlt_forecast[i] !== null && trendData.zlt_forecast[i] !== undefined) ? trendData.zlt_forecast[i] : null;
                    let isForecastZlt = (actualZlt === null) && (forecastZlt !== null);
                    let valZlt = (actualZlt !== null) ? actualZlt : forecastZlt;

                    zltSeriesData.push({
                        val: valZlt,
                        isForecast: isForecastZlt
                    });

                    if (valZst !== null || valZlt !== null) {
                        hasData = true;
                    }
                }

                // If no data at all, show empty state
                if (!hasData) {
                    $("#chart-ztrend").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:12px;"><i class="fa-solid fa-chart-line" style="margin-right:6px;opacity:0.5;"></i> Belum ada data trend untuk tahun ini</div>');
                    if ($("#trend-insight-box").length) {
                        $("#trend-insight-box").html('<span style="color:#cbd5e1; font-style:normal;"><i class="fa-solid fa-circle-info" style="color:#38bdf8; margin-right:6px;"></i> Belum ada data trend untuk tahun ini.</span>');
                    }
                    return;
                }

                // Update Trend Insight UI & AI Insight Box automatically
                let conclusionDiv = $("#trend-insight-box");
                if (conclusionDiv.length && trendData.forecast_conclusion) {
                    let cText = trendData.forecast_conclusion;
                    if (cText.includes('Kritis')) {
                        conclusionDiv.css({ 'background-color': 'rgba(239, 68, 68, 0.12)', 'color': '#f87171', 'border': '1px solid rgba(239, 68, 68, 0.3)' });
                    } else if (cText.includes('Waspada')) {
                        conclusionDiv.css({ 'background-color': 'rgba(245, 158, 11, 0.12)', 'color': '#fbbf24', 'border': '1px solid rgba(245, 158, 11, 0.3)' });
                    } else if (cText.includes('Positif') || cText.includes('Stabil')) {
                        conclusionDiv.css({ 'background-color': 'rgba(16, 185, 129, 0.12)', 'color': '#34d399', 'border': '1px solid rgba(16, 185, 129, 0.3)' });
                    } else {
                        conclusionDiv.css({ 'background-color': 'rgba(59, 130, 246, 0.12)', 'color': '#60a5fa', 'border': '1px solid rgba(59, 130, 246, 0.3)' });
                    }
                    conclusionDiv.html(`<div style="display:flex; align-items:flex-start; gap:8px;">${cText}</div>`);
                }

                if ($("#ai-insight-box").length && trendData.ai_forecast_text) {
                    $("#ai-insight-box").html(`<div style="display:flex; align-items:center; gap:8px; width:100%; font-size:12px;">${trendData.ai_forecast_text}</div>`).css({ 'color': '#f8fafc', 'font-style': 'normal', 'background-color': 'rgba(139, 92, 246, 0.12)', 'border': '1px solid rgba(139, 92, 246, 0.3)' });
                }

                let lastDataMonthIdx = -1;
                for (let i = 0; i < 12; i++) {
                    if (zstSeriesData[i].val !== null || zltSeriesData[i].val !== null) {
                        lastDataMonthIdx = i;
                    }
                }

                let displayCategories = monthLabels;
                if (lastDataMonthIdx >= 0 && lastDataMonthIdx < 11) {
                    displayCategories = monthLabels.slice(0, lastDataMonthIdx + 1);
                    zstSeriesData = zstSeriesData.slice(0, lastDataMonthIdx + 1);
                    zltSeriesData = zltSeriesData.slice(0, lastDataMonthIdx + 1);
                }

                let series = [
                    {
                        type: "column",
                        name: "ZST",
                        field: "val",
                        data: zstSeriesData,
                        color: function (point) {
                            return (point.dataItem && point.dataItem.isForecast) ? "#38bdf8" : "#10b981";
                        },
                        tooltip: {
                            visible: true,
                            template: function (e) {
                                if (e.value === null || e.value === undefined) return "";
                                let tag = (e.dataItem && e.dataItem.isForecast) ? " (AI Forecast)" : " (Actual)";
                                return "ZST" + tag + ": <b>" + kendo.toString(e.value, "n2") + "</b>";
                            }
                        }
                    },
                    {
                        type: "column",
                        name: "ZLT",
                        field: "val",
                        data: zltSeriesData,
                        color: function (point) {
                            return (point.dataItem && point.dataItem.isForecast) ? "#c084fc" : "#f59e0b";
                        },
                        tooltip: {
                            visible: true,
                            template: function (e) {
                                if (e.value === null || e.value === undefined) return "";
                                let tag = (e.dataItem && e.dataItem.isForecast) ? " (AI Forecast)" : " (Actual)";
                                return "ZLT" + tag + ": <b>" + kendo.toString(e.value, "n2") + "</b>";
                            }
                        }
                    }
                ];

                let allVals = [];
                zstSeriesData.forEach(d => { if (d.val !== null && !isNaN(d.val)) allVals.push(d.val); });
                zltSeriesData.forEach(d => { if (d.val !== null && !isNaN(d.val)) allVals.push(d.val); });

                let maxVal = allVals.length > 0 ? Math.max(...allVals) : 6;
                let axisMax = Math.max(6, Math.ceil(maxVal + 0.5));

                $("#chart-ztrend").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    series: series,
                    seriesDefaults: { gap: 0.2, spacing: 0.1 },
                    categoryAxis: {
                        categories: displayCategories,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" }
                    },
                    valueAxis: {
                        min: 0,
                        max: axisMax,
                        labels: { color: "#94a3b8", font: "9px 'Inter', sans-serif" },
                        plotBands: [{ from: 0, to: 3, color: "rgba(239, 68, 68, 0.15)" }]
                    },
                    legend: {
                        visible: false
                    }
                });

                // Add Custom Colored Legend Below Chart to clearly distinguish Actual vs AI Forecast
                let customLegendHtml = `
                    <div style="display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 8px; font-size: 11px; color: #94a3b8;">
                        <span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #10b981; border-radius: 2px;"></span> ZST (Actual)</span>
                        <span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #f59e0b; border-radius: 2px;"></span> ZLT (Actual)</span>
                        <span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #38bdf8; border-radius: 2px;"></span> ZST (AI Forecast)</span>
                        <span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #c084fc; border-radius: 2px;"></span> ZLT (AI Forecast)</span>
                    </div>
                `;
                $("#chart-ztrend").parent().find('.custom-ztrend-legend').remove();
                $("#chart-ztrend").after(`<div class="custom-ztrend-legend">${customLegendHtml}</div>`);
            },
            error: function (err) {
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

        let todayStr = new Date().toISOString().slice(0, 10);
        if (dateStr > todayStr) return true;
        if (dateStr < todayStr) return false;

        let now = new Date();
        let curH = now.getHours();
        let curM = now.getMinutes();
        if (curH < 7) curH += 24;
        let curMins = curH * 60 + curM;

        let clean = String(labelText).replace('.', ':');
        let m = clean.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return false;

        let sH = parseInt(m[1], 10);
        let sM = parseInt(m[2], 10);
        if (sH < 7) sH += 24;
        let slotMins = sH * 60 + sM;

        // Slot is future if current shift time is prior to slotMins
        return curMins < slotMins;
    }

    function isTimeSlotBeforeModelStartDetail(dateStr, labelText, rmCreatedAt) {
        if (!rmCreatedAt || !dateStr || !labelText) return false;

        let parts = String(rmCreatedAt).trim().split(' ');
        if (parts.length < 2) return false;

        let rmDate = parts[0];
        let rmTime = parts[1];

        if (rmDate > dateStr) return true;  // Model created on a future date -> slot is before model creation
        if (rmDate < dateStr) return false; // Model created on a past date -> model was already active

        let tParts = rmTime.split(':');
        let mH = parseInt(tParts[0], 10);
        let mM = parseInt(tParts[1], 10);
        if (isNaN(mH) || isNaN(mM)) return false;

        if (mH < 7) return false; // Created before 07:00 AM on same calendar day -> active before shift start

        let modelMinsFrom7 = (mH - 7) * 60 + mM;

        let defaultLabels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
        let clean = String(labelText).replace('.', ':');
        let idx = defaultLabels.findIndex(l => l.replace('.', ':').startsWith(clean));

        let nextSlotMinsFrom7;
        if (idx !== -1 && idx < defaultLabels.length - 1) {
            let nextLabel = defaultLabels[idx + 1];
            let nMatch = nextLabel.match(/^(\d{1,2})[:\.](\d{2})/);
            if (nMatch) {
                let nH = parseInt(nMatch[1], 10);
                let nM = parseInt(nMatch[2], 10);
                let nHShift = nH < 7 ? nH + 24 : nH;
                nextSlotMinsFrom7 = (nHShift - 7) * 60 + nM;
            }
        }

        if (!nextSlotMinsFrom7) {
            let sMatch = clean.match(/^(\d{1,2})[:\.](\d{2})/);
            if (!sMatch) return false;
            let sH = parseInt(sMatch[1], 10);
            let sM = parseInt(sMatch[2], 10);
            let sHShift = sH < 7 ? sH + 24 : sH;
            nextSlotMinsFrom7 = (sHShift - 7) * 60 + sM + 120;
        }

        return modelMinsFrom7 >= nextSlotMinsFrom7;
    }

    function updateSampleInputValidation($input) {
        if ($input.length === 0) return;
        let valStr = $input.val();
        let $clearBtn = $input.siblings('.btn-clear-sample');

        if ($input.prop('readonly') && ($input.hasClass('slot-disabled-before-creation') || ($input.attr('title') && $input.attr('title').includes('sebelum')))) {
            return;
        }

        if (valStr !== null && valStr !== undefined && String(valStr).trim() !== '') {
            $input.removeClass('slot-overdue-glowing');
            if (!$input.prop('readonly')) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            let val = parseFloat(valStr);
            if (!isNaN(val)) {
                let isOos = false;
                if (LSL !== null && LSL !== undefined && !isNaN(LSL) && val < LSL) isOos = true;
                if (USL !== null && USL !== undefined && !isNaN(USL) && val > USL) isOos = true;

                if (isOos) {
                    $input.css({
                        'border': '1.5px solid #ef4444',
                        'background-color': 'rgba(239, 68, 68, 0.25)',
                        'color': '#fca5a5',
                        'box-shadow': '0 0 10px rgba(239, 68, 68, 0.4)'
                    });
                } else {
                    $input.css({
                        'border': '1px solid rgba(16, 185, 129, 0.5)',
                        'background-color': 'rgba(16, 185, 129, 0.15)',
                        'color': '#34d399',
                        'box-shadow': 'none'
                    });
                }
            }
        } else {
            $clearBtn.hide();
            if (!$input.hasClass('slot-overdue-glowing')) {
                $input.css({
                    'border': '1px solid rgba(255, 255, 255, 0.15)',
                    'background-color': 'rgba(15, 23, 42, 0.5)',
                    'color': '#ffffff',
                    'box-shadow': 'none'
                });
            }
        }
    }

    $(document).on('input keyup change', ".sample-input, input[name^='sample_']", function () {
        updateSampleInputValidation($(this));
    });

    $(document).on('click', '.btn-clear-sample', function (e) {
        e.preventDefault();
        e.stopPropagation();
        let seq = $(this).data('sample');
        let $input = $("input[name='sample_" + seq + "']");
        $input.val("").removeClass('slot-overdue-glowing').trigger('change').focus();
        updateSampleInputValidation($input);
        $(this).hide();
    });

    // --- Auto Load Data on Date Change ---
    $("#input_inspection_date").change(function () {
        let selectedDate = $(this).val();
        if (!selectedDate) return;

        let todayStr = typeof getManufacturingProdDateStr === 'function' ? getManufacturingProdDateStr() : new Date().toISOString().slice(0, 10);
        if (selectedDate > todayStr) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanggal Tidak Valid',
                text: 'Pengisian data tidak boleh untuk tanggal di masa depan (maksimal hari ini: ' + todayStr + ').',
                confirmColor: '#3085d6'
            });
            $(this).val(todayStr);
            selectedDate = todayStr;
        }

        // Clear previous sample values before loading
        $("input[name^='sample_']").val("").prop("readonly", false).css({ 'opacity': '1', 'cursor': 'text' }).removeAttr('title').removeClass('slot-overdue-glowing');
        $('.btn-clear-sample').hide();
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
                let rmCreatedAt = response.running_model_created_at || null;
                let hasLoadedOOS = false;
                let isClosed = (response.status === "found" && parseInt(d.is_closed) === 1);
                let unfilledOverdueCount = 0;
                let totalSlotsCount = $("input[name^='sample_']").length || 11;
                window.selectedDateHasData = (response.status === "found");

                for (let i = 1; i <= totalSlotsCount; i++) {
                    let sampleVal = d["sample_" + i];
                    let $input = $("input[name='sample_" + i + "']");
                    let $clearBtn = $input.siblings('.btn-clear-sample');
                    if ($input.length === 0) continue;

                    $input.removeClass('slot-overdue-glowing');
                    let labelText = $("#label_sample_" + i).text().trim();
                    let isFuture = isTimeSlotFutureDetail(selectedDate, labelText);
                    let isBeforeModelStart = isTimeSlotBeforeModelStartDetail(selectedDate, labelText, rmCreatedAt);

                    if (isBeforeModelStart) {
                        $input.val(sampleVal !== null && sampleVal !== undefined ? sampleVal : "").prop("readonly", true).prop("disabled", true).css({
                            'border': '1px dashed rgba(255,255,255,0.12)',
                            'box-shadow': 'none',
                            'background-color': 'rgba(15, 23, 42, 0.45)',
                            'color': 'rgba(255,255,255,0.3)',
                            'opacity': '0.5',
                            'cursor': 'not-allowed',
                            'pointer-events': 'none'
                        }).attr('title', 'Slot jam sebelum running model di-add (Terkunci)');
                        $clearBtn.hide();
                    } else if (isFuture) {
                        $input.val("").prop("readonly", true).prop("disabled", true).css({
                            'border': '1px dashed rgba(255,255,255,0.15)',
                            'box-shadow': 'none',
                            'background-color': 'rgba(15, 23, 42, 0.4)',
                            'color': 'rgba(255,255,255,0.3)',
                            'opacity': '0.5',
                            'cursor': 'not-allowed',
                            'pointer-events': 'none'
                        }).attr('title', 'Belum masuk waktu pengisian (slot jam di masa depan)');
                        $clearBtn.hide();
                    } else if (sampleVal !== null && sampleVal !== undefined && sampleVal !== "" && String(sampleVal).trim() !== "") {
                        $input.prop("disabled", false).css('pointer-events', 'auto');
                        let val = parseFloat(sampleVal);
                        $input.val(val).removeAttr('title');

                        // LOCK ALREADY INPUTTED DATA (UNLESS ADMIN)
                        if (!isAdmin) {
                            $input.prop("readonly", true).css({
                                'opacity': '0.7',
                                'cursor': 'not-allowed'
                            });
                            $clearBtn.hide();
                        } else {
                            $input.prop("readonly", false).css({
                                'opacity': '1',
                                'cursor': 'text'
                            });
                            $clearBtn.show();
                        }

                        updateSampleInputValidation($input);

                        if (!isNaN(val) && (val < LSL || val > USL)) {
                            hasLoadedOOS = true;
                        }
                    } else {
                        $input.val("").removeAttr('title');
                        $clearBtn.hide();
                        // KEEP EMPTY SLOTS EDITABLE IF NOT CLOSED
                        $input.prop("readonly", false).css({
                            'opacity': '1',
                            'cursor': 'text'
                        });

                        // Glowing Yellow effect for overdue unfilled slots if not closed
                        if(!isClosed) {
                            $input.addClass('slot-overdue-glowing');
                            unfilledOverdueCount++;
                        } else {
                            updateSampleInputValidation($input);
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
            let $firstEmpty = $("input[name^='sample_']:not([readonly])").filter(function () { return $(this).val() === ''; }).first();
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

    let isExistingData = window.selectedDateHasData || false;

    if (!hasData && !isExistingData) {
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
