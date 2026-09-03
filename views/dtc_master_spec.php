<?php
require_once 'config/config.php';
$conn = getDBConnection();
$sqlSummary = "SELECT 
    COUNT(spec_id) as total_spec,
    SUM(CASE WHEN UPPER(line_name) = 'REF 01' THEN 1 ELSE 0 END) as ref01_count,
    SUM(CASE WHEN UPPER(line_name) = 'REF 02' THEN 1 ELSE 0 END) as ref02_count,
    SUM(CASE WHEN UPPER(line_name) = 'REF 03' THEN 1 ELSE 0 END) as ref03_count,
    SUM(CASE WHEN UPPER(data_type) = 'CTQ' THEN 1 ELSE 0 END) as ctq_count,
    SUM(CASE WHEN UPPER(data_type) = 'CTP' THEN 1 ELSE 0 END) as ctp_count,
    SUM(CASE WHEN UPPER(data_type) = 'TIME CHECK' THEN 1 ELSE 0 END) as tc_count,
    SUM(CASE WHEN UPPER(data_type) IN ('F/PROOF', 'FOOL PROOF') THEN 1 ELSE 0 END) as fp_count
FROM dtc_master_dtc_specs
WHERE 1=1 " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name');
$summarySpec = $conn->query($sqlSummary)->fetch(PDO::FETCH_ASSOC) ?: [
    'total_spec' => 0, 'ref01_count' => 0, 'ref02_count' => 0, 'ref03_count' => 0,
    'ctq_count' => 0, 'ctp_count' => 0, 'tc_count' => 0, 'fp_count' => 0
];
?>
<style>
    /* Styling for DataTables in Dark Mode */
    .dataTables_wrapper {
        display: flow-root; /* Clearfix for floated elements */
        padding-bottom: 10px;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: var(--text-light) !important;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 10px;
    }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background-color: var(--bg-dark);
        color: var(--text-light);
        border: 1px solid #334155;
        border-radius: 4px;
        padding: 4px 8px;
    }
    .dataTables_wrapper .dataTables_filter {
        float: right;
    }
    .dataTables_wrapper .dataTables_length {
        float: left;
    }
    .dataTables_wrapper .dataTables_paginate {
        float: right;
        margin-top: 10px;
    }
    .dataTables_wrapper .dataTables_info {
        float: left;
        margin-top: 10px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text-light) !important;
        padding: 6px 12px;
        margin: 0 4px;
        border: 1px solid #334155;
        border-radius: 4px;
        cursor: pointer;
        background-color: var(--bg-dark);
        text-decoration: none;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background-color: var(--primary);
        color: #ffffff !important;
        border-color: var(--primary);
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background-color: var(--primary);
        color: #ffffff !important;
        border-color: var(--primary);
    }

    /* Modal Form Styling */
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: var(--text-light);
    }
    .form-group .form-control {
        width: 100%;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #334155;
        background-color: var(--bg-dark);
        color: var(--text-light);
        box-sizing: border-box;
    }
    .form-group .form-control:focus {
        outline: none;
        border-color: var(--primary);
    }

    /* Table alignment fix */
    table.dataTable th,
    table.dataTable td {
        text-align: left !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
        font-size: 11px !important;
    }
    table.dataTable tbody td {
        padding: 6px 10px !important;
    }
    table.dataTable thead th {
        background-color: rgba(0, 0, 0, 0.2);
        border-bottom: 2px solid #334155 !important;
    }

    .manage-ls-tab-btn {
        padding: 6px 16px;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-muted);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .manage-ls-tab-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        color: var(--text-light);
    }
    .manage-ls-tab-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
    }

    /* Master Spec KPI Summary Cards */
    .spec-kpi-card {
        background: linear-gradient(135deg, rgba(15,23,42,0.92), rgba(30,41,59,0.85));
        border-radius: 10px;
        padding: 10px 14px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }
    .spec-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(0,0,0,0.5);
        filter: brightness(1.15);
    }
    .tab-badge {
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 10px;
        margin-left: 6px;
        background: rgba(255, 255, 255, 0.15);
        color: var(--text-light);
        font-weight: 700;
        display: inline-block;
        line-height: 1.2;
    }
    .filter-tab-btn.active .tab-badge {
        background: rgba(255, 255, 255, 0.3);
        color: #ffffff;
    }
