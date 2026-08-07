<?php
// dtc_settings.php
// Loaded via index.php?page=settings
require_once 'config/config.php';
$conn = getDBConnection();
$stmt = $conn->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key IN ('time_matrix_labels_REF 01', 'time_matrix_labels_REF 02')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $val = is_resource($row['setting_value']) ? stream_get_contents($row['setting_value']) : $row['setting_value'];
    $settings[$row['setting_key']] = json_decode($val, true);
}

$ref01_labels = $settings['time_matrix_labels_REF 01'] ?? ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
$ref02_labels = $settings['time_matrix_labels_REF 02'] ?? ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
?>
<div class="content-header">
    <div class="header-title">
        <h1 style="margin-bottom: 5px;"><i class="fa-solid fa-gear"></i> System Settings</h1>
        <p style="margin: 0; color: var(--text-muted);">Konfigurasi aplikasi Digital Time Check.</p>
    </div>
</div>

<div class="card" style="margin-top: 20px; max-width: 900px;">
    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
        <span><i class="fa-regular fa-clock"></i> Time Check Matrix Configuration</span>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 5px; font-weight: normal;">
            Atur label jam yang akan digunakan di form input pengukuran Time Check dan Tracker.
            Jumlah slot waktu dapat diatur secara dinamis per Line Name.
        </p>
    </div>
    <div class="card-body" style="padding: 20px;">
        <form id="form-settings-time">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- REF 01 -->
                <div style="background: rgba(0,0,0,0.1); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                    <h4 style="margin-top: 0; color: var(--accent); margin-bottom: 15px;">Line: REF 01</h4>
                    <div id="container_ref01" style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                        <?php foreach($ref01_labels as $idx => $lbl): ?>
                        <div class="slot-item" style="display: flex; gap: 10px; align-items: center;">
                            <span style="font-size: 11px; color: var(--text-muted); min-width: 30px;">S<?= $idx+1 ?></span>
                            <input type="text" name="ref01_labels[]" value="<?= htmlspecialchars($lbl) ?>" style="flex-grow: 1; padding: 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.5); color: white; font-size: 13px;">
                            <button type="button" class="btn-remove-slot" style="background: transparent; border: none; color: #ef4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add-slot" data-target="container_ref01" data-name="ref01_labels[]" style="margin-top: 15px; background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.3); padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; width: 100%;">
                        <i class="fa-solid fa-plus"></i> Add Slot for REF 01
                    </button>
                </div>
                
                <!-- REF 02 -->
                <div style="background: rgba(0,0,0,0.1); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                    <h4 style="margin-top: 0; color: var(--accent); margin-bottom: 15px;">Line: REF 02</h4>
                    <div id="container_ref02" style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                        <?php foreach($ref02_labels as $idx => $lbl): ?>
                        <div class="slot-item" style="display: flex; gap: 10px; align-items: center;">
                            <span style="font-size: 11px; color: var(--text-muted); min-width: 30px;">S<?= $idx+1 ?></span>
                            <input type="text" name="ref02_labels[]" value="<?= htmlspecialchars($lbl) ?>" style="flex-grow: 1; padding: 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.5); color: white; font-size: 13px;">
                            <button type="button" class="btn-remove-slot" style="background: transparent; border: none; color: #ef4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add-slot" data-target="container_ref02" data-name="ref02_labels[]" style="margin-top: 15px; background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.3); padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; width: 100%;">
                        <i class="fa-solid fa-plus"></i> Add Slot for REF 02
                    </button>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 30px;">
                <button type="submit" id="btn-save-settings" class="btn-primary" style="padding: 10px 24px;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Configurations
                </button>
            </div>
        </form>
    </div>
</div>
