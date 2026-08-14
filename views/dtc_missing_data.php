<?php
// dtc_missing_data.php
// Missing Data Tracker - Monitoring Control Center
$currentUserRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($currentUserRole !== 'admin' && strpos($currentUserRole, 'supervisor') === false) {
    echo "<script>alert('Unauthorized access.'); window.location.href='index.php?page=dtc';</script>";
    exit;
}
?>

<style>
    /* Monitoring Control KPI Cards */
    .kpi-card-container {
        display: grid;
        grid-template-columns: repeat(6, 1fr) !important;
        gap: 12px;
        margin-bottom: 20px;
    }

    .section-grid-6x2 {
        display: grid !important;
        grid-template-columns: repeat(6, 1fr) !important;
        gap: 12px !important;
        transition: all 0.4s ease-in-out;
    }

    /* Clickable Section & KPI Cards Hover Effect */
    .section-card-clickable {
        cursor: pointer !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
    }
    .section-card-clickable:hover {
        transform: translateY(-4px) scale(1.015);
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.45) !important;
        border-color: rgba(96, 165, 250, 0.8) !important;
    }
    .line-kpi-card-clickable {
        cursor: pointer !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    }
    .line-kpi-card-clickable:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(59, 130, 246, 0.4) !important;
    }

    .kpi-card {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 16px 20px;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }
    .kpi-card-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .kpi-card-value {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 6px;
    }
    .kpi-card-subtext {
        font-size: 12px;
        color: #94a3b8;
    }
    .kpi-card-bar-bg {
        height: 6px;
        width: 100%;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
        margin-top: 10px;
        overflow: hidden;
    }
    .kpi-card-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s ease;
    }

    /* Overdue Blinking Animation */
    @keyframes blink-yellow {
        0% { outline: 2px solid transparent; outline-offset: 1px; }
        50% { outline: 2px solid #eab308; outline-offset: 1px; box-shadow: 0 0 10px #eab308; }
        100% { outline: 2px solid transparent; outline-offset: 1px; }
    }
    .blinking-outline {
        animation: blink-yellow 1.5s infinite;
    }

    /* Live Refresh Pulse */
    @keyframes green-pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .live-pulse {
        animation: green-pulse 1.2s ease-out;
    }

    /* Summary Table Styling */
    .summary-table-card {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    .summary-table-header {
        padding: 14px 20px;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, transparent 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Block Table Styling */
    .block-cell {
        min-width: 34px;
        height: 22px;
        display: inline-block;
        border-radius: 4px;
        margin: 2px;
        padding: 0 2px;
        cursor: pointer;
        position: relative;
    }
    .block-filled { background-color: #3b82f6; box-shadow: 0 0 5px rgba(59, 130, 246, 0.4); }
    .block-closed { background-color: var(--success); box-shadow: 0 0 5px rgba(16, 185, 129, 0.4); }
    .block-empty { background-color: var(--danger); box-shadow: 0 0 5px rgba(239, 68, 68, 0.4); }
    .block-weekend { background-color: rgba(255,255,255,0.1); }
    .block-na { background-color: rgba(255,255,255,0.05); opacity: 0.5; cursor: not-allowed; }
    
    .day-col { text-align: center !important; vertical-align: middle !important; padding: 4px !important; }

    /* DataTable Dark mode adjustments */
    .dataTables_wrapper { display: flow-root; padding-bottom: 10px; }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate { color: var(--text-light) !important; }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background-color: var(--bg-dark);
        color: var(--text-light);
        border: 1px solid #334155;
        border-radius: 4px;
        padding: 4px 8px;
    }
</style>

<!-- GLOBAL CONTROL TOWER KPI SUMMARY BAR (DYNAMIC PER-LINE CARDS) -->
<div id="global-kpi-banner" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 14px;">
    <!-- Populated dynamically per Line via JavaScript -->
</div>

<!-- Unified Control & Filter Bar -->
<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: rgba(15,23,42,0.8); padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); margin-bottom: 14px;">
    <div style="color: var(--text-light); font-size: 14px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-filter" style="color: var(--primary);"></i> Filters:
    </div>

    <!-- Line Filter -->
    <div class="header-actions" style="display: flex; gap: 6px; align-items: center;">
        <label style="color: var(--text-muted); font-size: 13px; font-weight: 700;">Line:</label>
        <select id="filter_line_name" style="padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.25); background: rgba(15,23,42,0.9); color: white; min-width: 130px; font-size: 13px; font-weight: 600;">
            <option value="">-- All Lines --</option>
        </select>
    </div>

    <!-- Section Filter -->
    <div class="header-actions" style="display: flex; gap: 6px; align-items: center;">
        <label style="color: var(--text-muted); font-size: 13px; font-weight: 700;">Section:</label>
        <select id="filter_section_name" style="padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.25); background: rgba(15,23,42,0.9); color: white; min-width: 130px; font-size: 13px; font-weight: 600;">
            <option value="">-- All Sections --</option>
        </select>
    </div>

    <!-- Live Shift Badge -->
    <div style="display: flex; gap: 6px; align-items: center; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); padding: 5px 12px; border-radius: 6px; color: #38bdf8; font-size: 12px; font-weight: 700;">
        <i class="fa-solid fa-calendar-day"></i> Shift Hari Ini (<?= date('d M Y') ?>)
    </div>

    <!-- Monthly Reporting Export Bar -->
    <div style="display: flex; gap: 6px; align-items: center; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 6px;">
        <label style="color: #34d399; font-size: 12px; font-weight: 800;">Report Month:</label>
        <input type="month" id="report_month" value="<?= date('Y-m') ?>" style="padding: 3px 8px; border-radius: 5px; border: 1px solid rgba(16, 185, 129, 0.4); background: rgba(15, 23, 42, 0.9); color: white; font-size: 12px; font-weight: 700;">
        <button id="btn-export-monthly-report" title="Export Laporan Performance Bulanan Semua Stasiun" style="background: linear-gradient(135deg, #10b981, #047857); color: #ffffff; border: 1px solid rgba(52, 211, 153, 0.5); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease;">
            <i class="fa-solid fa-file-excel"></i> Export Excel Performance
        </button>
    </div>

    <!-- Full Mode Toggle Button -->
    <button id="btn-wall-fullscreen" title="Enter Fullscreen Wall Mode (Hide Topbar &amp; Sidebar)" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #ffffff; border: 1px solid rgba(56, 189, 248, 0.5); padding: 5px 14px; border-radius: 6px; font-size: 12px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 0 12px rgba(2, 132, 199, 0.4); transition: all 0.2s ease;">
        <i class="fa-solid fa-expand"></i> Full Mode
    </button>


</div>

<!-- SECTION PER LINE MONITORING CONTROL SUMMARY TABLE -->
<div class="summary-table-card" id="summary-section-container">
    <div id="summary-cards-container" style="padding: 12px 14px;">
        <div style="text-align:center; padding:30px; color:var(--text-muted);">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
            <p style="margin-top:10px;">Initializing Monitoring Control Cards...</p>
        </div>
    </div>
</div>

<!-- DETAILED PARAMETER DRILLDOWN GRID -->
<div class="card" id="detail-section-container" style="display: none;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px;">
        <div style="font-size: 14px; font-weight: 700;">
            <i class="fa-solid fa-grid-2" style="color: var(--primary);"></i> Parameter Slot Matrix Drilldown
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive" id="table-container">
            <div style="text-align: center; padding: 50px; color: var(--text-muted);">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Loading parameter matrix...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW & DOWNLOAD PERFORMANCE BULANAN -->
<div id="modal-monthly-performance" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.75); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-card); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; width: 92%; max-width: 1100px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.6);">
        <div class="modal-header" style="padding: 16px 24px; background: linear-gradient(90deg, rgba(16,185,129,0.2) 0%, transparent 100%); border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #34d399; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-chart-column"></i> <span id="perf-modal-title">Laporan Performance Bulanan Stasiun</span>
            </h3>
            <button id="btn-close-perf-modal" style="background: transparent; border: none; color: var(--text-muted); font-size: 20px; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="perf-modal-body" style="padding: 20px 24px; overflow-y: auto; flex: 1;">
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Generating performance report preview...</p>
            </div>
        </div>
        <div class="modal-footer" style="padding: 14px 24px; background: rgba(15,23,42,0.8); border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 12px; color: var(--text-muted);" id="perf-modal-info">Previewing Monthly Station Time Check Compliance Report</span>
            <div style="display: flex; gap: 10px;">
                <button id="btn-download-modal-excel" class="btn-rich-success" style="padding: 8px 18px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fa-solid fa-file-excel"></i> Download Excel (.xls)
                </button>
                <button id="btn-cancel-perf-modal" style="padding: 8px 16px; font-size: 13px; background: rgba(255,255,255,0.1); color: var(--text-light); border: 1px solid rgba(255,255,255,0.15); border-radius: 6px; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script src="Script/js/dtc/js_dtc_missing_data.js?v=<?= time() ?>"></script>
