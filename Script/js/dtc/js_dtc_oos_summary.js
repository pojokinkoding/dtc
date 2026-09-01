// Script/js/dtc/js_dtc_oos_summary.js

$(document).ready(function () {
    let currentTable = null;
    let currentOOSData = [];

    // Initialize month picker to current YYYY-MM
    let today = new Date();
    let defaultMonth = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, '0');
    $("#oos_month").val(defaultMonth);

    function loadOOSData() {
        let selectedMonth = $("#oos_month").val();
        if (!selectedMonth) return;

        if (currentTable) {
            currentTable.destroy();
            currentTable = null;
        }

        $.ajax({
            url: "Script/php/dtc/c_oos_summary.php",
            type: "GET",
            cache: false,
            data: { month: selectedMonth },
            dataType: "json",
            success: function (res) {
                if (res.status === 'success') {
                    currentOOSData = res.data || [];
                    let stats = res.stats || { total_oos: 0, below_lsl: 0, above_usl: 0, qualitative_ng: 0 };

                    // Update stat cards
                    $('#stat-total-oos').text(stats.total_oos || 0);
                    $('#stat-below-lsl').text(stats.below_lsl || 0);
                    $('#stat-above-usl').text(stats.above_usl || 0);
                    $('#stat-qualitative-ng').text(stats.qualitative_ng || 0);

                    populateFilters(currentOOSData);
                    renderOOSTable(currentOOSData);
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal Memuat Data!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function (xhr, status, error) {
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Gagal terhubung ke server untuk memuat data Out of Spec.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    }

    function populateFilters(data) {
        let selectedLine = $('#oos_filter_line').val();
        let selectedSec = $('#oos_filter_section').val();

        let lines = new Set();
        let sections = new Set();

        data.forEach(d => {
            if (d.line_name) lines.add(d.line_name);
            if (d.section_name) sections.add(d.section_name);
        });

        let lineHtml = '<option value="">All Lines</option>';
        Array.from(lines).sort().forEach(l => {
            lineHtml += `<option value="${l}" ${l === selectedLine ? 'selected' : ''}>${l}</option>`;
        });
        $('#oos_filter_line').html(lineHtml);

        let secHtml = '<option value="">All Sections</option>';
        Array.from(sections).sort().forEach(s => {
            secHtml += `<option value="${s}" ${s === selectedSec ? 'selected' : ''}>${s}</option>`;
        });
        $('#oos_filter_section').html(secHtml);
    }

    function renderOOSTable(data) {
        let lineFilter = $('#oos_filter_line').val();
        let secFilter = $('#oos_filter_section').val();

        let filtered = data.filter(d => {
            if (lineFilter && d.line_name !== lineFilter) return false;
            if (secFilter && d.section_name !== secFilter) return false;
            return true;
        });

        let html = '';
        let selectedMonth = $("#oos_month").val();

        filtered.forEach(row => {
            let lsl = row.lsl !== null && row.lsl !== undefined ? parseFloat(row.lsl) : null;
            let usl = row.usl !== null && row.usl !== undefined ? parseFloat(row.usl) : null;
            let minVal = row.min_value !== null && row.min_value !== undefined ? parseFloat(row.min_value) : null;
            let maxVal = row.max_value !== null && row.max_value !== undefined ? parseFloat(row.max_value) : null;

            let minHtml = (lsl !== null && minVal !== null && minVal < lsl) ? `<span class="val-danger">${row.min_value}</span>` : (row.min_value ?? '-');
            let maxHtml = (usl !== null && maxVal !== null && maxVal > usl) ? `<span class="val-danger">${row.max_value}</span>` : (row.max_value ?? '-');

            let specStr = '-';
            if (lsl !== null || usl !== null) {
                specStr = `LSL: ${lsl ?? '-'} | USL: ${usl ?? '-'}`;
            } else if (row.data_type === 'Qualitative' || row.measuring_item === 'Qualitative') {
                specStr = 'Qualitative (OK)';
            }

            let badgeBg = 'rgba(239, 68, 68, 0.2)';
            let badgeColor = '#fca5a5';
            if (row.oos_type === 'Below LSL') { badgeBg = 'rgba(245, 158, 11, 0.2)'; badgeColor = '#fbbf24'; }
            if (row.oos_type === 'Above USL') { badgeBg = 'rgba(56, 189, 248, 0.2)'; badgeColor = '#38bdf8'; }

            let detailLink = `index.php?page=dtc_detail&param_id=${row.parameter_id}&month=${selectedMonth}`;

            html += `<tr class="oos-row">
                <td>${row.inspection_date}</td>
                <td><span style="color: #f8fafc; font-weight: 700;">${row.model_name}</span></td>
                <td><span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 8px; border-radius: 4px; font-weight: 600;">${row.line_name}</span></td>
                <td><span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 8px; border-radius: 4px; font-weight: 600;">${row.section_name}</span></td>
                <td><span style="color: #cbd5e1;"><i class="fa-solid fa-gears" style="margin-right: 4px; color: #60a5fa;"></i> ${row.process_name}</span></td>
                <td style="color: #38bdf8; font-weight: bold;">${row.item_check_name} ${row.sub_item_check_name ? `<span style="font-size:10px; color:#cbd5e1;">(${row.sub_item_check_name})</span>` : ''} <span style="background:rgba(255,255,255,0.08); padding:1px 5px; border-radius:3px; font-size:10px;">${row.data_type}</span></td>
                <td style="color:#cbd5e1;">${specStr}</td>
                <td>Min: ${minHtml} <br> Max: ${maxHtml}</td>
                <td><span style="background: ${badgeBg}; color: ${badgeColor}; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">${row.oos_type}</span></td>
                <td>
                    <div style="display: flex; gap: 6px; justify-content: center;">
                        <button type="button" class="btn-update-oos-row" data-session="${row.session_id}" title="Update Pengukuran Langsung">
                            <i class="fa-solid fa-pen-to-square"></i> Update
                        </button>
                        <a href="${detailLink}" class="btn btn-sm" style="font-size: 11px; background-color: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 6px; text-decoration: none;" title="Lihat Detail Graph & Trend">
                            <i class="fa-solid fa-circle-info"></i> Detail
                        </a>
                    </div>
                </td>
            </tr>`;
        });

        $("#oos-tbody").html(html);

        currentTable = $('#oos-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search OOS Data..."
            }
        });
    }

    $("#oos_month, #oos_filter_line, #oos_filter_section").change(function () {
        if (this.id === 'oos_month') {
            loadOOSData();
        } else {
            if (currentOOSData) renderOOSTable(currentOOSData);
        }
    });

    // Handle Direct Update Button Click
    $(document).on('click', '.btn-update-oos-row', function (e) {
        e.preventDefault();
        let sessionId = $(this).data('session');
        if (!sessionId) return;

        $('#oos_edit_session_id').val(sessionId);
        $('#oos-samples-container').empty();
        $('#btn-save-oos-update').prop('disabled', true);

        $.ajax({
            url: 'Script/php/dtc/c_oos_summary.php',
            type: 'GET',
            data: { action: 'get_session_details', session_id: sessionId },
            dataType: 'json',
            success: function (res) {
                $('#btn-save-oos-update').prop('disabled', false);

                if (res.status !== 'success' || !res.session) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Gagal memuat detail sesi.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                    return;
                }

                let sess = res.session;
                let meas = res.measurements || [];

                $('#oos-info-model').text(sess.model_name || '-');
                $('#oos-info-date').text(sess.inspection_date || '-');
                $('#oos-info-linesec').text(`${sess.line_name} • ${sess.section_name}`);
                $('#oos-info-item').text(`${sess.item_check_name} (${sess.process_name})`);

                let lsl = sess.lsl !== null && sess.lsl !== undefined ? parseFloat(sess.lsl) : null;
                let usl = sess.usl !== null && sess.usl !== undefined ? parseFloat(sess.usl) : null;
                let isQualitative = (sess.measuring_item === 'Qualitative' || sess.data_type === 'Qualitative');

                let specText = '-';
                if (lsl !== null || usl !== null) {
                    specText = `LSL: ${lsl ?? '-'} | Target: ${sess.target_value ?? '-'} | USL: ${usl ?? '-'}`;
                } else if (isQualitative) {
                    specText = 'Qualitative (OK)';
                }
                $('#oos-info-spec').text(specText);
                $('#oos_edit_remarks').val(sess.remarks || '');

                let samplesHtml = '';

                meas.forEach((m, idx) => {
                    let lbl = m.sample_label || `S${idx + 1}`;
                    let val = m.sample_value !== null ? String(m.sample_value) : '';

                    if (isQualitative) {
                        let isOk = val.toUpperCase() === 'OK';
                        let isNg = val.toUpperCase() === 'NG';
                        samplesHtml += `
                            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 8px 10px; text-align: center;">
                                <label style="display: block; font-size: 11px; color: #94a3b8; margin-bottom: 4px; font-weight: 700;">${lbl}</label>
                                <select class="oos-sample-input-val form-control" data-mid="${m.measurement_id}" style="width: 100%; background: #0f172a; color: white; border: 1px solid rgba(255,255,255,0.15); font-size: 12px; font-weight: 700; border-radius: 4px; padding: 4px;">
                                    <option value="">-</option>
                                    <option value="OK" ${isOk ? 'selected' : ''}>OK</option>
                                    <option value="NG" ${isNg ? 'selected' : ''}>NG</option>
                                </select>
                            </div>
                        `;
                    } else {
                        let isOos = false;
                        if (val !== '' && !isNaN(parseFloat(val))) {
                            let num = parseFloat(val);
                            if (lsl !== null && num < lsl) isOos = true;
                            if (usl !== null && num > usl) isOos = true;
                        }
                        let borderColor = isOos ? '#ef4444' : (val !== '' ? '#10b981' : 'rgba(255,255,255,0.15)');
                        let bgColor = isOos ? 'rgba(239,68,68,0.2)' : (val !== '' ? 'rgba(16,185,129,0.15)' : 'rgba(15,23,42,0.8)');

                        samplesHtml += `
                            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 8px 10px; text-align: center;">
                                <label style="display: block; font-size: 11px; color: #94a3b8; margin-bottom: 4px; font-weight: 700;">${lbl}</label>
                                <input type="number" step="any" class="oos-sample-input-val form-control" 
                                       data-mid="${m.measurement_id}" data-lsl="${lsl ?? ''}" data-usl="${usl ?? ''}"
                                       value="${val}" placeholder="-" 
                                       style="width: 100%; background: ${bgColor}; color: white; border: 1px solid ${borderColor}; font-size: 12px; font-weight: 700; border-radius: 4px; text-align: center; padding: 4px;">
                            </div>
                        `;
                    }
                });

                $('#oos-samples-container').html(samplesHtml);
                $('body').addClass('modal-open-oos');
                if ('zoom' in document.body.style) {
                    document.body.style.zoom = 1;
                }
                document.body.style.transform = 'none';
                document.body.style.width = '100%';

                let $modal = $('#modal-quick-update-oos');
                if ($modal.length > 0 && $modal.parent()[0] !== document.body) {
                    $modal.appendTo('body');
                }
                $modal.css({ 'display': 'flex', 'z-index': '999999' });
            },
            error: function () {
                $('#btn-save-oos-update').prop('disabled', false);
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal', text: 'Server error saat mengambil data sesi.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    });

    // Live validation for sample inputs inside modal
    $(document).on('input change', '.oos-sample-input-val[type="number"]', function () {
        let valStr = $(this).val().trim();
        let lsl = $(this).data('lsl');
        let usl = $(this).data('usl');

        if (valStr === '') {
            $(this).css({ 'border-color': 'rgba(255,255,255,0.15)', 'background': 'rgba(15,23,42,0.8)' });
            return;
        }

        let num = parseFloat(valStr);
        let isOos = false;
        if (lsl !== '' && lsl !== null && num < parseFloat(lsl)) isOos = true;
        if (usl !== '' && usl !== null && num > parseFloat(usl)) isOos = true;

        if (isOos) {
            $(this).css({ 'border-color': '#ef4444', 'background': 'rgba(239,68,68,0.25)' });
        } else {
            $(this).css({ 'border-color': '#10b981', 'background': 'rgba(16,185,129,0.15)' });
        }
    });

    // Close Modal
    $(document).on('click', '#btn-close-oos-modal, #btn-cancel-oos-modal', function () {
        $('#modal-quick-update-oos').hide();
        $('body').removeClass('modal-open-oos');
        if (typeof applyFitScreen === 'function') {
            setTimeout(applyFitScreen, 60);
        }
    });

    // Save Updated Measurements
    $('#form-quick-update-oos').on('submit', function (e) {
        e.preventDefault();

        let sessionId = $('#oos_edit_session_id').val();
        let remarks = $('#oos_edit_remarks').val();

        let measurements = [];
        $('.oos-sample-input-val').each(function () {
            let mid = $(this).data('mid');
            let val = $(this).val();
            if (mid) {
                measurements.push({ measurement_id: mid, sample_value: val });
            }
        });

        $('#btn-save-oos-update').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: 'Script/php/dtc/c_oos_summary.php',
            type: 'POST',
            data: {
                action: 'save_session_update',
                session_id: sessionId,
                remarks: remarks,
                measurements: JSON.stringify(measurements)
            },
            dataType: 'json',
            success: function (res) {
                $('#btn-save-oos-update').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan');

                if (res.status === 'success') {
                    $('#modal-quick-update-oos').hide();
                    $('body').removeClass('modal-open-oos');
                    if (typeof applyFitScreen === 'function') {
                        setTimeout(applyFitScreen, 60);
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || 'Data pengukuran Out of Spec telah berhasil diperbarui.',
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#3b82f6',
                        timer: 1800
                    });
                    loadOOSData();
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', text: res.message || 'Terjadi kesalahan saat menyimpan.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function () {
                $('#btn-save-oos-update').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan');
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal', text: 'Server error saat menyimpan data.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    });

    // Initial Load
    loadOOSData();
});
