<?php
// dtc_oos_summary.php
// Loaded via index.php?page=oos_summary
?>

<style>
    .val-danger {
        color: #f87171;
        font-weight: 800;
        font-size: 1.05em;
    }
    .val-success {
        color: #34d399;
        font-weight: 700;
    }
    
    table.dataTable tbody tr {
        background-color: transparent !important;
        color: #f8fafc;
    }
    table.dataTable tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    
    #oos-table th, #oos-table td {
        vertical-align: middle;
        text-align: center;
        white-space: nowrap;
        font-size: 12px;
    }
    #oos-table td:nth-child(5), #oos-table td:nth-child(6) { /* Process & Item Check */
        text-align: left;
        white-space: normal;
    }
    
    .oos-stat-card {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        backdrop-filter: blur(10px);
        transition: all 0.2s ease;
    }
    .oos-stat-card:hover {
        background: rgba(15, 23, 42, 0.85);
        border-color: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }
    .btn-update-oos-row {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.4);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-update-oos-row:hover {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
    }
</style>

<!-- Top Statistics Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="oos-stat-card" style="border-left: 4px solid #ef4444;">
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Out of Spec</div>
            <div id="stat-total-oos" style="font-size: 24px; font-weight: 800; color: #f87171; margin-top: 4px;">0</div>
        </div>
        <div style="width: 42px; height: 42px; border-radius: 50%; background: rgba(239,68,68,0.15); display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; font-size: 18px;"></i>
        </div>
    </div>
    
    <div class="oos-stat-card" style="border-left: 4px solid #f59e0b;">
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Below LSL</div>
            <div id="stat-below-lsl" style="font-size: 24px; font-weight: 800; color: #fbbf24; margin-top: 4px;">0</div>
        </div>
        <div style="width: 42px; height: 42px; border-radius: 50%; background: rgba(245,158,11,0.15); display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-arrow-down" style="color: #f59e0b; font-size: 18px;"></i>
        </div>
    </div>

    <div class="oos-stat-card" style="border-left: 4px solid #38bdf8;">
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Above USL</div>
            <div id="stat-above-usl" style="font-size: 24px; font-weight: 800; color: #38bdf8; margin-top: 4px;">0</div>
        </div>
        <div style="width: 42px; height: 42px; border-radius: 50%; background: rgba(56,189,248,0.15); display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-arrow-up" style="color: #38bdf8; font-size: 18px;"></i>
        </div>
    </div>

    <div class="oos-stat-card" style="border-left: 4px solid #ec4899;">
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Qualitative NG</div>
            <div id="stat-qualitative-ng" style="font-size: 24px; font-weight: 800; color: #f472b6; margin-top: 4px;">0</div>
        </div>
        <div style="width: 42px; height: 42px; border-radius: 50%; background: rgba(236,72,153,0.15); display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-circle-xmark" style="color: #ec4899; font-size: 18px;"></i>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text-light); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Out of Spec (OOS) Tracker
            </h3>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select id="oos_filter_line" class="form-control" style="width: auto; background: rgba(15,23,42,0.8); color: white; border: 1px solid rgba(255,255,255,0.1); font-size: 12px; padding: 5px 10px; border-radius: 6px;">
                <option value="">All Lines</option>
            </select>
            <select id="oos_filter_section" class="form-control" style="width: auto; background: rgba(15,23,42,0.8); color: white; border: 1px solid rgba(255,255,255,0.1); font-size: 12px; padding: 5px 10px; border-radius: 6px;">
                <option value="">All Sections</option>
            </select>
            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="oos_month" style="margin: 0; font-size: 12px; color: var(--text-muted); font-weight: 600;">Bulan:</label>
                <input type="month" id="oos_month" class="form-control" style="width: auto; background: rgba(15,23,42,0.8); color: white; border: 1px solid rgba(255,255,255,0.1); font-size: 12px; padding: 4px 8px; border-radius: 6px;">
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 20px;">
        <div class="table-responsive">
            <table id="oos-table" class="display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Model Name</th>
                        <th>Line</th>
                        <th>Section</th>
                        <th>Process</th>
                        <th>Item Check</th>
                        <th>Specification</th>
                        <th>Min / Max</th>
                        <th>Status OOS</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="oos-tbody">
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Quick Update OOS Measurements -->
<div id="modal-quick-update-oos" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center; padding: 15px;">
    <div class="card" style="width: 650px; max-width: 95%; max-height: 90vh; overflow-y: auto; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 20px 40px rgba(0,0,0,0.8);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 14px 18px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #f8fafc; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-pen-to-square" style="color: #60a5fa;"></i> Update Pengukuran Out of Spec
            </h3>
            <i class="fa-solid fa-times" id="btn-close-oos-modal" style="cursor: pointer; font-size: 18px; color: #94a3b8;"></i>
        </div>
        <div class="card-body" style="padding: 20px;">
            <form id="form-quick-update-oos">
                <input type="hidden" id="oos_edit_session_id" name="session_id">
                
                <div style="background: rgba(30,41,59,0.6); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                        <div><span style="color: #94a3b8;">Model:</span> <strong id="oos-info-model" style="color: #f8fafc;">-</strong></div>
                        <div><span style="color: #94a3b8;">Tanggal:</span> <strong id="oos-info-date" style="color: #38bdf8;">-</strong></div>
                        <div><span style="color: #94a3b8;">Line & Section:</span> <strong id="oos-info-linesec" style="color: #f8fafc;">-</strong></div>
                        <div><span style="color: #94a3b8;">Spesifikasi:</span> <strong id="oos-info-spec" style="color: #fbbf24;">-</strong></div>
                        <div style="grid-column: span 2;"><span style="color: #94a3b8;">Item Check:</span> <strong id="oos-info-item" style="color: #60a5fa;">-</strong></div>
                    </div>
                </div>

                <h4 style="font-size: 13px; font-weight: 700; color: #f8fafc; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-list-ol" style="color: #38bdf8;"></i> Sampel Pengukuran (Sample Values)
                </h4>
                
                <div id="oos-samples-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;">
                    <!-- Sample input fields rendered via JS -->
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #94a3b8;">Catatan / Perbaikan (Remarks):</label>
                    <textarea id="oos_edit_remarks" name="remarks" rows="2" placeholder="Masukkan catatan atau tindakan perbaikan..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; font-size: 12px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" id="btn-cancel-oos-modal" class="btn-rich-secondary" style="padding: 8px 16px; font-size: 12px;">Batal</button>
                    <button type="submit" id="btn-save-oos-update" class="btn-rich-primary" style="padding: 8px 18px; font-size: 12px;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
