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
    <div class="modal-content" style="background-color: var(--bg-card); padding: 20px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 90vh; overflow-x: hidden; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 id="modal-title" style="margin: 0;">Add Master Data</h2>
            <button id="btn-close-modal" style="background: none; border: none; color: var(--text-light); font-size: 20px; cursor: pointer;">&times;</button>
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
            </div>

            <h4 style="margin-top:20px; font-size: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; margin-bottom: 15px;">2. Process & Specification Details</h4>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Process Name</label>
                    <input type="text" id="process_name" name="process_name" class="form-control" style="padding: 8px; font-size: 12px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Measuring Item</label>
                    <?php $isAdmin = (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'); ?>
                    <select id="measuring_item" name="measuring_item" class="form-control" style="padding: 8px; font-size: 12px; <?= !$isAdmin ? 'pointer-events: none; background: rgba(0,0,0,0.2);' : '' ?>" <?= !$isAdmin ? 'readonly tabindex="-1"' : 'required' ?>>
                        <option value="Quantitative">Quantitative</option>
                        <option value="Qualitative">Qualitative</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">LSL</label>
                    <input type="number" step="0.001" id="lsl" name="lsl" class="form-control" style="padding: 8px; font-size: 12px;" value="70" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">USL</label>
                    <input type="number" step="0.001" id="usl" name="usl" class="form-control" style="padding: 8px; font-size: 12px;" value="150" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Target Value</label>
                    <input type="number" step="0.001" id="target_value" name="target_value" class="form-control" style="padding: 8px; font-size: 12px;" value="110" required>
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
            
            <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <button type="button" id="btn-cancel-modal" class="btn-rich-secondary">Cancel</button>
                <button type="submit" id="btn-save-spec" class="btn-rich-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Spec
                </button>
            </div>
        </form>
    </div>
</div>
