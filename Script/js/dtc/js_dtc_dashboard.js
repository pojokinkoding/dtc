// js_dtc_dashboard.js

$(document).ready(function() {
    // Current Specs for OMEGA 6.8.9 Torque Hinge Center
    const LSL = 505.000;
    const USL = 506.000;

    // Helper to get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const paramId = urlParams.get('param_id') || 1;
    let currentMonth = urlParams.get('month');
    
    if(!currentMonth) {
        // If they came from an old link with date=2026-06-30, extract month
        const dateParam = urlParams.get('date');
        if(dateParam) {
            currentMonth = dateParam.substring(0, 7);
        } else {
            const today = new Date();
            currentMonth = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, '0');
        }
    }

    // Generate dynamic columns (Jam, 1..31)
    let matrixColumns = [
        { field: "jam", title: "Jam", width: "120px" }
    ];
    
    // Schema fields
    let schemaFields = {
        jam: { type: "string" }
    };
    
    for(let i = 1; i <= 31; i++) {
        let fieldName = "day_" + i;
        matrixColumns.push({ 
            field: fieldName, 
            title: i.toString(), 
            width: "60px",
            attributes: { style: "text-align: center;" }
        });
        schemaFields[fieldName] = { type: "string" };
    }

    // 1. Initialize Data Entry Grid (Matrix Pivot)
    $("#grid-input").kendoGrid({
        dataSource: {
            transport: {
                read: {
                    url: `Script/php/dtc/c_dtc_matrix.php?param_id=${paramId}&month=${currentMonth}`,
                    dataType: "json"
                }
            },
            schema: {
                model: {
                    id: "jam",
                    fields: schemaFields
                }
            }
        },
        editable: false,
        sortable: false, // Turn off sort because we rely on chronological order + min/max at bottom
        columns: matrixColumns,
        dataBound: function(e) {
            let grid = this;
            let rows = grid.tbody.find("tr");

            rows.each(function() {
                let row = $(this);
                let dataItem = grid.dataItem(row);
                
                // Style Summary rows
                if(dataItem.jam === "Max Data" || dataItem.jam === "Min Data" || dataItem.jam === "Zst" || dataItem.jam === "Zlt") {
                    row.css("background-color", "rgba(16, 185, 129, 0.15)");
                    row.css("font-weight", "bold");
                    row.css("color", "#10b981");
                } else {
                    // Check samples against LSL and USL to highlight cells
                    for(let i=1; i<=31; i++) {
                        let val = dataItem["day_" + i];
                        if(val && (val < LSL || val > USL)) {
                            // Highlight the specific cell (td)
                            let cell = row.children().eq(i);
                            cell.css("background-color", "rgba(239, 68, 68, 0.3)");
                            cell.css("color", "#fca5a5");
                            cell.css("font-weight", "bold");
                        }
                    }
                }
            });
        }
    });

    // 2. Initialize X-Bar Chart
    $("#chart-xbar").kendoChart({
        theme: "sass",
        chartArea: { background: "transparent" },
        series: [{
            type: "line",
            data: [505.14, 505.18, 505.09, 505.11, 505.19],
            name: "X-Bar",
            color: "#3b82f6"
        }],
        categoryAxis: {
            categories: ["Jam 1", "Jam 2", "Jam 3", "Jam 4", "Jam 5"],
            labels: { color: "#94a3b8" }
        },
        valueAxis: {
            labels: { color: "#94a3b8", format: "{0:n1}" },
            plotBands: [
                { from: 505, to: 506, color: "rgba(16, 185, 129, 0.1)" } // Green safe zone
            ]
        },
        tooltip: { visible: true, format: "{0:n3}" }
    });

    // 3. Initialize R Chart (Range)
    $("#chart-r").kendoChart({
        theme: "sass",
        chartArea: { background: "transparent" },
        series: [{
            type: "line",
            data: [0.05, 0.10, 0.04, 0.08, 0.12], // Dummy range data
            name: "Range",
            color: "#8b5cf6"
        }],
        categoryAxis: {
            categories: ["Jam 1", "Jam 2", "Jam 3", "Jam 4", "Jam 5"],
            labels: { color: "#94a3b8" }
        },
        valueAxis: { labels: { color: "#94a3b8", format: "{0:n1}" } },
        tooltip: { visible: true, format: "{0:n3}" }
    });
    
    // 4. Monthly Z-Trend with AI Forecast
    $("#chart-ztrend").kendoChart({
        theme: "sass",
        chartArea: { background: "transparent" },
        series: [{
            type: "column",
            data: [4.2, 4.0, 3.8, 3.5, 4.1], // Jan - May Actuals
            name: "Actual ZST",
            color: "#10b981"
        }, {
            type: "column",
            data: [null, null, null, null, null, 3.9, 4.0], // Jun - Jul AI Forecast
            name: "AI Forecast ZST",
            color: "rgba(96, 165, 250, 0.5)" // Semi-transparent to indicate prediction
        }],
        categoryAxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun(Pred)", "Jul(Pred)"],
            labels: { color: "#94a3b8" }
        },
        valueAxis: { 
            labels: { color: "#94a3b8" },
            plotBands: [{ from: 0, to: 3, color: "rgba(239, 68, 68, 0.1)" }] // Danger zone Z < 3
        },
        tooltip: { visible: true, format: "ZST: {0:n2}" }
    });

    // Make all Kendo charts responsive on window resize
    let resizeTimer;
    $(window).on("resize", function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (typeof kendo !== "undefined") {
                let w = $(window).width();
                let fSize = w < 768 ? "9px" : (w < 1200 ? "10px" : "11px");
                let fontStr = fSize + " Inter, sans-serif";
                
                $(".k-chart").each(function() {
                    let chart = $(this).data("kendoChart");
                    if (chart) {
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
});
