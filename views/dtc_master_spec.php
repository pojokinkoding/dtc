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
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
    <!-- Filter Tabs -->
    <div class="dtc-filter-tabs" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="filter-tab-btn active" data-filter="">All</button>
        <button class="filter-tab-btn" data-filter="CTQ">CTQ</button>
        <button class="filter-tab-btn" data-filter="CTP">CTP</button>
        <button class="filter-tab-btn" data-filter="Time Check">Time Check</button>
        <button class="filter-tab-btn" data-filter="F/Proof">F/Proof</button>
        
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
    
    <div class="header-actions">
        <button id="btn-add-spec" class="btn-rich-primary">
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
                    <th>Spec (LSL - USL)</th>
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
        
        <form id="form-master-spec">
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
                    <label style="font-size: 11px; margin-bottom: 4px;">Line Name</label>
                    <select id="line_name" name="line_name" class="form-control" style="padding: 8px; font-size: 12px;" required></select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Section Name</label>
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
