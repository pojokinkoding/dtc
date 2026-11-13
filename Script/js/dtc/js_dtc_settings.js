$(document).ready(function() {
    function updateSlotNumbers(containerId) {
        $(`#${containerId} .slot-item`).each(function(index) {
            $(this).find('span').text(`S${index + 1}`);
        });
    }

    $('.btn-add-slot').on('click', function() {
        const target = $(this).data('target');
        const inputName = $(this).data('name');
        const html = `
            <div class="slot-item" style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 11px; color: var(--text-muted); min-width: 30px;">S-</span>
                <input type="text" name="${inputName}" placeholder="e.g. 08:00" style="flex-grow: 1; padding: 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.5); color: white; font-size: 13px;">
                <button type="button" class="btn-remove-slot" style="background: transparent; border: none; color: #ef4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        $(`#${target}`).append(html);
        updateSlotNumbers(target);
    });

    $(document).on('click', '.btn-remove-slot', function() {
        const containerId = $(this).closest('div[id^="container_"]').attr('id');
        $(this).closest('.slot-item').remove();
        updateSlotNumbers(containerId);
    });

    function updateRefreshPreview(val) {
        let num = parseFloat(val);
        if (isNaN(num) || num <= 0) num = 1;
        let lbl = (num < 1) ? `${Math.round(num * 60)}s` : `${(num === Math.floor(num) ? Math.floor(num) : num)}m`;
        $('#preview-auto-refresh-badge').html(`<i class="fa-solid fa-arrows-rotate"></i> Preview Badge: Auto ${lbl}`);
    }

    $(document).on('input change', '#bulk_auto_refresh_minutes', function() {
        updateRefreshPreview($(this).val());
    });

    $(document).on('click', '.btn-refresh-preset', function() {
        let val = $(this).data('val');
        $('#bulk_auto_refresh_minutes').val(val).trigger('input');
        $('.btn-refresh-preset').css({
            'border-color': 'rgba(255,255,255,0.12)',
            'background': 'rgba(15,23,42,0.5)',
            'color': '#cbd5e1',
            'font-weight': 'normal'
        });
        $(this).css({
            'border-color': 'rgba(59,130,246,0.6)',
            'background': 'rgba(59,130,246,0.2)',
            'color': '#60a5fa',
            'font-weight': '700'
        });
    });

    $('#form-settings-time').on('submit', function(e) {
        e.preventDefault();
        $('#btn-save-settings').html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...').prop('disabled', true);
        
        $.ajax({
            url: 'Script/php/dtc/c_settings_save.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $('#btn-save-settings').html('<i class="fa-solid fa-floppy-disk"></i> Save Configurations').prop('disabled', false);
                if(res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Settings updated successfully!',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                $('#btn-save-settings').html('<i class="fa-solid fa-floppy-disk"></i> Save Configurations').prop('disabled', false);
                Swal.fire('Error', 'Failed to save settings.', 'error');
            }
        });
    });
});
