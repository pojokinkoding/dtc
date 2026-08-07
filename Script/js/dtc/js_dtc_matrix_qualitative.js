// Script/js/dtc/js_dtc_matrix_qualitative.js
// Refactored: Checkpoint-based Matrix

let matrixData = [];      // Array of checkpoints with their matrix data
let timeLabels = [];       // Time labels from Settings
let parametersList = [];   // List of parameters for dropdown
let daysInMonth = 31;
let closedDaysMap = {};    // Closed days status mapped by parameter_id and day

$(document).ready(function () {
    loadMatrixData();

    // === CELL CLICK → Open Input Modal ===
    $(document).on('click', '.cell-data', function () {
        let currentMonthStr = new Date().toISOString().slice(0, 7);
        if (matrixMonth && matrixMonth < currentMonthStr) {
            Swal.fire({
                icon: 'lock',
                title: 'Periode Bulan Lalu Terkunci Total',
                text: 'Data pengukuran dan checkpoint periode bulan lalu terkunci total dan tidak dapat diubah.',
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        let paramId = $(this).data('param-id');
        let cpId = $(this).data('checkpoint-id');
        let date = $(this).data('date');
        let timeLabel = $(this).data('label');
        let val = $(this).data('val');
        let cpName = $(this).data('cp-name');

        let formattedDate = `${matrixMonth}-${date.toString().padStart(2, '0')}`;

        // --- 1. Validation: Gak boleh isi sebelum waktunya ---
        let isFuture = false;
        if (/^\d{2}:\d{2}$/.test(timeLabel)) {
            let parts = timeLabel.split(':');
            let hours = parseInt(parts[0], 10);
            let minutes = parseInt(parts[1], 10);

            // Buat objek tanggal target berdasarkan shift date
            let targetDt = new Date(formattedDate + 'T00:00:00');
            if (hours < 7) {
                // Jam sebelum 07:00 masuk ke hari kalender berikutnya
                targetDt.setDate(targetDt.getDate() + 1);
            }
            targetDt.setHours(hours, minutes, 0, 0);

            if (targetDt > new Date()) {
                isFuture = true;
            }
        } else {
            // Custom label. Check date only.
            let today = new Date().toISOString().slice(0, 10);
            if (formattedDate > today) {
                isFuture = true;
            }
        }

        if (isFuture) {
            Swal.fire({
                icon: 'warning',
                title: 'Belum Waktunya',
                text: 'Belum masuk waktu pengisian untuk slot ini.'
            });
            return;
        }

        // --- 2. Validation: Hari telah dikunci (Closed) ---
        let isClosed = (closedDaysMap[paramId] && closedDaysMap[paramId][date] == 1);
        if (isClosed && !isAdmin) {
            Swal.fire({
                icon: 'lock',
                title: 'Terkunci',
                text: 'Hari ini telah di-close. Pengisian data tidak diperbolehkan.'
            });
            return;
        }

        // --- 3. Validation: Kalau sudah ada input, tidak bisa ganti kecuali admin ---
        if (val && val.trim() !== '' && !isAdmin) {
            Swal.fire({
                icon: 'warning',
                title: 'Akses Ditolak',
                text: 'Hanya Admin yang dapat mengubah data yang sudah diisi.'
            });
            return;
        }

        $('#input_param_id').val(paramId);
        $('#input_checkpoint_id').val(cpId);
        $('#input_date').val(formattedDate);
        $('#input_time').val(timeLabel);
        $('#input_result').val(val);

        // Hide warning msg when modal opens
        $('#result-warning-msg').hide();

        // Reset button states
        $('.btn-result').css({ 'opacity': '1', 'transform': 'scale(1)' });
        if (val) {
            $(`.btn-result[data-val="${val}"]`).css({ 'opacity': '1', 'transform': 'scale(1.05)' });
            $(`.btn-result[data-val!="${val}"]`).css({ 'opacity': '0.4' });
        }

        $('#modal-info-text').html(`<b>${cpName}</b> — Jam: <b>${timeLabel}</b> — Tanggal: <b>${formattedDate}</b>`);
        $('#modal-input-matrix').addClass('active');
    });

    // === TOGGLE LOCK CLICK ===
    $(document).on('click', '.btn-toggle-lock', function (e) {
        e.stopPropagation();
        if (!isPrivilegedUser) {
            Swal.fire('Akses Ditolak', 'Hanya Admin, Supervisor, atau Foreman yang dapat mengunci/membuka hari.', 'warning');
            return;
        }
        let paramId = $(this).data('param-id');
        let day = $(this).data('date-day');
        let formattedDate = `${matrixMonth}-${day.toString().padStart(2, '0')}`;

        Swal.fire({
            title: 'Kunci / Buka Hari',
            text: `Apakah Anda yakin ingin mengubah status kunci untuk tanggal ${formattedDate}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent)',
            confirmButtonText: 'Ya, Ubah',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_close_day',
                        parameter_id: paramId,
                        date: formattedDate
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Sukses', res.message, 'success');
                            loadMatrixData(false);
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Server connection error.', 'error');
                    }
                });
            }
        });
    });

    // === RESULT BUTTON CLICK ===
    $(document).on('click', '.btn-result', function () {
        let val = $(this).data('val');
        $('#input_result').val(val);
        $('#result-warning-msg').hide(); // Hide warning when selected
        $('.btn-result').css({ 'opacity': '0.4', 'transform': 'scale(1)' });
        $(this).css({ 'opacity': '1', 'transform': 'scale(1.05)' });
    });

    // === CLOSE MODALS ===
    $('#btn-close-modal, #btn-cancel-input').on('click', function () {
        $('#modal-input-matrix').removeClass('active');
    });
    $(document).on('click', '#btn-close-add-cp, #btn-cancel-add-cp', function () {
        $('#modal-add-checkpoint').removeClass('active');
    });

    // === SAVE DATA ===
    $('#form-matrix-input').on('submit', function (e) {
        e.preventDefault();

        let resultVal = $('#input_result').val();
        if (!resultVal || resultVal.trim() === '') {
            $('#result-warning-msg').fadeIn();
            return;
        }

        let btn = $('#btn-save-input');
        let originalText = btn.text();
        btn.text('Saving...').prop('disabled', true);

        $.ajax({
            url: 'Script/php/dtc/c_dtc_matrix_qualitative_save.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    $('#modal-input-matrix').removeClass('active');
                    loadMatrixData(false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Server connection error.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            },
            complete: function () {
                btn.text(originalText).prop('disabled', false);
            }
        });
    });

    // === OPEN ADD CHECKPOINT MODAL ===
    $(document).on('click', '#btn-open-add-checkpoint, #btn-open-add-checkpoint-empty, .btn-add-checkpoint', function () {
        // Fill parameter dropdown
        let opts = '';
        if (parametersList && parametersList.length > 0) {
            parametersList.forEach(p => {
                opts += `<option value="${p.parameter_id}">${p.item_check_name} [${p.data_type}]</option>`;
            });
        }
        $('#cp_param_select').html(opts);
        if (typeof matrixParamId !== 'undefined' && matrixParamId) {
            $('#cp_param_select').val(matrixParamId);
        }
        $('#cp_name').val('');
        $('#cp_spec').val('');
        $('#cp_type').val('Qualitative');
        $('#add_spec_bounds').css('display', 'none');
        $('#cp_lsl').val('');
        $('#cp_target_value').val('');
        $('#cp_usl').val('');
        $('#modal-add-checkpoint').addClass('active');
    });

    $(document).on('change', '#cp_type', function() {
        if ($(this).val() === 'Quantitative') {
            $('#add_spec_bounds').css('display', 'grid');
        } else {
            $('#add_spec_bounds').css('display', 'none');
        }
    });

    $(document).on('change', '#edit_cp_type', function() {
        if ($(this).val() === 'Quantitative') {
            $('#edit_spec_bounds').css('display', 'grid');
        } else {
            $('#edit_spec_bounds').css('display', 'none');
        }
    });

    // === SUBMIT ADD CHECKPOINT ===
    $(document).on('submit', '#form-add-checkpoint', function (e) {
        e.preventDefault();

        let lslVal = $('#cp_lsl').val() !== '' ? parseFloat($('#cp_lsl').val()) : null;
        let targetVal = $('#cp_target_value').val() !== '' ? parseFloat($('#cp_target_value').val()) : null;
        let uslVal = $('#cp_usl').val() !== '' ? parseFloat($('#cp_usl').val()) : null;

        if (lslVal !== null && uslVal !== null && lslVal > uslVal) {
            Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: `Batas LSL (${lslVal}) tidak boleh lebih besar dari USL (${uslVal}).`, background: '#1e293b', color: '#f8fafc' });
            return;
        }

        if (targetVal !== null) {
            if (lslVal !== null && targetVal < lslVal) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: `Nilai Target (${targetVal}) berada di bawah LSL (${lslVal}).`, background: '#1e293b', color: '#f8fafc' });
                return;
            }
            if (uslVal !== null && targetVal > uslVal) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: `Nilai Target (${targetVal}) berada di atas USL (${uslVal}).`, background: '#1e293b', color: '#f8fafc' });
                return;
            }
        }

        let formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'add' });

        let btn = $(this).find('button[type="submit"]');
        let origText = btn.html();
        btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Adding...').prop('disabled', true);

        $.ajax({
            url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function (res) {
                btn.html(origText).prop('disabled', false);
                if (res.status === 'success') {
                    $('#modal-add-checkpoint').removeClass('active');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1e293b',
                        color: '#f8fafc'
                    });
                    loadMatrixData(false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function () {
                btn.html(origText).prop('disabled', false);
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Server connection error.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    });

    // === DELETE CHECKPOINT ===
    $(document).on('click', '.btn-delete-cp', function (e) {
        e.stopPropagation();
        let cpId = $(this).data('checkpoint-id');
        let cpName = $(this).data('cp-name');

        Swal.fire({
            title: 'Hapus Checkpoint?',
            text: `Apakah Anda yakin ingin menghapus checkpoint "${cpName}"? Semua data pengukuran untuk checkpoint ini akan hilang.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
                    type: 'POST',
                    data: { action: 'delete', checkpoint_id: cpId },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            loadMatrixData(false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                        }
                    }
                });
            }
        });
    });

    // === EDIT CHECKPOINT CLICK ===
    $(document).on('click', '.btn-edit-cp', function (e) {
        e.stopPropagation();
        let cpId = $(this).data('checkpoint-id') || currentActiveCpId;
        let cp = matrixData.find(c => c.checkpoint_id === cpId);
        if (!cp) return;

        $('#edit_cp_id').val(cp.checkpoint_id);
        $('#edit_cp_name').val(cp.checkpoint_name);
        $('#edit_cp_spec').val(cp.spec_value || '');
        $('#edit_cp_type').val(cp.checkpoint_type || 'Qualitative');
        if (cp.checkpoint_type === 'Quantitative') {
            $('#edit_spec_bounds').css('display', 'grid');
        } else {
            $('#edit_spec_bounds').css('display', 'none');
        }
        $('#edit_cp_lsl').val(cp.lsl !== null && cp.lsl !== undefined ? cp.lsl : '');
        $('#edit_cp_target_value').val(cp.target_value !== null && cp.target_value !== undefined ? cp.target_value : '');
        $('#edit_cp_usl').val(cp.usl !== null && cp.usl !== undefined ? cp.usl : '');

        $('#modal-edit-checkpoint').addClass('active');
    });

    $('#btn-close-edit-cp, #btn-cancel-edit-cp').on('click', function () {
        $('#modal-edit-checkpoint').removeClass('active');
    });

    $('#form-edit-checkpoint').on('submit', function (e) {
        e.preventDefault();

        let lslVal = $('#edit_cp_lsl').val() !== '' ? parseFloat($('#edit_cp_lsl').val()) : null;
        let targetVal = $('#edit_cp_target_value').val() !== '' ? parseFloat($('#edit_cp_target_value').val()) : null;
        let uslVal = $('#edit_cp_usl').val() !== '' ? parseFloat($('#edit_cp_usl').val()) : null;

        if (lslVal !== null && uslVal !== null && lslVal > uslVal) {
            Swal.fire('Validasi Gagal', `Batas LSL (${lslVal}) tidak boleh lebih besar dari USL (${uslVal}).`, 'error');
            return;
        }

        if (targetVal !== null) {
            if (lslVal !== null && targetVal < lslVal) {
                Swal.fire('Validasi Gagal', `Nilai Target (${targetVal}) berada di bawah LSL (${lslVal}).`, 'error');
                return;
            }
            if (uslVal !== null && targetVal > uslVal) {
                Swal.fire('Validasi Gagal', `Nilai Target (${targetVal}) berada di atas USL (${uslVal}).`, 'error');
                return;
            }
        }

        let btn = $(this).find('button[type="submit"]');
        let origText = btn.html();
        btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Updating...').prop('disabled', true);

        $.ajax({
            url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.html(origText).prop('disabled', false);
                if (res.status === 'success') {
                    $('#modal-edit-checkpoint').removeClass('active');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadMatrixData(false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.html(origText).prop('disabled', false);
                Swal.fire('Error', 'Server error updating checkpoint.', 'error');
            }
        });
    });

    // === IMAGE UPLOAD TRIGGERS ===
    let uploadTargetCpId = null;

    $(document).on('click', '.btn-change-cp-img', function (e) {
        e.stopPropagation();
        uploadTargetCpId = $(this).data('checkpoint-id');
        $('#cp-image-file-input').val('').click();
    });

    $('#cp-image-file-input').on('change', function () {
        if (!this.files || !this.files[0] || !uploadTargetCpId) return;

        let formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('checkpoint_id', uploadTargetCpId);
        formData.append('reference_image', this.files[0]);

        // Show a saving alert
        Swal.fire({
            title: 'Uploading image...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Image uploaded successfully!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadMatrixData(false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Server connection error.', 'error');
            }
        });
    });

    // === LIGHTBOX FOR CHECKPOINT IMAGES ===
    $(document).on('click', '.cp-thumb-img', function (e) {
        e.stopPropagation();
        let src = $(this).attr('src');
        let title = $(this).attr('title') || 'Reference Image';

        Swal.fire({
            title: title,
            imageUrl: src,
            imageAlt: 'Reference Image',
            showConfirmButton: false,
            showCloseButton: true,
            background: '#1e293b',
            color: '#f8fafc',
            maxWidth: '90%'
        });
    });
});

