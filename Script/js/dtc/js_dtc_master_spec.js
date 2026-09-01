$(document).ready(function () {
    // 1. Initialize DataTable
    var table = $('#master-spec-table').DataTable({
        ajax: {
            url: 'Script/php/dtc/c_master_spec_list.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'spec_id' },
            { data: 'model_name' },
            { data: 'item_check_name' },
            {
                data: 'sub_item_check_name',
                render: function (data) {
                    return data ? `<span style="color:#94a3b8">${data}</span>` : '-';
                }
            },
            { data: 'data_type' },
            { data: 'line_name' },
            { data: 'section_name' },
            { data: 'process_name' },
            { data: 'measuring_item' },
            {
                data: null,
                render: function (data, type, row) {
                    let lslStr = row.lsl !== null && row.lsl !== undefined && row.lsl !== '' ? parseFloat(row.lsl).toFixed(1) : '-';
                    let uslStr = row.usl !== null && row.usl !== undefined && row.usl !== '' ? parseFloat(row.usl).toFixed(1) : '-';
                    return `LSL: ${lslStr} | USL: ${uslStr}`;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    return `${row.target_zst} / ${row.target_zlt}`;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    return `
                        <button class="btn-edit" data-id="${row.spec_id}" style="background-color: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px;"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-delete" data-id="${row.spec_id}" style="background-color: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
                    `;
                }
            }
        ],
        scrollX: true,
        order: [[0, 'desc']],
        pageLength: 10,
        lengthChange: false
    });

    // 1.5 Filter Tabs Logic
    $('.filter-tab-btn').on('click', function () {
        // Update active class
        $('.filter-tab-btn').removeClass('active');
        $(this).addClass('active');

        // Apply filter to column 4 (Data Type) since sub item is now column 3
        let filterValue = $(this).data('filter');
        if (filterValue === "") {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(filterValue).draw();
        }

        // Recalculate layout
        setTimeout(() => {
            if (table) {
                table.columns.adjust();
                if (table.responsive) table.responsive.recalc();
            }
        }, 50);
    });

    let masterSpecsList = [];

    function updateSectionFilterOptions() {
        let selectedLine = $('#filter-line').val();
        let selectedSection = $('#filter-section').val();

        let filteredSpecs = masterSpecsList;
        if (selectedLine) {
            filteredSpecs = filteredSpecs.filter(s => s.line_name === selectedLine);
        }

        let availableSections = [...new Set(filteredSpecs.map(s => s.section_name).filter(Boolean))].sort();

        let sectionOpts = '<option value="">All Sections</option>';
        availableSections.forEach(sec => {
            sectionOpts += `<option value="${sec}">${sec}</option>`;
        });
        $('#filter-section').html(sectionOpts);

        if (selectedSection && availableSections.includes(selectedSection)) {
            $('#filter-section').val(selectedSection);
        } else {
            $('#filter-section').val('');
        }

        updateItemCheckFilterOptions();
    }

    function updateItemCheckFilterOptions() {
        let selectedLine = $('#filter-line').val();
        let selectedSection = $('#filter-section').val();
        let selectedItemCheck = $('#filter-item-check').val();

        let filteredSpecs = masterSpecsList;
        if (selectedLine) {
            filteredSpecs = filteredSpecs.filter(s => s.line_name === selectedLine);
        }
        if (selectedSection) {
            filteredSpecs = filteredSpecs.filter(s => s.section_name === selectedSection);
        }

        let availableItemChecks = [...new Set(filteredSpecs.map(s => s.item_check_name).filter(Boolean))].sort();

        let itemCheckOpts = '<option value="">All Item Checks</option>';
        availableItemChecks.forEach(ic => {
            itemCheckOpts += `<option value="${ic}">${ic}</option>`;
        });
        $('#filter-item-check').html(itemCheckOpts);

        if (selectedItemCheck && availableItemChecks.includes(selectedItemCheck)) {
            $('#filter-item-check').val(selectedItemCheck);
        } else {
            $('#filter-item-check').val('');
        }
    }

    // Custom filtering for Line, Section, and Item Check
    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex, rowData) {
            let filterLine = $('#filter-line').val();
            let filterSection = $('#filter-section').val();
            let filterItemCheck = $('#filter-item-check').val();

            if (filterLine && rowData.line_name !== filterLine) return false;
            if (filterSection && rowData.section_name !== filterSection) return false;
            if (filterItemCheck && rowData.item_check_name !== filterItemCheck) return false;

            return true;
        }
    );

    $('#filter-line').on('change', function () {
        updateSectionFilterOptions();
        if (table) table.draw();
    });

    $('#filter-section').on('change', function () {
        updateItemCheckFilterOptions();
        if (table) table.draw();
    });

    $('#filter-item-check').on('change', function () {
        if (table) table.draw();
    });

    // 2. Modal Logic
    const modal = document.getElementById('modal-master-spec');
    const btnAdd = document.getElementById('btn-add-spec');
    const btnClose = document.getElementById('btn-close-modal');
    const btnCancel = document.getElementById('btn-cancel-modal');
    const formAdd = document.getElementById('form-master-spec');

    function formatDec1(val) {
        if (val === null || val === undefined || val === '') return '';
        let num = parseFloat(val);
        return isNaN(num) ? '' : num.toFixed(1);
    }

    function isCheckpointType() {
        const type = String($('#data_type').val() || '').toUpperCase();
        return type === 'TIME CHECK' || type === 'F/PROOF';
    }

    function masterCheckpointRow(index, checkpoint = {}) {
        const imageText = checkpoint.reference_image ? `<a href="${checkpoint.reference_image}" target="_blank" style="color:#60a5fa;">Current image</a>` : '';
        const checkpointType = checkpoint.checkpoint_type || 'Qualitative';
        let tolVal = '';
        if (checkpoint.target_value !== undefined && checkpoint.target_value !== null && checkpoint.target_value !== '' &&
            checkpoint.usl !== undefined && checkpoint.usl !== null && checkpoint.usl !== '') {
            let t = parseFloat(checkpoint.target_value);
            let u = parseFloat(checkpoint.usl);
            if (!isNaN(t) && !isNaN(u) && u >= t) {
                tolVal = formatDec1(u - t);
            }
        }
        return `<tr data-existing-image="${checkpoint.reference_image || ''}">
            <td class="master-cp-number" style="padding:6px 4px; text-align:center; color:var(--text-muted); font-weight:bold;"></td>
            <td style="padding:6px 4px;"><input class="form-control master-cp-name" value="${checkpoint.checkpoint_name || ''}" placeholder="e.g. Suhu / Dimensi" style="width:100%; padding:6px 8px; font-size:11.5px;"></td>
            <td style="padding:6px 4px;"><select class="form-control master-cp-type" style="width:100%; padding:6px; font-size:11px;"><option value="Qualitative" ${checkpointType === 'Qualitative' ? 'selected' : ''}>Qualitative</option><option value="Quantitative" ${checkpointType === 'Quantitative' ? 'selected' : ''}>Quantitative</option></select></td>
            <td style="padding:6px 4px;"><input class="form-control master-cp-spec" value="${checkpoint.spec_value || ''}" placeholder="e.g. OK / Max 5s" style="width:100%; padding:6px 8px; font-size:11.5px;"></td>
            <td style="padding:6px 4px;"><input type="number" step="0.1" class="form-control master-cp-target" value="${formatDec1(checkpoint.target_value)}" placeholder="Target" style="width:100%; text-align:center; padding:6px 4px; font-size:11.5px; color:#34d399; font-weight:bold;"></td>
            <td style="padding:6px 4px;"><input type="number" step="0.1" class="form-control master-cp-tol" value="${tolVal}" placeholder="±" title="Toleransi (±) untuk menghitung LSL & USL otomatis" style="width:100%; text-align:center; padding:6px 4px; font-size:11.5px; border-color:rgba(167,139,250,0.6); color:#a78bfa;"></td>
            <td style="padding:6px 4px;"><input type="number" step="0.1" class="form-control master-cp-lsl" value="${formatDec1(checkpoint.lsl)}" placeholder="LSL" style="width:100%; text-align:center; padding:6px 4px; font-size:11.5px; color:#f87171;"></td>
            <td style="padding:6px 4px;"><input type="number" step="0.1" class="form-control master-cp-usl" value="${formatDec1(checkpoint.usl)}" placeholder="USL" style="width:100%; text-align:center; padding:6px 4px; font-size:11.5px; color:#60a5fa;"></td>
            <td style="padding:6px 4px;"><input type="file" accept="image/png,image/jpeg,image/gif" class="master-cp-image" style="font-size:10px; width:100%; max-width:160px;"><div class="master-cp-current-image" style="font-size:10px; margin-top:2px;">${imageText}</div></td>
            <td style="padding:6px 4px; text-align:center;"><button type="button" class="btn-remove-master-cp" title="Hapus checkpoint" style="color:#f87171; background:none; border:0; cursor:pointer; font-size:13px; padding:4px;"><i class="fa-solid fa-trash"></i></button></td>
        </tr>`;
    }

    function renumberMasterCheckpointRows() {
        $('#master-checkpoint-tbody tr').each(function (index) {
            $(this).find('.master-cp-number').text(index + 1);
            $(this).find('.master-cp-image').attr('name', `checkpoint_images[${index}]`);
        });
    }

    function syncMasterCheckpointTypeRow($row) {
        const isQuantitative = $row.find('.master-cp-type').val() === 'Quantitative';
        const $limits = $row.find('.master-cp-lsl, .master-cp-target, .master-cp-tol, .master-cp-usl');
        $limits.prop('disabled', !isQuantitative);
        if (!isQuantitative) $limits.val('');
        $limits.css('opacity', isQuantitative ? '1' : '0.4');
    }

    // Auto calculate LSL & USL from Target and Tolerance in checkpoint row
    $(document).on('input', '.master-cp-target, .master-cp-tol', function () {
        const $row = $(this).closest('tr');
        const targetStr = $row.find('.master-cp-target').val();
        const tolStr = $row.find('.master-cp-tol').val();
        if (targetStr !== '' && tolStr !== '') {
            const target = parseFloat(targetStr);
            const tol = parseFloat(tolStr);
            if (!isNaN(target) && !isNaN(tol)) {
                const lslVal = (target - tol).toFixed(1);
                const uslVal = (target + tol).toFixed(1);
                $row.find('.master-cp-lsl').val(lslVal);
                $row.find('.master-cp-usl').val(uslVal);

                const $spec = $row.find('.master-cp-spec');
                if (!$spec.val().trim() || $spec.val().includes('±') || $spec.val().includes('+/-')) {
                    $spec.val(`${target} ± ${tol}`);
                }
            }
        }
    });

    // Auto calculate LSL & USL in Single Quant Spec Section (Target ± Tolerance)
    $('#target_value, #quant_tolerance').on('input', function () {
        const targetStr = $('#target_value').val();
        const tolStr = $('#quant_tolerance').val();
        if (targetStr !== '' && tolStr !== '') {
            const target = parseFloat(targetStr);
            const tol = parseFloat(tolStr);
            if (!isNaN(target) && !isNaN(tol)) {
                $('#lsl').val((target - tol).toFixed(1));
                $('#usl').val((target + tol).toFixed(1));
            }
        }
    });

    function resetMasterCheckpoints() {
        $('#master-checkpoint-tbody').html(masterCheckpointRow(0));
        renumberMasterCheckpointRows();
        syncMasterCheckpointTypeRow($('#master-checkpoint-tbody tr').first());
    }

    function syncSpecFormByType() {
        const checkpoints = isCheckpointType();
        $('#quant-spec-section').toggle(!checkpoints);
        $('#master-checkpoint-section').toggle(checkpoints);
        if (checkpoints) {
            $('#measuring_item').val('Qualitative');
            if (!$('#master-checkpoint-tbody tr').length) resetMasterCheckpoints();
        } else {
            $('#measuring_item').val('Quantitative');
        }
    }

    function loadMasterSpecCheckpoints(specId) {
        if (!specId) return resetMasterCheckpoints();
        $.getJSON('Script/php/dtc/c_master_spec_checkpoints.php', { spec_id: specId }, function (res) {
            $('#master-checkpoint-tbody').empty();
            (res.status === 'success' ? res.data : []).forEach((cp, index) => $('#master-checkpoint-tbody').append(masterCheckpointRow(index, cp)));
            if (!$('#master-checkpoint-tbody tr').length) $('#master-checkpoint-tbody').append(masterCheckpointRow(0));
            renumberMasterCheckpointRows();
            $('#master-checkpoint-tbody tr').each(function () { syncMasterCheckpointTypeRow($(this)); });
        });
    }

    $('#data_type').on('change', syncSpecFormByType);
    $(document).on('click', '#btn-add-master-cp-row', function () {
        $('#master-checkpoint-tbody').append(masterCheckpointRow($('#master-checkpoint-tbody tr').length));
        renumberMasterCheckpointRows();
        syncMasterCheckpointTypeRow($('#master-checkpoint-tbody tr').last());
    });
    $(document).on('click', '.btn-remove-master-cp', function () {
        $(this).closest('tr').remove();
        if (!$('#master-checkpoint-tbody tr').length) $('#master-checkpoint-tbody').append(masterCheckpointRow(0));
        renumberMasterCheckpointRows();
        $('#master-checkpoint-tbody tr').each(function () { syncMasterCheckpointTypeRow($(this)); });
    });
    $(document).on('change', '.master-cp-type', function () {
        syncMasterCheckpointTypeRow($(this).closest('tr'));
    });

    // Load dropdown options from API
    function loadSelectOptions(callback = null) {
        $.ajax({
            url: 'Script/php/dtc/c_dtc_master_data.php',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.dtc_categories) {
                    let opts = '<option value="">-- Select Data Type --</option>';
                    opts += '<option value="CTQ">CTQ</option>';
                    opts += '<option value="CTP">CTP</option>';
                    opts += '<option value="Time Check">Time Check</option>';
                    opts += '<option value="F/Proof">F/Proof</option>';
                    $('#data_type').html(opts);
                }
                if (res.lines) {
                    let opts = '<option value="">-- Select Line --</option>';
                    let filterOpts = '<option value="">All Lines</option>';
                    res.lines.forEach(l => {
                        opts += `<option value="${l.line_name}">${l.line_name}</option>`;
                        filterOpts += `<option value="${l.line_name}">${l.line_name}</option>`;
                    });
                    $('#line_name').html(opts);
                    $('#filter-line').html(filterOpts);
                }
                if (res.sections) {
                    let opts = '<option value="">-- Select Section --</option>';
                    res.sections.forEach(s => {
                        opts += `<option value="${s.section_name}">${s.section_name}</option>`;
                    });
                    $('#section_name').html(opts);
                }
                if (res.specs) {
                    masterSpecsList = res.specs;
                    updateSectionFilterOptions();
                }
                if (callback) callback();
            }
        });
    }

    // Initial load of dropdowns
    loadSelectOptions();

    btnAdd.onclick = function () {
        formAdd.reset();
        $('#spec_id').val('');
        $('#quant_tolerance').val('');
        resetMasterCheckpoints();
        syncSpecFormByType();
        $('#modal-title').text('Add Master Spec');
        modal.style.display = 'flex';
    }

    btnClose.onclick = function () { modal.style.display = 'none'; }
    btnCancel.onclick = function () { modal.style.display = 'none'; }
    window.onclick = function (event) { if (event.target == modal) { modal.style.display = "none"; } }

    // Edit Button Click
    $('#master-spec-table tbody').on('click', '.btn-edit', function () {
        let data = table.row($(this).parents('tr')).data();

        $('#spec_id').val(data.spec_id);
        $('#model_name').val(data.model_name);
        $('#item_check_name').val(data.item_check_name);
        $('#sub_item_check_name').val(data.sub_item_check_name);
        $('#data_type').val(data.data_type);
        $('#line_name').val(data.line_name);
        $('#section_name').val(data.section_name);
        $('#process_name').val(data.process_name);
        $('#measuring_item').val(data.measuring_item);
        $('#lsl').val(formatDec1(data.lsl));
        $('#usl').val(formatDec1(data.usl));
        $('#target_value').val(formatDec1(data.target_value));
        
        let quantTol = '';
        if (data.target_value !== null && data.target_value !== undefined && data.target_value !== '' &&
            data.usl !== null && data.usl !== undefined && data.usl !== '') {
            let t = parseFloat(data.target_value);
            let u = parseFloat(data.usl);
            if (!isNaN(t) && !isNaN(u) && u >= t) {
                quantTol = formatDec1(u - t);
            }
        }
        $('#quant_tolerance').val(quantTol);

        $('#uom').val(data.uom);
        $('#target_zst').val(data.target_zst !== null && data.target_zst !== undefined ? parseFloat(data.target_zst).toFixed(2) : '3.00');
        $('#target_zlt').val(data.target_zlt !== null && data.target_zlt !== undefined ? parseFloat(data.target_zlt).toFixed(2) : '4.00');
        syncSpecFormByType();
        if (String(data.data_type).toUpperCase() === 'TIME CHECK' || String(data.data_type).toUpperCase() === 'F/PROOF') {
            loadMasterSpecCheckpoints(data.spec_id);
        }

        $('#modal-title').text('Edit Master Spec');
        modal.style.display = 'flex';
    });

    // Delete Button Click
    $('#master-spec-table tbody').on('click', '.btn-delete', function () {
        let spec_id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_master_spec_delete.php',
                    type: 'POST',
                    data: { spec_id: spec_id },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire('Deleted!', response.message, 'success');
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Form Submit (Save / Update)
    $('#form-master-spec').submit(function (e) {
        e.preventDefault();

        const modelName = ($('#model_name').val() || '').trim();
        const itemCheckName = ($('#item_check_name').val() || '').trim();
        const dataType = ($('#data_type').val() || '').trim();
        const lineName = ($('#line_name').val() || '').trim();
        const sectionName = ($('#section_name').val() || '').trim();
        const processName = ($('#process_name').val() || '').trim();

        if (!modelName || !itemCheckName || !dataType || !lineName || !sectionName || !processName) {
            Swal.fire('Error', 'Mohon lengkapi semua field Basic Information yang wajib diisi (*)', 'warning');
            return;
        }

        let btn = $('#btn-save-spec');
        const formData = new FormData(this);

        if (!isCheckpointType()) {
            const targetVal = ($('#target_value').val() || '').trim();
            const lslVal = ($('#lsl').val() || '').trim();
            const uslVal = ($('#usl').val() || '').trim();
            if (targetVal === '' || lslVal === '' || uslVal === '') {
                Swal.fire('Error', 'Mohon isi Target Value, LSL, dan USL', 'warning');
                return;
            }
            if (parseFloat(lslVal) > parseFloat(uslVal)) {
                Swal.fire('Error', 'Nilai LSL tidak boleh lebih besar dari USL', 'warning');
                return;
            }
        } else {
            let checkpoints = [];
            let invalid = false;
            $('#master-checkpoint-tbody tr').each(function (index) {
                const name = $(this).find('.master-cp-name').val().trim();
                if (!name) { invalid = true; return false; }
                const lsl = $(this).find('.master-cp-lsl').val();
                const target = $(this).find('.master-cp-target').val();
                const usl = $(this).find('.master-cp-usl').val();
                if (lsl !== '' && usl !== '' && Number(lsl) > Number(usl)) { invalid = true; return false; }
                checkpoints.push({ checkpoint_name: name, checkpoint_type: $(this).find('.master-cp-type').val(), spec_value: $(this).find('.master-cp-spec').val().trim(), lsl, target_value: target, usl, image_index: index, reference_image: $(this).data('existing-image') || '' });
            });
            if (invalid || !checkpoints.length) {
                Swal.fire('Error', 'Minimal satu checkpoint wajib diisi dan LSL tidak boleh lebih besar dari USL.', 'error');
                return;
            }
            formData.append('checkpoints', JSON.stringify(checkpoints));
        }

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'Script/php/dtc/c_master_spec_save.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Spec');
                if (response.status === 'success') {
                    Swal.fire('Success!', response.message, 'success');
                    modal.style.display = 'none';
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Spec');
                Swal.fire('Error', 'Failed to connect to server', 'error');
            }
        });
    });
});
