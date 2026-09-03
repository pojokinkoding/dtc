$(document).ready(function () {
    // 1. Initialize DataTable
    var table = $('#master-spec-table').DataTable({
        ajax: {
            url: 'Script/php/dtc/c_master_spec_list.php',
            dataSrc: function (json) {
                let data = json.data || [];
                updateMasterSpecSummary(data);
                return data;
            }
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
                    return `${row.target_zst} / ${row.target_zlt}`;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    return `
                        <button class="btn-edit" data-id="${row.spec_id}" title="Edit Spec" style="background-color: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px;"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-copy" data-id="${row.spec_id}" title="Copy Spec" style="background-color: #10b981; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px;"><i class="fa-solid fa-copy"></i></button>
                        <button class="btn-delete" data-id="${row.spec_id}" title="Delete Spec" style="background-color: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
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
    $('.dtc-filter-tabs .filter-tab-btn').on('click', function () {
        // Update active class
        $('.dtc-filter-tabs .filter-tab-btn').removeClass('active');
        $(this).addClass('active');

        // Apply filter to column 4 (Data Type) since sub item is now column 3
        let filterValue = $(this).data('filter');
        if (filterValue === "" || filterValue === undefined) {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(String(filterValue)).draw();
        }

        // Recalculate layout
        setTimeout(() => {
            if (table) {
                table.columns.adjust();
                if (table.responsive) table.responsive.recalc();
            }
        }, 50);
    });

    // 1.6 Master Spec KPI Summary Logic
    function updateMasterSpecSummary(data) {
        if (!Array.isArray(data)) return;

        let total = data.length;
        let ref01 = 0, ref02 = 0, ref03 = 0;
        let ctq = 0, ctp = 0, tc = 0, fp = 0;

        data.forEach(item => {
            let line = (item.line_name || '').toUpperCase().trim();
            let type = (item.data_type || '').toUpperCase().trim();

            if (line === 'REF 01') ref01++;
            else if (line === 'REF 02') ref02++;
            else if (line === 'REF 03') ref03++;

            if (type === 'CTQ') ctq++;
            else if (type === 'CTP') ctp++;
            else if (type === 'TIME CHECK') tc++;
            else if (type === 'F/PROOF' || type === 'FOOL PROOF') fp++;
        });

        $('#kpi-val-total').text(total.toLocaleString());
        $('#kpi-val-ref01').text(ref01.toLocaleString());
        $('#kpi-val-ref02').text(ref02.toLocaleString());
        $('#kpi-val-ref03').text(ref03.toLocaleString());
        if (ref03 > 0) {
            $('#card-kpi-ref03').show();
        } else {
            $('#card-kpi-ref03').hide();
        }

        $('#kpi-val-ctq').text(ctq.toLocaleString());
        $('#kpi-val-ctp').text(ctp.toLocaleString());
        $('#kpi-val-tc').text(tc.toLocaleString());
        $('#kpi-val-fp').text(fp.toLocaleString());

        // Update tab badges
        $('#badge-all').text(total.toLocaleString());
        $('#badge-ctq').text(ctq.toLocaleString());
        $('#badge-ctp').text(ctp.toLocaleString());
        $('#badge-time-check').text(tc.toLocaleString());
        $('#badge-f-proof').text(fp.toLocaleString());
    }

    // Click on KPI card to quickly filter table
    $(document).on('click', '.spec-kpi-card', function () {
        let kpi = $(this).data('kpi');
        if (kpi === 'all') {
            $('#filter-line').val('').trigger('change');
            $('#filter-section').val('').trigger('change');
            $('#filter-item-check').val('').trigger('change');
            $('.dtc-filter-tabs .filter-tab-btn[data-filter=""]').trigger('click');
        } else if (kpi === 'line') {
            let line = $(this).data('line');
            $('#filter-line').val(line).trigger('change');
        } else if (kpi === 'type') {
            let type = $(this).data('type');
            $(`.dtc-filter-tabs .filter-tab-btn[data-filter="${type}"]`).trigger('click');
        }
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
        $('#modal-title').html('<i class="fa-solid fa-plus" style="margin-right:6px; color:var(--primary);"></i> Add Master Spec');
        $('#btn-save-spec').html('<i class="fa-solid fa-floppy-disk"></i> Save Spec');
        modal.style.display = 'flex';
    }

    btnClose.onclick = function () { modal.style.display = 'none'; }
    btnCancel.onclick = function () { modal.style.display = 'none'; }

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

        $('#modal-title').html('<i class="fa-solid fa-pen" style="margin-right:6px; color:var(--primary);"></i> Edit Master Spec');
        $('#btn-save-spec').html('<i class="fa-solid fa-floppy-disk"></i> Save Spec');
        modal.style.display = 'flex';
    });

    // Copy Button Click (Row Action Popup)
    $('#master-spec-table tbody').on('click', '.btn-copy', function () {
        let data = table.row($(this).parents('tr')).data();

        // Empty spec_id so it will INSERT as a new spec
        $('#spec_id').val('');
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

        $('#modal-title').html('<i class="fa-solid fa-copy" style="margin-right:6px; color:var(--accent);"></i> Copy Master Spec');
        $('#btn-save-spec').html('<i class="fa-solid fa-copy"></i> Save as New Spec');
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
                            loadSelectOptions();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Form Submit (Save / Update / Copy)
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
                    loadSelectOptions();
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

    // 3. Modal Copy by Model Logic
    const modalCopy = document.getElementById('modal-copy-model-spec');
    const btnOpenCopy = document.getElementById('btn-copy-model-spec');
    const btnCloseCopy = document.getElementById('btn-close-copy-modal');
    const btnCancelCopy = document.getElementById('btn-cancel-copy-modal');
    const formCopy = document.getElementById('form-copy-model-spec');

    function updateCopyModelDropdowns() {
        let uniqueModels = [...new Set(masterSpecsList.map(s => s.model_name).filter(Boolean))].sort();
        let modelOpts = '<option value="">-- Pilih Model Sumber --</option>';
        uniqueModels.forEach(m => {
            modelOpts += `<option value="${m}">${m}</option>`;
        });
        $('#copy_source_model').html(modelOpts);

        // Lines and Sections for Copy Modal
        let uniqueLines = [...new Set(masterSpecsList.map(s => s.line_name).filter(Boolean))].sort();
        let lineFilterOpts = '<option value="">Semua Line</option>';
        let lineTargetOpts = '<option value="">-- Sama Seperti Sumber --</option>';
        uniqueLines.forEach(l => {
            lineFilterOpts += `<option value="${l}">${l}</option>`;
            lineTargetOpts += `<option value="${l}">${l}</option>`;
        });
        $('#copy_source_line').html(lineFilterOpts);
        $('#copy_target_line').html(lineTargetOpts);

        let uniqueSections = [...new Set(masterSpecsList.map(s => s.section_name).filter(Boolean))].sort();
        let sectionFilterOpts = '<option value="">Semua Section</option>';
        let sectionTargetOpts = '<option value="">-- Sama Seperti Sumber --</option>';
        uniqueSections.forEach(s => {
            sectionFilterOpts += `<option value="${s}">${s}</option>`;
            sectionTargetOpts += `<option value="${s}">${s}</option>`;
        });
        $('#copy_source_section').html(sectionFilterOpts);
        $('#copy_target_section').html(sectionTargetOpts);
    }

    function calculateCopyPreview() {
        let srcModel = $('#copy_source_model').val();
        let srcLine = $('#copy_source_line').val();
        let srcSec = $('#copy_source_section').val();

        if (!srcModel) {
            $('#copy-preview-info').hide();
            return;
        }

        let filtered = masterSpecsList.filter(s => s.model_name === srcModel);
        if (srcLine) filtered = filtered.filter(s => s.line_name === srcLine);
        if (srcSec) filtered = filtered.filter(s => s.section_name === srcSec);

        $('#copy-preview-count').text(filtered.length);
        $('#copy-preview-info').show();
    }

    if (btnOpenCopy) {
        btnOpenCopy.onclick = function () {
            formCopy.reset();
            updateCopyModelDropdowns();
            $('#copy-preview-info').hide();
            modalCopy.style.display = 'flex';
        };
    }

    if (btnCloseCopy) {
        btnCloseCopy.onclick = function () { modalCopy.style.display = 'none'; };
    }
    if (btnCancelCopy) {
        btnCancelCopy.onclick = function () { modalCopy.style.display = 'none'; };
    }

    $('#copy_source_model, #copy_source_line, #copy_source_section').on('change', calculateCopyPreview);

    // Copy Model Form Submission
    $('#form-copy-model-spec').on('submit', function (e) {
        e.preventDefault();

        let srcModel = ($('#copy_source_model').val() || '').trim();
        let tgtModel = ($('#copy_target_model').val() || '').trim();

        if (!srcModel) {
            Swal.fire('Peringatan', 'Harap pilih Model Sumber yang ingin disalin.', 'warning');
            return;
        }
        if (!tgtModel) {
            Swal.fire('Peringatan', 'Harap masukkan Nama Model Baru / Tujuan.', 'warning');
            return;
        }

        let btn = $('#btn-submit-copy-model');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyalin...');

        $.ajax({
            url: 'Script/php/dtc/c_master_spec_copy.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-clone"></i> Salin Semua Spesifikasi');
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success');
                    modalCopy.style.display = 'none';
                    table.ajax.reload(null, false);
                    loadSelectOptions();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-clone"></i> Salin Semua Spesifikasi');
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            }
        });
    });

    // 4. Manage Lines & Sections Modal Logic
    const modalManageLS = document.getElementById('modal-manage-lines-sections');
    const btnOpenManageLS = document.getElementById('btn-manage-line-section');
    const btnCloseManageLSModal = document.getElementById('btn-close-manage-ls-modal');
    const btnCloseManageLS = document.getElementById('btn-close-manage-ls');

    let currentMasterLines = [];
    let currentMasterSections = [];

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderMasterLinesTable(lines) {
        currentMasterLines = lines || [];
        $('#badge-lines-count').text(currentMasterLines.length);
        let html = '';
        if (!currentMasterLines.length) {
            html = '<tr><td colspan="5" style="text-align:center; padding:15px; color:var(--text-muted);">Belum ada data Line.</td></tr>';
        } else {
            currentMasterLines.forEach((l, idx) => {
                html += `
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 8px 10px; text-align: center; color: var(--text-muted);">${idx + 1}</td>
                        <td style="padding: 8px 10px; font-weight: 600; color: #38bdf8;">${escapeHtml(l.line_name)}</td>
                        <td style="padding: 8px 10px; color: var(--text-muted);">${escapeHtml(l.description || '-')}</td>
                        <td style="padding: 8px 10px; text-align: center; color: var(--text-light);">${l.sort_order ?? 0}</td>
                        <td style="padding: 8px 10px; text-align: center;">
                            <button type="button" class="btn-edit-line" data-id="${l.line_id}" data-name="${escapeHtml(l.line_name)}" data-desc="${escapeHtml(l.description || '')}" data-sort="${l.sort_order ?? 0}" title="Edit Line" style="background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; font-size: 11px;"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="btn-delete-line" data-id="${l.line_id}" data-name="${escapeHtml(l.line_name)}" title="Hapus Line" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#tbody-manage-lines').html(html);

        // Update line options in Manage Section form dropdown
        let secLineOpts = '<option value="">Semua Line (General)</option>';
        currentMasterLines.forEach(l => {
            secLineOpts += `<option value="${escapeHtml(l.line_name)}">${escapeHtml(l.line_name)}</option>`;
        });
        $('#manage_section_line').html(secLineOpts);
    }

    function renderMasterSectionsTable(sections) {
        currentMasterSections = sections || [];
        $('#badge-sections-count').text(currentMasterSections.length);
        let html = '';
        if (!currentMasterSections.length) {
            html = '<tr><td colspan="6" style="text-align:center; padding:15px; color:var(--text-muted);">Belum ada data Section.</td></tr>';
        } else {
            currentMasterSections.forEach((s, idx) => {
                let lineBadge = s.line_name ? `<span style="padding: 2px 6px; border-radius: 4px; background: rgba(56,189,248,0.15); color: #38bdf8; font-size: 10.5px;">${escapeHtml(s.line_name)}</span>` : '<span style="color: var(--text-muted); font-size: 11px;">Semua Line</span>';
                html += `
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 8px 10px; text-align: center; color: var(--text-muted);">${idx + 1}</td>
                        <td style="padding: 8px 10px; font-weight: 600; color: #c084fc;">${escapeHtml(s.section_name)}</td>
                        <td style="padding: 8px 10px;">${lineBadge}</td>
                        <td style="padding: 8px 10px; color: var(--text-muted);">${escapeHtml(s.description || '-')}</td>
                        <td style="padding: 8px 10px; text-align: center; color: var(--text-light);">${s.sort_order ?? 0}</td>
                        <td style="padding: 8px 10px; text-align: center;">
                            <button type="button" class="btn-edit-section" data-id="${s.section_id}" data-name="${escapeHtml(s.section_name)}" data-line="${escapeHtml(s.line_name || '')}" data-desc="${escapeHtml(s.description || '')}" data-sort="${s.sort_order ?? 0}" title="Edit Section" style="background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; font-size: 11px;"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="btn-delete-section" data-id="${s.section_id}" data-name="${escapeHtml(s.section_name)}" title="Hapus Section" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#tbody-manage-sections').html(html);
    }

    function loadMasterLinesAndSections(callback = null) {
        $.getJSON('Script/php/dtc/c_master_lines_sections_list.php', function (res) {
            if (res.status === 'success') {
                renderMasterLinesTable(res.lines);
                renderMasterSectionsTable(res.sections);
            }
            if (callback) callback();
        });
    }

    if (btnOpenManageLS) {
        btnOpenManageLS.onclick = function () {
            loadMasterLinesAndSections();
            $('#tab-btn-lines').addClass('active');
            $('#tab-btn-sections').removeClass('active');
            $('#panel-manage-lines').show();
            $('#panel-manage-sections').hide();
            modalManageLS.style.display = 'flex';
        };
    }
    if (btnCloseManageLSModal) {
        btnCloseManageLSModal.onclick = function () { modalManageLS.style.display = 'none'; };
    }
    if (btnCloseManageLS) {
        btnCloseManageLS.onclick = function () { modalManageLS.style.display = 'none'; };
    }

    // Tabs switching in Manage Modal
    $('#tab-btn-lines').on('click', function (e) {
        e.preventDefault();
        $('#tab-btn-lines').addClass('active');
        $('#tab-btn-sections').removeClass('active');
        $('#panel-manage-lines').show();
        $('#panel-manage-sections').hide();
    });

    $('#tab-btn-sections').on('click', function (e) {
        e.preventDefault();
        $('#tab-btn-sections').addClass('active');
        $('#tab-btn-lines').removeClass('active');
        $('#panel-manage-sections').show();
        $('#panel-manage-lines').hide();
    });

    // Reset Line Form
    function resetLineForm() {
        $('#manage_line_id').val('');
        $('#manage_line_name').val('');
        $('#manage_line_desc').val('');
        $('#manage_line_sort').val('');
        $('#form-line-title').html('<i class="fa-solid fa-plus"></i> Tambah Line Baru');
        $('#btn-save-line').html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
        $('#btn-reset-line').hide();
    }
    $('#btn-reset-line').on('click', resetLineForm);

    // Save Line
    $('#form-manage-line').on('submit', function (e) {
        e.preventDefault();
        let btn = $('#btn-save-line');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: 'Script/php/dtc/c_master_line_save.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success');
                    resetLineForm();
                    loadMasterLinesAndSections();
                    loadSelectOptions();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            }
        });
    });

    // Edit Line Click
    $(document).on('click', '.btn-edit-line', function () {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let desc = $(this).data('desc');
        let sort = $(this).data('sort');

        $('#manage_line_id').val(id);
        $('#manage_line_name').val(name).focus();
        $('#manage_line_desc').val(desc);
        $('#manage_line_sort').val(sort);

        $('#form-line-title').html('<i class="fa-solid fa-pen"></i> Edit Line: ' + escapeHtml(name));
        $('#btn-save-line').html('<i class="fa-solid fa-floppy-disk"></i> Update Line');
        $('#btn-reset-line').show();
    });

    // Delete Line Click
    $(document).on('click', '.btn-delete-line', function () {
        let id = $(this).data('id');
        let name = $(this).data('name');

        Swal.fire({
            title: 'Hapus Line?',
            text: `Anda yakin ingin menghapus Line '${name}'?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_master_line_delete.php',
                    type: 'POST',
                    data: { line_id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Terhapus!', res.message, 'success');
                            resetLineForm();
                            loadMasterLinesAndSections();
                            loadSelectOptions();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    // Reset Section Form
    function resetSectionForm() {
        $('#manage_section_id').val('');
        $('#manage_section_name').val('');
        $('#manage_section_line').val('');
        $('#manage_section_desc').val('');
        $('#manage_section_sort').val('');
        $('#form-section-title').html('<i class="fa-solid fa-plus"></i> Tambah Section Baru');
        $('#btn-save-section').html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
        $('#btn-reset-section').hide();
    }
    $('#btn-reset-section').on('click', resetSectionForm);

    // Save Section
    $('#form-manage-section').on('submit', function (e) {
        e.preventDefault();
        let btn = $('#btn-save-section');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: 'Script/php/dtc/c_master_section_save.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success');
                    resetSectionForm();
                    loadMasterLinesAndSections();
                    loadSelectOptions();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            }
        });
    });

    // Edit Section Click
    $(document).on('click', '.btn-edit-section', function () {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let line = $(this).data('line');
        let desc = $(this).data('desc');
        let sort = $(this).data('sort');

        $('#manage_section_id').val(id);
        $('#manage_section_name').val(name).focus();
        $('#manage_section_line').val(line);
        $('#manage_section_desc').val(desc);
        $('#manage_section_sort').val(sort);

        $('#form-section-title').html('<i class="fa-solid fa-pen"></i> Edit Section: ' + escapeHtml(name));
        $('#btn-save-section').html('<i class="fa-solid fa-floppy-disk"></i> Update Section');
        $('#btn-reset-section').show();
    });

    // Delete Section Click
    $(document).on('click', '.btn-delete-section', function () {
        let id = $(this).data('id');
        let name = $(this).data('name');

        Swal.fire({
            title: 'Hapus Section?',
            text: `Anda yakin ingin menghapus Section '${name}'?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_master_section_delete.php',
                    type: 'POST',
                    data: { section_id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Terhapus!', res.message, 'success');
                            resetSectionForm();
                            loadMasterLinesAndSections();
                            loadSelectOptions();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    // Quick Add Line from Spec Form
    $('#btn-quick-add-line').on('click', function () {
        Swal.fire({
            title: 'Tambah Line Baru',
            html: `
                <div style="text-align: left; padding: 5px;">
                    <label style="font-size: 12px; color: var(--text-light); margin-bottom: 4px; display: block;">Nama Line *</label>
                    <input id="swal_line_name" class="swal2-input" placeholder="e.g. REF 03" style="width: 100%; margin: 0 0 12px 0; font-size: 13px; box-sizing: border-box; background: rgba(15,23,42,0.8); color: white; border: 1px solid #334155;">
                    <label style="font-size: 12px; color: var(--text-light); margin-bottom: 4px; display: block;">Deskripsi (Opsional)</label>
                    <input id="swal_line_desc" class="swal2-input" placeholder="e.g. Refrigerator Line 03" style="width: 100%; margin: 0; font-size: 13px; box-sizing: border-box; background: rgba(15,23,42,0.8); color: white; border: 1px solid #334155;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-plus"></i> Tambahkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#475569',
            focusConfirm: false,
            preConfirm: () => {
                const name = ($('#swal_line_name').val() || '').trim();
                const desc = ($('#swal_line_desc').val() || '').trim();
                if (!name) {
                    Swal.showValidationMessage('Nama Line wajib diisi.');
                    return false;
                }
                return { line_name: name, description: desc };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_master_line_save.php',
                    type: 'POST',
                    data: result.value,
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success');
                            loadSelectOptions(function () {
                                if (res.line && res.line.line_name) {
                                    $('#line_name').val(res.line.line_name);
                                }
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    // Quick Add Section from Spec Form
    $('#btn-quick-add-section').on('click', function () {
        const currentLine = $('#line_name').val() || '';
        Swal.fire({
            title: 'Tambah Section Baru',
            html: `
                <div style="text-align: left; padding: 5px;">
                    <label style="font-size: 12px; color: var(--text-light); margin-bottom: 4px; display: block;">Nama Section *</label>
                    <input id="swal_section_name" class="swal2-input" placeholder="e.g. Final Assembly" style="width: 100%; margin: 0 0 12px 0; font-size: 13px; box-sizing: border-box; background: rgba(15,23,42,0.8); color: white; border: 1px solid #334155;">
                    <label style="font-size: 12px; color: var(--text-light); margin-bottom: 4px; display: block;">Deskripsi (Opsional)</label>
                    <input id="swal_section_desc" class="swal2-input" placeholder="e.g. Stasiun Rakit Akhir" style="width: 100%; margin: 0; font-size: 13px; box-sizing: border-box; background: rgba(15,23,42,0.8); color: white; border: 1px solid #334155;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-plus"></i> Tambahkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#475569',
            focusConfirm: false,
            preConfirm: () => {
                const name = ($('#swal_section_name').val() || '').trim();
                const desc = ($('#swal_section_desc').val() || '').trim();
                if (!name) {
                    Swal.showValidationMessage('Nama Section wajib diisi.');
                    return false;
                }
                return { section_name: name, line_name: currentLine, description: desc };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_master_section_save.php',
                    type: 'POST',
                    data: result.value,
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success');
                            loadSelectOptions(function () {
                                if (res.section && res.section.section_name) {
                                    $('#section_name').val(res.section.section_name);
                                }
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    window.onclick = function (event) {
        if (event.target == modal) { modal.style.display = "none"; }
        if (event.target == modalCopy) { modalCopy.style.display = "none"; }
        if (event.target == modalManageLS) { modalManageLS.style.display = "none"; }
    };
});