let currentActiveCpId = null; // Tracks active checkpoint tab

// === LOAD DATA ===
function loadMatrixData(showLoading = true) {
    if (showLoading) {
        $('#matrix-container').html('<div style="text-align: center; padding: 50px; color: var(--text-muted);"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Loading...</div>');
    }

    $.ajax({
        url: 'Script/php/dtc/c_dtc_matrix_qualitative_get.php',
        type: 'GET',
        data: { param_id: matrixParamId, model: matrixModel, line: matrixLine, section: matrixSection, month: matrixMonth },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                matrixData = res.data;
                timeLabels = res.time_labels;
                daysInMonth = res.days_in_month;
                parametersList = res.parameters;
                closedDaysMap = res.closed_days || {};

                if (parametersList.length === 0) {
                    $('#matrix-container').html('<div style="text-align: center; padding: 50px; color: var(--text-muted);"><i class="fa-solid fa-inbox fa-2x" style="opacity:0.3;"></i><br><br>No Qualitative (Time Check / F-Proof) parameters found for this model.</div>');
                    return;
                }

                // Set process name, measuring item, and data type header dynamically
                if (parametersList.length > 0) {
                    if (parametersList[0].process_name) {
                        $('.hdr-process-name, #hdr-process-name').text(parametersList[0].process_name);
                    }
                    if (parametersList[0].measuring_item) {
                        $('.hdr-measuring-item, #hdr-measuring-item').text(parametersList[0].measuring_item);
                    }
                    if (parametersList[0].data_type) {
                        $('.hdr-data-type, #hdr-data-type').text(parametersList[0].data_type);
                    }
                }

                if (matrixData.length > 0) {
                    // Check if current active CP ID is still valid, else pick the first one
                    if (!currentActiveCpId || !matrixData.find(cp => cp.checkpoint_id === currentActiveCpId)) {
                        currentActiveCpId = matrixData[0].checkpoint_id;
                    }
                } else {
                    currentActiveCpId = null;
                }

                renderTabs();
                renderMatrixTable();
            } else {
                $('#matrix-container').html(`<div style="color:red; padding:20px;">Error: ${res.message}</div>`);
            }
        },
        error: function () {
            $('#matrix-container').html('<div style="color:red; padding:20px;">Failed to fetch data from server.</div>');
        }
    });
}

// === RENDER TABS ===
function renderTabs() {
    let html = '';
    if (matrixData.length > 0) {
        matrixData.forEach(cp => {
            let activeClass = cp.checkpoint_id === currentActiveCpId ? 'active' : '';
            html += `<button class="filter-tab-btn ${activeClass}" data-checkpoint-id="${cp.checkpoint_id}" style="border-radius: 8px; padding: 7px 16px; font-weight: 700; font-size: 12px; transition: all 0.2s;">
                        <i class="fa-solid fa-layer-group" style="margin-right: 6px; opacity: 0.8;"></i> ${cp.checkpoint_name}
                     </button>`;
        });
    }
    html += `<div style="margin-left: auto; display: flex; gap: 8px; align-items: center;">
                <button id="btn-trigger-today-bulk-input" class="btn-rich-primary" style="padding: 6px 14px; font-size: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; background: rgba(59, 130, 246, 0.25); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4);" title="Input Bulk Pengukuran Hari Ini">
                    <i class="fa-solid fa-list-check"></i> Input Bulk Hari Ini
                </button>
                <button class="btn-add-checkpoint" id="btn-open-add-checkpoint" style="padding: 6px 14px; font-size: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fa-solid fa-plus"></i> Add Check Point
                </button>
                <a href="index.php?page=dtc" class="btn-rich-secondary" style="text-decoration: none; padding: 6px 14px; font-size: 12px; border-radius: 6px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
             </div>`;
    $('#tabs-container').html(html);
}

