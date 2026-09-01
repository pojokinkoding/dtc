<?php
// views/dtc_matrix_qualitative.php

$param_id = $_GET['param_id'] ?? $_GET['parameter_id'] ?? 0;
$checkpoint_id = $_GET['checkpoint_id'] ?? $_GET['cp_id'] ?? 0;
$model = $_GET['model'] ?? '';
$line = $_GET['line'] ?? '';
$section = $_GET['section'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

if (empty($model) || empty($line) || empty($section)) {
    echo "<script>alert('Missing required parameters'); window.location.href='index.php?page=dtc';</script>";
    exit;
}
?>
<style>
    @keyframes pulse-yellow-glow {
        0% {
            border-color: #f59e0b !important;
            box-shadow: 0 0 4px rgba(245, 158, 11, 0.4), inset 0 0 2px rgba(245, 158, 11, 0.2) !important;
        }
        50% {
            border-color: #eab308 !important;
            box-shadow: 0 0 16px rgba(234, 179, 8, 0.9), inset 0 0 10px rgba(234, 179, 8, 0.5) !important;
        }
        100% {
            border-color: #facc15 !important;
            box-shadow: 0 0 8px rgba(250, 204, 21, 0.8), inset 0 0 6px rgba(250, 204, 21, 0.3) !important;
        }
    }
    .slot-overdue-glowing {
        animation: pulse-yellow-glow 1.2s infinite ease-in-out !important;
        border: 2px solid #facc15 !important;
        background-color: rgba(234, 179, 8, 0.25) !important;
        color: #fef08a !important;
        font-weight: 800 !important;
    }

    .matrix-header {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .matrix-header h2 {
        margin: 0;
        font-size: 22px;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .matrix-container {
        width: 100%;
        overflow-x: auto;
        background: rgba(15, 23, 42, 0.6);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        padding: 15px;
    }

    .matrix-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        background-color: rgba(15, 23, 42, 0.7);
    }
    .matrix-table th, .matrix-table td {
        border-bottom: 1px solid rgba(255,255,255,0.08);
        border-right: 1px solid rgba(255,255,255,0.08);
        padding: 6px 8px;
        text-align: center;
        white-space: nowrap;
        color: var(--text-light);
    }
    .matrix-table th {
        background-color: rgba(15, 23, 42, 1);
        color: var(--text-light);
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 2;
        border-top: 1px solid rgba(255,255,255,0.08);
    }
    .matrix-table td:first-child, .matrix-table th:first-child {
        border-left: 1px solid rgba(255,255,255,0.08);
    }
    .matrix-table .row-separator td {
        background: rgba(56, 189, 248, 0.08);
        border: none;
        padding: 2px;
    }
    
    .cell-data {
        cursor: default;
        width: 32px;
        height: 28px;
        border-radius: 4px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto;
        font-size: 11px;
        font-weight: 600;
    }
    .cell-data:hover {
        background: rgba(255,255,255,0.08);
    }
    .cell-ok {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .cell-ng {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    .cell-empty {
        color: rgba(255,255,255,0.15);
    }
    .cell-locked {
        background: rgba(100, 116, 139, 0.05) !important;
        border: 1px dashed rgba(255,255,255,0.05);
        color: rgba(255,255,255,0.1) !important;
    }

    .btn-add-checkpoint {
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.15), rgba(59, 130, 246, 0.15));
        color: #38bdf8;
        border: 1px dashed rgba(56, 189, 248, 0.4);
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-add-checkpoint:hover {
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.25), rgba(59, 130, 246, 0.25));
        border-color: #38bdf8;
        box-shadow: 0 0 12px rgba(56, 189, 248, 0.2);
    }
    .btn-delete-cp {
        background: transparent;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 11px;
        padding: 2px 4px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .btn-delete-cp:hover {
        color: #ef4444;
        background: rgba(239,68,68,0.1);
    }

    /* Modal Styling */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-box {
        background: #1e293b;
        padding: 25px;
        border-radius: 12px;
        width: 100%;
        max-width: 420px;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    .modal-box h3 {
        margin: 0 0 20px 0;
        color: white;
        font-size: 18px;
    }
    .modal-box label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-light);
        font-size: 14px;
        font-weight: 500;
    }
    .modal-box input, .modal-box select {
        width: 100%;
        padding: 10px;
        background: rgba(15,23,42,0.8);
        border: 1px solid rgba(255,255,255,0.1);
        color: white;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 13px;
        box-sizing: border-box;
    }
    .modal-box input:focus, .modal-box select:focus {
        outline: none;
        border-color: var(--accent);
    }

    .header-grid {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 15px;
    }
    .header-item-title { 
        font-size: 11px; 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        font-weight: 600; 
        color: #94a3b8; 
        margin-bottom: 3px; 
    }
    .header-item-value { 
        font-size: 15px; 
        font-weight: 700; 
        color: #f8fafc; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
    }

    /* 6-Panel Dense Dashboard Grid */
    .dense-dashboard {
        display: grid;
        grid-template-columns: 1.1fr 1.1fr 0.9fr;
        grid-gap: 12px;
        margin-top: 15px;
    }
    .dense-dashboard .card {
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
    }
    .dense-dashboard .card-header {
        font-size: 11px;
        font-weight: 700;
        color: #f8fafc;
        margin-bottom: 8px;
        padding: 0;
        border-bottom: none;
        min-height: auto;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .dense-dashboard .chart-container {
        height: 240px;
        width: 100%;
    }
</style>

<div class="card" style="padding-top: 15px;">
    <div id="tabs-container" class="nav-tabs-custom" style="display: flex; gap: 8px; flex-wrap: wrap; padding: 0 15px 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.08);">
        <!-- Tabs injected by JS -->
    </div>

    <!-- Mode Qualitative Container (Vertical Time Matrix Table) -->
    <div id="container-qualitative-view" style="padding: 15px;">
        
        <!-- KPI Qualitative Metrics Summary Row (2 Cards: Checkpoint Info & Inspection Results) -->
        <div id="qual-kpi-summary-row" style="display: grid; grid-template-columns: 1.8fr 1fr; gap: 15px; margin-bottom: 20px;">
            <!-- Metric 1: Active Checkpoint Overview & General Parameter Header Info -->
            <div style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid #38bdf8; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                    <span style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Active Checkpoint Information</span>
                    <div style="display:flex; align-items:center; gap: 8px;">
                        <a id="btn-qual-download-pdf-card" class="btn-cp-pdf-link" href="Script/php/dtc/c_missing_data_monthly_report.php?format=pdf&month=<?= urlencode($month) ?>&param_id=<?= intval($param_id) ?><?= !empty($checkpoint_id) ? '&checkpoint_id=' . intval($checkpoint_id) : '' ?>" target="_blank" title="Print atau Simpan Laporan Checkpoint sebagai PDF" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: 1px solid rgba(239, 68, 68, 0.5); padding: 2px 10px; border-radius: 5px; font-size: 11px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(239,68,68,0.3); transition: all 0.2s ease;">
                            <i class="fa-solid fa-print"></i> Print / Save PDF
                        </a>
                        <i class="fa-solid fa-clipboard-check" style="color: #38bdf8; font-size: 13px;"></i>
                    </div>
                </div>
                <div id="qual-kpi-cp-name" style="font-size: 14px; font-weight: 700; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px;">-</div>
                
                <!-- General Header Meta Details Embedded in Checkpoint Header Card -->
                <div style="display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 10.5px; color: #cbd5e1; margin-bottom: 6px; padding: 5px 8px; background: rgba(15, 23, 42, 0.6); border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                    <span><i class="fa-solid fa-bars-staggered" style="color: #38bdf8; font-size: 9px;"></i> <b>Line:</b> <?= htmlspecialchars($line) ?></span>
                    <span><i class="fa-solid fa-layer-group" style="color: #38bdf8; font-size: 9px;"></i> <b>Section:</b> <?= htmlspecialchars($section) ?></span>
                    <span><i class="fa-solid fa-cube" style="color: #38bdf8; font-size: 9px;"></i> <b>Model:</b> <?= htmlspecialchars($model) ?></span>
                    <span><i class="fa-solid fa-gears" style="color: #38bdf8; font-size: 9px;"></i> <b>Process:</b> <span class="hdr-process-name">-</span></span>
                    <span><i class="fa-solid fa-tag" style="color: #38bdf8; font-size: 9px;"></i> <b>Type:</b> <span class="hdr-data-type">-</span></span>
                    <span><i class="fa-regular fa-calendar-days" style="color: #38bdf8; font-size: 9px;"></i> <b>Month:</b> <?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
                </div>

                <div style="font-size: 11px; color: #cbd5e1;">Spec: <b id="qual-kpi-spec-val" style="color:#38bdf8;">-</b></div>
            </div>

            <!-- Metric 2: Monthly OK / NG Counts -->
            <div style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid #10b981; border-radius: 10px; padding: 12px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
                    <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Inspection Results</span>
                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 14px;"></i>
                </div>
                <div style="display:flex; align-items:baseline; gap:12px;">
                    <div style="font-size: 22px; font-weight: 800; color: #34d399;"><span id="qual-kpi-ok-count">0</span> <span style="font-size:11px; font-weight:600; color:#94a3b8;">OK</span></div>
                    <div style="font-size: 22px; font-weight: 800; color: #f87171;"><span id="qual-kpi-ng-count">0</span> <span style="font-size:11px; font-weight:600; color:#94a3b8;">NG</span></div>
                </div>
                <div id="qual-kpi-total-inspections" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Total Checks: 0</div>
            </div>
        </div>

        <div class="matrix-container" id="matrix-container" style="border-top: none;">
            <div style="text-align: center; padding: 50px; color: var(--text-muted);">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Loading Matrix Data...
            </div>
        </div>
    </div>

    <!-- Mode Quantitative Container (Dashboard Charts + Tracking History + Input) -->
    <div id="container-quantitative-view" style="display: none; padding: 15px;">
        
        <!-- KPI Executive Metrics Summary Row (3 Cards: Checkpoint Info, AI/Trend Insights, Reference Image) -->
        <div id="quant-kpi-summary-row" style="display: grid; grid-template-columns: 1.8fr 1.4fr 1.1fr; gap: 12px; margin-bottom: 20px; align-items: stretch;">
            <!-- Metric 1: Checkpoint & General Parameter Header Info -->
            <div style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid #38bdf8; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                        <span style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Active Checkpoint Information</span>
                        <div style="display:flex; align-items:center; gap: 8px;">
                            <a id="btn-quant-download-pdf-card" class="btn-cp-pdf-link" href="Script/php/dtc/c_missing_data_monthly_report.php?format=pdf&month=<?= urlencode($month) ?>&param_id=<?= intval($param_id) ?><?= !empty($checkpoint_id) ? '&checkpoint_id=' . intval($checkpoint_id) : '' ?>" target="_blank" title="Print atau Simpan Laporan Checkpoint sebagai PDF" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: 1px solid rgba(239, 68, 68, 0.5); padding: 2px 10px; border-radius: 5px; font-size: 11px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(239,68,68,0.3); transition: all 0.2s ease;">
                                <i class="fa-solid fa-print"></i> Print / Save PDF
                            </a>
                            <i class="fa-solid fa-bullseye" style="color: #38bdf8; font-size: 13px;"></i>
                        </div>
                    </div>
                    <div id="kpi-cp-name" style="font-size: 14px; font-weight: 700; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px;">-</div>
                    
                    <!-- General Header Meta Details Embedded in Checkpoint Header Card -->
                    <div style="display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 10.5px; color: #cbd5e1; margin-bottom: 6px; padding: 5px 8px; background: rgba(15, 23, 42, 0.6); border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                        <span><i class="fa-solid fa-bars-staggered" style="color: #38bdf8; font-size: 9px;"></i> <b>Line:</b> <?= htmlspecialchars($line) ?></span>
                        <span><i class="fa-solid fa-layer-group" style="color: #38bdf8; font-size: 9px;"></i> <b>Section:</b> <?= htmlspecialchars($section) ?></span>
                        <span><i class="fa-solid fa-cube" style="color: #38bdf8; font-size: 9px;"></i> <b>Model:</b> <?= htmlspecialchars($model) ?></span>
                        <span><i class="fa-solid fa-gears" style="color: #38bdf8; font-size: 9px;"></i> <b>Process:</b> <span class="hdr-process-name">-</span></span>
                        <span><i class="fa-solid fa-tag" style="color: #38bdf8; font-size: 9px;"></i> <b>Type:</b> <span class="hdr-data-type">-</span></span>
                        <span><i class="fa-regular fa-calendar-days" style="color: #38bdf8; font-size: 9px;"></i> <b>Month:</b> <?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
                    </div>
                </div>
                
                <div style="display:flex; gap: 6px; font-size: 10.5px; font-weight: 700;">
                    <span style="padding: 2px 6px; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #f87171; border-radius: 4px;">LSL: <span id="kpi-spec-lsl">-</span></span>
                    <span style="padding: 2px 6px; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399; border-radius: 4px;">T: <span id="kpi-spec-target">-</span></span>
                    <span style="padding: 2px 6px; background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.3); color: #60a5fa; border-radius: 4px;">USL: <span id="kpi-spec-usl">-</span></span>
                </div>
            </div>

            <!-- Metric 2: AI Insight & Trend Insight Card (Sejajar Reference Image) -->
            <div style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid #a855f7; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                <div>
                    <div style="font-size: 10.5px; font-weight: 700; color: #a855f7; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; display:flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Insight (Prompt)
                    </div>
                    <div id="ai-insight-box" style="font-size: 11.5px; line-height: 1.35; color: var(--text-light); background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 6px; padding: 6px 8px; min-height: 38px; display: flex; align-items: center;">
                        <i class="fa-solid fa-circle-notch fa-spin" style="margin-right: 6px; color: #a855f7;"></i> Analyzing active checkpoint data...
                    </div>
                </div>
                <div style="margin-top: 6px;">
                    <div style="font-size: 10.5px; font-weight: 700; color: #38bdf8; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; display:flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-chart-line"></i> Trend Insight
                    </div>
                    <div id="trend-insight-box" style="font-size: 11.5px; line-height: 1.35; color: var(--text-light); background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 6px; padding: 6px 8px; min-height: 30px; display: flex; align-items: center;">
                        <i class="fa-solid fa-circle-notch fa-spin" style="margin-right: 6px; color: #38bdf8;"></i> Loading trend...
                    </div>
                </div>
            </div>

            <!-- Metric 5: Checkpoint Reference Image (Upload & Taller View) -->
            <div id="quant-kpi-img-card" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid #00f3ff; border-radius: 10px; padding: 10px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
                    <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Reference Image</span>
                    <button id="btn-quant-upload-img" class="btn-change-cp-img" style="background: rgba(0,243,255,0.15); border: 1px solid rgba(0,243,255,0.3); color: #00f3ff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s;" title="Upload/Change Checkpoint Image">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                    </button>
                </div>
                <div id="quant-cp-img-container" style="flex: 1; min-height: 85px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; border-radius: 6px; background: rgba(15, 23, 42, 0.9); border: 1px dashed rgba(0,243,255,0.3); padding: 4px;">
                    <span id="quant-cp-no-img" style="font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 5px;">
                        <i class="fa-regular fa-image" style="font-size: 16px;"></i> No Reference Image
                    </span>
                    <img id="quant-cp-img-element" src="" alt="Reference Image" style="display: none; width: 100%; height: 85px; object-fit: contain; border-radius: 4px; cursor: pointer; transition: transform 0.2s;" class="btn-preview-img" title="Click to view full image">
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <div class="topbar-tabs" style="display: flex; gap: 4px; background: rgba(15,23,42,0.8); padding: 4px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                <button class="quant-tab-btn active" data-target="quant-tab-dashboard" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; transition: all 0.2s; box-shadow: 0 2px 8px rgba(59,130,246,0.4);"><i class="fa-solid fa-chart-line" style="margin-right: 5px;"></i> Dashboard</button>
                <button class="quant-tab-btn" data-target="quant-tab-history" style="background: transparent; color: var(--text-muted); border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s;"><i class="fa-solid fa-table" style="margin-right: 5px;"></i> Tracking History</button>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <div id="cp-img-preview-badge" style="display: none;"></div>
                <button class="btn-edit-cp" style="background: rgba(30,41,59,0.9); color: #38bdf8; border: 1px solid rgba(56,189,248,0.3); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; transition: all 0.2s; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Checkpoint
                </button>
                <button id="btn-open-quant-input" style="background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; box-shadow: 0 4px 12px rgba(37,99,235,0.4); transition: all 0.2s; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-keyboard"></i> Input / Update Data
                </button>
                <a id="btn-quant-download-pdf" class="btn-cp-pdf-link" href="Script/php/dtc/c_missing_data_monthly_report.php?format=pdf&month=<?= urlencode($month) ?>&param_id=<?= intval($param_id) ?><?= !empty($checkpoint_id) ? '&checkpoint_id=' . intval($checkpoint_id) : '' ?>" target="_blank" title="Print atau Simpan Laporan Checkpoint sebagai PDF" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: 1px solid rgba(239, 68, 68, 0.5); padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(239,68,68,0.4); transition: all 0.2s ease;">
                    <i class="fa-solid fa-print"></i> Print / Save PDF
                </a>
            </div>
        </div>

        <!-- Dashboard Charts Tab (6-Panel SPC Grid Layout) -->
        <div id="quant-tab-dashboard" class="quant-tab-content">
            <!-- 1-Screen Dense Dashboard Area (6 Panels) -->
            <div class="dense-dashboard" style="margin-top: 0;">
                <!-- ROW 1 -->
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-chart-line" style="color:#38bdf8;"></i> X-BAR CHART (AVERAGES)</div>
                    <div id="chart-xbar" class="chart-container"></div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-chart-pie" style="color:#f97316;"></i> 4-BLOCK DIAGRAM (ZST VS Z-SHIFT)</div>
                    <div id="chart-4block" class="chart-container"></div>
                </div>

                <div class="card summary-container">
                    <div class="card-header"><i class="fa-solid fa-table-list" style="color:#34d399;"></i> DATA SUMMARY</div>
                    <table class="matrix-table" style="width: 100%; height: 100%; background: transparent; margin: 0; border: none;">
                        <tbody style="font-size: 11px;">
                            <tr>
                                <td style="color:#94a3b8;">Sample Q'ty(n)</td><td id="summ-n" style="text-align: right; font-weight:bold; color:white;">0</td>
                                <td style="color:#94a3b8;">Center spec</td><td id="summ-center" style="text-align: right; font-weight:bold; color:white;">0</td>
                            </tr>
                            <tr>
                                <td style="color:#94a3b8;">Maximum data</td><td id="summ-max" style="text-align: right; font-weight:bold; color:white;">0</td>
                                <td style="color:#94a3b8;">Cp</td><td id="summ-cp" style="text-align: right; font-weight:bold; color:#10b981;">0</td>
                            </tr>
                            <tr>
                                <td style="color:#94a3b8;">Minimum data</td><td id="summ-min" style="text-align: right; font-weight:bold; color:white;">0</td>
                                <td style="color:#94a3b8;">Cpk</td><td id="summ-cpk" style="text-align: right; font-weight:bold; color:#10b981;">0</td>
                            </tr>
                            <tr>
                                <td style="color:#94a3b8;">Avg(X-bar)</td><td id="summ-avg" style="text-align: right; font-weight:bold; color:#38bdf8;">0</td>
                                <td style="color:#94a3b8;"><strong>Z<sub>ST</sub></strong></td><td id="summ-zst" style="text-align: right; font-weight: bold; color:#38bdf8; font-size: 1.1em;">0</td>
                            </tr>
                            <tr>
                                <td style="color:#94a3b8;">Std deviation</td><td id="summ-std" style="text-align: right; font-weight:bold; color:white;">0</td>
                                <td style="color:#94a3b8;"><strong>Z<sub>LT</sub></strong></td><td id="summ-zlt" style="text-align: right; font-weight: bold; color:#38bdf8; font-size: 1.1em;">0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ROW 2 -->
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-chart-area" style="color:#a855f7;"></i> R CHART (RANGES)</div>
                    <div id="chart-r" class="chart-container"></div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-wave-square" style="color:#8b5cf6;"></i> PROCESS CAPABILITY CURVE</div>
                    <div id="chart-capability" class="chart-container"></div>
                </div>

                <div class="card">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fa-solid fa-chart-bar" style="color:#10b981;"></i> MONTHLY Z-VALUE TREND</span>
                    </div>
                    <div id="chart-ztrend" class="chart-container"></div>
                </div>
            </div>
        </div>

        <!-- Tracking History Tab -->
        <div id="quant-tab-history" class="quant-tab-content" style="display: none;">
            <div style="width: 100%; overflow: auto; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(30,41,59,0.4);">
                <table class="matrix-table" id="quant-history-table">
                    <thead>
                        <tr id="quant-history-header"></tr>
                    </thead>
                    <tbody id="quant-history-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Checkpoint (Multiple / Single Batch Creator) -->
<div class="modal-overlay" id="modal-add-checkpoint">
    <div class="modal-box" style="max-width: 1160px; width: 95%; background: #1e293b; border-radius: 12px; padding: 22px 26px; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
            <div style="display:flex; align-items:center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.3); display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-layer-group" style="color:#38bdf8; font-size: 16px;"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size: 16px; color: white;">Multiple Add Check Points</h3>
                    <div style="font-size: 11px; color: #94a3b8;">Tambah beberapa checkpoint sekaligus (Single atau Batch) ke dalam DTC Item yang terpilih</div>
                </div>
            </div>
            <i class="fa-solid fa-xmark" id="btn-close-add-cp" style="color:var(--text-muted); cursor:pointer; font-size:20px; transition: color 0.2s;" title="Tutup"></i>
        </div>
        
        <form id="form-add-checkpoint">
            <!-- Header Controls: Parameter Selector & Quick Actions -->
            <div style="background: rgba(15,23,42,0.6); padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 250px;">
                    <label style="margin:0; font-size: 12px; font-weight: 700; color: #cbd5e1; white-space: nowrap;">Parameter (DTC Item):</label>
                    <input type="hidden" id="cp_param_select" name="parameter_id" value="">
                    <span id="cp_param_display_text" style="font-size: 13px; font-weight: 700; color: #38bdf8; background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.3); padding: 5px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-cube" style="font-size: 11px;"></i> <span id="cp_param_label_value">-</span>
                    </span>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" id="btn-add-cp-row" style="background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.4); color: #38bdf8; padding: 7px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                        <i class="fa-solid fa-plus"></i> Tambah 1 Baris
                    </button>
                    <button type="button" id="btn-clear-cp-rows" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #f87171; padding: 7px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px;" title="Kosongkan seluruh baris">
                        <i class="fa-solid fa-trash-can"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Scrollable Dynamic Table Grid -->
            <div style="max-height: 420px; overflow-x: hidden; overflow-y: auto; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; margin-bottom: 15px; background: rgba(15,23,42,0.4);">
                <table class="matrix-table" id="multiple-cp-table" style="width: 100%; table-layout: auto; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center; padding: 10px 4px;">No</th>
                            <th style="text-align: left; padding: 10px 8px;">Nama Check Point *</th>
                            <th style="text-align: left; padding: 10px 8px;">Spec Text (Opsional)</th>
                            <th style="width: 140px; text-align: left; padding: 10px 8px;">Tipe Checkpoint</th>
                            <th style="width: 80px; text-align: center; color: #f87171; padding: 10px 4px;">LSL (Min)</th>
                            <th style="width: 80px; text-align: center; color: #34d399; padding: 10px 4px;">Target</th>
                            <th style="width: 80px; text-align: center; color: #60a5fa; padding: 10px 4px;">USL (Max)</th>
                            <th style="width: 50px; text-align: center; padding: 10px 4px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="multiple-cp-tbody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Modal Footer -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px;">
                <div style="font-size: 11px; color: #64748b;" id="multiple-cp-summary-info">
                    Total: <b>0</b> checkpoint siap disimpan.
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" id="btn-cancel-add-cp" style="padding:8px 16px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:white; border-radius:6px; cursor:pointer; font-size: 12px; font-weight: 600;">Batal</button>
                    <button type="submit" id="btn-submit-multiple-cp" style="padding:8px 20px; background: linear-gradient(135deg, #10b981, #059669); border:none; color:white; border-radius:6px; font-weight:bold; cursor:pointer; font-size: 12px; display:flex; align-items:center; gap:6px; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Semua Checkpoint (Batch Save)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Checkpoint -->
<div class="modal-overlay" id="modal-edit-checkpoint">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;"><i class="fa-solid fa-pen-to-square" style="color:var(--accent);"></i> Edit Check Point</h3>
            <i class="fa-solid fa-times" id="btn-close-edit-cp" style="color:var(--text-muted); cursor:pointer; font-size:18px;"></i>
        </div>
        
        <form id="form-edit-checkpoint">
            <input type="hidden" id="edit_cp_id" name="checkpoint_id">
            <input type="hidden" name="action" value="edit">
            
            <div>
                <label>Checkpoint Name</label>
                <input type="text" id="edit_cp_name" name="checkpoint_name" placeholder="e.g. Pemakaian Tekanan Angin" required>
            </div>
            
            <div>
                <label>Spec Text (Optional)</label>
                <input type="text" id="edit_cp_spec" name="spec_value" placeholder="e.g. 5 Bar">
            </div>

            <div>
                <label>Checkpoint Type</label>
                <select id="edit_cp_type" name="checkpoint_type">
                    <option value="Qualitative">Qualitative (Matrix OK/NG)</option>
                    <option value="Quantitative">Quantitative (Measurement / Numeric)</option>
                </select>
            </div>

            <!-- Quantitative Spec Bounds (LSL, Target, USL) -->
            <div id="edit_spec_bounds" style="display: none; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; margin-bottom: 10px;">
                <div>
                    <label style="color: #f87171;">LSL (Min)</label>
                    <input type="number" step="any" id="edit_cp_lsl" name="lsl" placeholder="e.g. 60">
                </div>
                <div>
                    <label style="color: #34d399;">Target</label>
                    <input type="number" step="any" id="edit_cp_target_value" name="target_value" placeholder="e.g. 80">
                </div>
                <div>
                    <label style="color: #60a5fa;">USL (Max)</label>
                    <input type="number" step="any" id="edit_cp_usl" name="usl" placeholder="e.g. 100">
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
                <button type="button" id="btn-cancel-edit-cp" style="padding:10px 15px; background:rgba(255,255,255,0.1); border:none; color:white; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 15px; background:var(--accent); border:none; color:white; border-radius:6px; font-weight:bold; cursor:pointer;">
                    <i class="fa-solid fa-check"></i> Update Checkpoint
                </button>
            </div>
        </form>
    </div>
</div>



<!-- Modal Input Data (Qualitative) -->
<div class="modal-overlay" id="modal-input-matrix">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Input Data</h3>
            <i class="fa-solid fa-times" id="btn-close-modal" style="color:var(--text-muted); cursor:pointer; font-size:18px;"></i>
        </div>
        
        <div style="margin-bottom: 15px; color:var(--text-muted); font-size:13px;" id="modal-info-text"></div>
        <div id="input-spec-badge" style="display:none; margin-bottom: 15px; padding: 8px 12px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 6px; color: #38bdf8; font-size: 12px; font-weight: 600;"></div>

        <form id="form-matrix-input">
            <input type="hidden" id="input_param_id" name="parameter_id">
            <input type="hidden" id="input_checkpoint_id" name="checkpoint_id">
            <input type="hidden" id="input_date" name="date">
            <input type="hidden" id="input_time" name="time_label">
            <input type="hidden" id="input_result" name="result" required>

            <!-- Mode Qualitative (OK/NG) -->
            <div id="container-input-qualitative" style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:10px; color:var(--text-light); font-size:14px;">Result</label>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn-result" data-val="OK" style="flex:1; padding:12px; background:rgba(16, 185, 129, 0.1); border:1px solid #10b981; color:#10b981; border-radius:8px; font-weight:bold; cursor:pointer; transition: all 0.2s;">OK</button>
                    <button type="button" class="btn-result" data-val="NG" style="flex:1; padding:12px; background:rgba(239, 68, 68, 0.1); border:1px solid #ef4444; color:#ef4444; border-radius:8px; font-weight:bold; cursor:pointer; transition: all 0.2s;">NG</button>
                </div>
            </div>

            <!-- Mode Quantitative (Numeric Input) -->
            <div id="container-input-quantitative" style="display:none; margin-bottom: 20px;">
                <label style="display:block; margin-bottom:8px; color:var(--text-light); font-size:14px;">Measurement Value (Numeric)</label>
                <input type="number" step="any" id="input_numeric_value" style="width:100%; padding:12px; background:rgba(15,23,42,0.8); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:6px; font-size:16px; font-weight:600; box-sizing:border-box;" placeholder="Enter measurement value...">
            </div>

            <div id="result-warning-msg" style="display:none; color:#ef4444; font-size:12px; margin-bottom:15px; font-weight:600;">
                <i class="fa-solid fa-circle-exclamation"></i> Input belum diisi dan data tidak tersimpan!
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:8px; color:var(--text-light); font-size:14px;">Remarks (Optional)</label>
                <input type="text" id="input_remarks" name="remarks" style="width:100%; padding:10px; background:rgba(15,23,42,0.8); border:1px solid rgba(255,255,255,0.1); color:white; border-radius:6px; box-sizing:border-box;" placeholder="Add note...">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" id="btn-cancel-input" style="padding:10px 15px; background:rgba(255,255,255,0.1); border:none; color:white; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" id="btn-save-input" style="padding:10px 15px; background:var(--accent); border:none; color:white; border-radius:6px; font-weight:bold; cursor:pointer;">Save Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Input/Update Data Quantitative (Identical to dtc_detail) -->
<div id="modal-quant-input-data" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center;">
    <div class="card" style="width: 520px; max-width: 90%; max-height: 90vh; overflow-x: hidden; overflow-y: auto; position: relative; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <!-- Title Bar -->
        <div class="card-header" style="font-size: 14px; margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <i class="fa-solid fa-keyboard" style="color: var(--accent); margin-right: 6px;"></i> 
                <span id="modal-quant-cp-title" style="color: #10b981; font-weight: bold; font-size: 16px;"></span>
                <span id="modal-quant-datatype-badge" style="color: var(--text-muted); font-size: 12px; margin-left: 6px;"></span>
            </div>
            <i class="fa-solid fa-times" id="btn-close-quant-modal" style="color:var(--text-muted); cursor:pointer; font-size:18px;"></i>
        </div>

        <!-- Spec Info Bar -->
        <div style="display: flex; gap: 10px; padding: 10px 15px; background: rgba(15,23,42,0.6); border-top: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 15px;">
            <div style="flex:1; text-align:center; padding: 8px; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); border-radius: 6px;">
                <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">LSL</div>
                <div id="quant-spec-lsl" style="font-size: 16px; font-weight: 700; color: #f87171;">-</div>
            </div>
            <div style="flex:1; text-align:center; padding: 8px; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3); border-radius: 6px;">
                <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">TARGET</div>
                <div id="quant-spec-target" style="font-size: 16px; font-weight: 700; color: #34d399;">-</div>
            </div>
            <div style="flex:1; text-align:center; padding: 8px; background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3); border-radius: 6px;">
                <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">USL</div>
                <div id="quant-spec-usl" style="font-size: 16px; font-weight: 700; color: #60a5fa;">-</div>
            </div>
        </div>

        <form id="form-quant-input-data" style="padding: 0 15px 15px 15px;">
            <input type="hidden" id="quant_input_param_id" name="parameter_id">
            <input type="hidden" id="quant_input_checkpoint_id" name="checkpoint_id">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 5px;">Inspection Date & Closing Status</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
                    <input type="date" name="inspection_date" id="quant_input_date" required max="<?= date('Y-m-d') ?>"
                           style="flex: 1; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(59,130,246,0.6); background: rgba(15,23,42,0.8); color: #60a5fa; font-weight: 700; box-sizing: border-box; cursor: pointer;" title="Mode Admin: Klik untuk mengubah tanggal inspeksi (hanya tanggal lalu s/d hari ini)">
                    <?php else: ?>
                    <input type="date" name="inspection_date" id="quant_input_date" required readonly 
                           style="flex: 1; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: rgba(15,23,42,0.6); color: rgba(255,255,255,0.7); box-sizing: border-box; cursor: not-allowed; pointer-events: none;" title="Tanggal pengisian bersifat tetap (Read-only)">
                    <?php endif; ?>
                    <span id="quant-day-close-badge" style="font-size: 11px; font-weight: 700; padding: 6px 10px; border-radius: 6px; background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-lock-open"></i> Open
                    </span>
                    <?php if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
                    <button type="button" id="btn-toggle-close-day-quant" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #f87171; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px; transition: all 0.2s;" title="Lock/Close Day">
                        <i class="fa-solid fa-lock"></i> Close Day
                    </button>
                    <?php endif; ?>
                </div>
                <div id="quant-close-day-notice" style="display: none; color: #f87171; font-size: 11px; font-weight: 600; margin-top: 6px; background: rgba(239,68,68,0.1); padding: 6px 10px; border-radius: 4px; border: 1px solid rgba(239,68,68,0.2);">
                    <i class="fa-solid fa-lock"></i> Hari ini telah di-close. Hanya Admin yang dapat mengubah data.
                </div>
            </div>
            
            <div style="margin-bottom: 8px;">
                <h4 style="margin: 0; color: #10b981; font-size: 12px; font-weight: 700;">Samples (Time Check Mapping)</h4>
            </div>
            
            <div style="font-size: 11px; font-weight: bold; color: var(--primary); margin-bottom: 10px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;">
                <i class="fa-solid fa-clock"></i> Measurement Samples
            </div>

            <!-- Dynamic Samples Grid -->
            <div id="quant-samples-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 15px;">
                <!-- Filled dynamically by JS -->
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 5px;">Remarks (OOS Details)</label>
                <input type="text" name="remarks" id="quant_input_remarks" placeholder="Optional. Required if out of spec..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(15,23,42,0.8); color: white; box-sizing: border-box;">
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <button type="button" id="btn-cancel-quant-input" style="padding: 10px 18px; background: rgba(255,255,255,0.1); border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" id="btn-save-quant-input" style="padding: 10px 18px; background: #3b82f6; border: none; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Image Lightbox Preview -->
<div class="modal-overlay" id="modal-image-lightbox" style="z-index: 10000;">
    <div class="modal-box" style="max-width: 800px; padding: 15px; background: rgba(15, 23, 42, 0.95); text-align: center;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
            <span style="font-size: 14px; font-weight: 700; color: white;" id="lightbox-title"><i class="fa-regular fa-image" style="color: #00f3ff; margin-right: 6px;"></i> Checkpoint Reference Image</span>
            <i class="fa-solid fa-times" id="btn-close-lightbox" style="color: var(--text-muted); cursor: pointer; font-size: 20px;"></i>
        </div>
        <div style="max-height: 70vh; overflow: auto; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: #000; padding: 10px; display: flex; align-items: center; justify-content: center;">
            <img id="lightbox-img" src="" alt="Full Reference Image" style="max-width: 100%; max-height: 65vh; object-fit: contain; border-radius: 4px;">
        </div>
    </div>
</div>

<!-- Hidden file input for uploading checkpoint reference image -->
<input type="file" id="cp-image-file-input" style="display:none;" accept="image/*">

<script>
    const matrixParamId = <?= json_encode($param_id) ?>;
    const matrixModel = <?= json_encode($model) ?>;
    const matrixLine = <?= json_encode($line) ?>;
    const matrixSection = <?= json_encode($section) ?>;
    const matrixMonth = <?= json_encode($month) ?>;
    const isAdmin = <?= (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') ? 'true' : 'false' ?>;
    const isPrivilegedUser = <?= (isset($_SESSION['role']) && in_array(strtolower(trim($_SESSION['role'])), ['admin', 'foreman', 'supervisor'])) ? 'true' : 'false' ?>;
</script>

<?php require_once __DIR__ . '/modal_bulk_input.php'; ?>
