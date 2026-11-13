<?php
// dtc_settings.php
// Loaded via index.php?page=settings
require_once 'config/config.php';
$conn = getDBConnection();
$stmt = $conn->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key IN ('time_matrix_labels_REF 01', 'time_matrix_labels_REF 02', 'bulk_auto_refresh_minutes')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $val = is_resource($row['setting_value']) ? stream_get_contents($row['setting_value']) : $row['setting_value'];
    if ($row['setting_key'] === 'bulk_auto_refresh_minutes') {
        $settings[$row['setting_key']] = (float)$val;
    } else {
        $settings[$row['setting_key']] = normalizeTimeLabels(json_decode($val, true));
    }
}

$ref01_labels = $settings['time_matrix_labels_REF 01'] ?? ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '00:30', '02:30'];
$ref02_labels = $settings['time_matrix_labels_REF 02'] ?? ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '00:30', '02:30'];
$bulk_auto_refresh_minutes = $settings['bulk_auto_refresh_minutes'] ?? 1;
if ($bulk_auto_refresh_minutes <= 0) $bulk_auto_refresh_minutes = 1;
$bulkRefreshPreviewLabel = ($bulk_auto_refresh_minutes < 1) ? (round($bulk_auto_refresh_minutes * 60) . 's') : (($bulk_auto_refresh_minutes == (int)$bulk_auto_refresh_minutes ? (int)$bulk_auto_refresh_minutes : $bulk_auto_refresh_minutes) . 'm');
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
            
            <!-- Bulk Input Auto Refresh Configuration -->
            <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.08);">
                <div style="margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #38bdf8; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Bulk Input Auto Refresh Interval
                    </h4>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 5px 0 0 0;">
                        Atur angka durasi interval refresh otomatis (auto refresh) untuk form modal Bulk Input Pengukuran (tampil sebagai badge <span style="color:#34d399; font-weight:700;">Auto Xm</span> di modal).
                    </p>
                </div>

                <div style="background: rgba(0,0,0,0.15); padding: 18px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 280px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; font-weight: 600; margin-bottom: 8px;">
                            Interval Durasi Auto Refresh:
                        </label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" step="any" min="0.2" max="60" id="bulk_auto_refresh_minutes" name="bulk_auto_refresh_minutes" 
                                   value="<?= htmlspecialchars($bulk_auto_refresh_minutes) ?>" 
                                   style="width: 100px; padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(59,130,246,0.4); background: rgba(15,23,42,0.8); color: #38bdf8; font-size: 15px; font-weight: 700; text-align: center;">
                            <span style="font-size: 13px; color: #94a3b8; font-weight: 600;">Menit</span>
                            <span id="preview-auto-refresh-badge" style="font-size: 11px; color: #34d399; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); padding: 4px 10px; border-radius: 6px; font-weight: 700; margin-left: 6px;">
                                <i class="fa-solid fa-arrows-rotate"></i> Preview Badge: Auto <?= htmlspecialchars($bulkRefreshPreviewLabel) ?>
                            </span>
                        </div>
                        <span style="font-size: 11px; color: #64748b; margin-top: 6px; display: block;">
                            * Contoh: isi <code>1</code> untuk 1 menit (Auto 1m), <code>2</code> untuk 2 menit (Auto 2m), <code>0.5</code> untuk 30 detik (Auto 30s).
                        </span>
                    </div>

                    <!-- Quick Presets -->
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Preset Cepat:</span>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn-refresh-preset" data-val="0.5" style="padding: 5px 12px; font-size: 11px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(15,23,42,0.5); color: #cbd5e1; cursor: pointer;">30 Detik</button>
                            <button type="button" class="btn-refresh-preset" data-val="1" style="padding: 5px 12px; font-size: 11px; border-radius: 4px; border: 1px solid rgba(59,130,246,0.4); background: rgba(59,130,246,0.15); color: #60a5fa; cursor: pointer; font-weight: 700;">1 Menit</button>
                            <button type="button" class="btn-refresh-preset" data-val="2" style="padding: 5px 12px; font-size: 11px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(15,23,42,0.5); color: #cbd5e1; cursor: pointer;">2 Menit</button>
                            <button type="button" class="btn-refresh-preset" data-val="3" style="padding: 5px 12px; font-size: 11px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(15,23,42,0.5); color: #cbd5e1; cursor: pointer;">3 Menit</button>
                            <button type="button" class="btn-refresh-preset" data-val="5" style="padding: 5px 12px; font-size: 11px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.12); background: rgba(15,23,42,0.5); color: #cbd5e1; cursor: pointer;">5 Menit</button>
                        </div>
                    </div>
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