// Tab click handler
$(document).on('click', '.filter-tab-btn[data-checkpoint-id]', function () {
    currentActiveCpId = $(this).data('checkpoint-id');
    $('.filter-tab-btn[data-checkpoint-id]').removeClass('active');
    $(this).addClass('active');
    renderMatrixTable();
    setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
});// Tab click handler for Quantitative view (Dashboard vs History)
$(document).on('click', '.quant-tab-btn', function () {
    $('.quant-tab-btn').removeClass('active').css({ 'background': 'transparent', 'color': 'var(--text-muted)' });
    $(this).addClass('active').css({ 'background': 'var(--primary)', 'color': 'white' });
    let target = $(this).data('target');
    $('.quant-tab-content').hide();
    $('#' + target).show();
    if (target === 'quant-tab-dashboard') {
        setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
    }
});

// Open Bulk Input Modal button for current DTC item ID (in detail view)
$(document).on('click', '#btn-trigger-today-bulk-input', function () {
    if (typeof openBulkInputModal === 'function') {
        let activeCp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
        let activePid = activeCp ? activeCp.parameter_id : (typeof matrixParamId !== 'undefined' ? matrixParamId : 0);
        openBulkInputModal(matrixModel, matrixLine, matrixSection, activePid);
    }
});

// Open Checkpoint Data Input Modal for current active checkpoint
$(document).on('click', '#btn-open-quant-input', function () {
    openQuantInputModal();
});

// Click cell in Quantitative Tracking History grid
$(document).on('click', '#quant-history-body .cell-data', function () {
    let day = $(this).data('date');
    openQuantInputModal(day);
});

function getManufacturingProdDay() {
    let now = new Date();
    if (now.getHours() < 7) {
        now.setDate(now.getDate() - 1);
    }
    return now.getDate();
}

// Function to open Quantitative Input Modal (identical to dtc_detail)
function openQuantInputModal(selectedDay) {
    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    if (!cp) return;
    let paramInfo = parametersList.find(p => p.parameter_id === cp.parameter_id) || {};

    let dayInt = (selectedDay !== undefined && selectedDay !== null && selectedDay !== '')
        ? parseInt(selectedDay, 10)
        : getManufacturingProdDay();
    let dayStr = dayInt.toString().padStart(2, '0');
    let formattedDate = `${matrixMonth}-${dayStr}`;

    $('#quant_input_param_id').val(cp.parameter_id);
    $('#quant_input_checkpoint_id').val(cp.checkpoint_id);
    $('#quant_input_date').val(formattedDate);
    $('#quant_input_remarks').val('');

    // Title & Data Type Badge
    $('#modal-quant-cp-title').text(cp.checkpoint_name);
    $('#modal-quant-datatype-badge').text(`[${paramInfo.data_type || 'Time Check'}]`);

    // Spec Box Info (Prefer Checkpoint Spec, Fallback to Parameter Spec)
    let lslVal = (cp.lsl !== undefined && cp.lsl !== null) ? cp.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : '-');
    let targetVal = (cp.target_value !== undefined && cp.target_value !== null) ? cp.target_value : (paramInfo.target_value !== null ? paramInfo.target_value : '-');
    let uslVal = (cp.usl !== undefined && cp.usl !== null) ? cp.usl : (paramInfo.usl !== null ? paramInfo.usl : '-');

    $('#quant-spec-lsl').text(lslVal);
    $('#quant-spec-target').text(targetVal);
    $('#quant-spec-usl').text(uslVal);

    // Build Dynamic Samples Grid
    let gridHtml = '';
    timeLabels.forEach((label, idx) => {
        let seq = idx + 1;
        gridHtml += `<div>
                        <label style="display: block; font-size: 10px; text-align: center; color: var(--text-muted); margin-bottom: 4px;">${label}</label>
                        <input type="hidden" name="sample_label_${seq}" value="${label}">
                        <input type="number" step="any" name="sample_val_${seq}" id="quant_sample_val_${seq}" class="sample-input quant-sample-input" 
                               style="width: 100%; text-align: center; padding: 8px 4px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.4); background: rgba(15,23,42,0.8); color: white; transition: all 0.3s; font-size: 12px; font-weight: 600;" 
                               placeholder="S${seq}">
                    </div>`;
    });
    $('#quant-samples-grid').html(gridHtml);

    // Populate existing values
    populateQuantSamplesForDate(cp, parseInt(dayStr, 10));

    $('#modal-quant-input-data').css('display', 'flex');

    // Auto-focus on first active/editable empty sample input
    setTimeout(() => {
        let $firstEmpty = $(".quant-sample-input:not([readonly]):filter(function() { return $(this).val() === ''; }).first()");
        if ($firstEmpty.length > 0) {
            $firstEmpty.focus();
        } else {
            $(".quant-sample-input:not([readonly])").first().focus();
        }
    }, 150);
}

function isTimeSlotFuture(dateStr, label) {
    if (!dateStr || !label) return false;
    let parts = label.split(':');
    if (parts.length < 2) return false;
    let hours = parseInt(parts[0], 10);
    let minutes = parseInt(parts[1], 10);
    let offsetDay = 0;

    if (hours >= 24) {
        offsetDay = Math.floor(hours / 24);
        hours = hours % 24;
    } else if (hours < 7) {
        offsetDay = 1;
    }

    let targetDate = new Date(dateStr + 'T00:00:00');
    if (isNaN(targetDate.getTime())) return false;

    if (offsetDay > 0) {
        targetDate.setDate(targetDate.getDate() + offsetDay);
    }
    targetDate.setHours(hours, minutes, 0, 0);

    let now = new Date();
    return targetDate.getTime() > now.getTime();
}

function applySampleInputGlowing($input, val, lsl, usl, isClosed, isFuture) {
    if (isFuture) {
        $input.removeClass('slot-overdue-glowing').prop('readonly', true).css({
            'border': '1px dashed rgba(255,255,255,0.15)',
            'box-shadow': 'none',
            'background-color': 'rgba(15, 23, 42, 0.4)',
            'color': 'rgba(255,255,255,0.3)',
            'opacity': '0.5',
            'cursor': 'not-allowed'
        }).attr('title', 'Belum masuk waktu pengisian (slot jam di masa depan)');
        return;
    }

    if (val === '' || val === null || !is_numeric_val(val)) {
        if (isClosed && !isAdmin) {
            $input.removeClass('slot-overdue-glowing').prop('readonly', true).css({
                'border': '1px solid rgba(255,255,255,0.1)',
                'box-shadow': 'none',
                'background-color': 'rgba(255,255,255,0.05)',
                'color': 'rgba(255,255,255,0.4)',
                'opacity': '0.6',
                'cursor': 'not-allowed'
            });
        } else {
            if (!isClosed) {
                // Late form input -> Yellow alarm pulsing glowing
                $input.addClass('slot-overdue-glowing').prop('readonly', false).removeAttr('title');
            } else {
                $input.removeClass('slot-overdue-glowing').prop('readonly', false).css({
                    'border': '1px solid rgba(255,255,255,0.3)',
                    'box-shadow': 'none',
                    'background-color': 'rgba(15,23,42,0.8)',
                    'color': '#ffffff',
                    'opacity': '1',
                    'cursor': 'text'
                }).removeAttr('title');
            }
        }
        return;
    }

    $input.removeClass('slot-overdue-glowing');
    let numVal = parseFloat(val);
    let isOos = (lsl !== null && numVal < lsl) || (usl !== null && numVal > usl);

    if (isOos) {
        // Glowing Red OOS Style (Batas Atas / Batas Bawah Terlewati)
        $input.css({
            'border': '2px solid #ef4444',
            'box-shadow': '0 0 12px rgba(239, 68, 68, 0.8), inset 0 0 8px rgba(239, 68, 68, 0.4)',
            'background-color': 'rgba(239, 68, 68, 0.2)',
            'color': '#ff8888',
            'font-weight': '800'
        });
    } else {
        // Glowing Green OK Style (Dalam Rentang Spek)
        $input.css({
            'border': '1px solid #10b981',
            'box-shadow': '0 0 8px rgba(16, 185, 129, 0.4)',
            'background-color': 'rgba(16, 185, 129, 0.15)',
            'color': '#34d399',
            'font-weight': '700'
        });
    }

    if (!isAdmin) {
        $input.prop('readonly', true).css({ 'opacity': '0.85', 'cursor': 'not-allowed' });
    } else {
        $input.prop('readonly', false).css({ 'opacity': '1', 'cursor': 'text' }).removeAttr('title');
    }
}

function populateQuantSamplesForDate(cp, day) {
    let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][day] == 1);
    let paramInfo = parametersList.find(p => p.parameter_id === cp.parameter_id) || {};
    let lsl = cp.lsl !== null && cp.lsl !== undefined ? cp.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : null);
    let usl = cp.usl !== null && cp.usl !== undefined ? cp.usl : (paramInfo.usl !== null ? paramInfo.usl : null);
    let selectedDateStr = $('#quant_input_date').val();

    if (isClosed) {
        $('#quant-day-close-badge').html('<i class="fa-solid fa-lock" style="color:#ef4444;"></i> Closed (Locked)').css({
            'background': 'rgba(239,68,68,0.15)',
            'color': '#f87171',
            'border-color': 'rgba(239,68,68,0.3)'
        });
        $('#btn-toggle-close-day-quant').html('<i class="fa-solid fa-lock-open"></i> Reopen Day');
        if (!isAdmin) {
            $('#quant-close-day-notice').show();
            $('#btn-save-quant-input').prop('disabled', true).css('opacity', '0.5');
        } else {
            $('#quant-close-day-notice').hide();
            $('#btn-save-quant-input').prop('disabled', false).css('opacity', '1');
        }
    } else {
        $('#quant-day-close-badge').html('<i class="fa-solid fa-lock-open" style="color:#34d399;"></i> Open').css({
            'background': 'rgba(16,185,129,0.15)',
            'color': '#34d399',
            'border-color': 'rgba(16,185,129,0.3)'
        });
        $('#btn-toggle-close-day-quant').html('<i class="fa-solid fa-lock"></i> Close Day');
        $('#quant-close-day-notice').hide();
        $('#btn-save-quant-input').prop('disabled', false).css('opacity', '1');
    }

    timeLabels.forEach((label, idx) => {
        let seq = idx + 1;
        let rowData = cp.matrix[label] || {};
        let val = rowData[day] || '';
        let $input = $(`#quant_sample_val_${seq}`);

        let isFuture = isTimeSlotFuture(selectedDateStr, label);
        $input.val(val !== '' && is_numeric_val(val) ? parseFloat(val) : '');
        applySampleInputGlowing($input, val, lsl, usl, isClosed, isFuture);
    });
}

