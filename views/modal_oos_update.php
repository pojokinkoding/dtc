<?php
// views/modal_oos_update.php
// Shared Modal for Direct Out of Spec Quick Update from OOS Badge Click
?>
<style>
    .swal2-container {
        z-index: 9999999 !important;
    }
</style>
<script>
    window.currentIsAdmin = <?= (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') ? 'true' : 'false' ?>;
</script>
<div id="modal-oos-param-update" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; padding: 15px;">
    <div class="card" style="width: 750px; max-width: 95%; max-height: 90vh; display: flex; flex-direction: column; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 20px 40px rgba(0,0,0,0.8);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 14px 18px; flex-shrink: 0;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #f8fafc; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Update Pengukuran Out of Spec
            </h3>
            <i class="fa-solid fa-times btn-close-oos-param-modal" style="cursor: pointer; font-size: 18px; color: #94a3b8;"></i>
        </div>
        <div class="card-body" style="padding: 20px; overflow-y: auto; flex: 1;">
            <form id="form-oos-param-update">
                <input type="hidden" id="oos_param_id">
                <input type="hidden" id="oos_param_month">

                <!-- Parameter Info Banner -->
                <div style="background: rgba(30,41,59,0.6); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                        <div><span style="color: #94a3b8;">Model Name:</span> <strong id="oos-banner-model" style="color: #f8fafc;">-</strong></div>
                        <div><span style="color: #94a3b8;">Line & Section:</span> <strong id="oos-banner-linesec" style="color: #38bdf8;">-</strong></div>
                        <div><span style="color: #94a3b8;">Item Check:</span> <strong id="oos-banner-item" style="color: #60a5fa;">-</strong></div>
                        <div><span style="color: #94a3b8;">Spesifikasi:</span> <strong id="oos-banner-spec" style="color: #fbbf24;">-</strong></div>
                    </div>
                </div>

                <div id="oos-sessions-loading" style="text-align: center; padding: 30px; color: #94a3b8;">
                    <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: #38bdf8; margin-bottom: 10px; display: block;"></i>
                    <span>Memuat sesi pengukuran Out of Spec...</span>
                </div>

                <div id="oos-sessions-empty" style="display: none; text-align: center; padding: 30px; color: #34d399;">
                    <i class="fa-solid fa-circle-check fa-2x" style="margin-bottom: 10px; display: block;"></i>
                    <span>Semua sampel pengukuran sudah sesuai dengan spesifikasi (0 OOS).</span>
                </div>

                <!-- OOS Sessions Container -->
                <div id="oos-sessions-container" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Dynamically populated via JS -->
                </div>
            </form>
        </div>
        <div class="card-footer" style="padding: 12px 18px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: flex-end; gap: 10px; background: rgba(15,23,42,0.9); flex-shrink: 0;">
            <button type="button" class="btn-rich-secondary btn-close-oos-param-modal" style="padding: 8px 16px; font-size: 12px;">Batal</button>
            <button type="button" id="btn-save-oos-param-modal" class="btn-rich-primary" style="padding: 8px 18px; font-size: 12px;">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</div>
