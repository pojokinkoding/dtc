<!-- Modal Bulk Input Pengukuran Running Model (Extra Large Fullscreen) -->
<div id="modal-bulk-input" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center; padding: 10px;">
    <div class="card" style="width: 98vw; max-width: 1900px; height: 95vh; display: flex; flex-direction: column; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; box-shadow: 0 25px 60px rgba(0,0,0,0.9); overflow: hidden;">
        
        <!-- Ultra-Slim Header Bar (Minimal Space Usage + Alarm System) -->
        <div style="background: rgba(15,23,42,0.98); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 6px 16px; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-weight: 700; color: #f8fafc; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-list-check" style="color: #60a5fa;"></i> Bulk Input Pengukuran
                </span>
                <span style="color: #60a5fa; background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.3); padding: 1px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                    <i class="fa-regular fa-calendar-days"></i> <?php echo ((int)date('H') < 7) ? date('d M Y', strtotime('-1 day')) : date('d M Y'); ?>
                </span>
                <span style="color: #94a3b8; font-size: 11px;">
                    <i class="fa-solid fa-user" style="color: #60a5fa; margin-right: 2px;"></i> Operator: <strong style="color: #f8fafc;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Operator'); ?></strong>
                </span>
                <span id="bulk-alarm-badge" style="font-size: 10px; font-weight: 700; color: #f87171; background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.4); padding: 1px 10px; border-radius: 10px; display: none; cursor: pointer;" title="Klik untuk cek slot belum terisi">
                    <i class="fa-solid fa-bell fa-bounce"></i> <span id="unfilled-count-text">0</span> Slot Belum Diisi!
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 10px; color: #34d399; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); padding: 2px 8px; border-radius: 4px; font-weight: 600;" title="Form diperbarui otomatis setiap 1 menit">
                    <i class="fa-solid fa-arrows-rotate fa-spin"></i> Auto 1m
                </span>
                <button type="button" id="btn-toggle-bulk-mute" class="btn-rich-warning" style="height: 26px; padding: 0 10px; font-size: 10px; border-radius: 4px; background: rgba(239,68,68,0.25); color: #f87171; border: 1px solid rgba(239,68,68,0.4); font-weight: 600; cursor: pointer;" title="Mute / Unmute Suara Alarm">
                    <i class="fa-solid fa-volume-high" id="mute-icon"></i> <span id="mute-btn-text">Mute Alarm</span>
                </button>
                <button type="button" id="btn-reload-bulk-data" class="btn-rich-secondary" style="height: 26px; padding: 0 10px; font-size: 10px; border-radius: 4px;" title="Reload Form Manual">
                    <i class="fa-solid fa-rotate-right"></i> Reload
                </button>
                <i class="fa-solid fa-times" id="btn-close-bulk-modal" style="cursor: pointer; font-size: 18px; color: #94a3b8; padding: 2px 6px; transition: color 0.2s;" title="Tutup Modal"></i>
            </div>
        </div>

        <!-- Body / Scrollable Form Container -->
        <div id="bulk-input-body" class="card-body" style="padding: 20px; overflow-y: auto; flex-grow: 1; background: rgba(15,23,42,0.4);">
            <div id="bulk-loading-state" style="display: none; text-align: center; padding: 60px 20px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 36px; color: #60a5fa; margin-bottom: 15px;"></i>
                <p style="color: #94a3b8; font-size: 14px;">Memuat form pengukuran untuk model...</p>
            </div>
            
            <div id="bulk-empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fa-solid fa-cubes" style="font-size: 48px; color: #475569; margin-bottom: 15px;"></i>
                <h4 style="color: #cbd5e1; margin-bottom: 5px;">Pilih Running Model untuk Mengisi Pengukuran</h4>
                <p style="color: #64748b; font-size: 13px; max-width: 500px; margin: 0 auto;">
                    Silakan pilih model aktif dari dropdown di atas atau klik tombol Bulk Input Pengukuran.
                </p>
            </div>

            <form id="form-bulk-save" style="display: none;">
                <div id="bulk-items-container"></div>
            </form>
        </div>

        <!-- Footer -->
        <div class="card-footer" style="background: rgba(15,23,42,0.95); border-top: 1px solid rgba(255,255,255,0.1); padding: 15px 25px; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
            <div id="bulk-form-summary" style="font-size: 13px; color: #94a3b8;">
                Total Item Check: <strong id="bulk-total-count" style="color: #f8fafc;">0</strong> | Terisi: <strong id="bulk-filled-count" style="color: #34d399;">0</strong>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="button" id="btn-cancel-bulk-modal" class="btn-rich-secondary" style="padding: 10px 20px; font-size: 13px;">Batal</button>
                <button type="button" id="btn-submit-bulk-save" class="btn-rich-primary" style="padding: 10px 24px; font-size: 14px; font-weight: 700; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 12px rgba(59,130,246,0.3);">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Semua Data Pengukuran
                </button>
            </div>
        </div>
    </div>
</div>
