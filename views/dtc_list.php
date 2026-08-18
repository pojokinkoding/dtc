<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    /* Always display SweetAlert on top of all custom modals */
    .swal2-container {
        z-index: 99999 !important;
    }

    /* DataTables Dark Theme Overrides */
    table.dataTable, table.dataTable th, table.dataTable td {
        color: var(--text-light) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        font-size: 11px !important;
    }
    table.dataTable tbody td {
        padding: 6px 10px !important;
    }
    table.dataTable thead th, table.dataTable thead td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    table.dataTable tbody tr {
        background-color: transparent !important;
    }
    table.dataTable tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing, .dataTables_wrapper .dataTables_paginate {
        color: var(--text-muted) !important;
        margin-bottom: 10px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text-muted) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current, .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--primary) !important;
        color: white !important;
        border-color: var(--primary) !important;
    }
    .dataTables_wrapper input, .dataTables_wrapper select {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: var(--text-light) !important;
        border-radius: 4px !important;
        padding: 4px 8px;
        margin-left: 5px;
    }
    .dataTables_wrapper .dataTables_length select {
        padding: 4px 20px 4px 8px;
    }

    /* Running Model Table Panel Styling */
    .rm-table th, .rm-table td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
        vertical-align: middle !important;
    }
    .rm-table tr:last-child th, .rm-table tr:last-child td {
        border-bottom: none !important;
    }
    .rm-section-badge {
        font-size: 11px;
        font-weight: 700;
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.3);
        padding: 3px 10px;
        border-radius: 14px;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        user-select: none;
    }
    .running-model-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.35);
        padding: 4px 10px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }
    .running-model-badge:hover {
        background: rgba(16, 185, 129, 0.25);
        border-color: #34d399;
        box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
    }
    .running-model-badge.active-filter {
        background: #10b981;
        color: #ffffff;
        border-color: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
    }
    .running-model-badge .btn-remove-rm {
        font-size: 10px;
        opacity: 0.6;
        transition: opacity 0.2s;
        padding: 2px;
    }
    .running-model-badge .btn-remove-rm:hover {
        opacity: 1;
        color: #ef4444;
    }
    .btn-add-running-model {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
        border: 1px dashed rgba(59, 130, 246, 0.4);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-add-running-model:hover {
        background: rgba(59, 130, 246, 0.25);
        border-color: #60a5fa;
        color: #ffffff;
    }
    .btn-preview-bulk-img {
        transition: all 0.25s ease-in-out !important;
    }
    .btn-preview-bulk-img:hover {
        transform: scale(1.12) !important;
        border-color: #38bdf8 !important;
        box-shadow: 0 0 12px rgba(56, 189, 248, 0.6) !important;
    }

    @keyframes dtc-marquee {
        0% { transform: translate3d(0, 0, 0); }
        100% { transform: translate3d(-100%, 0, 0); }
    }
    .dtc-ticker-track:hover .dtc-ticker-content {
        animation-play-state: paused !important;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: var(--text-light); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-list-check" style="color: var(--accent);"></i> DTC List
            <span style="font-size: 12px; font-weight: 600; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 3px 10px; border-radius: 20px;">
                <i class="fa-regular fa-calendar-check"></i> Bulan Ini (<?= date('F Y') ?>)
            </span>
        </h2>
    </div>
</div>

<!-- Running Model Table Panel (Collapsible Card Layout) -->
<div id="running-model-panel" style="margin-bottom: 20px; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; overflow: hidden; backdrop-filter: blur(10px); transition: all 0.3s ease;">
    <!-- Panel Header -->
    <div id="toggle-running-model-panel" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: rgba(255, 255, 255, 0.03); cursor: pointer; user-select: none;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span style="font-size: 13px; font-weight: 700; color: var(--text-light); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-cubes" style="color: #f59e0b;"></i> Daftar Running Model Aktif
            </span>
            <span id="rm-active-count-badge" style="font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 3px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;" title="Klik untuk Expand / Minimize Tabel Running Model">
                <span id="rm-count-text">0 Models Active</span>
                <i id="rm-panel-chevron" class="fa-solid fa-chevron-down" style="color: #34d399; font-size: 10px; transition: transform 0.3s ease; transform: rotate(-180deg);"></i>
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <button id="btn-open-add-running-model" class="btn-add-running-model" title="Add Running Model">
                <i class="fa-solid fa-circle-plus"></i> Add Model
            </button>
            <button id="btn-open-ctp-matrix" class="btn-rich-success" style="padding: 4px 10px; font-size: 12px; border-radius: 8px; display: none;" title="Open Check Sheet Matrix">
                <i class="fa-solid fa-table-cells"></i> CTP Matrix
            </button>
        </div>
    </div>

    <!-- Panel Body Table -->
    <div id="running-model-table-wrapper" style="display: none; padding: 12px 16px; border-top: 1px solid rgba(255,255,255,0.08); overflow-x: auto; max-height: 240px; overflow-y: auto;">
        <table class="rm-table" style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <th style="padding: 8px 12px; width: 220px; font-weight: 600;"><i class="fa-solid fa-layer-group" style="color: #f59e0b; margin-right: 4px;"></i> Line & Section</th>
                    <th style="padding: 8px 12px; font-weight: 600;"><i class="fa-solid fa-cubes" style="color: #10b981; margin-right: 4px;"></i> Active Running Models</th>
                </tr>
            </thead>
            <tbody id="running-model-table-body">
                <!-- Dynamically populated via JS -->
            </tbody>
        </table>
    </div>
</div>

<?php 
$currentUserRole = strtolower(trim($_SESSION['role'] ?? ''));
$isSupervisorRole = (strpos($currentUserRole, 'supervisor') !== false);
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
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
        
        <!-- Checkbox Filter Out of Spec Only -->
        <label style="display: inline-flex; align-items: center; gap: 6px; margin-left: 8px; font-size: 12px; font-weight: 600; color: #fca5a5; cursor: pointer; user-select: none; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); padding: 5px 12px; border-radius: 4px; transition: all 0.2s ease;" title="Tampilkan Hanya Parameter Yang Memiliki Pengukuran Out of Spec">
            <input type="checkbox" id="filter-oos-only" style="cursor: pointer; accent-color: #ef4444; width: 14px; height: 14px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Out of Spec Only
        </label>
    </div>

    <div class="header-actions">
        <div class="btn-group-rich" style="display: flex; gap: 8px; align-items: center;">
            <?php if (!$isSupervisorRole): ?>
            <button id="btn-open-bulk-input-modal" class="btn-rich-primary" title="Input Bulk Pengukuran Running Model Hari Ini" style="height: 36px; padding: 0 14px; display: inline-flex; justify-content: center; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                <i class="fa-solid fa-list-check"></i> Bulk Input Pengukuran
            </button>
            <?php endif; ?>
            <?php if (!$isSupervisorRole): ?>
            <button id="btn-add-dtc" class="btn-rich-primary" title="Add Parameter" style="width: 36px; height: 36px; padding: 0; display: inline-flex; justify-content: center; align-items: center;">
                <i class="fa-solid fa-circle-plus"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Live Running Text Summary Ticker Bar -->
<div class="dtc-ticker-wrapper" style="margin-bottom: 15px; background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 8px; padding: 6px 12px; display: flex; align-items: center; overflow: hidden; position: relative; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);">
    <div class="dtc-ticker-badge" style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; white-space: nowrap; margin-right: 15px; display: flex; align-items: center; gap: 6px; z-index: 2; box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);">
        <i class="fa-solid fa-bullhorn fa-bounce"></i> SUMMARY SHIFT
    </div>
    <div class="dtc-ticker-track" style="white-space: nowrap; overflow: hidden; flex-grow: 1; position: relative;" title="Hover untuk pause running text">
        <div class="dtc-ticker-content" id="dtc-summary-ticker-text" style="display: inline-block; padding-left: 100%; animation: dtc-marquee 28s linear infinite; font-size: 12px; color: #cbd5e1; font-weight: 600;">
            <span style="color: #94a3b8;"><i class="fa-solid fa-circle-notch fa-spin"></i> Memuat ringkasan data shift hari ini...</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table id="dtc-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Month</th>
                    <th>Line & Section</th>
                    <th>Model Name</th>
                    <th>Item Check & Process</th>
                    <th>Specification</th>
                    <th>Out of Spec</th>
                    <th>Operator</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add DTC -->
<div id="modal-add-dtc" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center;">
    <div class="card" style="width: 600px; max-width: 90%; max-height: 90vh; overflow-x: hidden; overflow-y: auto; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.8);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 15px 20px;">
            <h3 style="margin: 0; font-size: 16px; color: #f8fafc; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-circle-plus" style="color: #60a5fa;"></i> Add New DTC Parameters
            </h3>
            <i class="fa-solid fa-times" id="btn-close-modal" style="cursor: pointer; font-size: 18px; color: #94a3b8;"></i>
        </div>
        <div class="card-body" style="padding: 20px;">
            <form id="form-add-dtc" enctype="multipart/form-data">
                <div style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 8px; padding: 10px 14px; margin-bottom: 18px; font-size: 12px; color: #93c5fd; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-circle-info" style="font-size: 14px; color: #60a5fa;"></i>
                    <span>DTC Parameter akan dibuat secara otomatis untuk <strong>Bulan Ini (<?php echo date('M Y'); ?>)</strong> berdasarkan Line & Section yang dipilih.</span>
                </div>

                <h4 style="margin-bottom: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; font-size: 13px; font-weight: 700;">Selection & Location</h4>
                
                <!-- Target Month (Auto Locked Current Month) -->
                <input type="hidden" name="target_month" value="<?php echo date('Y-m'); ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; color: #94a3b8; font-size: 12px; font-weight: 600;">Line</label>
                        <select id="add_dtc_line" name="line_name" required style="width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: rgba(15,23,42,0.8); color: white; font-size: 13px;">
                            <option value="">-- Select Line --</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; color: #94a3b8; font-size: 12px; font-weight: 600;">Section</label>
                        <select id="add_dtc_section" name="section_name" required style="width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: rgba(15,23,42,0.8); color: white; font-size: 13px;">
                            <option value="">-- Select Section --</option>
                        </select>
                    </div>
                </div>

                <h4 style="margin-bottom: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; font-size: 13px; font-weight: 700;">Reference Image (Optional)</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 6px; color: #94a3b8; font-size: 12px; font-weight: 600;">Upload Reference Image for New DTC</label>
                    <input type="file" name="reference_image" accept="image/*" id="add-ref-image-input" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: rgba(15,23,42,0.8); color: white; font-size: 12px;">
                    <div id="add-ref-image-preview" style="margin-top: 8px; display: none;">
                        <img id="add-ref-image-thumb" src="" alt="Preview" style="max-width: 120px; max-height: 90px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.15); object-fit: cover;">
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <button type="button" id="btn-cancel-add" class="btn-rich-secondary">Batal</button>
                    <button type="submit" id="btn-save-dtc" class="btn-rich-primary" style="background: #3b82f6; color: white; font-weight: 600;"><i class="fa-solid fa-floppy-disk"></i> Generate & Save DTC</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Modal Add Running Model -->
<div id="modal-add-running-model" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; justify-content: center; align-items: center;">
    <div class="card" style="width: 480px; max-width: 90%;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px;"><i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Add Running Model</h3>
            <i class="fa-solid fa-times" id="btn-close-rm-modal" style="cursor: pointer; font-size: 18px;"></i>
        </div>
        <div class="card-body">
            <form id="form-add-running-model">
                <input type="hidden" name="target_month" value="<?php echo date('Y-m'); ?>">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 12px;">Line Name</label>
                        <select id="rm_line_select" name="line_name" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white;">
                            <option value="">-- Select Line --</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 12px;">Section Name</label>
                        <select id="rm_section_select" name="section_name" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white;">
                            <option value="">-- Select Section --</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 12px;">Model Name</label>
                        <select id="rm_model_select" name="model_name" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white;">
                            <option value="">-- Select Model --</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" id="btn-cancel-rm" class="btn-rich-secondary">Cancel</button>
                    <button type="submit" id="btn-save-rm" class="btn-rich-primary"><i class="fa-solid fa-floppy-disk"></i> Save Running Model</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.userSectionName = <?= json_encode($_SESSION['section_name'] ?? '') ?>;
    window.userLineName = <?= json_encode($_SESSION['line_name'] ?? '') ?>;
    window.userRole = <?= json_encode($_SESSION['role'] ?? '') ?>;
    window.currentIsAdmin = <?= json_encode(isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') ?>;
</script>

<?php require_once __DIR__ . '/modal_bulk_input.php'; ?>
<?php require_once __DIR__ . '/modal_oos_update.php'; ?>