// Live typing glowing feedback for quantitative sample inputs
$(document).on('input keyup change', '.quant-sample-input', function () {
    let val = $(this).val();
    if (val !== null && val !== undefined && String(val).trim() !== '') {
        $(this).removeClass('slot-overdue-glowing');
    }
    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    if (!cp) return;
    let paramInfo = parametersList.find(p => p.parameter_id === cp.parameter_id) || {};
    let lsl = cp.lsl !== null && cp.lsl !== undefined ? cp.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : null);
    let usl = cp.usl !== null && cp.usl !== undefined ? cp.usl : (paramInfo.usl !== null ? paramInfo.usl : null);

    let dateVal = $('#quant_input_date').val();
    let day = dateVal ? parseInt(dateVal.split('-')[2], 10) : 1;
    let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][day] == 1);

    let seq = $(this).attr('id') ? $(this).attr('id').replace('quant_sample_val_', '') : '';
    let label = $(`input[name="sample_label_${seq}"]`).val();
    let isFuture = isTimeSlotFuture(dateVal, label);

    applySampleInputGlowing($(this), val, lsl, usl, isClosed, isFuture);
});

// Date change inside Quantitative Modal
$(document).on('change', '#quant_input_date', function () {
    let dateVal = $(this).val();
    if (!dateVal) return;
    let day = parseInt(dateVal.split('-')[2], 10);
    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    if (cp) {
        populateQuantSamplesForDate(cp, day);
    }
});

// Close Quantitative Modal
$(document).on('click', '#btn-close-quant-modal, #btn-cancel-quant-input', function () {
    $('#modal-quant-input-data').hide();
});

// Submit Quantitative Modal Form
$(document).on('submit', '#form-quant-input-data', function (e) {
    e.preventDefault();

    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    let paramInfo = cp ? (parametersList.find(p => p.parameter_id === cp.parameter_id) || {}) : {};
    let lsl = cp ? ((cp.lsl !== null && cp.lsl !== undefined) ? cp.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : null)) : null;
    let usl = cp ? ((cp.usl !== null && cp.usl !== undefined) ? cp.usl : (paramInfo.usl !== null ? paramInfo.usl : null)) : null;

    let hasOOS = false;
    $('.quant-sample-input').each(function () {
        let val = $(this).val();
        if (val !== '' && val !== null && is_numeric_val(val)) {
            let numVal = parseFloat(val);
            if ((lsl !== null && numVal < lsl) || (usl !== null && numVal > usl)) {
                hasOOS = true;
                return false; // break loop
            }
        }
    });

    let $form = $(this);
    let btn = $('#btn-save-quant-input');
    let origText = btn.html();

    let executeSave = function () {
        btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: 'Script/php/dtc/c_dtc_matrix_qualitative_save.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (res) {
                btn.html(origText).prop('disabled', false);
                if (res.status === 'success') {
                    $('#modal-quant-input-data').hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadMatrixData(false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.html(origText).prop('disabled', false);
                Swal.fire('Error', 'Server error saving measurement.', 'error');
            }
        });
    };

    if (hasOOS && typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Data Out of Spec!',
            text: 'Beberapa nilai sampel yang Anda masukkan berada di luar batas spesifikasi (under / upper spec). Yakin ingin tetap menyimpannya?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Simpan Data',
            cancelButtonText: 'Batal',
            background: '#1e293b',
            color: '#f8fafc'
        }).then((result) => {
            if (result.isConfirmed) {
                executeSave();
            }
        });
    } else if (hasOOS) {
        if (confirm("Beberapa nilai sampel berada di luar batas spesifikasi. Yakin ingin tetap menyimpannya?")) {
            executeSave();
        }
    } else {
        executeSave();
    }
});

