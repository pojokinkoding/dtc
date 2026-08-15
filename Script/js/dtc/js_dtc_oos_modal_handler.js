// Script/js/dtc/js_dtc_oos_modal_handler.js

$(document).ready(function () {

    function getCpRemark(fullRemarks, cpName) {
        if (!fullRemarks) return '';
        let remStr = String(fullRemarks).trim();
        if (!cpName) return remStr;

        let escapedName = cpName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        let regex = new RegExp('\\[\\s*' + escapedName + '\\s*\\]\\s*:\\s*([^;]+)', 'i');
        let match = remStr.match(regex);
        if (match) {
            return match[1].trim();
        }

        // Fallback: if no [CP]: format exists and only 1 CP, return full text
        if (remStr.indexOf('[') === -1) {
            return remStr;
        }
        return '';
    }

    // Open OOS Quick Update Modal when clicking total OOS badge
    $(document).on('click', '.btn-open-oos-modal', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof window.currentIsAdmin !== 'undefined' && !window.currentIsAdmin) {
            Swal.fire({
                icon: 'warning',
                title: 'Akses Ditolak',
                text: 'Fitur Quick Update Out of Spec ini hanya dapat diakses oleh Admin.',
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444',
                didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
            });
            return;
        }

        let paramId = $(this).data('param');
        let dateVal = $(this).data('date') || '';
        let monthVal = $(this).data('month') || $('#filter-month').val() || '';

        if (!paramId) return;

        $('#oos_param_id').val(paramId);
        $('#oos_param_month').val(monthVal);

        $('#oos-banner-model').text('-');
        $('#oos-banner-linesec').text('-');
        $('#oos-banner-item').text('-');
        $('#oos-banner-spec').text('-');
        $('#oos-param-img-container').hide();

        $('#oos-sessions-container').empty();
        $('#oos-sessions-empty').hide();
        $('#oos-sessions-loading').show();
        $('#btn-save-oos-param-modal').prop('disabled', true);

        let $modal = $('#modal-oos-param-update');
        if ($modal.length > 0 && $modal.parent()[0] !== document.body) {
            $modal.appendTo('body');
        }
        $modal.css({ 'display': 'flex', 'z-index': '9999' });

        $.ajax({
            url: 'Script/php/dtc/c_oos_summary.php',
            type: 'GET',
            data: {
                action: 'get_param_oos',
                parameter_id: paramId,
                month: monthVal
            },
            dataType: 'json',
            success: function (res) {
                $('#oos-sessions-loading').hide();

                if (res.status !== 'success' || !res.parameter) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'Gagal memuat sampel Out of Spec.',
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#ef4444',
                        didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
                    });
                    return;
                }

                let p = res.parameter;
                let sessions = res.sessions || [];

                $('#oos-banner-model').text(p.model_name || '-');
                $('#oos-banner-linesec').text(`${p.line_name || '-'} • ${p.section_name || '-'}`);
                $('#oos-banner-item').text(`${p.item_check_name || '-'} (${p.process_name || '-'})`);



                let lsl = p.lsl !== null && p.lsl !== undefined ? parseFloat(p.lsl) : null;
                let usl = p.usl !== null && p.usl !== undefined ? parseFloat(p.usl) : null;
                let isQualitative = (p.measuring_item === 'Qualitative' || p.data_type === 'Qualitative');

                let specText = '-';
                if (lsl !== null || usl !== null) {
                    specText = `LSL: ${lsl ?? '-'} | Target: ${p.target_value ?? '-'} | USL: ${usl ?? '-'}`;
                } else if (isQualitative) {
                    specText = 'Qualitative (OK)';
                }
                $('#oos-banner-spec').text(specText);

                if (sessions.length === 0) {
                    $('#oos-sessions-empty').show();
                    return;
                }

                $('#btn-save-oos-param-modal').prop('disabled', false);

                let html = '';
                sessions.forEach((sess, sIdx) => {
                    html += `
                        <div class="oos-session-block" data-sid="${sess.session_id}" style="background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                                <div style="font-size: 13px; font-weight: 700; color: #38bdf8;">
                                    <i class="fa-regular fa-clock" style="margin-right: 5px;"></i> Tanggal Inspeksi: ${sess.inspection_date_str || sess.inspection_date}
                                </div>
                                <span style="font-size: 10px; background: rgba(239,68,68,0.2); color: #fca5a5; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                                    Sesi ${sIdx + 1}
                                </span>
                            </div>
                    `;

                    // Group measurements by Checkpoint
                    let cpGroups = {};
                    (sess.measurements || []).forEach(m => {
                        let cpId = m.checkpoint_id || 0;
                        let cpKey = cpId ? `cp_${cpId}` : 'default';
                        if (!cpGroups[cpKey]) {
                            cpGroups[cpKey] = {
                                id: cpId,
                                name: m.checkpoint_name || '',
                                image: m.cp_reference_image || null,
                                lsl: m.lsl,
                                usl: m.usl,
                                target: m.target_value,
                                measurements: []
                            };
                        }
                        cpGroups[cpKey].measurements.push(m);
                    });

                    let cpKeys = Object.keys(cpGroups);
                    cpKeys.forEach((cpKey, cpIdx) => {
                        let grp = cpGroups[cpKey];
                        let cpTitle = grp.name ? `Check Point: ${grp.name}` : (cpKeys.length > 1 ? `Check Point ${cpIdx + 1}` : 'Sampel Pengukuran');

                        let sampleLsl = grp.lsl !== null && grp.lsl !== undefined && grp.lsl !== '' ? parseFloat(grp.lsl) : null;
                        let sampleUsl = grp.usl !== null && grp.usl !== undefined && grp.usl !== '' ? parseFloat(grp.usl) : null;

                        let cpSpecBadge = '';
                        if (sampleLsl !== null || sampleUsl !== null) {
                            cpSpecBadge = `<span style="font-size: 11px; background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); padding: 2px 8px; border-radius: 4px; font-weight: 700; margin-left: 8px;">Spec: ${sampleLsl ?? '-'} ~ ${sampleUsl ?? '-'}</span>`;
                        } else {
                            cpSpecBadge = `<span style="font-size: 11px; background: rgba(56,189,248,0.15); color: #38bdf8; border: 1px solid rgba(56,189,248,0.3); padding: 2px 8px; border-radius: 4px; font-weight: 700; margin-left: 8px;">Kualitatif (Visual OK/NG)</span>`;
                        }

                        let cpImgCardHtml = '';
                        if (grp.image) {
                            let cpImgUrl = (grp.image.indexOf('/') >= 0 || grp.image.indexOf('uploads/') === 0) 
                                ? grp.image 
                                : ('uploads/dtc/' + grp.image);
                            cpImgCardHtml = `
                                <div style="width: 200px; flex-shrink: 0; background: rgba(15,23,42,0.9); border: 1px solid rgba(255,255,255,0.12); border-radius: 6px; padding: 6px; text-align: center;">
                                    <img src="${cpImgUrl}" class="oos-cp-thumb-img" 
                                         style="width: 100%; height: 135px; border-radius: 4px; object-fit: contain; cursor: pointer; transition: transform 0.15s;" 
                                         title="Klik untuk melihat gambar dalam ukuran penuh">
                                    <div style="font-size: 9px; color: #38bdf8; margin-top: 4px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i> 
                                        <span>Perbesar Gambar</span>
                                    </div>
                                </div>
                            `;
                        } else {
                            cpImgCardHtml = `
                                <div style="width: 200px; height: 160px; flex-shrink: 0; background: rgba(15,23,42,0.4); border: 1px dashed rgba(255,255,255,0.15); border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748b; text-align: center; padding: 10px;">
                                    <i class="fa-regular fa-image" style="font-size: 26px; margin-bottom: 6px; color: #475569;"></i>
                                    <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">No Picture</span>
                                    <span style="font-size: 9px; color: #64748b; margin-top: 2px;">Tidak Ada Gambar Referensi</span>
                                </div>
                            `;
                        }

                        let cpRemarkVal = getCpRemark(sess.remarks, grp.name);

                        html += `
                            <div style="background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                                <div style="font-size: 12px; font-weight: 700; color: #f8fafc; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-layer-group" style="color: #60a5fa;"></i> 
                                        <span>${cpTitle}</span>
                                        ${cpSpecBadge}
                                    </div>
                                </div>

                                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-start;">
                                    ${cpImgCardHtml}

                                    <div style="flex: 1; min-width: 240px;">
                                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 8px; margin-bottom: 8px;">
                        `;

                        grp.measurements.sort((a, b) => (a.sample_label || '').localeCompare(b.sample_label || ''));

                        grp.measurements.forEach((m, mIdx) => {
                            let lbl = m.sample_label || `Jam ${m.sample_sequence || (mIdx + 1)}`;
                            let val = m.sample_value !== null ? String(m.sample_value).trim() : '';

                            let isNumericSample = (sampleLsl !== null || sampleUsl !== null) || (!isNaN(parseFloat(val)) && val !== '');

                            if (!isNumericSample) {
                                let isOk = val.toUpperCase() === 'OK';
                                let isNg = val.toUpperCase() === 'NG';
                                let selBorder = isNg ? '#ef4444' : (isOk ? '#10b981' : 'rgba(255,255,255,0.15)');
                                let selBg = isNg ? 'rgba(239,68,68,0.25)' : (isOk ? 'rgba(16,185,129,0.15)' : '#0f172a');

                                html += `
                                    <div style="background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 6px; text-align: center;">
                                        <label style="display: block; font-size: 10px; color: #94a3b8; margin-bottom: 3px; font-weight: 700;">${lbl}</label>
                                        <select class="oos-sample-val-input form-control" data-mid="${m.measurement_id}" style="width: 100%; background: ${selBg}; color: white; border: 1px solid ${selBorder}; font-size: 11px; font-weight: 700; border-radius: 4px; padding: 3px;">
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
                                    if (sampleLsl !== null && !isNaN(sampleLsl) && num < sampleLsl) isOos = true;
                                    if (sampleUsl !== null && !isNaN(sampleUsl) && num > sampleUsl) isOos = true;
                                }
                                let borderColor = isOos ? '#ef4444' : (val !== '' ? '#10b981' : 'rgba(255,255,255,0.15)');
                                let bgColor = isOos ? 'rgba(239,68,68,0.25)' : (val !== '' ? 'rgba(16,185,129,0.15)' : 'rgba(15,23,42,0.8)');

                                html += `
                                    <div style="background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 6px; text-align: center;">
                                        <label style="display: block; font-size: 10px; color: #94a3b8; margin-bottom: 3px; font-weight: 700;">${lbl}</label>
                                        <input type="number" step="any" class="oos-sample-val-input form-control" 
                                               data-mid="${m.measurement_id}" data-lsl="${sampleLsl ?? ''}" data-usl="${sampleUsl ?? ''}"
                                               value="${val}" placeholder="-" 
                                               style="width: 100%; background: ${bgColor}; color: white; border: 1px solid ${borderColor}; font-size: 11px; font-weight: 700; border-radius: 4px; text-align: center; padding: 3px;">
                                    </div>
                                `;
                            }
                        });

                        html += `
                                        </div>
                                        <div style="margin-top: 6px; border-top: 1px dashed rgba(255,255,255,0.06); padding-top: 6px;">
                                            <label style="display: block; font-size: 10px; color: #94a3b8; margin-bottom: 3px; font-weight: 600;">
                                                <i class="fa-regular fa-comment-dots" style="color: #38bdf8;"></i> Catatan / Actions (${grp.name || 'Check Point'}):
                                            </label>
                                            <input type="text" class="oos-cp-remarks-input form-control" 
                                                   data-cpid="${grp.id}" data-cpname="${grp.name}" 
                                                   value="${cpRemarkVal}" 
                                                   placeholder="Tuliskan catatan/penyebab Out of Spec untuk ${grp.name || 'check point ini'}..." 
                                                   style="width: 100%; background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.1); color: white; font-size: 11px; border-radius: 4px; padding: 4px 8px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += `
                        </div>
                    `;
                });

                $('#oos-sessions-container').html(html);
            },
            error: function () {
                $('#oos-sessions-loading').hide();
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    text: 'Gagal mengambil data dari server.',
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444',
                    didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
                });
            }
        });
    });

    // Preview parameter or checkpoint reference image full view
    $(document).on('click', '#oos-param-img, .oos-cp-thumb-img', function () {
        let imgSrc = $(this).attr('src');
        if (imgSrc) {
            Swal.fire({
                imageUrl: imgSrc,
                imageAlt: 'Gambar Referensi Spesifikasi',
                background: '#0f172a',
                color: '#f8fafc',
                confirmButtonColor: '#38bdf8',
                confirmButtonText: 'Tutup',
                didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
            });
        }
    });

    // Live color validation for sample inputs inside modal (number inputs)
    $(document).on('input change', 'input.oos-sample-val-input[type="number"]', function () {
        let valStr = $(this).val().trim();
        let lsl = $(this).data('lsl');
        let usl = $(this).data('usl');

        if (valStr === '') {
            $(this).css({ 'border-color': 'rgba(255,255,255,0.15)', 'background': 'rgba(15,23,42,0.8)' });
            return;
        }

        let num = parseFloat(valStr);
        let isOos = false;
        if (lsl !== '' && lsl !== null && !isNaN(parseFloat(lsl)) && num < parseFloat(lsl)) isOos = true;
        if (usl !== '' && usl !== null && !isNaN(parseFloat(usl)) && num > parseFloat(usl)) isOos = true;

        if (isOos) {
            $(this).css({ 'border-color': '#ef4444', 'background': 'rgba(239,68,68,0.25)' });
        } else {
            $(this).css({ 'border-color': '#10b981', 'background': 'rgba(16,185,129,0.15)' });
        }
    });

    // Live color validation for qualitative dropdown selects inside modal
    $(document).on('change', 'select.oos-sample-val-input', function () {
        let val = $(this).val().toUpperCase();
        if (val === 'NG') {
            $(this).css({ 'border-color': '#ef4444', 'background': 'rgba(239,68,68,0.25)' });
        } else if (val === 'OK') {
            $(this).css({ 'border-color': '#10b981', 'background': 'rgba(16,185,129,0.15)' });
        } else {
            $(this).css({ 'border-color': 'rgba(255,255,255,0.15)', 'background': '#0f172a' });
        }
    });

    // Close Modal
    $(document).on('click', '.btn-close-oos-param-modal', function () {
        $('#modal-oos-param-update').hide();
    });

    // Submit Save for all modified sessions
    $(document).on('click', '#btn-save-oos-param-modal', function (e) {
        e.preventDefault();

        let sessionDataList = [];
        $('.oos-session-block').each(function () {
            let sid = $(this).data('sid');
            let measurements = [];

            $(this).find('.oos-sample-val-input').each(function () {
                let mid = $(this).data('mid');
                let val = $(this).val();
                if (mid) {
                    measurements.push({ measurement_id: mid, sample_value: val });
                }
            });

            let cpRemarks = [];
            $(this).find('.oos-cp-remarks-input').each(function () {
                let cpName = $(this).data('cpname');
                let remVal = $(this).val().trim();
                if (remVal) {
                    cpRemarks.push(cpName ? `[${cpName}]: ${remVal}` : remVal);
                }
            });
            let combinedRemarks = cpRemarks.join('; ');

            if (sid) {
                sessionDataList.push({
                    session_id: sid,
                    remarks: combinedRemarks,
                    measurements: measurements
                });
            }
        });

        if (sessionDataList.length === 0) {
            $('#modal-oos-param-update').hide();
            return;
        }

        // Check if any sample is STILL Out of Spec
        let stillOOSCount = 0;
        $('.oos-sample-val-input').each(function () {
            let val = $(this).val().trim();
            let isSelect = $(this).is('select');
            if (isSelect) {
                if (val.toUpperCase() === 'NG') stillOOSCount++;
            } else if (val !== '' && !isNaN(parseFloat(val))) {
                let num = parseFloat(val);
                let lsl = $(this).data('lsl');
                let usl = $(this).data('usl');
                if (lsl !== '' && lsl !== null && !isNaN(parseFloat(lsl)) && num < parseFloat(lsl)) stillOOSCount++;
                if (usl !== '' && usl !== null && !isNaN(parseFloat(usl)) && num > parseFloat(usl)) stillOOSCount++;
            }
        });

        if (stillOOSCount > 0) {
            Swal.fire({
                title: 'Data Masih Out of Spec!',
                text: `Terdapat ${stillOOSCount} sampel yang nilainya masih Out of Spec (OOS). Apakah Anda yakin ingin tetap menyimpan perubahan ini?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Tetap Simpan',
                cancelButtonText: 'Batal',
                background: '#1e293b',
                color: '#f8fafc',
                didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
            }).then((result) => {
                if (result.isConfirmed) {
                    executeSaveOOSData(sessionDataList);
                }
            });
        } else {
            executeSaveOOSData(sessionDataList);
        }
    });

    function executeSaveOOSData(sessionDataList) {
        $('#btn-save-oos-param-modal').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: 'Script/php/dtc/c_oos_summary.php',
            type: 'POST',
            data: {
                action: 'save_param_oos_update',
                sessions: JSON.stringify(sessionDataList)
            },
            dataType: 'json',
            success: function (res) {
                $('#btn-save-oos-param-modal').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan');

                if (res.status === 'success') {
                    $('#modal-oos-param-update').hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || 'Nilai sampel Out of Spec telah berhasil diperbarui.',
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#3b82f6',
                        timer: 1800,
                        didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
                    });

                    // Redraw / reload active DataTable (either in dtc_list or dtc_history)
                    if (typeof $.fn.DataTable !== 'undefined') {
                        $('.dataTable').each(function () {
                            if ($.fn.DataTable.isDataTable(this)) {
                                let dt = $(this).DataTable();
                                if (dt.ajax && typeof dt.ajax.reload === 'function') {
                                    dt.ajax.reload(null, false);
                                } else {
                                    dt.draw(false);
                                }
                            }
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: res.message || 'Terjadi kesalahan saat menyimpan.',
                        background: '#1e293b',
                        color: '#f8fafc',
                        confirmButtonColor: '#ef4444',
                        didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
                    });
                }
            },
            error: function () {
                $('#btn-save-oos-param-modal').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan');
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    text: 'Server error saat menyimpan data.',
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#ef4444',
                    didOpen: () => { $('.swal2-container').css('z-index', '9999999'); }
                });
            }
        });
    }
});