</style>

<!-- Summary Total Master Spec KPI Banner -->
<div id="master-spec-summary-banner" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 16px;">
    <!-- 1. Total Spec -->
    <div class="spec-kpi-card" data-kpi="all" title="Klik untuk menampilkan semua data" style="border: 1px solid rgba(56,189,248,0.35); border-left: 4px solid #38bdf8;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-layer-group" style="color: #38bdf8; margin-right: 4px;"></i> TOTAL</span>
            <span style="background: rgba(56,189,248,0.2); color: #38bdf8; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">SPEC</span>
        </div>
        <div id="kpi-val-total" style="font-size: 22px; font-weight: 900; color: #38bdf8; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['total_spec'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Total Master Spec
        </div>
    </div>

    <!-- 2. REF 01 -->
    <div class="spec-kpi-card" data-kpi="line" data-line="REF 01" title="Klik untuk filter Line REF 01" style="border: 1px solid rgba(2,132,199,0.35); border-left: 4px solid #0284c7;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-industry" style="color: #0284c7; margin-right: 4px;"></i> REF 01</span>
            <span style="background: rgba(2,132,199,0.2); color: #38bdf8; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">LINE</span>
        </div>
        <div id="kpi-val-ref01" style="font-size: 22px; font-weight: 900; color: #38bdf8; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['ref01_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Line REF 01
        </div>
    </div>

    <!-- 3. REF 02 -->
    <div class="spec-kpi-card" data-kpi="line" data-line="REF 02" title="Klik untuk filter Line REF 02" style="border: 1px solid rgba(59,130,246,0.35); border-left: 4px solid #3b82f6;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-industry" style="color: #3b82f6; margin-right: 4px;"></i> REF 02</span>
            <span style="background: rgba(59,130,246,0.2); color: #60a5fa; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">LINE</span>
        </div>
        <div id="kpi-val-ref02" style="font-size: 22px; font-weight: 900; color: #60a5fa; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['ref02_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Line REF 02
        </div>
    </div>

    <!-- 4. CTQ -->
    <div class="spec-kpi-card" data-kpi="type" data-type="CTQ" title="Klik untuk filter kategori CTQ" style="border: 1px solid rgba(244,63,94,0.35); border-left: 4px solid #f43f5e;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-check-double" style="color: #f43f5e; margin-right: 4px;"></i> CTQ</span>
            <span style="background: rgba(244,63,94,0.2); color: #fb7185; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">QUALITY</span>
        </div>
        <div id="kpi-val-ctq" style="font-size: 22px; font-weight: 900; color: #fb7185; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['ctq_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Critical to Quality
        </div>
    </div>

    <!-- 5. CTP -->
    <div class="spec-kpi-card" data-kpi="type" data-type="CTP" title="Klik untuk filter kategori CTP" style="border: 1px solid rgba(245,158,11,0.35); border-left: 4px solid #f59e0b;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-sliders" style="color: #f59e0b; margin-right: 4px;"></i> CTP</span>
            <span style="background: rgba(245,158,11,0.2); color: #fbbf24; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">PROCESS</span>
        </div>
        <div id="kpi-val-ctp" style="font-size: 22px; font-weight: 900; color: #fbbf24; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['ctp_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Critical to Process
        </div>
    </div>

    <!-- 6. Time Check -->
    <div class="spec-kpi-card" data-kpi="type" data-type="Time Check" title="Klik untuk filter kategori Time Check" style="border: 1px solid rgba(16,185,129,0.35); border-left: 4px solid #10b981;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-clock" style="color: #10b981; margin-right: 4px;"></i> TIME CHECK</span>
            <span style="background: rgba(16,185,129,0.2); color: #34d399; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">TIME</span>
        </div>
        <div id="kpi-val-tc" style="font-size: 22px; font-weight: 900; color: #34d399; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['tc_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Pemeriksaan Waktu
        </div>
    </div>

    <!-- 7. Fool Proof -->
    <div class="spec-kpi-card" data-kpi="type" data-type="F/Proof" title="Klik untuk filter kategori Fool Proof" style="border: 1px solid rgba(168,85,247,0.35); border-left: 4px solid #a855f7;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-shield-halved" style="color: #a855f7; margin-right: 4px;"></i> FOOL PROOF</span>
            <span style="background: rgba(168,85,247,0.2); color: #c084fc; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">F/PROOF</span>
        </div>
        <div id="kpi-val-fp" style="font-size: 22px; font-weight: 900; color: #c084fc; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['fp_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Fool Proof (Poka-Yoke)
        </div>
    </div>

    <!-- 8. REF 03 (Shown if present) -->
    <div id="card-kpi-ref03" class="spec-kpi-card" data-kpi="line" data-line="REF 03" title="Klik untuk filter Line REF 03" style="border: 1px solid rgba(139,92,246,0.35); border-left: 4px solid #8b5cf6; <?= ($summarySpec['ref03_count'] ?? 0) > 0 ? '' : 'display: none;' ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <span><i class="fa-solid fa-industry" style="color: #8b5cf6; margin-right: 4px;"></i> REF 03</span>
            <span style="background: rgba(139,92,246,0.2); color: #c4b5fd; font-size: 9px; padding: 1px 5px; border-radius: 4px; font-weight: 700;">LINE</span>
        </div>
        <div id="kpi-val-ref03" style="font-size: 22px; font-weight: 900; color: #c4b5fd; margin-top: 4px; line-height: 1;">
            <?= number_format($summarySpec['ref03_count'] ?? 0) ?>
        </div>
        <div style="font-size: 10.5px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">
            Line REF 03
        </div>
    </div>