// === RENDER TABLE ===
function renderMatrixTable() {
    if (matrixData.length === 0) {
        $('#container-quantitative-view').hide();
        $('#container-qualitative-view').show();
        // Parameters exist but no checkpoints yet
        $('#matrix-container').html(`
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fa-solid fa-clipboard-list fa-3x" style="opacity:0.2; margin-bottom:15px;"></i><br>
                <p style="font-size:15px; margin-bottom:5px; font-weight: bold; color: white;">Belum ada Check Point.</p>
                <p style="font-size:13px; margin-bottom:15px;">Klik tombol di bawah ini atau tombol <b>"+ Add Check Point"</b> di kanan atas untuk menambahkan checkpoint pertama.</p>
                <button class="btn-add-checkpoint" id="btn-open-add-checkpoint-empty" style="padding: 8px 18px; font-size: 13px; border-radius: 8px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-plus"></i> Add Check Point
                </button>
            </div>
        `);
        return;
    }

    // Get the currently active checkpoint data
    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    if (!cp && matrixData.length > 0) {
        cp = matrixData[0];
        currentActiveCpId = cp.checkpoint_id;
    }
    if (!cp) return;

    let paramInfo = parametersList.find(p => p.parameter_id === cp.parameter_id) || {};
    let isQuantitative = (cp.checkpoint_type === 'Quantitative');

    if (isQuantitative) {
        // Mode Quantitative: Hide qualitative vertical matrix table, show charts & history grid
        $('#container-qualitative-view').hide();
        $('#container-quantitative-view').css('display', 'block');
        updateQuantKPICards(cp, paramInfo);
        renderQuantHistoryTable(cp);

        // Schedule chart rendering & Kendo resize after DOM layout tick
        setTimeout(() => {
            renderCheckpointCharts(cp, true);
            kendo.resize($('#container-quantitative-view'));
        }, 50);
        return;
    }

    // Mode Qualitative: Show vertical matrix table, hide quantitative view
    $('#container-quantitative-view').hide();
    $('#container-qualitative-view').show();
    updateQualKPICards(cp);

    let tableHtml = `<div style="max-height: 75vh; overflow: auto; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px;">
                        <table class="matrix-table" style="min-width: ${900 + (daysInMonth * 42)}px;">
                            <thead>
                                <tr>
                                    <th style="width: 180px; min-width: 160px; text-align:left;">Check Point / Spec</th>
                                    <th style="width:100px;">Jam</th>`;

    for (let d = 1; d <= daysInMonth; d++) {
        let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][d] == 1);
        let lockIcon = '';
        if (isClosed) {
            lockIcon = isPrivilegedUser ? `<br><i class="fa-solid fa-lock btn-toggle-lock" data-date-day="${d}" data-param-id="${cp.parameter_id}" style="color:#ef4444; font-size:10px; cursor:pointer;" title="Click to Unlock Day"></i>` : `<br><i class="fa-solid fa-lock" style="color:#64748b; font-size:9px; opacity:0.5;" title="Locked"></i>`;
        } else {
            lockIcon = isPrivilegedUser ? `<br><i class="fa-solid fa-lock-open btn-toggle-lock" data-date-day="${d}" data-param-id="${cp.parameter_id}" style="color:#34d399; font-size:9px; cursor:pointer;" title="Click to Lock Day"></i>` : '';
        }
        tableHtml += `<th style="width:38px; padding: 4px 2px; font-size: 11px;">${d}${lockIcon}</th>`;
    }
    tableHtml += `</tr></thead><tbody>`;

    let labels = timeLabels;
    let rowCount = labels.length;

    labels.forEach((label, lIdx) => {
        tableHtml += `<tr>`;

        if (lIdx === 0) {
            let imgHtml = '';
            if (cp.reference_image) {
                imgHtml = `<div style="margin-top:12px; position:relative; display:inline-block; width:100%; text-align:center;">
                              <img src="${cp.reference_image}" class="cp-thumb-img" style="width:100%; max-height:90px; border-radius:6px; border:1px solid rgba(255,255,255,0.15); cursor:pointer; object-fit:cover;" title="Click to view full image">
                              <div class="btn-change-cp-img" data-checkpoint-id="${cp.checkpoint_id}" style="position:absolute; bottom:6px; right:6px; background:rgba(15,23,42,0.85); border:1px solid rgba(255,255,255,0.15); padding:4px 8px; border-radius:4px; color:#38bdf8; font-size:9px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:4px;" title="Change Image">
                                  <i class="fa-solid fa-camera"></i> Ubah
                              </div>
                           </div>`;
            } else {
                imgHtml = `<div style="margin-top:12px;">
                              <button class="btn-change-cp-img" data-checkpoint-id="${cp.checkpoint_id}" style="background: rgba(56,189,248,0.08); color: #38bdf8; border: 1px dashed rgba(56,189,248,0.3); padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 10px; transition:all 0.2s; width:100%; box-sizing:border-box; font-weight:600; display:flex; align-items:center; justify-content:center; gap:5px;">
                                  <i class="fa-solid fa-camera"></i> Upload Image
                              </button>
                           </div>`;
            }

            tableHtml += `<td rowspan="${rowCount}" style="text-align:left; vertical-align:top; background:rgba(30,41,59,0.4); padding: 15px; width: 190px; min-width: 180px; max-width: 190px; border-right: 2px solid rgba(255,255,255,0.08);">
                            <div style="font-weight:700; color:var(--accent); font-size:15px; margin-bottom:6px; word-wrap: break-word; white-space: normal; line-height:1.3;">${cp.checkpoint_name}</div>
                            <div style="font-size:11px; color:#94a3b8; margin-bottom:12px;">Spec: <b style="color:#cbd5e1;">${cp.spec_value || '-'}</b></div>
                            ${imgHtml}
                            <div style="margin-top:15px; display:flex; flex-direction:column; gap:6px;">
                                <button class="btn-edit-cp" data-checkpoint-id="${cp.checkpoint_id}" style="background: rgba(56,189,248,0.08); color:#38bdf8; border: 1px solid rgba(56,189,248,0.2); padding: 6px 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s; width:100%; font-size:10px; font-weight:700; box-sizing:border-box; display:flex; align-items:center; justify-content:center; gap:5px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit Checkpoint
                                </button>
                                <button class="btn-delete-cp" data-checkpoint-id="${cp.checkpoint_id}" data-cp-name="${cp.checkpoint_name}" style="background: rgba(239,68,68,0.08); color:#ef4444; border: 1px solid rgba(239,68,68,0.2); padding: 6px 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s; width:100%; font-size:10px; font-weight:700; box-sizing:border-box; display:flex; align-items:center; justify-content:center; gap:5px;">
                                    <i class="fa-solid fa-trash-can"></i> Hapus Checkpoint
                                </button>
                            </div>
                          </td>`;
        }

        tableHtml += `<td style="text-align:center; font-weight:600; color:#cbd5e1; font-size:12px; background:rgba(15,23,42,0.6);">${label}</td>`;

        let rowData = cp.matrix[label] || {};
        for (let d = 1; d <= daysInMonth; d++) {
            let val = rowData[d] || '';
            let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][d] == 1);
            let cellClass = val === 'OK' ? 'cell-ok' : (val === 'NG' ? 'cell-ng' : 'cell-empty');
            if (isClosed) {
                cellClass += ' cell-locked';
            }

            let displayVal = val || '<i class="fa-solid fa-minus" style="font-size:8px;"></i>';
            if (isClosed && !val) {
                displayVal = '<i class="fa-solid fa-lock" style="font-size:9px; opacity:0.3;"></i>';
            }

            tableHtml += `<td>
                            <div class="cell-data ${cellClass}" 
                                 data-param-id="${cp.parameter_id}" 
                                 data-checkpoint-id="${cp.checkpoint_id}" 
                                 data-date="${d}" 
                                 data-label="${label}" 
                                 data-val="${val}"
                                 data-cp-name="${cp.checkpoint_name}"
                                 style="${isClosed ? 'cursor: not-allowed; opacity: 0.8;' : ''}"
                                 title="${cp.checkpoint_name} | ${label} | Day ${d}${isClosed ? ' (LOCKED)' : ''}">
                                ${displayVal}
                            </div>
                          </td>`;
        }
        tableHtml += `</tr>`;
    });

    tableHtml += `</tbody></table></div>`;

    tableHtml += `<div style="margin-top: 15px; font-size: 12px; color: #94a3b8; background: rgba(30,41,59,0.6); padding: 10px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-block; width:12px; height:12px; background:#10b981; border-radius:50%; box-shadow:0 0 8px rgba(16,185,129,0.5);"></span>
                        <span style="font-weight:600; color:#34d399;">OK (Passed)</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-block; width:12px; height:12px; background:#ef4444; border-radius:50%; box-shadow:0 0 8px rgba(239,68,68,0.5);"></span>
                        <span style="font-weight:600; color:#f87171;">NG (Not Good / Out-of-Spec)</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-lock" style="color:#ef4444; font-size:11px;"></i>
                        <span style="font-weight:600; color:#94a3b8;">Closed / Locked Day</span>
                    </div>
                    <div style="margin-left:auto; color:#64748b; font-size:11px; font-weight:600;">
                        <i class="fa-regular fa-clock" style="margin-right:4px;"></i> Shift Schedule: ${timeLabels.length} Time Slots
                    </div>
                  </div>`;

    $('#matrix-container').html(tableHtml);
}

function renderQuantHistoryTable(cp) {
    let headerHtml = `<th class="sticky-col" style="width:100px;">Jam</th>`;
    for (let d = 1; d <= daysInMonth; d++) {
        let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][d] == 1);
        let lockIcon = isClosed ? ' <i class="fa-solid fa-lock" style="color:#ef4444; font-size:9px;"></i>' : '';
        let cursorStyle = isAdmin ? 'cursor:pointer;' : '';
        let titleAttr = isAdmin ? (isClosed ? 'Klik untuk membuka kunci hari' : 'Klik untuk mengunci hari') : '';
        headerHtml += `<th style="width:45px; text-align:center; ${cursorStyle}" class="btn-toggle-close-quant-header" data-day="${d}" title="${titleAttr}">${d}${lockIcon}</th>`;
    }
    $('#quant-history-header').html(headerHtml);

    let bodyHtml = '';
    timeLabels.forEach(label => {
        bodyHtml += `<tr><td class="sticky-col" style="font-weight:600; text-align:center; background:rgba(30,41,59,0.8);">${label}</td>`;
        let rowData = cp.matrix[label] || {};
        for (let d = 1; d <= daysInMonth; d++) {
            let val = rowData[d] || '';
            let isClosed = (closedDaysMap[cp.parameter_id] && closedDaysMap[cp.parameter_id][d] == 1);
            let cellClass = 'cell-empty';
            if (val !== '' && is_numeric_val(val)) {
                let numVal = parseFloat(val);
                let lsl = cp.chart_data ? cp.chart_data.lsl : null;
                let usl = cp.chart_data ? cp.chart_data.usl : null;
                let isOos = (lsl !== null && numVal < lsl) || (usl !== null && numVal > usl);
                cellClass = isOos ? 'cell-ng' : 'cell-ok';
            }
            if (isClosed) cellClass += ' cell-locked';

            let displayVal = val || '<i class="fa-solid fa-minus" style="font-size:8px;"></i>';
            bodyHtml += `<td>
                            <div class="cell-data ${cellClass}" 
                                 data-param-id="${cp.parameter_id}" 
                                 data-checkpoint-id="${cp.checkpoint_id}" 
                                 data-date="${d}" 
                                 data-label="${label}" 
                                 data-val="${val}"
                                 data-cp-name="${cp.checkpoint_name}"
                                 style="${isClosed ? 'cursor: not-allowed; opacity: 0.8;' : 'cursor: pointer;'}"
                                 title="${cp.checkpoint_name} | ${label} | Day ${d}">
                                ${displayVal}
                            </div>
                        </td>`;
        }
        bodyHtml += `</tr>`;
    });
    $('#quant-history-body').html(bodyHtml);
}

