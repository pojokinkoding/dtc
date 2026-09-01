<?php /* docs.php - Documentation Page (Comprehensive Latest Documentation) */ ?>

<style>
    .docs-section {
        margin-bottom: 24px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .docs-section:hover {
        box-shadow: 0 8px 30px rgba(0, 243, 255, 0.12);
    }
    .docs-section h2 {
        font-size: clamp(18px, 1.6vw, 22px);
        color: var(--accent);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .docs-section h3 {
        font-size: clamp(14px, 1.2vw, 16px);
        color: var(--text-light);
        margin: 16px 0 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .docs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: clamp(12px, 1.05vw, 14px);
        margin-top: 10px;
    }
    .docs-table th {
        background: rgba(15,23,42,0.9);
        color: var(--text-muted);
        padding: 9px 12px;
        text-align: left;
        border-bottom: 2px solid rgba(255,255,255,0.1);
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .docs-table td {
        padding: 9px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: top;
        line-height: 1.6;
    }
    .docs-table tr:hover td {
        background: rgba(255,255,255,0.03);
    }
    .badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .badge-green { background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.4); }
    .badge-blue  { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.4); }
    .badge-red   { background: rgba(239,68,68,0.2);  color: #f87171; border: 1px solid rgba(239,68,68,0.4); }
    .badge-purple{ background: rgba(139,92,246,0.2); color: #a78bfa; border: 1px solid rgba(139,92,246,0.4); }
    .badge-amber { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.4); }
    .badge-cyan  { background: rgba(0,243,255,0.15); color: #00f3ff; border: 1px solid rgba(0,243,255,0.3); }

    .note-box {
        background: rgba(59,130,246,0.08);
        border-left: 4px solid #3b82f6;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 12px;
        color: #93c5fd;
        margin-top: 12px;
        line-height: 1.6;
    }
    .warning-box {
        background: rgba(245,158,11,0.08);
        border-left: 4px solid #f59e0b;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 12px;
        color: #fcd34d;
        margin-top: 12px;
        line-height: 1.6;
    }
    .success-box {
        background: rgba(16,185,129,0.08);
        border-left: 4px solid #10b981;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 12px;
        color: #6ee7b7;
        margin-top: 12px;
        line-height: 1.6;
    }
    .step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        font-weight: bold;
        margin-right: 8px;
        font-size: 11px;
        flex-shrink: 0;
    }
    .feature-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 12.5px;
        color: var(--text-light);
        line-height: 1.6;
    }
</style>

<!-- Page Title Header -->
<div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--primary); padding: 16px 20px;">
    <div class="card-header" style="padding: 0 0 8px 0; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 16px; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
        <span><i class="fa-solid fa-book-open" style="color: var(--primary); margin-right: 8px;"></i> Panduan Operasional Digital Time Check (DTC Documentation)</span>
        <span class="badge badge-cyan"><i class="fa-solid fa-circle-check"></i> Versi Terbaru 2026</span>
    </div>
    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.7; margin-top: 8px;">
        Dokumentasi resmi dan panduan penggunaan sistem <strong>System Digital Time Check (DTC)</strong>. Halaman ini menjelaskan seluruh alur kerja mulai dari Master Spec (Multiple Checkpoints), Running Model, Bulk Input Pengukuran Matriks, Quick Update OOS, Monitoring Data, hingga Skedul Auto-Close Harian.
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

    <!-- LEFT COLUMN -->
    <div>
        <!-- 1. TAMPILAN & AKSES PENGGUNA -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-play" style="color:#38bdf8;"></i> 1. Tampilan & Hak Akses Pengguna</div>
            <div style="padding: 16px;">
                <h3><i class="fa-solid fa-right-to-bracket" style="color:var(--primary);"></i> Login Page & Branding LG</h3>
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Halaman login menggunakan logo resmi LG dengan latar belakang <i>Dot Matrix Grid Pattern</i> interaktif beraksen pencahayaan neon, serta dilengkapi tombol <strong>Show / Hide Password</strong> untuk kemudahan input.
                </p>

                <h3><i class="fa-solid fa-users" style="color:var(--primary);"></i> Matriks Hak Akses & Multi-Section</h3>
                <table class="docs-table">
                    <thead><tr><th>Role</th><th>Keterangan Hak Akses</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-purple">Admin</span></td><td>Akses penuh ke Master Spec, Manajemen User, Pengaturan Jam Matriks, Edit Sesi Tertutup, dan Database Migration.</td></tr>
                        <tr><td><span class="badge badge-blue">Operator / Foreman</span></td><td>Mengisi data pengukuran harian pada jam aktif, input bulk model, dan mengaktifkan running model.</td></tr>
                        <tr><td><span class="badge badge-green">Supervisor</span></td><td>Memonitor data real-time, melihat grafik SPC, export Excel/PDF, serta akses multi-section (contoh: <code>PRE CASE,PU CASE</code>).</td></tr>
                    </tbody>
                </table>

                <div class="note-box">
                    <i class="fa-solid fa-circle-info"></i> <strong>Multi-Section Supervisor:</strong> Supervisor dapat dikonfigurasi memiliki akses ke beberapa section sekaligus melalui kolom <code>allowed_sections</code> di User Management.
                </div>
            </div>
        </div>

        <!-- 2. MASTER SPEC & MULTIPLE CHECKPOINTS -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-gears" style="color:#a855f7;"></i> 2. Master Spec & Multiple Checkpoints</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Master Spec adalah pusat konfigurasi spesifikasi parameter DTC. Kini mendukung <strong>Multiple Checkpoints Template</strong> per item check:
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div class="feature-item"><span class="step-number">1</span> <span><strong>Pembuatan Template Checkpoint:</strong> Pada form Master Spec, pengguna dapat menambahkan beberapa checkpoint sekaligus (nama checkpoint, tipe Kualitatif/Kuantitatif, LSL, Target, USL, dan gambar acuan).</span></div>
                    <div class="feature-item"><span class="step-number">2</span> <span><strong>Penyalinan Otomatis (Auto-Clone):</strong> Saat Running Model diaktifkan di DTC List, sistem secara otomatis menyalin template dari tabel <code>dtc_master_spec_checkpoints</code> ke parameter bulanan di tabel <code>dtc_checkpoints</code>.</span></div>
                    <div class="feature-item"><span class="step-number">3</span> <span><strong>Reference Standard Image:</strong> Setiap checkpoint dapat memiliki gambar standar visual yang diunggah dan dapat di-zoom saat inspeksi.</span></div>
                </div>

                <div class="success-box">
                    <i class="fa-solid fa-check-double"></i> <strong>Efisiensi:</strong> Tidak perlu membuat ulang checkpoint setiap bulan. Cukup atur sekali di Master Spec, dan checkpoint akan otomatis aktif saat model dijalankan.
                </div>
            </div>
        </div>

        <!-- 3. BULK INPUT PENGUKURAN MATRIX -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-list-check" style="color:#00f3ff;"></i> 3. Bulk Input Pengukuran Matriks (Fullscreen)</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Fitur <strong>Bulk Input Pengukuran</strong> memudahkan operator mengisi seluruh item check pada running model aktif dalam satu tampilan tabel besar terintegrasi:
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div class="feature-item">&bull; <strong>Auto-Detect Active Slot:</strong> Sistem otomatis menandai slot jam aktif saat ini (<span class="badge badge-blue">● AKTIF</span>) dan mengunci slot jam masa depan.</div>
                    <div class="feature-item">&bull; <strong>Alarm Slot Belum Diisi:</strong> Menghitung jumlah slot yang jatuh tempo namun belum terisi data serta memicu peringatan audio & visual.</div>
                    <div class="feature-item">&bull; <strong>Keyboard Navigation:</strong> Mendukung navigasi cepat menggunakan tombol panah (Arrow Keys), Tab, dan Enter antar input pengukuran.</div>
                    <div class="feature-item">&bull; <strong>Global Save Shortcut:</strong> Tekan <kbd style="background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px; border:1px solid rgba(255,255,255,0.2);">Ctrl + S</kbd> untuk menyimpan seluruh data pengukuran secara instan.</div>
                </div>

                <div class="note-box">
                    <i class="fa-solid fa-expand"></i> <strong>Layar Penuh Terisolasi:</strong> Modal Bulk Input menyesuaikan tinggi monitor (100% viewport) dengan header & tombol simpan yang selalu terlihat tanpa terpotong.
                </div>
            </div>
        </div>

        <!-- 4. QUICK UPDATE OUT OF SPEC (OOS) -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> 4. Quick Update Out of Spec (OOS)</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Jika terdapat data pengukuran yang menyimpang dari spesifikasi (Out of Spec), sistem memberikan akses koreksi cepat:
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div class="feature-item">&bull; <strong>Direct Badge Click:</strong> Klik pada badge total OOS merah (<span class="badge badge-red">X OOS</span>) di halaman DTC List atau History untuk membuka pop-up koreksi data.</div>
                    <div class="feature-item">&bull; <strong>Live Color Validation:</strong> Input sampel otomatis berubah warna hijau jika masuk range spec atau merah jika masih di luar batas toleransi (LSL/USL).</div>
                    <div class="feature-item">&bull; <strong>Remarks & Action Plan:</strong> Input catatan perbaikan terisolasi per checkpoint (format: <code>[Checkpoint]: Catatan</code>).</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
        <!-- 5. ATURAN PENGISIAN & SHIFT LOCK LOGIC -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-user-clock" style="color:#f59e0b;"></i> 5. Aturan Pengisian & Shift Lock Logic</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Sistem membagi jadwal harian ke dalam slot jam kerja standar (Shift 1 & Shift 2 Night Shift) dengan aturan penguncian otomatis:
                </p>
                
                <table class="docs-table">
                    <thead><tr><th>Kategori Slot</th><th>Ketentuan Pengisian</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-amber">Shift 2 Night Shift</span></td>
                            <td>Jam pengisian <code>&lt; 07:00</code> (seperti <b>02:30</b> & <b>04:30</b>) termasuk ke dalam shift 2 tanggal kerja manufaktur hari sebelumnya.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-red">Future Slot Lock</span></td>
                            <td>Slot jam pengisian di masa depan (belum tiba waktunya) **terkunci rapat (readonly)** untuk menjaga integritas data riil.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-blue">Historical Data Lock</span></td>
                            <td>Data tanggal kerja yang telah lewat / ter-close hanya dapat diedit oleh role Admin. Operator terkunci otomatis.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="warning-box">
                    <i class="fa-solid fa-clock"></i> <strong>Aturan Slot Masa Depan:</strong> Jika jam saat ini masih 01:00 AM, maka slot jam 02:30 AM dan 04:30 AM berstatus <code>readonly</code> dengan tooltip <i>"Belum masuk waktu pengisian"</i>.
                </div>
            </div>
        </div>

        <!-- 6. DATA MONITORING & PERFORMANCE REPORT -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-chart-pie" style="color:#10b981;"></i> 6. Data Monitoring & Station Performance</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Menu <strong>Data Monitoring (Missing Data)</strong> menyajikan rekapitulasi kepatuhan inspeksi:
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div class="feature-item">&bull; <strong>Missing Data Detection:</strong> Mengidentifikasi slot inspeksi yang terlewat atau tidak diisi oleh operator pada setiap shift.</div>
                    <div class="feature-item">&bull; <strong>Monthly Performance Preview:</strong> Modal performa bulanan stasiun kerja yang menghitung persentase keterisian dan kepatuhan.</div>
                    <div class="feature-item">&bull; <strong>Export Excel Report:</strong> Mendukung pengunduhan laporan performa stasiun kerja bulanan dalam format Excel (.xlsx).</div>
                </div>
            </div>
        </div>

        <!-- 7. AUTO CLOSE SHIFT CRON (06:40 AM) -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-clock-rotate-left" style="color:#ec4899;"></i> 7. Auto Close Shift & Reset Cron (06:40 AM)</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Sistem menjalankan scheduler otomatis <code>cron_close_shift.php</code> setiap hari pada pukul <strong>06:40 AM WIB</strong>:
                </p>

                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div class="feature-item">&bull; <strong>Auto Close Session:</strong> Mengunci otomatis seluruh sesi pengukuran (<code>is_closed = 1</code>) hari sebelumnya agar data historis aman.</div>
                    <div class="feature-item">&bull; <strong>Reset Running Models:</strong> Membersihkan daftar running model hari sebelumnya sehingga line dapat memilih model baru yang sedang running.</div>
                    <div class="feature-item">&bull; <strong>Execution Logging:</strong> Log waktu eksekusi cron dicatat secara otomatis di tabel <code>dtc_app_settings</code>.</div>
                </div>
            </div>
        </div>

        <!-- 8. DATABASE MIGRATION & BACKUP TOOLS -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-database" style="color:#38bdf8;"></i> 8. Script Migrasi Database & Backup Linux</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Tersedia script command line di folder <code>database/</code> untuk pemeliharaan server Linux tanpa risiko kehilangan data:
                </p>

                <table class="docs-table">
                    <thead><tr><th>Script</th><th>Command Eksekusi di Linux</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><strong>Backup DB</strong></td>
                            <td><code>./database/backup_db.sh dtc_v1 root</code> (Otomatis membuat file timestamp & backup.sql)</td>
                        </tr>
                        <tr>
                            <td><strong>Alter Migrasi</strong></td>
                            <td><code>./database/run_alter.sh dtc_v1 root</code> (Menambah tabel & kolom baru secara non-destruktif)</td>
                        </tr>
                    </tbody>
                </table>

                <div class="success-box">
                    <i class="fa-solid fa-shield-halved"></i> <strong>Panduan Lengkap:</strong> Langkah-langkah detail, perintah troubleshooting, dan verifikasi SQL dapat dibaca di file <code>MIGRATION_GUIDE.md</code>.
                </div>
            </div>
        </div>
    </div>

</div>