</div>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
    <!-- Filter Tabs -->
    <div class="dtc-filter-tabs" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="filter-tab-btn active" data-filter="">All <span class="tab-badge" id="badge-all"><?= number_format($summarySpec['total_spec'] ?? 0) ?></span></button>
        <button class="filter-tab-btn" data-filter="CTQ">CTQ <span class="tab-badge" id="badge-ctq"><?= number_format($summarySpec['ctq_count'] ?? 0) ?></span></button>
        <button class="filter-tab-btn" data-filter="CTP">CTP <span class="tab-badge" id="badge-ctp"><?= number_format($summarySpec['ctp_count'] ?? 0) ?></span></button>
        <button class="filter-tab-btn" data-filter="Time Check">Time Check <span class="tab-badge" id="badge-time-check"><?= number_format($summarySpec['tc_count'] ?? 0) ?></span></button>
        <button class="filter-tab-btn" data-filter="F/Proof">F/Proof <span class="tab-badge" id="badge-f-proof"><?= number_format($summarySpec['fp_count'] ?? 0) ?></span></button>
        
        <!-- Dropdown Filters -->
        <select id="filter-line" style="margin-left: 10px; padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Lines</option>
        </select>
        <select id="filter-section" style="padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Sections</option>
        </select>
        <select id="filter-item-check" style="padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Item Checks</option>
        </select>
    </div>
    
    <div class="header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button id="btn-manage-line-section" class="btn-rich-secondary" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(56,189,248,0.15); border-color: rgba(56,189,248,0.4); color: #38bdf8;">
            <i class="fa-solid fa-layer-group"></i> Kelola Line & Section
        </button>
        <button id="btn-copy-model-spec" class="btn-rich-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-clone"></i> Copy by Model
        </button>
        <button id="btn-add-spec" class="btn-rich-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-plus"></i> Add New Spec
        </button>
    </div>
