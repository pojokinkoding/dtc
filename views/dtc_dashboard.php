<?php
// Fetch Header Data
require_once 'config/config.php';
$param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$headerData = null;

if ($param_id > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT p.*, s.model_name, s.item_check_name, s.data_type, s.lsl, s.usl, s.target_value,
                       COALESCE(p.line_name, s.line_name) as line_name,
                       COALESCE(p.process_name, s.process_name) as process_name,
                       COALESCE(p.section_name, s.section_name) as section_name,
                       COALESCE(p.measuring_item, s.measuring_item) as measuring_item,
                       COALESCE(p.target_zst, s.target_zst) as target_zst,
                       COALESCE(p.target_zlt, s.target_zlt) as target_zlt
                FROM dtc_master_parameters p 
                JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id 
                WHERE p.parameter_id = :param_id " . getIPAccessFilterSQL('COALESCE(p.line_name, s.line_name)', 'COALESCE(p.section_name, s.section_name)');
        $stmt = $conn->prepare($sql);
        $stmt->execute([':param_id' => $param_id]);
        $headerData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        // Silently ignore or log error
    }
}
?>
    <!-- Dashboard Grid Area -->
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .header-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px;
        }
        .header-item-title {
            color: var(--text-muted); 
            font-size: 12px; 
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .header-item-value {
            font-weight: 600; 
            font-size: 16px;
        }
    </style>

    <?php if($headerData): ?>
    <!-- DTC Detail Header Section -->
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--primary);">
        <div class="card-header"><i class="fa-solid fa-circle-info"></i> Detail Information (<?= htmlspecialchars($month) ?>)</div>
        <div class="header-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
            <!-- Column 1 -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-bars-staggered" style="color: #94a3b8; margin-right: 4px;"></i> Line</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['line_name']) ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-layer-group" style="color: #94a3b8; margin-right: 4px;"></i> Section</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['section_name'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-cube" style="color: #94a3b8; margin-right: 4px;"></i> Model Name</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['model_name']) ?></div>
                </div>
            </div>

            <!-- Column 2 -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div class="header-item">
                    <div class="header-item-title"><i class="fa-solid fa-tag" style="color: #94a3b8; margin-right: 4px;"></i> Item Check & Data Type</div>
                    <div class="header-item-value"><?= htmlspecialchars($headerData['item_check_name']) ?> <span style="font-size:12px; color:var(--text-muted);">[<?= htmlspecialchars($headerData['data_type']) ?>]</span></div>
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
                    <div class="header-item-value"><?= htmlspecialchars($headerData['measuring_item'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div class="header-item-title"><i class="fa-solid fa-bullseye" style="color: #94a3b8; margin-right: 4px;"></i> Target ZST / ZLT</div>
                    <div class="header-item-value" style="color: var(--accent);">
                        <?= htmlspecialchars(!empty($headerData['target_zst']) ? (float)$headerData['target_zst'] : '4') ?> / <?= htmlspecialchars(!empty($headerData['target_zlt']) ? (float)$headerData['target_zlt'] : '3') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Entry Section -->
    <div class="card" style="margin-bottom: 20px; overflow: hidden; max-width: 100%;">
        <div class="card-header"><i class="fa-solid fa-table-cells"></i> Measurement Data Entry <?= ($headerData && $headerData['measuring_item'] == 'Qualitative') ? '(Time Check)' : '' ?></div>
        <div style="width: 100%; overflow-x: auto; padding-bottom: 10px;">
            <div id="grid-input" style="width: max-content; min-width: 100%;"></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-line"></i> X-Bar Chart (Averages)</div>
            <div id="chart-xbar"></div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-area"></i> R Chart (Ranges)</div>
            <div id="chart-r"></div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-bell-curve"></i> Process Capability Curve</div>
            <div id="chart-capability"></div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-robot"></i> Monthly Z-Value Trend (AI Forecast)</div>
            <div id="chart-ztrend"></div>
        </div>
    </div>
