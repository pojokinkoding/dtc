<?php
// Fetch Header Data
require_once 'config/config.php';
$param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$headerData = null;

if ($param_id > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT p.*, 
                    COALESCE(p.model_name, s.model_name) as model_name, 
                    COALESCE(p.item_check_name, s.item_check_name) as item_check_name, 
                    COALESCE(p.sub_item_check_name, s.sub_item_check_name) as sub_item_check_name, 
                    COALESCE(p.data_type, s.data_type) as data_type, 
                    COALESCE(p.lsl, s.lsl) as lsl, 
                    COALESCE(p.usl, s.usl) as usl, 
                    COALESCE(p.target_value, s.target_value) as target_value, 
                    COALESCE(p.measuring_item, s.measuring_item) as measuring_item 
                FROM dtc_master_parameters p 
                LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id 
                WHERE p.parameter_id = :param_id " . getIPAccessFilterSQL('COALESCE(p.line_name, s.line_name)', 'COALESCE(p.section_name, s.section_name)');
        $stmt = $conn->prepare($sql);
        $stmt->execute([':param_id' => $param_id]);
        $headerData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$headerData) {
            echo "<script>alert('Parameter not found!'); window.location.href='index.php?page=dtc';</script>";
            exit;
        }
        
        // --- ADDED REDIRECT FOR QUALITATIVE MATRIX ---
        // If the user attempts to view a Qualitative parameter in the Quantitative detail view (e.g. from an old link),
        // we automatically redirect them to the new CTP Matrix Dashboard.
        if ($headerData['data_type'] === 'Time Check' || $headerData['data_type'] === 'F/Proof') {
            $m = urlencode($headerData['model_name']);
            $l = urlencode($headerData['line_name']);
            $s = urlencode($headerData['section_name']);
            $mo = urlencode($month);
            echo "<script>window.location.href='index.php?page=dtc_matrix_qualitative&param_id=$param_id&model=$m&line=$l&section=$s&month=$mo';</script>";
            exit;
        }
        // ---------------------------------------------
        
    } catch(Exception $e) {
        // Silently ignore or log error
    }
} else {
    echo "<script>alert('Invalid Parameter'); window.location.href='index.php?page=dtc';</script>";
    exit;
}
?>
    <!-- Dashboard Grid Area -->
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr; /* Force 1 column / 1 row per chart */
            gap: 20px;
            margin-bottom: 20px;
        }
        @keyframes pulse-yellow-glow {
            0% {
                border-color: #facc15 !important;
                box-shadow: 0 0 8px rgba(250, 204, 21, 0.8), inset 0 0 6px rgba(250, 204, 21, 0.3) !important;
            }
            50% {
                border-color: #eab308 !important;
                box-shadow: 0 0 20px rgba(234, 179, 8, 1), inset 0 0 10px rgba(234, 179, 8, 0.5) !important;
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
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .header-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); /* Auto-fit for variable items */
            gap: 10px;
        }
        @media (max-width: 1200px) {
            .header-grid {
                grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); 
            }
        }
        .header-item-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: #94a3b8; margin-bottom: 3px; }
        .header-item-value { font-size: 15px; font-weight: 700; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* Custom Matrix Table Styles */
        .matrix-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate; /* Changed from collapse for sticky to work perfectly */
            border-spacing: 0;
            font-size: clamp(9px, 0.8vw, 13px);
            background-color: rgba(15, 23, 42, 0.7);
        }
        .matrix-table th, .matrix-table td {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            border-right: 1px solid rgba(255,255,255,0.1);
            padding: 6px clamp(4px, 0.5vw, 12px);
            text-align: center;
            white-space: nowrap;
        }
        .matrix-table th {
            background-color: rgba(15, 23, 42, 1);
            color: var(--text-light);
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 2;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .matrix-table td:first-child, .matrix-table th:first-child {
            border-left: 1px solid rgba(255,255,255,0.1);
        }
        .matrix-table .sticky-col {
            position: sticky;
            left: 0;
            background-color: rgba(30, 41, 59, 1); /* solid dark blue for overlap */
            font-weight: bold;
            z-index: 3;
            border-right: 2px solid rgba(255,255,255,0.2);
            text-align: left;
        }
        .matrix-table th.sticky-col {
            z-index: 4;
            left: 0;
            top: 0;
        }
        .matrix-table .summary-row {
            background-color: rgba(16, 185, 129, 0.1) !important;
            color: #10b981;
            font-weight: bold;
        }
        .matrix-table .summary-row .sticky-col {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .matrix-table .oos-cell {
            background-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            font-weight: bold;
        }
        /* Dense 1-Screen Grid */
        .dense-dashboard {
            display: grid;
            grid-template-columns: repeat(3, 1fr) !important;
            grid-template-rows: repeat(2, 1fr) !important;
            gap: 12px;
            height: calc(100vh - 245px); /* Fill remaining viewport height */
            min-height: 400px;
        }
        @media (max-width: 768px) {
            .dense-dashboard {
                grid-template-columns: 1fr !important;
                grid-template-rows: auto !important;
                height: auto !important;
            }
        }
        .dense-dashboard .card {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            padding: 0; /* Remove default padding to maximize chart area */
            overflow: hidden;
        }
        .dense-dashboard .card-header {
            padding: 8px 12px;
            font-size: 13px;
            min-height: 35px;
            margin-bottom: 0;
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, transparent 100%);
            border-left: 3px solid #3b82f6;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .dense-dashboard .chart-container {
            flex: 1;
            min-height: 0;
            height: 100% !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden;
        }
        .dense-dashboard .chart-container > div,
        .dense-dashboard .chart-container .k-chart,
        .dense-dashboard .chart-container .k-chart-surface,
        .dense-dashboard .chart-container svg {
            height: 100% !important;
            width: 100% !important;
            flex: 1 !important;
        }
        .summary-container {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        /* Reference Image Thumbnail */
        .ref-thumb-container {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .ref-thumb-container:hover {
            transform: scale(1.05);
        }
        .ref-thumb-img {
            max-width: 250px;
            max-height: 130px;
            border-radius: 6px;
            border: 2px solid rgba(255,255,255,0.15);
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        /* Zoom Modal */
        .zoom-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            cursor: zoom-out;
        }
        .zoom-overlay.active {
            display: flex;
        }
        .zoom-overlay img {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
    </style>

    <?php if($headerData): ?>
    <!-- DTC Detail Header Section -->
    <div class="card" style="margin-bottom: 15px; padding: 12px 15px; border-left: 4px solid var(--primary);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0 0 8px 0; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); min-height: auto; position: relative;">
            <div class="topbar-tabs" style="display: flex; gap: 5px; background: rgba(0,0,0,0.2); padding: 4px; border-radius: 8px; z-index: 1;">
                <button class="topbar-tab-btn active" data-target="tab-dashboard" style="background: var(--primary); color: white; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s;"><i class="fa-solid fa-chart-line"></i> Dashboard</button>
                <button class="topbar-tab-btn" data-target="tab-measurement" style="background: transparent; color: var(--text-muted); border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s;"><i class="fa-solid fa-table"></i> Tracking History</button>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const tabBtns = document.querySelectorAll('.topbar-tab-btn');
                    tabBtns.forEach(btn => {
                        btn.addEventListener('click', function() {
                            // Update active button state
                            tabBtns.forEach(b => {
                                b.classList.remove('active');
                                b.style.background = 'transparent';
                                b.style.color = 'var(--text-muted)';
                            });
                            this.classList.add('active');
                            this.style.background = 'var(--primary)';
                            this.style.color = 'white';

                            // Toggle content
                            const targetId = this.getAttribute('data-target');
                            document.querySelectorAll('.tab-content').forEach(content => {
                                content.style.display = 'none';
                            });
                            const targetEl = document.getElementById(targetId);
                            if(targetEl) {
                                targetEl.style.display = targetId === 'tab-dashboard' ? 'grid' : 'block';
                                if(targetId === 'tab-dashboard') {
                                    targetEl.style.display = ''; // revert to original CSS (grid)
                                }
                                // Trigger window resize to fix charts that might not render correctly when hidden
                                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
                            }
                        });
                    });
                });
            </script>
            <span class="detail-header-title" style="font-size: 13px; font-weight: bold; position: absolute; left: 50%; transform: translateX(-50%);">DETAIL INFORMATION</span>
            <div style="z-index: 1;">
                <?php if (!empty($headerData['target_month']) && $headerData['target_month'] < date('Y-m')): ?>
                    <span style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-lock"></i> Periode Bulan Lalu (Terkunci Total)
                    </span>
                <?php else: ?>
                    <button id="btn-input-data" style="background-color: var(--primary); color: white; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-right: 8px; font-weight: bold;">
                        <i class="fa-solid fa-keyboard"></i> Input / Update Data
                    </button>
                    <button id="btn-edit-header" style="background: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-grid" style="grid-template-columns: 1fr 1.2fr 1fr <?= $headerData['measuring_item'] === 'Quantitative' ? '1.5fr ' : '' ?>auto; gap: 15px;">
            <!-- Column 1 -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-bars-staggered" style="color: #94a3b8; margin-right: 4px;"></i> Line</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['line_name']) ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-layer-group" style="color: #94a3b8; margin-right: 4px;"></i> Section</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['section_name']) ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-cube" style="color: #94a3b8; margin-right: 4px;"></i> Model Name</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['model_name']) ?></div>
                </div>
            </div>

            <!-- Column 2 -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-tag" style="color: #94a3b8; margin-right: 4px;"></i> Item Check & Data Type</div>
                    <div class="header-item-value">
                        <?= htmlspecialchars($headerData['item_check_name']) ?>
                        <?php if (!empty($headerData['sub_item_check_name']) && $headerData['sub_item_check_name'] !== '-'): ?>
                             - <?= htmlspecialchars($headerData['sub_item_check_name']) ?>
                        <?php endif; ?>
                        <span style="font-size:12px; color:var(--text-muted);">[<?= htmlspecialchars($headerData['data_type']) ?>]</span>
                    </div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-gears" style="color: #94a3b8; margin-right: 4px;"></i> Process Name</div>
                    <div class="header-item-value" style="white-space: normal; line-height: 1.3; overflow: visible;"><?= htmlspecialchars($headerData['process_name']) ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-arrows-left-right-to-line" style="color: #94a3b8; margin-right: 4px;"></i> Spec (LSL - USL)</div>
                    <div class="header-item-value">
                        <?= htmlspecialchars($headerData['lsl']) ?> &mdash; <?= htmlspecialchars($headerData['usl']) ?>
                        <?php if (!empty($headerData['target_value'])): ?>
                            <span style="color: #10b981; font-size: 11px; margin-left: 4px;">(T: <?= htmlspecialchars($headerData['target_value']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Column 3 -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-ruler-combined" style="color: #94a3b8; margin-right: 4px;"></i> Measurement</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['measuring_item']) ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-bullseye" style="color: #94a3b8; margin-right: 4px;"></i> Target ZST / ZLT</div>
                    <div class="header-item-value" style="color: var(--accent);">
                        <?= htmlspecialchars(!empty($headerData['target_zst']) ? (float)$headerData['target_zst'] : '4') ?> / <?= htmlspecialchars(!empty($headerData['target_zlt']) ? (float)$headerData['target_zlt'] : '3') ?>
                    </div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-regular fa-calendar-days" style="color: #94a3b8; margin-right: 4px;"></i> Month</div>
                    <div class="header-item-value"><?= date('F Y', strtotime($month . '-01')) ?></div>
                </div>
            </div>



            <!-- Column 4 (AI Insight & Trend Insight) - Spans both rows -->
            <?php if ($headerData['measuring_item'] === 'Quantitative'): ?>
            <div style="display: flex; flex-direction: column; gap: 6px; grid-column: 4; grid-row: 1 / span 2;">
                <div class="header-item-title" style="color: var(--purple);"><i class="fa-solid fa-wand-magic-sparkles" style="margin-right: 4px;"></i> AI Insight (Prompt)</div>
                <div id="ai-insight-box" style="font-size: 12.5px; line-height: 1.4; color: var(--text-light); background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 6px; padding: 6px; font-style: italic; display: flex; align-items: center; min-height: 52px; width: 100%;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="margin-right: 6px; color: var(--purple);"></i> Analyzing X-Bar data...
                </div>
                <div class="header-item-title" style="color: var(--primary); margin-top: 4px;"><i class="fa-solid fa-chart-line" style="margin-right: 4px;"></i> Trend Insight</div>
                <div id="trend-insight-box" style="font-size: 12.5px; line-height: 1.4; color: var(--text-light); background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; padding: 6px; font-style: normal; display: flex; align-items: center; min-height: 32px; width: 100%;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="margin-right: 6px; color: var(--primary);"></i> Loading trend...
                </div>
            </div>
            <?php endif; ?>

            <!-- Column 5 (Reference Image) - Spans both rows -->
            <?php if (!empty($headerData['reference_image'])): ?>
            <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; grid-column: 5; grid-row: 1 / span 2;">
                <div class="header-item-title"><i class="fa-solid fa-image" style="color: #94a3b8; margin-right: 4px;"></i> Reference</div>
                <div class="ref-thumb-container" onclick="document.getElementById('zoom-overlay').classList.add('active')" style="display: inline-block;">
                    <img src="<?= htmlspecialchars($headerData['reference_image']) ?>" alt="Reference" class="ref-thumb-img" style="margin-top: 4px;">
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <input type="hidden" id="spec_lsl" value="<?= htmlspecialchars($headerData['lsl']) ?>">
    <input type="hidden" id="spec_usl" value="<?= htmlspecialchars($headerData['usl']) ?>">

    <!-- Zoom Overlay for Reference Image -->
    <?php if (!empty($headerData['reference_image'])): ?>
    <div id="zoom-overlay" class="zoom-overlay" onclick="this.classList.remove('active')">
        <img src="<?= htmlspecialchars($headerData['reference_image']) ?>" alt="Reference Image Full">
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Tab Dashboard -->
    <div id="tab-dashboard" class="tab-content" style="display: block;">
        
    <?php if ($headerData['measuring_item'] == 'Qualitative'): ?>
    <!-- Data Entry Section (Only for Qualitative on Dashboard Tab) -->
    <div class="card" style="margin-bottom: 20px; overflow: hidden; max-width: 100%;">
        <div class="card-header"><i class="fa-solid fa-table-cells"></i> Measurement Data Entry (Time Check)</div>
        <div style="width: 100%; overflow-x: auto; padding-bottom: 10px;">
            <div id="grid-input-qualitative" style="width: max-content; min-width: 100%;"></div>
            <script>
                // Hide the 'Measurement' tab in the topbar since Qualitative only has one Check Sheet view
                document.addEventListener('DOMContentLoaded', function() {
                    const measTabBtn = document.querySelector('.topbar-tab-btn[data-target="tab-measurement"]');
                    if (measTabBtn) measTabBtn.style.display = 'none';
                    const dashTabBtn = document.querySelector('.topbar-tab-btn[data-target="tab-dashboard"]');
                    if (dashTabBtn) dashTabBtn.innerHTML = '<i class="fa-solid fa-list-check"></i> Check Sheet';
                });
            </script>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($headerData['measuring_item'] != 'Qualitative'): ?>
    <!-- 1-Screen Dense Dashboard Area -->
    <div class="dense-dashboard">
        <!-- ROW 1 -->
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-line"></i> X-Bar Chart (Averages)</div>
            <div id="chart-xbar" class="chart-container"></div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-pie"></i> 4-Block Diagram (Zst vs Z-shift)</div>
            <div id="chart-4block" class="chart-container"></div>
        </div>

        <div class="card summary-container">
            <div class="card-header"><i class="fa-solid fa-table-list"></i> Data Summary</div>
            <table class="matrix-table" style="width: 100%; height: 100%; background: var(--bg-card); margin: 0; border: none;">
                <tbody style="font-size: 12px;">
                    <tr>
                        <td>Sample Q'ty(n)</td><td id="summ-n" style="text-align: right;">0</td>
                        <td>Center spec</td><td id="summ-center" style="text-align: right;">0</td>
                    </tr>
                    <tr>
                        <td>Maximum data</td><td id="summ-max" style="text-align: right;">0</td>
                        <td>Cp</td><td id="summ-cp" style="text-align: right;">0</td>
                    </tr>
                    <tr>
                        <td>Minimum data</td><td id="summ-min" style="text-align: right;">0</td>
                        <td>Cpk</td><td id="summ-cpk" style="text-align: right; color: var(--danger);">0</td>
                    </tr>
                    <tr>
                        <td>Avg(X-bar)</td><td id="summ-avg" style="text-align: right;">0</td>
                        <td><strong>Z<sub>ST</sub></strong></td><td id="summ-zst" style="text-align: right; font-weight: bold; font-size: 1.1em;">0</td>
                    </tr>
                    <tr>
                        <td>Std deviation</td><td id="summ-std" style="text-align: right;">0</td>
                        <td><strong>Z<sub>LT</sub></strong></td><td id="summ-zlt" style="text-align: right; font-weight: bold; font-size: 1.1em;">0</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ROW 2 -->
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-area"></i> R Chart (Ranges)</div>
            <div id="chart-r" class="chart-container"></div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fa-solid fa-wave-square"></i> Process Capability Curve</div>
            <div id="chart-capability" class="chart-container"></div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa-solid fa-chart-line"></i> Monthly Z-Value Trend</span>
            </div>
            <div id="chart-ztrend" class="chart-container"></div>
        </div>

    </div>

    </div>
    <?php endif; ?>
    <!-- End Tab Dashboard -->

    <!-- Tab Measurement -->
    <div id="tab-measurement" class="tab-content" style="display: none; height: calc(100vh - 310px);">
        <div class="card" style="padding: 15px; margin-bottom: 0; height: 100%; display: flex; flex-direction: column;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-shrink: 0;">
                <span><i class="fa-solid fa-table"></i> Measurement Data Grid</span>
            </div>
            <div style="width: 100%; flex-grow: 1; overflow: auto; padding-bottom: 10px; border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;">
                <table class="matrix-table" id="html-grid-input">
                    <thead><tr id="matrix-header"><th class="sticky-col">Jam</th></tr></thead>
                    <tbody id="matrix-body"></tbody>
                </table>
            </div>
        </div>
        <!-- Hidden Daily Raw Samples -->
        <div id="chart-histogram" style="display: none;"></div>
    </div>
    <!-- End Tab Measurement -->

    <!-- Modal Input/Update Data Measurement -->
    <div id="modal-input-data" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="card" style="width: 520px; max-width: 90%; max-height: 90vh; overflow-x: hidden; overflow-y: auto; position: relative;">
            <div class="card-header" style="font-size: 14px; margin-bottom: 8px;">
                <i class="fa-solid fa-keyboard"></i> 
                <div style="font-size: 16px; margin-bottom: 5px;">
                    <span style="color: var(--accent); font-weight: bold;">
                        <?= htmlspecialchars($headerData['item_check_name'] ?? '') ?>
                        <?php if (!empty($headerData['sub_item_check_name']) && $headerData['sub_item_check_name'] !== '-'): ?>
                             - <?= htmlspecialchars($headerData['sub_item_check_name']) ?>
                        <?php endif; ?>
                    </span>
                    <span style="color: var(--text-muted); font-size: 12px; margin-left: 5px;">[<?= htmlspecialchars($headerData['data_type'] ?? '') ?>]</span>
                </div>
            </div>

            <?php if ($headerData['measuring_item'] != 'Qualitative'): ?>
            <!-- Spec Info Bar -->
            <div style="display: flex; gap: 8px; padding: 8px 15px; background: rgba(15,23,42,0.6); border-top: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 4px;">
                <div style="flex:1; text-align:center; padding: 6px 8px; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); border-radius: 6px;">
                    <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">LSL</div>
                    <div style="font-size: 15px; font-weight: 700; color: #f87171;"><?= htmlspecialchars($headerData['lsl'] ?? '-') ?></div>
                </div>
                <div style="flex:1; text-align:center; padding: 6px 8px; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3); border-radius: 6px;">
                    <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">TARGET</div>
                    <div style="font-size: 15px; font-weight: 700; color: #34d399;"><?= htmlspecialchars($headerData['target_value'] ?? '-') ?></div>
                </div>
                <div style="flex:1; text-align:center; padding: 6px 8px; background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3); border-radius: 6px;">
                    <div style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">USL</div>
                    <div style="font-size: 15px; font-weight: 700; color: #60a5fa;"><?= htmlspecialchars($headerData['usl'] ?? '-') ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div style="padding: 10px 15px;">
                <?php
                    $target_month = !empty($headerData['target_month']) ? $headerData['target_month'] : date('Y-m');
                    $min_date = $target_month . '-01';
                    $max_date = date('Y-m-t', strtotime($min_date));
                    $prod_hour = (int)date('H');
                    $today = ($prod_hour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
                    if ($max_date > $today) {
                        $max_date = $today;
                    }
                    // Default to production date if target month matches
                    $default_date = (substr($today, 0, 7) === $target_month) ? $today : '';
                    
                    // Fetch dynamic time labels
                    $dbConn = getDBConnection();
                    $line_name = $headerData['line_name'] ?? 'REF 01';
                    $setting_key = 'time_matrix_labels_' . $line_name;
                    
                    $stmtLabel = $dbConn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :key");
                    $stmtLabel->execute([':key' => $setting_key]);
                    $rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC);
                    $time_labels = [];
                    if ($rowSetting && $rowSetting['setting_value']) {
                        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
                        $time_labels = json_decode($val, true);
                    }
                    if (empty($time_labels)) {
                        $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
                    }
                    
                    // Fetch existing labels for this parameter to maintain consistency across all months
                    $stmtExistingLabels = $dbConn->prepare("
                        SELECT m.sample_sequence, m.sample_label 
                        FROM dtc_measurements m 
                        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
                        WHERE s.parameter_id = :pid AND s.is_active = 1
                        ORDER BY s.inspection_date ASC, m.measurement_id ASC
                    ");
                    $stmtExistingLabels->execute([':pid' => $param_id]);
                    $existing_labels = [];
                    $param_max_seq = 0;
                    while ($r = $stmtExistingLabels->fetch(PDO::FETCH_ASSOC)) {
                        $seq = intval($r['sample_sequence']);
                        if ($seq > $param_max_seq) {
                            $param_max_seq = $seq;
                        }
                        $lbl = trim($r['sample_label'] ?? '');
                        if ($lbl && strtolower($lbl) !== 'null' && !isset($existing_labels[$seq])) {
                            $existing_labels[$seq] = $lbl;
                        }
                    }
                    
                    // Merge existing labels into time_labels to keep the UI consistent with existing data
                    for ($i = 0; $i < count($time_labels); $i++) {
                        if (isset($existing_labels[$i + 1])) {
                            $time_labels[$i] = $existing_labels[$i + 1];
                        }
                    }
                ?>
                <form id="form-input-data">
                    <input type="hidden" name="parameter_id" value="<?= htmlspecialchars($param_id) ?>">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 5px;">Inspection Date</label>
                            <input type="date" name="inspection_date" id="input_inspection_date" required 
                                   min="<?= $min_date ?>" max="<?= $max_date ?>" value="<?= $default_date ?>"
                                   style="width: 100%; padding: 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 0 8px rgba(255,255,255,0.2); background: rgba(15,23,42,0.5); color: white;">
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h4 style="margin: 0; color: var(--accent); font-size: 12px;">Samples (Time Check Mapping)</h4>
                    </div>
                    
                    <div style="font-size: 11px; font-weight: bold; color: var(--primary); margin-bottom: 6px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;"><i class="fa-solid fa-clock"></i> Measurement Samples</div>
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 15px;">
                        <?php foreach ($time_labels as $idx => $label): ?>
                        <div>
                            <label id="label_sample_<?= $idx+1 ?>" style="display: block; font-size: 10px; text-align: center; color: var(--text-muted); margin-bottom: 4px;"><?= htmlspecialchars($label) ?></label>
                            <input type="number" step="any" name="sample_<?= $idx+1 ?>" class="sample-input" style="width: 100%; text-align: center; padding: 6px 4px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 0 5px rgba(255,255,255,0.2); background: rgba(15,23,42,0.5); color: white; transition: all 0.3s; font-size: 12px;" placeholder="S<?= $idx+1 ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Remarks (OOS Details)</label>
                        <input type="text" name="remarks" placeholder="Optional. Required if out of spec..." style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 0 5px rgba(255,255,255,0.2); background: rgba(15,23,42,0.5); color: white;">
                    </div>
                    
                    <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; width: 100%; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                        <button type="button" id="btn-close-data" class="btn-rich-danger" style="display: none;"><i class="fa-solid fa-lock"></i> Close Measurement</button>
                        <div style="flex-grow: 1;"></div>
                        <button type="button" id="btn-cancel-input" class="btn-rich-secondary">Cancel</button>
                        <button type="submit" id="btn-save-input" class="btn-rich-primary"><i class="fa-solid fa-floppy-disk"></i> Save Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Header DTC -->
    <div id="modal-edit-header" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; justify-content: center; align-items: center;">
        <div class="card" style="width: 750px; max-width: 95%; max-height: 90vh; overflow-x: hidden; overflow-y: auto;">
            <div class="card-header">
                <i class="fa-solid fa-pen-to-square"></i> Edit DTC Header &mdash;
                <span style="color: var(--accent); font-weight: bold;" id="edit-header-title">
                    <?= htmlspecialchars($headerData['item_check_name'] ?? '') ?>
                    <?php if (!empty($headerData['sub_item_check_name']) && $headerData['sub_item_check_name'] !== '-'): ?>
                         - <?= htmlspecialchars($headerData['sub_item_check_name']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div style="padding: 16px;">
                <form id="form-edit-header" enctype="multipart/form-data">
                    <input type="hidden" name="parameter_id" value="<?= $param_id ?>">

                    <!-- Basic Information -->
                    <h4 style="margin-bottom: 10px; color: var(--accent);">Basic Information</h4>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Target Month</label>
                            <input type="month" name="target_month" value="<?= htmlspecialchars($headerData['target_month'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted); pointer-events:none;">
                        </div>
                        <div class="form-group" style="margin-bottom: 5px;">
                            <label style="display:block; margin-bottom:5px; color:var(--text-muted); font-size:12px;">Master Data</label>
                            <select id="edit_spec_id" name="spec_id" data-val="<?= htmlspecialchars($headerData['spec_id'] ?? '') ?>" required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(15,23,42,0.5); color:white;">
                                <!-- Loaded via JS -->
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Model Name</label>
                            <input type="text" id="edit_model_name" name="model_name" value="<?= htmlspecialchars($headerData['model_name'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Process Name</label>
                            <input type="text" id="edit_process_name" name="process_name" value="<?= htmlspecialchars($headerData['process_name'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Line Name</label>
                            <input type="text" id="edit_line_name" name="line_name" value="<?= htmlspecialchars($headerData['line_name'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Section Name</label>
                            <input type="text" id="edit_section_name" name="section_name" value="<?= htmlspecialchars($headerData['section_name'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Measuring Item</label>
                            <input type="text" name="measuring_item" value="Quantitative" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                    </div>

                    <?php if ($headerData['measuring_item'] != 'Qualitative'): ?>
                    <!-- Specification Information -->
                    <h4 style="margin-bottom: 10px; color: var(--accent);">Specification Information</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">LSL</label>
                            <input type="number" step="any" id="edit_lsl" name="lsl" value="<?= htmlspecialchars($headerData['lsl'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">USL</label>
                            <input type="number" step="any" id="edit_usl" name="usl" value="<?= htmlspecialchars($headerData['usl'] ?? '') ?>" readonly required style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Target Value (Center)</label>
                            <input type="number" step="any" id="edit_target_value" name="target_value" value="<?= htmlspecialchars($headerData['target_value'] ?? '') ?>" readonly style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Target ZST</label>
                            <input type="number" step="any" name="target_zst" value="4" readonly style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Target ZLT</label>
                            <input type="number" step="any" name="target_zlt" value="3" readonly style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.8); color:var(--text-muted);">
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Reference Image Upload -->
                    <h4 style="margin-bottom: 10px; color: var(--accent);">Reference Image</h4>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Upload Reference Image (Optional)</label>
                        <input type="file" name="reference_image" accept="image/*" id="edit-ref-image-input" style="width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:rgba(15,23,42,0.5); color:white; font-size:12px;">
                        <div id="edit-ref-image-preview" style="margin-top: 8px;<?= empty($headerData['reference_image']) ? ' display:none;' : '' ?>">
                            <?php if (!empty($headerData['reference_image'])): ?>
                            <img id="edit-ref-image-thumb" src="<?= htmlspecialchars($headerData['reference_image']) ?>" alt="Current" style="max-width: 120px; max-height: 90px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.15); object-fit: cover;">
                            <span style="display:block; font-size:10px; color:var(--text-muted); margin-top:4px;">Current image. Select a new file to replace.</span>
                            <?php else: ?>
                            <img id="edit-ref-image-thumb" src="" alt="Preview" style="max-width: 120px; max-height: 90px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.15); object-fit: cover;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                        <button type="button" id="btn-cancel-edit-header" class="btn-rich-secondary">Cancel</button>
                        <button type="submit" id="btn-save-edit-header" class="btn-rich-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const specLSL = <?= htmlspecialchars($headerData['lsl'] ?? 0) ?>;
        const specUSL = <?= htmlspecialchars($headerData['usl'] ?? 0) ?>;
        const currentParamId = <?= $param_id ?>;
        const currentMonth = "<?= htmlspecialchars($month) ?>";
        const isQualitative = <?= isset($headerData['measuring_item']) && $headerData['measuring_item'] == 'Qualitative' ? 'true' : 'false' ?>;
        const dtcName = <?= json_encode($headerData['item_check_name'] ?? '') ?>;
        const dataType = <?= json_encode($headerData['data_type'] ?? '') ?>;
        const defaultTimeLabels = <?= json_encode($time_labels) ?>;
        const userRole = "<?= $_SESSION['role'] ?? 'Operator' ?>";
        const isAdmin = (userRole.toLowerCase() === 'admin');
    </script>