</div>

<div class="card" style="margin-top: 20px; overflow: hidden;">
    <div class="card-body">
        <table id="master-spec-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Model</th>
                    <th>Item Check Name</th>
                    <th>Sub Item Check</th>
                    <th>Data Type</th>
                    <th>Line</th>
                    <th>Section</th>
                    <th>Process</th>
                    <th>Measuring Item</th>
                    <th>Target Zst/Zlt</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by DataTables -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add/Edit -->
<div id="modal-master-spec" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: var(--bg-card); padding: 22px 26px; border-radius: 10px; width: 95%; max-width: 1180px; max-height: 92vh; overflow-x: hidden; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
            <h2 id="modal-title" style="margin: 0; font-size: 18px; color: white;">Add Master Data</h2>
            <button id="btn-close-modal" style="background: none; border: none; color: var(--text-light); font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <form id="form-master-spec" novalidate>
            <input type="hidden" id="spec_id" name="spec_id" value="">
            
            <h4 style="margin-top:0; font-size: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; margin-bottom: 15px;">1. Basic Information</h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Model Name</label>
                    <input type="text" id="model_name" name="model_name" class="form-control" style="padding: 8px; font-size: 12px;" value="Default Model" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Item Check Name</label>
                    <input type="text" id="item_check_name" name="item_check_name" class="form-control" style="padding: 8px; font-size: 12px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Sub Item Check</label>
                    <input type="text" id="sub_item_check_name" name="sub_item_check_name" class="form-control" style="padding: 8px; font-size: 12px;" placeholder="Optional">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Data Type</label>
                    <select id="data_type" name="data_type" class="form-control" style="padding: 8px; font-size: 12px;" required></select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label style="font-size: 11px; margin-bottom: 0;">Line Name</label>
                        <button type="button" id="btn-quick-add-line" style="background: none; border: none; font-size: 10.5px; color: #38bdf8; cursor: pointer; padding: 0; display: inline-flex; align-items: center; gap: 3px;" title="Tambah Line Baru">
                            <i class="fa-solid fa-circle-plus"></i> Tambah
                        </button>
                    </div>
                    <select id="line_name" name="line_name" class="form-control" style="padding: 8px; font-size: 12px;" required></select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label style="font-size: 11px; margin-bottom: 0;">Section Name</label>
                        <button type="button" id="btn-quick-add-section" style="background: none; border: none; font-size: 10.5px; color: #38bdf8; cursor: pointer; padding: 0; display: inline-flex; align-items: center; gap: 3px;" title="Tambah Section Baru">
                            <i class="fa-solid fa-circle-plus"></i> Tambah
                        </button>
                    </div>
                    <select id="section_name" name="section_name" class="form-control" style="padding: 8px; font-size: 12px;" required></select>
                </div>
                <div class="form-group" style="margin-bottom: 0; grid-column: span 3;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Process Name</label>
                    <input type="text" id="process_name" name="process_name" class="form-control" style="padding: 8px; font-size: 12px;" required>
                </div>
            </div>

            <div id="quant-spec-section">
                <h4 style="margin-top:20px; font-size: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; margin-bottom: 15px;">2. Specification Details</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Measuring Item</label>
                    <?php $isAdmin = (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'); ?>
                    <select id="measuring_item" name="measuring_item" class="form-control" style="padding: 8px; font-size: 12px; <?= !$isAdmin ? 'pointer-events: none; background: rgba(0,0,0,0.2);' : '' ?>" <?= !$isAdmin ? 'readonly tabindex="-1"' : 'required' ?>>
                        <option value="Quantitative">Quantitative</option>
                        <option value="Qualitative">Qualitative</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px; color:#34d399;">Target Value</label>
                    <input type="number" step="0.1" id="target_value" name="target_value" class="form-control" style="padding: 8px; font-size: 12px;" value="110.0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px; color:#a78bfa;" title="Toleransi Simetris (±)">±</label>
                    <input type="number" step="0.1" id="quant_tolerance" class="form-control" style="padding: 8px; font-size: 12px;" placeholder="e.g. 10.0">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px; color:#f87171;">LSL</label>
                    <input type="number" step="0.1" id="lsl" name="lsl" class="form-control" style="padding: 8px; font-size: 12px;" value="70.0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px; color:#60a5fa;">USL</label>
                    <input type="number" step="0.1" id="usl" name="usl" class="form-control" style="padding: 8px; font-size: 12px;" value="150.0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">UoM (Unit)</label>
                    <input type="text" id="uom" name="uom" class="form-control" style="padding: 8px; font-size: 12px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Target Zst</label>
                    <input type="number" step="0.01" id="target_zst" name="target_zst" class="form-control" style="padding: 8px; font-size: 12px;" value="3.00" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Target Zlt</label>
                    <input type="number" step="0.01" id="target_zlt" name="target_zlt" class="form-control" style="padding: 8px; font-size: 12px;" value="4.00" required>
                </div>
                </div>
            </div>

            <div id="master-checkpoint-section" style="display: none;">
                <h4 style="margin-top:20px; font-size: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; margin-bottom: 12px;">2. Multiple Add Checkpoints</h4>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px;">
                    <span style="font-size:11px; color:var(--text-muted);">Checkpoint akan dibuat otomatis saat Running Model ditambahkan.</span>
                    <button type="button" id="btn-add-master-cp-row" class="btn-rich-secondary" style="padding:5px 12px; font-size:11.5px;"><i class="fa-solid fa-plus"></i> Tambah Checkpoint</button>
                </div>
                <div style="overflow-x: hidden; overflow-y: auto; border:1px solid rgba(255,255,255,0.1); border-radius:6px; background: rgba(15,23,42,0.4);">
                    <table style="width:100%; border-collapse:collapse; font-size:11.5px; table-layout: auto;">
                        <thead><tr style="color:var(--text-muted); text-align:left; border-bottom:1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.7);">
                            <th style="padding:8px 4px; width:35px; text-align:center;">No</th>
                            <th style="padding:8px 6px;">Checkpoint Name *</th>
                            <th style="padding:8px 6px; width:110px;">Type</th>
                            <th style="padding:8px 6px;">Spec Value</th>
                            <th style="padding:8px 4px; width:70px; text-align:center; color:#34d399;">Target</th>
                            <th style="padding:8px 4px; width:55px; text-align:center; color:#a78bfa;" title="Toleransi Simetris (±)">±</th>
                            <th style="padding:8px 4px; width:70px; text-align:center; color:#f87171;">LSL</th>
                            <th style="padding:8px 4px; width:70px; text-align:center; color:#60a5fa;">USL</th>
                            <th style="padding:8px 6px; width:160px;">Reference Image</th>
                            <th style="padding:8px 4px; width:35px; text-align:center;"></th>
                        </tr></thead>
                        <tbody id="master-checkpoint-tbody"></tbody>
                    </table>
                </div>
            </div>
            
            <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <button type="button" id="btn-cancel-modal" class="btn-rich-secondary">Cancel</button>
                <button type="submit" id="btn-save-spec" class="btn-rich-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Spec
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Copy Specs by Model -->
<div id="modal-copy-model-spec" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: var(--bg-card); padding: 22px 26px; border-radius: 10px; width: 95%; max-width: 650px; max-height: 90vh; overflow-x: hidden; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clone" style="color: var(--accent); font-size: 18px;"></i>
                <h2 style="margin: 0; font-size: 17px; color: white;">Copy Specs by Model</h2>
            </div>
            <button id="btn-close-copy-modal" style="background: none; border: none; color: var(--text-light); font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <p style="font-size: 12px; color: var(--text-muted); margin-top: 0; margin-bottom: 16px;">
            Duplikasi seluruh spesifikasi dan template checkpoint dari suatu model ke model baru dengan cepat.
        </p>

        <form id="form-copy-model-spec" novalidate>
            <div style="background: rgba(15,23,42,0.4); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px;">
                <h4 style="margin-top: 0; font-size: 12px; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Sumber Data (Source)
                </h4>
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Pilih Model Sumber *</label>
                        <select id="copy_source_model" name="source_model" class="form-control" style="padding: 8px; font-size: 12px;" required>
                            <option value="">-- Pilih Model Sumber --</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Filter Line (Opsional)</label>
                        <select id="copy_source_line" name="source_line" class="form-control" style="padding: 8px; font-size: 12px;">
                            <option value="">Semua Line</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Filter Section (Opsional)</label>
                        <select id="copy_source_section" name="source_section" class="form-control" style="padding: 8px; font-size: 12px;">
                            <option value="">Semua Section</option>
                        </select>
                    </div>
                </div>
                <div id="copy-preview-info" style="margin-top: 10px; font-size: 11.5px; color: #34d399; display: none;">
                    <i class="fa-solid fa-circle-check"></i> <span id="copy-preview-count">0</span> spesifikasi ditemukan dan siap disalin.
                </div>
            </div>

            <div style="background: rgba(15,23,42,0.4); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px;">
                <h4 style="margin-top: 0; font-size: 12px; color: var(--accent); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Target Tujuan (Destination)
                </h4>
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Nama Model Baru / Tujuan *</label>
                        <input type="text" id="copy_target_model" name="target_model" class="form-control" style="padding: 8px; font-size: 12px;" placeholder="Contoh: Model_B_2026" required>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Target Line (Opsional)</label>
                        <select id="copy_target_line" name="target_line" class="form-control" style="padding: 8px; font-size: 12px;">
                            <option value="">-- Sama Seperti Sumber --</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Target Section (Opsional)</label>
                        <select id="copy_target_section" name="target_section" class="form-control" style="padding: 8px; font-size: 12px;">
                            <option value="">-- Sama Seperti Sumber --</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px;">
                <button type="button" id="btn-cancel-copy-modal" class="btn-rich-secondary">Batal</button>
                <button type="submit" id="btn-submit-copy-model" class="btn-rich-primary">
                    <i class="fa-solid fa-clone"></i> Salin Semua Spesifikasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Manage Lines & Sections -->
<div id="modal-manage-lines-sections" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: var(--bg-card); padding: 22px 26px; border-radius: 10px; width: 95%; max-width: 900px; max-height: 90vh; overflow-x: hidden; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-layer-group" style="color: #38bdf8; font-size: 18px;"></i>
                <h2 style="margin: 0; font-size: 17px; color: white;">Kelola Master Line & Section</h2>
            </div>
            <button id="btn-close-manage-ls-modal" style="background: none; border: none; color: var(--text-light); font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Sub Tabs -->
        <div style="display: flex; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px;">
            <button type="button" id="tab-btn-lines" class="manage-ls-tab-btn active" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-industry"></i> Master Lines <span id="badge-lines-count" style="font-size: 10px; padding: 2px 6px; border-radius: 10px; background: rgba(56,189,248,0.2); color: #38bdf8;">0</span>
            </button>
            <button type="button" id="tab-btn-sections" class="manage-ls-tab-btn" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-shapes"></i> Master Sections <span id="badge-sections-count" style="font-size: 10px; padding: 2px 6px; border-radius: 10px; background: rgba(168,85,247,0.2); color: #c084fc;">0</span>
            </button>
        </div>

        <!-- Tab Panel: Lines -->
        <div id="panel-manage-lines">
            <div style="background: rgba(15,23,42,0.4); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px;">
                <h4 id="form-line-title" style="margin-top: 0; font-size: 12px; color: #38bdf8; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-plus"></i> Tambah Line Baru
                </h4>
                <form id="form-manage-line" style="display: grid; grid-template-columns: 2fr 3fr 1fr auto; gap: 10px; align-items: flex-end;">
                    <input type="hidden" id="manage_line_id" name="line_id" value="">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Nama Line *</label>
                        <input type="text" id="manage_line_name" name="line_name" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="e.g. REF 03" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Deskripsi (Opsional)</label>
                        <input type="text" id="manage_line_desc" name="description" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="e.g. Refrigerator Line 03">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Urutan</label>
                        <input type="number" id="manage_line_sort" name="sort_order" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="Auto">
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="submit" id="btn-save-line" class="btn-rich-primary" style="padding: 7px 14px; font-size: 12px; white-space: nowrap;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                        <button type="button" id="btn-reset-line" class="btn-rich-secondary" style="padding: 7px 10px; font-size: 12px; display: none;" title="Batal Edit">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div style="border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: rgba(15,23,42,0.8); color: var(--text-muted); text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th style="padding: 8px 10px; width: 40px; text-align: center;">No</th>
                            <th style="padding: 8px 10px;">Nama Line</th>
                            <th style="padding: 8px 10px;">Deskripsi</th>
                            <th style="padding: 8px 10px; width: 70px; text-align: center;">Urutan</th>
                            <th style="padding: 8px 10px; width: 90px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-manage-lines">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Panel: Sections -->
        <div id="panel-manage-sections" style="display: none;">
            <div style="background: rgba(15,23,42,0.4); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px;">
                <h4 id="form-section-title" style="margin-top: 0; font-size: 12px; color: #c084fc; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-plus"></i> Tambah Section Baru
                </h4>
                <form id="form-manage-section" style="display: grid; grid-template-columns: 2fr 2fr 2fr 1fr auto; gap: 10px; align-items: flex-end;">
                    <input type="hidden" id="manage_section_id" name="section_id" value="">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Nama Section *</label>
                        <input type="text" id="manage_section_name" name="section_name" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="e.g. Final Assembly" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Line (Opsional)</label>
                        <select id="manage_section_line" name="line_name" class="form-control" style="padding: 7px 10px; font-size: 12px;">
                            <option value="">Semua Line (General)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Deskripsi (Opsional)</label>
                        <input type="text" id="manage_section_desc" name="description" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="e.g. Stasiun Rakit Akhir">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 4px;">Urutan</label>
                        <input type="number" id="manage_section_sort" name="sort_order" class="form-control" style="padding: 7px 10px; font-size: 12px;" placeholder="Auto">
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="submit" id="btn-save-section" class="btn-rich-primary" style="padding: 7px 14px; font-size: 12px; white-space: nowrap;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                        <button type="button" id="btn-reset-section" class="btn-rich-secondary" style="padding: 7px 10px; font-size: 12px; display: none;" title="Batal Edit">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div style="border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: rgba(15,23,42,0.8); color: var(--text-muted); text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th style="padding: 8px 10px; width: 40px; text-align: center;">No</th>
                            <th style="padding: 8px 10px;">Nama Section</th>
                            <th style="padding: 8px 10px; width: 140px;">Line Terkait</th>
                            <th style="padding: 8px 10px;">Deskripsi</th>
                            <th style="padding: 8px 10px; width: 70px; text-align: center;">Urutan</th>
                            <th style="padding: 8px 10px; width: 90px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-manage-sections">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 20px; display: flex; justify-content: flex-end; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 14px;">
            <button type="button" id="btn-close-manage-ls" class="btn-rich-secondary" style="padding: 7px 18px; font-size: 12px;">
                Tutup
            </button>
        </div>
    </div>
</div>

