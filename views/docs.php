<?php /* docs.php - Documentation Page (Updated Latest Documentation) */ ?>

<style>
    .docs-section {
        margin-bottom: 24px;
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
        font-size: clamp(15px, 1.3vw, 18px);
        color: var(--text-light);
        margin: 16px 0 8px;
    }
    .docs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: clamp(13px, 1.1vw, 15px);
        margin-top: 10px;
    }
    .docs-table th {
        background: rgba(15,23,42,0.9);
        color: var(--text-muted);
        padding: 10px 14px;
        text-align: left;
        border-bottom: 2px solid rgba(255,255,255,0.1);
    }
    .docs-table td {
        padding: 10px 14px;
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
        padding: 12px 16px;
        font-size: 12px;
        color: #93c5fd;
        margin-top: 12px;
        line-height: 1.6;
    }
    .warning-box {
        background: rgba(245,158,11,0.08);
        border-left: 4px solid #f59e0b;
        border-radius: 6px;
        padding: 12px 16px;
        font-size: 12px;
        color: #fcd34d;
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
    }
</style>

<!-- Page Title Header -->
<div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--primary); padding: 16px 20px;">
    <div class="card-header" style="padding: 0 0 8px 0; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 16px; font-weight: 700;">
        <i class="fa-solid fa-book-open" style="color: var(--primary); margin-right: 6px;"></i> Panduan Operasional Sistem Digital Time Check (DTC Documentation)
    </div>
    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.7; margin-top: 8px;">
        Dokumentasi resmi dan panduan penggunaan sistem <strong>System Digital Time Check (DTC)</strong> versi terbaru. Halaman ini menjelaskan aturan pengisian data harian, integrasi checkpoint, validasi slot waktu otomatis, hingga skedul pembersihan & auto-close harian.
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

    <!-- LEFT COLUMN -->
    <div>
        <!-- 1. MEMULAI APLIKASI & INTERFACE -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-play" style="color:#38bdf8;"></i> 1. Tampilan & Antarmuka Aplikasi</div>
            <div style="padding: 16px;">
                <h3><i class="fa-solid fa-magnifying-glass-plus" style="color:var(--primary);"></i> Auto-Zoom Preset (65%)</h3>
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Sistem secara otomatis dikonfigurasi dengan skala tampilan <strong>Zoom 65%</strong> untuk memberikan pandangan yang luas, presisi, dan padat pada layar monitor produksi (Andon Display) tanpa mengorbankan kenyamanan membaca.
                </p>

                <h3><i class="fa-solid fa-palette" style="color:var(--primary);"></i> Tema & Mode Layar Penuh</h3>
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Di sudut kanan topbar terdapat tombol <strong>Theme Switcher</strong> (Ikon Bulan/Robot) untuk beralih antara tema Dark Standard dan Sci-Fi Robot. Tombol <strong>Full Screen</strong> tersedia untuk menampilkan dashboard di TV produksi secara optimal.
                </p>

                <h3><i class="fa-solid fa-bell" style="color:var(--primary);"></i> Notifikasi Dark-Themed (SweetAlert2)</h3>
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Seluruh notifikasi dan dialog konfirmasi sistem telah diperbarui menggunakan dialog modern berdesain dark-mode <strong>SweetAlert2 (`Swal.fire()`)</strong>, menggantikan dialog bawaan browser.
                </p>

                <h3><i class="fa-solid fa-users" style="color:var(--primary);"></i> Hak Akses Pengguna</h3>
                <table class="docs-table">
                    <thead><tr><th>Role</th><th>Keterangan Hak Akses</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-purple">Admin</span></td><td>Akses penuh ke Master Spec, User Management, Settings, Edit Data Historis, dan Re-open Session.</td></tr>
                        <tr><td><span class="badge badge-blue">Operator / Foreman</span></td><td>Mengisi data pengukuran harian pada jam aktif & mengelola running model.</td></tr>
                        <tr><td><span class="badge badge-green">Supervisor / Guest</span></td><td>Monitoring real-time, melihat grafik SPC, export data, dan analisis AI.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. SETUP MASTER DATA & RUNNING MODEL -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-gears" style="color:#a855f7;"></i> 2. Setup Master Spec & Running Model</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Master Spec menentukan parameter pengujian, sedangkan Running Model mengatur model yang aktif berjalan di line produksi pada shift berjalan.
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div style="margin-bottom: 8px;"><span class="step-number">1</span> <strong>Master Spec Setup (Admin)</strong>: Buat spesifikasi di menu <i>Master Spec</i> (Line, Section, Model Name, Measuring Item, LSL, Target, USL).</div>
                    <div style="margin-bottom: 8px;"><span class="step-number">2</span> <strong>Running Model Selection</strong>: Di menu <i>DTC List</i>, pilih model yang sedang aktif diproduksi di line/section.</div>
                    <div style="margin-bottom: 8px;"><span class="step-number">3</span> <strong>Automated Parameter Generation</strong>: Parameter bulanan (`target_month = YYYY-MM`) otomatis dibuat dari Master Spec saat model diaktifkan.</div>
                </div>

                <div class="note-box">
                    <i class="fa-solid fa-circle-info"></i> <strong>Informasi Running Model:</strong> Model yang tidak di-set sebagai <i>Running Model</i> akan otomatis disembunyikan dari daftar pengisian harian agar operator fokus pada model aktif.
                </div>
            </div>
        </div>

        <!-- 3. MATRIX QUALITATIVE & CHECKPOINT INTEGRATION -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-cubes" style="color:#00f3ff;"></i> 3. Integrasi Matrix Checkpoint (CTP / Visual)</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Halaman <strong>Matrix Qualitative / CTP</strong> menggunakan tata letak terintegrasi yang efisien per checkpoint:
                </p>
                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div style="margin-bottom: 8px;">&bull; <strong>Single Header Integrated Card</strong>: Informasi parameter (`Line`, `Section`, `Model`, `Process`, `Type`, `Month`) menyatu langsung di dalam <b>Card 1 (Active Checkpoint Information)</b>.</div>
                    <div style="margin-bottom: 8px;">&bull; <strong>Inline Checkpoint Tab Bar</strong>: Tombol <span class="badge badge-cyan"><i class="fa-solid fa-plus"></i> Add Check Point</span> dan <span class="badge badge-blue"><i class="fa-solid fa-arrow-left"></i> Back</span> diletakkan sejajar di baris tab checkpoint sebelah kanan.</div>
                    <div style="margin-bottom: 8px;">&bull; <strong>Reference Image Support</strong>: Setiap checkpoint mendukung gambar acuan standar visual yang bisa diunggah dan di-zoom.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
        <!-- 4. ATURAN PENGISIAN HARIAN & SHIFT LOGIC -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-user-clock" style="color:#f59e0b;"></i> 4. Aturan Pengisian & Shift Lock Logic</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Sistem mengenerate 10 slot jam pengisian per tanggal kerja (Shift 1 & Shift 2 Night Shift) dengan aturan penguncian ketat:
                </p>
                
                <table class="docs-table">
                    <thead><tr><th>Kategori Slot</th><th>Ketentuan Pengisian</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-amber">Shift 2 Night Shift</span></td>
                            <td>Jam pengisian `< 07:00` (seperti <b>02:30</b> & <b>04:30</b>) termasuk ke dalam shift 2 tanggal kerja sebelumnya.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-red">Future Slot Lock</span></td>
                            <td>Slot jam pengisian di masa depan (belum masuk waktunya) **terkunci rapat (readonly)** untuk siapapun, termasuk Admin.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-blue">Historical Data Lock</span></td>
                            <td>Data tanggal kerja yang telah lewat / di-close hanya dapat diubah oleh Admin. Operator terkunci otomatis.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="warning-box">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Aturan Slot Masa Depan:</strong> Jika jam nyata saat ini masih jam 01:00 AM, maka slot jam 02:30 AM dan 04:30 AM berwarna abu-abu gelap dan berstatus `readonly` dengan tooltip <i>"Belum masuk waktu pengisian"</i>.
                </div>
            </div>
        </div>

        <!-- 5. ANALISIS SPC & AI INSIGHT -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-chart-line" style="color:#10b981;"></i> 5. Analisis SPC & AI Prompt Insight</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Halaman detail kuantitatif menyajikan 6 panel grafik SPC lengkap beserta analisis kecerdasan buatan:
                </p>

                <div style="font-size:12px; color:var(--text-muted); line-height:1.7; background: rgba(15,23,42,0.6); padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); margin-top: 10px;">
                    <div style="margin-bottom: 8px;">
                        <strong style="color:var(--text-light);">1. AI Insight (Prompt)</strong><br>
                        Evaluasi otomatis kapabilitas proses berdasarkan indikator <strong>Cpk</strong>:<br>
                        &bull; <span style="color:#f87171">Unstable</span>: Cpk &lt; 1.0 (Variasi tinggi / bergeser dari target)<br>
                        &bull; <span style="color:#fbbf24">Needs Improvement</span>: 1.0 &le; Cpk &lt; 1.33 (Proses baik namun minim margin)<br>
                        &bull; <span style="color:#34d399">Stable &amp; Capable</span>: Cpk &ge; 1.33 (Proses stabil &amp; ideal)
                    </div>
                    <div>
                        <strong style="color:var(--text-light);">2. Trend Insight (ZST / ZLT)</strong><br>
                        Evaluasi performa jangka panjang <strong>ZST (Technology) &amp; ZLT (Shift)</strong>:<br>
                        &bull; <span style="color:#f87171">Kritis</span>: Performa ZST &lt; 3.0<br>
                        &bull; <span style="color:#fbbf24">Waspada</span>: Performa ZST &lt; 4.0<br>
                        &bull; <span style="color:#34d399">Positif</span>: Performa ZST &ge; 4.0
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. AUTO CLOSE SHIFT CRON (06:40 AM) -->
        <div class="card docs-section">
            <div class="card-header"><i class="fa-solid fa-clock-rotate-left" style="color:#ec4899;"></i> 6. Auto Close Shift & Reset Cron (06:40 AM)</div>
            <div style="padding: 16px;">
                <p style="font-size:12.5px; color:var(--text-muted); line-height:1.7;">
                    Sistem dilengkapi dengan skedul otomatis `c_dtc_cron_close_shift.php` yang dijalankan setiap hari pada jam <strong>06:40 AM WIB</strong>:
                </p>

                <div style="font-size: 12.5px; color: var(--text-light); line-height: 1.8; margin-top: 10px;">
                    <div style="margin-bottom: 8px;">&bull; <strong>Auto Close Session Hari Sebelumnya</strong>: Mengunci otomatis seluruh sesi pengukuran (`is_closed = 1`) pada tanggal kerja sebelumnya agar data historis terkunci aman.</div>
                    <div style="margin-bottom: 8px;">&bull; <strong>Reset Running Models</strong>: Menghapus daftaran *Running Model* hari sebelumnya di tabel `dtc_running_models` sehingga operator/foreman dapat menentukan kembali model aktif untuk shift hari yang baru.</div>
                    <div style="margin-bottom: 8px;">&bull; <strong>Execution Logging</strong>: Catatan waktu eksekusi tersimpan secara otomatis di tabel `dtc_app_settings`.</div>
                </div>
            </div>
        </div>
    </div>

</div>