// === TOGGLE CLOSE DAY FOR QUANTITATIVE VIEW ===
$(document).on('click', '#btn-toggle-close-day-quant, #btn-quant-close-day-dash, .btn-toggle-close-quant-header', function (e) {
    e.stopPropagation();
    let cp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
    let activeParamId = cp ? cp.parameter_id : (matrixParamId || (parametersList.length > 0 ? parametersList[0].parameter_id : 0));

    let clickedDay = $(this).data('day');
    let dateStr = clickedDay ? `${matrixMonth}-${String(clickedDay).padStart(2, '0')}` : ($('#quant_input_date').val() || `${matrixMonth}-20`);

    if (!activeParamId || !dateStr) {
        Swal.fire('Warning', 'Parameter dan tanggal harus terpilih.', 'warning');
        return;
    }

    if (!isAdmin) {
        Swal.fire('Akses Ditolak', 'Hanya Admin yang memiliki wewenang untuk mengunci/membuka status hari.', 'error');
        return;
    }

    Swal.fire({
        title: 'Konfirmasi Closing Hari (Quantitative)',
        text: `Apakah Anda yakin ingin mengunci/membuka status pengisian data untuk tanggal ${dateStr}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Ubah Status Hari'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'Script/php/dtc/c_dtc_checkpoint_manage.php',
                type: 'POST',
                data: {
                    action: 'toggle_close_day',
                    parameter_id: activeParamId,
                    date: dateStr
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sukses',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        loadMatrixData(false);
                        if ($('#modal-quant-input-data').is(':visible')) {
                            let day = parseInt(dateStr.split('-')[2], 10);
                            let activeCp = matrixData.find(c => c.checkpoint_id === currentActiveCpId);
                            if (activeCp) populateQuantSamplesForDate(activeCp, day);
                        }
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                }
            });
        }
    });
});

function is_numeric_val(val) {
    return !isNaN(parseFloat(val)) && isFinite(val);
}

// === RENDER CHECKPOINT CHARTS (KENDO UI 6-PANEL SPC GRID) ===
function renderCheckpointCharts(cp, isQuantitative) {
    if (!isQuantitative || !cp || !cp.chart_data) {
        return;
    }

    let cData = cp.chart_data;
    let categories = cData.categories;
    let xbarValues = cData.xbar;
    let rValues = cData.r;

    let paramInfo = parametersList.find(p => p.parameter_id === cp.parameter_id) || {};
    let LSL = cData.lsl !== null && cData.lsl !== undefined ? cData.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : null);
    let USL = cData.usl !== null && cData.usl !== undefined ? cData.usl : (paramInfo.usl !== null ? paramInfo.usl : null);
    let Target = cData.target !== null && cData.target !== undefined ? cData.target : (paramInfo.target_value !== null ? paramInfo.target_value : null);

    // 1. X-Bar Chart
    let xbarSeries = [
        {
            type: "line",
            name: "Avg (X-Bar)",
            data: xbarValues,
            color: "#38bdf8",
            markers: { visible: true, size: 5, background: "#38bdf8", border: { color: "#38bdf8", width: 2 } },
            line: { width: 2.5, style: "smooth" }
        }
    ];

    if (Target !== null) {
        xbarSeries.push({
            type: "line",
            name: "Target",
            data: Array(categories.length).fill(Target),
            color: "#10b981",
            dashType: "dash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }
    if (LSL !== null) {
        xbarSeries.push({
            type: "line",
            name: "LSL",
            data: Array(categories.length).fill(LSL),
            color: "#ef4444",
            dashType: "dash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }
    if (USL !== null) {
        xbarSeries.push({
            type: "line",
            name: "USL",
            data: Array(categories.length).fill(USL),
            color: "#ef4444",
            dashType: "dash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }

    let validXbar = xbarValues.filter(v => v !== null && v !== undefined && !isNaN(v));
    let allXbarVals = [LSL, USL, xbarMean, ...validXbar].filter(v => v !== null && v !== undefined && !isNaN(v));
    let xMinVal = allXbarVals.length > 0 ? Math.min(...allXbarVals) : 0;
    let xMaxVal = allXbarVals.length > 0 ? Math.max(...allXbarVals) : 10;
    let xbarSpan = xMaxVal - xMinVal;
    let xPadding = (xbarSpan === 0) ? (Math.abs(xMaxVal) > 0 ? Math.abs(xMaxVal) * 0.1 : 0.5) : Math.max(xbarSpan * 0.25, 0.1);
    let axisMinXbar = xMinVal - xPadding;
    let axisMaxXbar = xMaxVal + xPadding;
    let formatXbar = (xbarSpan < 2) ? "{0:n2}" : "{0:n1}";

    $("#chart-xbar").kendoChart({
        theme: "sass",
        chartArea: { background: "transparent" },
        legend: { position: "bottom", labels: { color: "#94a3b8", font: "11px Inter, sans-serif" } },
        seriesDefaults: { type: "line" },
        series: xbarSeries,
        categoryAxis: {
            categories: categories,
            labels: { color: "#94a3b8", font: "9px Inter, sans-serif" },
            majorGridLines: { visible: false },
            justified: true
        },
        valueAxis: {
            min: axisMinXbar,
            max: axisMaxXbar,
            labels: { color: "#94a3b8", font: "9px Inter, sans-serif", format: formatXbar },
            majorGridLines: { color: "rgba(255,255,255,0.05)" }
        },
        tooltip: {
            visible: true,
            template: "Day #= category #: #= value !== null ? kendo.toString(value, 'n2') : 'N/A' #"
        }
    });

    // 2. R Chart (Ranges)
    let validR = rValues.filter(v => v !== null && v !== undefined && !isNaN(v));
    let rMean = validR.length > 0 ? (validR.reduce((a, b) => a + parseFloat(b), 0) / validR.length) : null;
    let D4 = 1.777, D3 = 0.223;
    let UCL_R = rMean !== null ? (D4 * rMean) : null;
    let LCL_R = rMean !== null ? (D3 * rMean) : null;

    let rSeries = [
        {
            type: "line",
            name: "Range (R)",
            data: rValues,
            color: "#a855f7",
            markers: { visible: true, size: 5, background: "#a855f7", border: { color: "#a855f7", width: 2 } },
            line: { width: 2 }
        }
    ];

    if (UCL_R !== null) {
        rSeries.push({
            type: "line",
            name: "UCL",
            data: Array(categories.length).fill(parseFloat(UCL_R.toFixed(2))),
            color: "#ef4444",
            dashType: "dash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }
    if (rMean !== null) {
        rSeries.push({
            type: "line",
            name: "CL (Mean)",
            data: Array(categories.length).fill(parseFloat(rMean.toFixed(2))),
            color: "#10b981",
            dashType: "longDash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }
    if (LCL_R !== null && LCL_R > 0) {
        rSeries.push({
            type: "line",
            name: "LCL",
            data: Array(categories.length).fill(parseFloat(LCL_R.toFixed(2))),
            color: "#ef4444",
            dashType: "dash",
            markers: { visible: false },
            line: { width: 1.5 }
        });
    }

    let allRVals = [LCL_R, UCL_R, rMean, ...validR].filter(v => v !== null && v !== undefined && !isNaN(v));
    let rMinVal = allRVals.length > 0 ? Math.min(...allRVals) : 0;
    let rMaxVal = allRVals.length > 0 ? Math.max(...allRVals) : 1;
    let rSpan = rMaxVal - rMinVal;
    let rPad = (rSpan === 0) ? (Math.abs(rMaxVal) > 0 ? Math.abs(rMaxVal) * 0.2 : 0.05) : Math.max(rSpan * 0.25, 0.02);
    let axisMinR = Math.max(0, rMinVal - rPad);
    let axisMaxR = rMaxVal + rPad;
    let formatR = (rSpan < 2) ? "{0:n2}" : "{0:n1}";

    $("#chart-r").kendoChart({
        theme: "sass",
        chartArea: { background: "transparent" },
        legend: { position: "bottom", labels: { color: "#94a3b8", font: "11px Inter, sans-serif" } },
        seriesDefaults: { type: "line" },
        series: rSeries,
        categoryAxis: {
            categories: categories,
            labels: { color: "#94a3b8", font: "9px Inter, sans-serif" },
            majorGridLines: { visible: false },
            justified: true
        },
        valueAxis: {
            min: axisMinR,
            max: axisMaxR,
            labels: { color: "#94a3b8", font: "9px Inter, sans-serif", format: formatR },
            majorGridLines: { color: "rgba(255,255,255,0.05)" }
        },
        tooltip: {
            visible: true,
            template: "Day #= category # Range: #= value !== null ? kendo.toString(value, 'n2') : 'N/A' #"
        }
    });

    // 3. Extract All Samples for Capability Curve & 4-Block & Data Summary
    let allSamples = [];
    timeLabels.forEach(label => {
        let rowData = cp.matrix[label] || {};
        for (let d = 1; d <= daysInMonth; d++) {
            let val = rowData[d];
            if (val !== undefined && val !== '' && is_numeric_val(val)) {
                allSamples.push(parseFloat(val));
            }
        }
    });

    let n = allSamples.length;
    let maxData = n > 0 ? Math.max(...allSamples) : null;
    let minData = n > 0 ? Math.min(...allSamples) : null;
    let sum = n > 0 ? allSamples.reduce((a, b) => a + b, 0) : 0;
    let mean = n > 0 ? sum / n : null;
    let sumSq = (n > 1 && mean !== null) ? allSamples.reduce((a, b) => a + Math.pow(b - mean, 2), 0) : 0;
    let std = n > 1 ? Math.sqrt(sumSq / (n - 1)) : 0;
    let centerSpec = (USL !== null && LSL !== null) ? (USL + LSL) / 2 : (Target !== null ? Target : mean);

    let cpVal = null;
    let cpk = null;
    let zstTrue = null;
    let zltTrue = null;
    let zShift = null;

    if (std > 0 && USL !== null && LSL !== null && USL > LSL) {
        let cpu = (USL - mean) / (3 * std);
        let cpl = (mean - LSL) / (3 * std);
        cpk = Math.min(cpu, cpl);
        cpVal = (USL - LSL) / (6 * std);
        zltTrue = 3 * cpk;
        zstTrue = 3 * cpVal;
        zShift = zstTrue - zltTrue;
    } else if (std === 0 && n > 0 && USL !== null && LSL !== null) {
        if (mean >= LSL && mean <= USL) {
            cpVal = 99.99;
            cpk = 99.99;
            zstTrue = 6.0;
            zltTrue = 6.0;
            zShift = 0.0;
        } else {
            cpVal = 0.0;
            cpk = 0.0;
            zstTrue = 0.0;
            zltTrue = 0.0;
            zShift = 0.0;
        }
    }

    // ALWAYS UPDATE SUMMARY TABLE ELEMENTS
    $("#summ-n").text(n);
    $("#summ-max").text(maxData !== null ? kendo.toString(maxData, "n2") : "-");
    $("#summ-min").text(minData !== null ? kendo.toString(minData, "n2") : "-");
    $("#summ-avg").text(mean !== null ? kendo.toString(mean, "n2") : "-");
    $("#summ-std").text(n > 1 ? kendo.toString(std, "n2") : "0.00");
    $("#summ-center").text(centerSpec !== null ? kendo.toString(centerSpec, "n2") : "-");
    $("#summ-cp").text(cpVal !== null ? (cpVal >= 99.9 ? "Ideal" : kendo.toString(cpVal, "n2")) : "-");
    $("#summ-cpk").text(cpk !== null ? (cpk >= 99.9 ? "Ideal" : kendo.toString(cpk, "n2")) : "-");
    $("#summ-zst").text(zstTrue !== null ? kendo.toString(zstTrue, "n2") : "-");
    $("#summ-zlt").text(zltTrue !== null ? kendo.toString(zltTrue, "n2") : "-");

    if (cpVal !== null && cpVal < 1.0) $("#summ-cp").css("color", "#ef4444");
    else if (cpVal !== null && cpVal < 1.33) $("#summ-cp").css("color", "#f59e0b");
    else $("#summ-cp").css("color", "#10b981");

    if (cpk !== null && cpk < 1.0) $("#summ-cpk").css("color", "#ef4444");
    else if (cpk !== null && cpk < 1.33) $("#summ-cpk").css("color", "#f59e0b");
    else $("#summ-cpk").css("color", "#10b981");

    // AI INSIGHT BOX
    let oosPoints = allSamples.filter(v => (LSL !== null && v < LSL) || (USL !== null && v > USL));
    let oosStr = "";
    if (oosPoints.length > 0) {
        let uniqueOos = [...new Set(oosPoints)].sort((a, b) => a - b);
        let sampleStr = uniqueOos.slice(0, 3).map(v => kendo.toString(v, "n2")).join(", ");
        if (uniqueOos.length > 3) sampleStr += " etc.";
        oosStr = ` <span style="color: #ef4444;">AI detected <strong>${oosPoints.length} outlier(s)</strong> (${sampleStr}) outside limits (${LSL ?? '-'} - ${USL ?? '-'}).</span>`;
    }

    let aiText = "";
    if (n === 0) {
        aiText = `ℹ️ <strong>Waiting for Data.</strong> Belum ada sampel pengukuran untuk checkpoint ini.`;
    } else if (cpk !== null && cpk < 1.0) {
        aiText = `🚨 <strong>Process is Unstable.</strong> Cpk (${cpk >= 99.9 ? 'Ideal' : kendo.toString(cpk, "n2")}) is below 1.0, indicating high variation.${oosStr}`;
    } else if (cpk !== null && cpk < 1.33) {
        aiText = `⚠️ <strong>Process Needs Improvement.</strong> Cpk is ${kendo.toString(cpk, "n2")}, process is capable but lacks safety margin.${oosStr ? oosStr : ""}`;
    } else {
        aiText = `✅ <strong>Process is Stable & Capable.</strong> (Mean: ${kendo.toString(mean, "n2")}) All measurements within specification.${oosStr ? oosStr : ""}`;
    }
    if ($("#ai-insight-box").length) $("#ai-insight-box").html(aiText);

    // 4-Block Diagram (Scatter Chart)
    if (n > 0) {
        let displayZst = zstTrue !== null ? zstTrue : 0;
        let displayZShift = zShift !== null ? zShift : 0;
        let cappedZst = displayZst > 6.0 ? 6.0 : displayZst;
        let cappedZShift = displayZShift > 3.0 ? 3.0 : displayZShift;
        let rightCenterX = 4.75;
        let leftCenterX = 1.5;
        let topCenterY = 2.5;

        $("#chart-4block").kendoChart({
            theme: "sass",
            chartArea: { background: "transparent" },
            series: [
                {
                    type: "scatter",
                    data: [{ x: cappedZst, y: cappedZShift }],
                    xField: "x",
                    yField: "y",
                    markers: { size: 14, type: "circle", background: "#f97316", border: { color: "white", width: 2 } },
                    tooltip: { visible: true, template: "<b>Current Performance</b><br>Zst: #= kendo.toString(" + displayZst + ", 'n2') #<br>Z-Shift: #= kendo.toString(" + displayZShift + ", 'n2') #" }
                },
                {
                    type: "scatter",
                    data: [
                        { x: leftCenterX, y: topCenterY, label: "Poor Tech & Control\n(Needs overhaul)", color: "rgba(239, 68, 68, 0.5)" },
                        { x: rightCenterX, y: topCenterY, label: "Off Target\n(Needs centering)", color: "rgba(245, 158, 11, 0.5)" },
                        { x: leftCenterX, y: 0.75, label: "Poor Technology\n(Needs variance reduction)", color: "rgba(245, 158, 11, 0.5)" },
                        { x: rightCenterX, y: 0.75, label: "IDEAL STATE\n(World Class)", color: "rgba(16, 185, 129, 0.5)" }
                    ],
                    xField: "x", yField: "y",
                    labels: {
                        visible: true,
                        template: "#= dataItem.label #",
                        font: "bold 10px Inter, sans-serif",
                        color: function (e) { return e.dataItem.color; },
                        position: "center"
                    },
                    markers: { visible: false },
                    tooltip: { visible: false }
                }
            ],
            xAxis: {
                min: 0, max: 6.5,
                title: { text: "Technology / Capability (Zst)", color: "#94a3b8", font: "10px Inter" },
                labels: { color: "#94a3b8", font: "9px Inter" },
                plotBands: [{ from: 2.98, to: 3.02, color: "rgba(148, 163, 184, 0.5)" }]
            },
            yAxis: {
                min: 0, max: 3.5,
                title: { text: "Control / Stability (Z shift)", color: "#94a3b8", font: "10px Inter" },
                labels: { color: "#94a3b8", font: "9px Inter" },
                plotBands: [{ from: 1.48, to: 1.52, color: "rgba(148, 163, 184, 0.5)" }]
            }
        });

        // Process Capability Curve
        let capabilityData = [];
        let calcLSL = LSL !== null ? LSL : (mean !== null ? mean - 5 : 0);
        let calcUSL = USL !== null ? USL : (mean !== null ? mean + 5 : 10);
        let calcMean = mean !== null ? mean : (calcLSL + calcUSL) / 2;
        let calcStd = std > 0 ? std : 0.1;

        let minX = Math.min(calcLSL - (0.05 * Math.abs(calcLSL || 1)), calcMean - 4 * calcStd);
        let maxX = Math.max(calcUSL + (0.05 * Math.abs(calcUSL || 1)), calcMean + 4 * calcStd);
        let step = (maxX - minX) / 100;

        for (let x = minX; x <= maxX; x += step) {
            let exponent = Math.exp(-0.5 * Math.pow((x - calcMean) / calcStd, 2));
            let y = (1 / (calcStd * Math.sqrt(2 * Math.PI))) * exponent;
            capabilityData.push({ x: x, y: y });
        }

        let xAxisNotesData = [];
        if (LSL !== null) xAxisNotesData.push({ value: LSL, label: { text: "LSL: " + LSL, background: "#ef4444" }, line: { color: "#ef4444", length: 60 } });
        if (USL !== null) xAxisNotesData.push({ value: USL, label: { text: "USL: " + USL, background: "#ef4444" }, line: { color: "#ef4444", length: 60 } });
        if (mean !== null) xAxisNotesData.push({ value: mean, label: { text: "Mean: " + kendo.toString(mean, 'n2'), background: "#10b981", color: "#0f172a" }, line: { color: "#10b981", length: 60 } });

        $("#chart-capability").kendoChart({
            theme: "sass",
            chartArea: { background: "transparent", margin: { top: 35 } },
            legend: { visible: false },
            series: [{
                type: "scatterLine",
                style: "smooth",
                data: capabilityData,
                xField: "x", yField: "y",
                color: "#8b5cf6",
                markers: { visible: false }
            }],
            xAxis: {
                min: minX, max: maxX,
                labels: { visible: false },
                majorGridLines: { visible: false },
                plotBands: (LSL !== null && USL !== null) ? [{ from: LSL, to: USL, color: "rgba(16, 185, 129, 0.1)" }] : [],
                notes: { label: { color: "white", font: "9px Inter" }, data: xAxisNotesData }
            },
            yAxis: { labels: { visible: false }, majorGridLines: { visible: false } },
            tooltip: { visible: true, template: "Val: <b>#= kendo.toString(value.x, 'n2') #</b><br>Density: <b>#= kendo.toString(value.y, 'n3') #</b>" }
        });
    }

    // 4. Fetch Monthly Z-Trend for active parameter
    let year = matrixMonth ? matrixMonth.split('-')[0] : new Date().getFullYear();
    $.ajax({
        url: `Script/php/dtc/c_dtc_ztrend.php?param_id=${cp.parameter_id}&checkpoint_id=${cp.checkpoint_id || 0}&year=${year}`,
        type: "GET",
        dataType: "json",
        success: function (trendData) {
            const monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            let series = [];
            let hasActual = trendData.zst_actual && trendData.zst_actual.some(v => v !== null);
            let displayLabels = monthLabels;

            if (hasActual) {
                let lastIndex = -1;
                for (let i = 0; i < 12; i++) {
                    if (trendData.zst_actual[i] !== null) lastIndex = i;
                }
                if (lastIndex >= 0) {
                    trendData.zst_actual = trendData.zst_actual.slice(0, lastIndex + 1);
                    trendData.zlt_actual = trendData.zlt_actual.slice(0, lastIndex + 1);
                    displayLabels = monthLabels.slice(0, lastIndex + 1);
                }

                series.push({ type: "column", data: trendData.zst_actual, name: "ZST (Actual)", color: "#10b981" });
                series.push({ type: "column", data: trendData.zlt_actual, name: "ZLT (Actual)", color: "#f59e0b" });
            }

            let conclusionDiv = $("#trend-insight-box");
            if (conclusionDiv.length && trendData.forecast_conclusion) {
                let cText = trendData.forecast_conclusion;
                if (cText.includes('Kritis')) {
                    conclusionDiv.css({ 'background-color': 'rgba(239, 68, 68, 0.1)', 'color': '#ef4444', 'border': '1px solid rgba(239, 68, 68, 0.3)' });
                } else if (cText.includes('Waspada')) {
                    conclusionDiv.css({ 'background-color': 'rgba(245, 158, 11, 0.1)', 'color': '#f59e0b', 'border': '1px solid rgba(245, 158, 11, 0.3)' });
                } else {
                    conclusionDiv.css({ 'background-color': 'rgba(16, 185, 129, 0.1)', 'color': '#10b981', 'border': '1px solid rgba(16, 185, 129, 0.3)' });
                }
                conclusionDiv.html(cText);
            } else if (conclusionDiv.length) {
                conclusionDiv.html('Belum ada data historis tren Z-Value untuk periode ini.');
            }

            if (series.length > 0) {
                $("#chart-ztrend").kendoChart({
                    theme: "sass",
                    chartArea: { background: "transparent" },
                    series: series,
                    seriesDefaults: { gap: 0.3 },
                    categoryAxis: { categories: displayLabels, labels: { color: "#94a3b8", font: "9px Inter" } },
                    valueAxis: { labels: { color: "#94a3b8", font: "9px Inter" }, majorUnit: 40 },
                    legend: { position: "bottom", labels: { color: "#94a3b8", font: "11px Inter" } },
                    tooltip: { visible: true, format: "{0:n2}" }
                });
            } else {
                $("#chart-ztrend").html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:11px;">No trend data available</div>');
            }
        }
    });
}

// === UPDATE KPI EXECUTIVE CARDS FOR QUANTITATIVE VIEW ===
function updateQuantKPICards(cp, paramInfo) {
    if (!cp) return;

    let lsl = cp.lsl !== null && cp.lsl !== undefined ? cp.lsl : (paramInfo.lsl !== null ? paramInfo.lsl : null);
    let target = cp.target_value !== null && cp.target_value !== undefined ? cp.target_value : (paramInfo.target_value !== null ? paramInfo.target_value : null);
    let usl = cp.usl !== null && cp.usl !== undefined ? cp.usl : (paramInfo.usl !== null ? paramInfo.usl : null);

    // KPI 1: Checkpoint & Spec Info
    $('#kpi-cp-name').text(cp.checkpoint_name + (cp.spec_value ? ` (${cp.spec_value})` : ''));
    $('#kpi-spec-lsl').text(lsl !== null ? lsl : '-');
    $('#kpi-spec-target').text(target !== null ? target : '-');
    $('#kpi-spec-usl').text(usl !== null ? usl : '-');

    // Calculate metrics across all matrix measurements
    let allSamples = [];
    let latestVal = null;
    let latestDay = null;
    let latestLabel = null;
    let oosCount = 0;

    for (let d = daysInMonth; d >= 1; d--) {
        timeLabels.forEach(label => {
            let rowData = cp.matrix[label] || {};
            let val = rowData[d];
            if (val !== undefined && val !== '' && is_numeric_val(val)) {
                let numVal = parseFloat(val);
                allSamples.push(numVal);

                if (latestVal === null) {
                    latestVal = numVal;
                    latestDay = d;
                    latestLabel = label;
                }

                if ((lsl !== null && numVal < lsl) || (usl !== null && numVal > usl)) {
                    oosCount++;
                }
            }
        });
    }

    // KPI 2: Latest Inspection
    if (latestVal !== null) {
        $('#kpi-latest-val').text(latestVal);
        let formattedLatestDate = `Day ${latestDay} (${latestLabel})`;
        $('#kpi-latest-date').text(formattedLatestDate);

        let isLatestOos = (lsl !== null && latestVal < lsl) || (usl !== null && latestVal > usl);
        if (isLatestOos) {
            $('#kpi-latest-badge').text('OUT OF SPEC').css({
                'background': 'rgba(239,68,68,0.2)',
                'color': '#ef4444',
                'border-color': '#ef4444'
            });
        } else {
            $('#kpi-latest-badge').text('OK').css({
                'background': 'rgba(16,185,129,0.2)',
                'color': '#10b981',
                'border-color': '#10b981'
            });
        }
    } else {
        $('#kpi-latest-val').text('-');
        $('#kpi-latest-date').text('No inspections yet');
        $('#kpi-latest-badge').text('NO DATA').css({
            'background': 'rgba(148,163,184,0.2)',
            'color': '#94a3b8',
            'border-color': '#94a3b8'
        });
    }

    // KPI 3: Monthly Mean (X-Bar)
    if (allSamples.length > 0) {
        let sum = allSamples.reduce((a, b) => a + b, 0);
        let mean = sum / allSamples.length;
        $('#kpi-avg-val').text(mean.toFixed(2));

        // Average daily range R
        let cData = cp.chart_data || {};
        let rList = (cData.r || []).filter(v => v !== null);
        let avgR = rList.length > 0 ? (rList.reduce((a, b) => a + b, 0) / rList.length).toFixed(2) : '-';
        $('#kpi-range-val').text(`Avg Daily Range R: ${avgR}`);
    } else {
        $('#kpi-avg-val').text('-');
        $('#kpi-range-val').text('Avg Daily Range R: -');
    }


    // KPI 5: Reference Image Card
    $('#btn-quant-upload-img').data('checkpoint-id', cp.checkpoint_id);

    if (cp.reference_image) {
        $('#quant-cp-no-img').hide();
        let imgUrl = (cp.reference_image.indexOf('uploads/') === 0) ? cp.reference_image : ('uploads/dtc/' + cp.reference_image);
        $('#quant-cp-img-element').attr('src', imgUrl).data('full-img', imgUrl).show();

        $('#cp-img-preview-badge').html(`
            <button class="cp-thumb-img-btn" data-src="${imgUrl}" style="background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.3); color: #38bdf8; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; display:flex; align-items:center; gap:5px;">
                <i class="fa-solid fa-image"></i> View Spec Image
            </button>
        `).show();
    } else {
        $('#quant-cp-img-element').hide().attr('src', '');
        $('#quant-cp-no-img').show();
        $('#cp-img-preview-badge').hide();
    }
}

// Lightbox click for preview image badge button & KPI image element
$(document).on('click', '.cp-thumb-img-btn, .btn-preview-img', function (e) {
    e.stopPropagation();
    let src = $(this).data('src') || $(this).data('full-img') || $(this).attr('src');
    if (src) {
        Swal.fire({
            imageUrl: src,
            imageAlt: 'Checkpoint Spec Image',
            showCloseButton: true,
            showConfirmButton: false,
            background: '#0f172a',
            color: '#fff'
        });
    }
});

// === UPDATE KPI EXECUTIVE CARDS FOR QUALITATIVE VIEW ===
function updateQualKPICards(cp) {
    if (!cp) return;

    // KPI 1: Checkpoint & Spec Info
    $('#qual-kpi-cp-name').text(cp.checkpoint_name);
    $('#qual-kpi-spec-val').text(cp.spec_value || '-');

    let okCount = 0;
    let ngCount = 0;
    let totalFilled = 0;
    let totalSlots = daysInMonth * timeLabels.length;

    timeLabels.forEach(label => {
        let rowData = cp.matrix[label] || {};
        for (let d = 1; d <= daysInMonth; d++) {
            let val = rowData[d];
            if (val === 'OK') {
                okCount++;
                totalFilled++;
            } else if (val === 'NG') {
                ngCount++;
                totalFilled++;
            }
        }
    });

    // KPI 2: Inspection Results
    $('#qual-kpi-ok-count').text(okCount);
    $('#qual-kpi-ng-count').text(ngCount);
    $('#qual-kpi-total-inspections').text(`Total Checks: ${totalFilled}`);

    // KPI 3: Quality Pass Rate
    if (totalFilled > 0) {
        let passRate = ((okCount / totalFilled) * 100).toFixed(1);
        $('#qual-kpi-pass-rate').text(`${passRate}%`);
        if (parseFloat(passRate) < 100) {
            $('#qual-kpi-pass-rate').css('color', '#f87171');
        } else {
            $('#qual-kpi-pass-rate').css('color', '#10b981');
        }
    } else {
        $('#qual-kpi-pass-rate').text('100%').css('color', '#10b981');
    }

    // KPI 4: Completion Progress
    if (totalSlots > 0) {
        let progressPct = ((totalFilled / totalSlots) * 100).toFixed(1);
        $('#qual-kpi-progress').text(`${progressPct}%`);
        $('#qual-kpi-progress-detail').text(`${totalFilled} / ${totalSlots} Slots Filled`);
    } else {
        $('#qual-kpi-progress').text('0%');
        $('#qual-kpi-progress-detail').text('0 Slots');
    }
}
