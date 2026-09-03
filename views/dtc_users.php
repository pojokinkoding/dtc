<?php
// dtc_users.php
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/config.php';
$conn = getDBConnection();
ensureMasterLinesAndSectionsTables($conn);

$db_lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY sort_order ASC, line_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$spec_lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND TRIM(line_name) != ''")->fetchAll(PDO::FETCH_COLUMN);
$distinct_lines = array_unique(array_merge($db_lines, $spec_lines));
sort($distinct_lines);

$db_sections = $conn->query("SELECT DISTINCT section_name FROM dtc_master_sections WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY sort_order ASC, section_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$spec_sections = $conn->query("SELECT DISTINCT section_name FROM dtc_master_dtc_specs WHERE section_name IS NOT NULL AND TRIM(section_name) != ''")->fetchAll(PDO::FETCH_COLUMN);
$distinct_sections = array_unique(array_merge($db_sections, $spec_sections));
sort($distinct_sections);
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    /* DataTables Dark Theme Overrides */
    table.dataTable, table.dataTable th, table.dataTable td {
        color: var(--text-light) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
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
</style>

<div class="content-header" style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px;">
    <button id="btn-add-user" class="btn-rich-primary">
        <i class="fa-solid fa-plus"></i> Add New User
    </button>
</div>

<div class="card">
    <div class="card-body">
        <table id="users-table" class="display responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Line</th>
                    <th>Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add/Edit -->
<div id="modal-user" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="card" style="background-color: var(--bg-card); padding: 20px; border-radius: 8px; width: 90%; max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 id="modal-title" style="margin: 0;">Add User</h2>
            <button id="btn-close-modal" style="background: none; border: none; color: var(--text-light); font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        
        <form id="form-user" enctype="multipart/form-data">
            <input type="hidden" id="user_id" name="user_id" value="">
            
            <div style="display: flex; gap: 20px;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: flex-start; margin-top: 10px;">
                    <div id="profile_pic_preview" style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-dark); border: 2px solid #334155; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                        <i class="fa-solid fa-user" id="profile_pic_icon" style="color: #64748b; font-size: 40px;"></i>
                        <img id="profile_pic_img" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    </div>
                    <label for="profile_picture" style="cursor: pointer; background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 4px; font-size: 11px; color: var(--text-light); border: 1px solid rgba(255,255,255,0.2);">Change Photo</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display: none;">
                </div>
                
                <div style="flex-grow: 1;">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>
            
            <div class="form-group">
                <label>Role / Authority</label>
                <select id="role" name="role" class="form-control" required>
                    <option value="Operator">Operator</option>
                    <option value="Foreman">Foreman</option>
                    <option value="Supervisor">Supervisor (Monitoring Tracker)</option>
                    <option value="Management">Management (Office Pusat)</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <div class="form-group" id="supervisor-sections-group" style="display: none; background: rgba(15, 23, 42, 0.6); padding: 12px; border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.3); margin-bottom: 15px;">
                <label style="color: #60a5fa; font-weight: 600; margin-bottom: 8px;">
                    <i class="fa-solid fa-layer-group"></i> Dynamic Section Monitoring (Supervisor)
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; max-height: 150px; overflow-y: auto; padding-right: 5px;">
                    <?php foreach ($distinct_sections as $sec): ?>
                        <label style="font-size: 11px; font-weight: normal; color: #cbd5e1; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="allowed_sections[]" value="<?= htmlspecialchars($sec) ?>" class="cb-supervisor-section">
                            <?= htmlspecialchars($sec) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 10px;">Check all sections this Supervisor is authorized to monitor missing data for.</small>
            </div>
            
            <div class="form-group" id="single-line-group">
                <label>Access: Line</label>
                <select id="line_name" name="line_name" class="form-control">
                    <option value="">-- All Lines --</option>
                    <?php foreach ($distinct_lines as $line): ?>
                        <option value="<?= htmlspecialchars($line) ?>"><?= htmlspecialchars($line) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted);">Leave empty to allow all lines.</small>
            </div>

            <div class="form-group" id="single-section-group">
                <label>Access: Section</label>
                <select id="section_name" name="section_name" class="form-control">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($distinct_sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted);">Leave empty to allow all sections.</small>
            </div>
            
            <div class="form-group">
                <label id="password-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <small id="password-hint" style="color: var(--text-muted); display: none;">Leave blank to keep current password.</small>
            </div>
            </div> <!-- End flex-grow column -->
            </div> <!-- End flex row -->
            
            <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="btn-cancel-modal" class="btn-rich-secondary">Cancel</button>
                <button type="submit" id="btn-save-user" class="btn-rich-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save User
                </button>
            </div>
        </form>
    </div>
</div>
