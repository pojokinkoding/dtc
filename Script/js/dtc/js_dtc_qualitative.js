// Script/js/dtc/js_dtc_qualitative.js
$(document).ready(function () {
    const gridContainer = $("#grid-input-qualitative");
    
    // Convert text inputs to selects for Qualitative
    if (typeof isQualitative !== 'undefined' && isQualitative) {
        $(".sample-input").each(function() {
            const name = $(this).attr("name");
            const placeholder = $(this).attr("placeholder");
            const isRequired = $(this).prop("required") ? "required" : "";
            
            const selectHtml = `
                <select name="${name}" class="sample-input" ${isRequired} style="width: 100%; text-align: center; padding: 6px 4px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.5); color: white; transition: all 0.3s; font-size: 12px; cursor: pointer; appearance: auto;">
                    <option value="" disabled selected>${placeholder}</option>
                    <option value="OK">OK</option>
                    <option value="NG">NG</option>
                </select>
            `;
            $(this).replaceWith(selectHtml);
        });
    }
    
    window.loadMatrixData = function() {
        gridContainer.html('<div style="color: white; padding: 20px; text-align: center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading Data...</div>');
        
        $.ajax({
            url: `Script/php/dtc/c_dtc_matrix_qualitative.php?param_id=${currentParamId}&month=${currentMonth}`,
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    renderMatrix(res.data);
                } else {
                    gridContainer.html(`<div style="color: red; padding: 20px;">Error: ${res.message}</div>`);
                }
            },
            error: function () {
                gridContainer.html('<div style="color: red; padding: 20px;">Failed to fetch data from server.</div>');
            }
        });
    }
    
    function renderMatrix(data) {
        if (!data || data.length === 0) {
            gridContainer.html('<div style="color: #94a3b8; padding: 20px; text-align: center;">No qualitative data found for this section.</div>');
            return;
        }
        
        let html = '<table class="matrix-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th rowspan="2" class="sticky-col" style="min-width: 40px;">No</th>';
        html += '<th rowspan="2" class="sticky-col" style="left: 40px; min-width: 200px;">Check Point</th>';
        html += '<th rowspan="2" class="sticky-col" style="left: 240px; min-width: 80px; box-shadow: 2px 0 5px rgba(0,0,0,0.5);">Time</th>';
        
        for (let i = 1; i <= 31; i++) {
            html += `<th>${i}</th>`;
        }
        html += '</tr><tr></tr>'; // empty row to satisfy rowspan=2
        html += '</thead><tbody>';
        
        data.forEach(param => {
            const rowCount = param.times.length > 0 ? param.times.length : 1;
            
            // Generate rows for this parameter
            for (let seqIndex = 0; seqIndex < param.times.length; seqIndex++) {
                html += '<tr>';
                
                // Only for the first row, output the rowspan columns
                if (seqIndex === 0) {
                    html += `<td rowspan="${rowCount}" class="sticky-col" style="background-color: rgba(30, 41, 59, 1);">${param.no}</td>`;
                    html += `<td rowspan="${rowCount}" class="sticky-col" style="background-color: rgba(30, 41, 59, 1); left: 40px; white-space: normal;">${param.check_point}</td>`;
                }
                
                let timeData = param.times[seqIndex];
                html += `<td class="sticky-col" style="background-color: rgba(30, 41, 59, 1); left: 240px; box-shadow: 2px 0 5px rgba(0,0,0,0.5);">${timeData.time_label}</td>`;
                
                // Days data
                for (let d = 1; d <= 31; d++) {
                    let val = timeData.days[d];
                    let isClosed = param.closed_days[d] === 1;
                    let displayVal = val ? val : '';
                    let bgColor = '';
                    let color = '';
                    
                    if (displayVal === 'NG') {
                        bgColor = 'rgba(239, 68, 68, 0.2)'; // Light red
                        color = '#ef4444'; // Red text
                    } else if (displayVal === 'OK') {
                        color = '#10b981'; // Green text
                    }
                    
                    let style = '';
                    if (bgColor) style += `background-color: ${bgColor}; `;
                    if (color) style += `color: ${color}; font-weight: bold; `;
                    
                    // Add lock icon if closed
                    if (isClosed && val) {
                        displayVal += ' <i class="fa-solid fa-lock" style="font-size: 8px; color: #10b981;"></i>';
                    }
                    
                    html += `<td style="${style}">${displayVal}</td>`;
                }
                
                html += '</tr>';
            }
        });
        
        html += '</tbody></table>';
        gridContainer.html(html);
    };
    
    // Auto load data on load
    window.loadMatrixData();
    
    // Override Save logic for Qualitative (keeps standard form logic but ensures it reloads the qualitative grid)
    $("#form-input-data").off("submit").on("submit", function (e) {
        e.preventDefault();
        
        const dateVal = $("#input_inspection_date").val();
        if (!dateVal) {
            Swal.fire({ icon: 'warning', title: 'Pilih Tanggal!', text: 'Silakan pilih tanggal inspeksi terlebih dahulu.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#3b82f6' });
            return;
        }

        let formData = new FormData(this);
        formData.append("parameter_id", currentParamId);

        let hasData = false;
        $('.sample-input').each(function () {
            let val = $(this).val();
            if (val && val.trim && val.trim() !== "") {
                hasData = true;
            }
        });

        if (!hasData) {
            Swal.fire({
                title: 'Data Kosong!',
                text: 'Minimal harus ada satu data pengukuran yang diisi sebelum menyimpan.',
                icon: 'warning',
                confirmButtonColor: '#3b82f6',
                background: '#1e293b',
                color: '#f8fafc'
            });
            return;
        }

        let isFutureTime = false;
        let futureLabel = "";
        let now = new Date();

        $('.sample-input').each(function(index) {
            let val = $(this).val();
            // Use strict truthy check to avoid any undefined/null/empty string quirks
            if (val && val.trim && val.trim() !== "" && $(this).css('pointer-events') !== 'none') {
                let seq = index + 1;
                let labelText = $("#label_sample_" + seq).text().trim();
                
                // Construct Date object for this input
                let timeParts = labelText.match(/^(\d{1,2}):(\d{2})$/);
                if (timeParts) {
                    let inputDateStr = $("#input_inspection_date").val();
                        let dateParts = inputDateStr.split('-');
                        if (dateParts.length === 3) {
                            let year = parseInt(dateParts[0], 10);
                            let month = parseInt(dateParts[1], 10) - 1; // 0-indexed
                            let day = parseInt(dateParts[2], 10);
                            let hours = parseInt(timeParts[1], 10);
                            let minutes = parseInt(timeParts[2], 10);
                            
                            let offsetDay = 0;
                            if (hours >= 24) {
                                offsetDay = Math.floor(hours / 24);
                                hours = hours % 24;
                            } else if (hours < 7) {
                                // Assuming shift starts at 07:00. Times between 00:00 and 06:59 belong to the next day.
                                offsetDay = 1;
                            }
                            
                            let inputDateTime = new Date(year, month, day + offsetDay, hours, minutes, 0);
                            if (inputDateTime > now) {
                                isFutureTime = true;
                                futureLabel = labelText;
                                return false; // break .each loop
                            }
                        }
                }
            }
        });

        if (isFutureTime) {
            Swal.fire({
                title: 'Waktu Input Belum Masuk!',
                text: `Anda tidak dapat menginput data untuk sampel jam ${futureLabel} karena waktu saat ini belum mencapai jadwal tersebut.`,
                icon: 'warning',
                confirmButtonColor: '#3b82f6',
                background: '#1e293b',
                color: '#f8fafc'
            });
            return;
        }
        
        // Disable save button to prevent double submission
        let $btn = $("#btn-save-data");
        let originalText = $btn.html();
        $btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: "Script/php/dtc/c_dtc_measurement_save.php", // We can use the same save API!
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.status === "success") {
                    document.getElementById("modal-input-data").style.display = "none";
                    loadMatrixData(); // Reload the giant table
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: "Error saving data: " + res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: "Server error occurred while saving.", background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            },
            complete: function() {
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Auto Load Data on Date Change for Qualitative form
    $("#input_inspection_date").off("change").on("change", function () {
        let selectedDate = $(this).val();
        if (!selectedDate) return;

        // Reset fields
        $(".sample-input").val("").css({
            'pointer-events': 'auto',
            'opacity': '1',
            'background': 'rgba(15,23,42,0.5)'
        });
        $("#input_remarks").val("");

        // Add loading indicator to inputs
        $(".sample-input").prop("disabled", true);

        $.ajax({
            url: "Script/php/dtc/c_dtc_measurement_get.php",
            method: "GET",
            data: {
                parameter_id: currentParamId,
                date: selectedDate
            },
            success: function (res) {
                $(".sample-input").prop("disabled", false);
                
                if (res.status === "found" && res.data) {
                    // Data exists, populate inputs
                    for (let seq = 1; seq <= 10; seq++) {
                        let val = res.data["sample_" + seq];
                        let $select = $(`select[name="sample_${seq}"]`);
                        
                        // Reset any previous "fake disabled" styling
                        $select.css({
                            'pointer-events': 'auto',
                            'opacity': '1',
                            'background': 'rgba(15,23,42,0.5)'
                        });
                        
                        if (val !== null && val !== undefined && val !== '') {
                            // Assume numeric or qualitative string
                            if (val !== 'OK' && val !== 'NG') {
                                val = (parseFloat(val) > 0) ? 'OK' : 'NG'; // Fallback
                            }
                            $select.val(val);
                            
                            // LOCK ALREADY INPUTTED DATA (UNLESS ADMIN)
                            if (!isAdmin) {
                                $select.css({
                                    'pointer-events': 'none',
                                    'opacity': '0.7',
                                    'background': 'rgba(255,255,255,0.05)'
                                });
                            }
                        }
                    }
                    
                    if (res.data.remarks) {
                        $("#input_remarks").val(res.data.remarks);
                    }
                    
                    // Handle Is Closed status
                    if (res.data.is_closed === 1) {
                        if (!isAdmin) {
                            $(".sample-input").prop("disabled", true);
                            $("#input_remarks").prop("readonly", true);
                            $("#btn-save-input").hide();
                            $("#btn-close-data").hide();
                        } else {
                            // Admin can edit closed forms
                            $(".sample-input").prop("disabled", false);
                            $("#input_remarks").prop("readonly", false);
                            $("#btn-save-input").show();
                            $("#btn-close-data").hide();
                        }
                        
                        // Let user know
                        if ($("#close-warning").length === 0) {
                            $("#form-input-data").prepend(`
                                <div id="close-warning" style="background-color: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class="fa-solid fa-lock" style="color: #10b981; font-size: 16px;"></i>
                                        <div>
                                            <strong style="color: #10b981; font-size: 12px; display: block;">Measurement Closed</strong>
                                            <span style="color: var(--text-muted); font-size: 11px;">This record is closed and cannot be edited.</span>
                                        </div>
                                    </div>
                                </div>
                            `);
                        }
                    } else {
                        $(".sample-input").prop("disabled", false);
                        $("#input_remarks").prop("readonly", false);
                        $("#btn-save-input").show();
                        $("#btn-close-data").show();
                        $("#close-warning").remove();
                    }
                    
                } else {
                    // No data for this date
                    $("#close-warning").remove();
                    $(".sample-input").prop("disabled", false);
                    $("#input_remarks").prop("readonly", false);
                    $("#btn-save-input").show();
                    $("#btn-close-data").hide();
                }
            },
            error: function () {
                $(".sample-input").prop("disabled", false);
            }
        });
    });
});
