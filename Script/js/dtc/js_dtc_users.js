$(document).ready(function() {
    // Initialize DataTable
    var table = $('#users-table').DataTable({
        ajax: {
            url: 'Script/php/dtc/c_dtc_users_list.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'user_id' },
            { 
                data: 'username',
                render: function(data, type, row) {
                    let img = row.profile_picture ? `<img src="uploads/profiles/${row.profile_picture}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; margin-right: 10px;">` : `<div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; margin-right: 10px;"><i class="fa-solid fa-user" style="font-size: 12px; color: #94a3b8;"></i></div>`;
                    return `<div style="display: flex; align-items: center;">${img}<span style="color: #e2e8f0; font-weight: 500;">${data}</span></div>`;
                }
            },
            { data: 'full_name' },
            { 
                data: 'role',
                render: function(data) {
                    let bg = 'rgba(255,255,255,0.1)';
                    let color = '#94a3b8';
                    if (data === 'Admin') {
                        bg = 'rgba(239, 68, 68, 0.2)';
                        color = '#ef4444';
                    } else if (data === 'Supervisor') {
                        bg = 'rgba(245, 158, 11, 0.2)';
                        color = '#f59e0b';
                    } else if (data === 'Foreman') {
                        bg = 'rgba(59, 130, 246, 0.2)';
                        color = '#3b82f6';
                    } else {
                        bg = 'rgba(16, 185, 129, 0.2)';
                        color = '#10b981';
                    }
                    return `<span style="background: ${bg}; color: ${color}; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">${data}</span>`;
                }
            },
            {
                data: 'line_name',
                render: function(data) {
                    return data ? `<span style="color: var(--text-light);">${data}</span>` : `<span style="color: var(--text-muted); font-style: italic;">All Lines</span>`;
                }
            },
            {
                data: 'section_name',
                render: function(data) {
                    return data ? `<span style="color: var(--text-light);">${data}</span>` : `<span style="color: var(--text-muted); font-style: italic;">All Sections</span>`;
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    return `
                        <button class="btn-edit" data-id="${row.user_id}" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 4px 8px; border-radius: 4px; margin-right: 5px; cursor: pointer;">
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                        <button class="btn-delete" data-id="${row.user_id}" data-name="${row.username}" style="background-color: transparent; border: 1px solid var(--danger); color: var(--danger); padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    `;
                }
            }
        ],
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "Search Users:",
            searchPlaceholder: "Type here..."
        }
    });

    // Modal Elements
    const modal = document.getElementById('modal-user');
    const form = document.getElementById('form-user');
    
    // Open Add Modal
    $('#btn-add-user').click(function() {
        form.reset();
        $('#user_id').val('');
        $('#modal-title').text('Add User');
        $('#password').prop('required', true);
        $('#password-hint').hide();
        $('#profile_pic_img').attr('src', '').hide();
        $('#profile_pic_icon').show();
        modal.style.display = 'flex';
    });

    // Close Modal
    $('#btn-close-modal').click(function() {
        modal.style.display = 'none';
    });
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Image Preview Logic
    $('#profile_picture').on('change', function(e) {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#profile_pic_img').attr('src', e.target.result).show();
                $('#profile_pic_icon').hide();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Submit Form (Add / Edit)
    $('#form-user').submit(function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        let userId = $('#user_id').val();
        let url = userId ? 'Script/php/dtc/c_dtc_users_edit.php' : 'Script/php/dtc/c_dtc_users_add.php';
        
        let btn = $('#btn-save-user');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save');
                if(response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        background: 'var(--bg-card)',
                        color: 'var(--text-light)',
                        confirmButtonColor: 'var(--primary)'
                    });
                    modal.style.display = 'none';
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        background: 'var(--bg-card)',
                        color: 'var(--text-light)',
                        confirmButtonColor: 'var(--danger)'
                    });
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to connect to server',
                    background: 'var(--bg-card)',
                    color: 'var(--text-light)',
                    confirmButtonColor: 'var(--danger)'
                });
            }
        });
    });

    // Edit Button Click
    $('#users-table tbody').on('click', '.btn-edit', function() {
        let rowData = table.row($(this).parents('tr')).data();
        if(!rowData) {
            // Handle responsive view where child row might be clicked
            rowData = table.row($(this).closest('tr').prev('tr')).data();
        }
        
        $('#user_id').val(rowData.user_id);
        $('#username').val(rowData.username);
        $('#full_name').val(rowData.full_name);
        $('#role').val(rowData.role);
        $('#line_name').val(rowData.line_name || '');
        $('#section_name').val(rowData.section_name || '');
        
        $('#password').prop('required', false).val('');
        $('#password-hint').show();
        
        $('#profile_picture').val('');
        if (rowData.profile_picture) {
            $('#profile_pic_img').attr('src', 'uploads/profiles/' + rowData.profile_picture).show();
            $('#profile_pic_icon').hide();
        } else {
            $('#profile_pic_img').attr('src', '').hide();
            $('#profile_pic_icon').show();
        }
        
        $('#modal-title').text('Edit User');
        modal.style.display = 'flex';
    });

    // Delete Button Click
    $('#users-table tbody').on('click', '.btn-delete', function() {
        let userId = $(this).data('id');
        let username = $(this).data('name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete user: ${username}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, delete it!',
            background: 'var(--bg-card)',
            color: 'var(--text-light)'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'Script/php/dtc/c_dtc_users_delete.php',
                    type: 'POST',
                    data: { user_id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.status === 'success') {
                            Swal.fire({
                                title: 'Deleted!', 
                                text: response.message, 
                                icon: 'success',
                                background: 'var(--bg-card)',
                                color: 'var(--text-light)'
                            });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                title: 'Error!', 
                                text: response.message, 
                                icon: 'error',
                                background: 'var(--bg-card)',
                                color: 'var(--text-light)'
                            });
                        }
                    }
                });
            }
        });
    });
});
