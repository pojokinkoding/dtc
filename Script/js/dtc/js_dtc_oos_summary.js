// Script/js/dtc/js_dtc_oos_summary.js

$(document).ready(function() {
    let currentTable = null;

    // Initialize month picker
    let today = new Date();
    let defaultMonth = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, '0');
    $("#oos_month").val(defaultMonth);

    function loadOOSData() {
        let selectedMonth = $("#oos_month").val();
        if (!selectedMonth) return;

        if (currentTable) {
            currentTable.destroy();
        }

        $.ajax({
            url: "Script/php/dtc/c_oos_summary.php",
            type: "GET",
            cache: false,
            data: { month: selectedMonth },
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    let data = res.data;

                    data.forEach(row => {
                        let lsl = parseFloat(row.lsl);
                        let usl = parseFloat(row.usl);
                        let minVal = parseFloat(row.min_value);
                        let maxVal = parseFloat(row.max_value);
                        
                        let minHtml = minVal < lsl ? `<span class="val-danger">${row.min_value}</span>` : row.min_value;
                        let maxHtml = maxVal > usl ? `<span class="val-danger">${row.max_value}</span>` : row.max_value;

                        let link = `index.php?page=dtc_detail&param_id=${row.parameter_id}&month=${selectedMonth}&auto_add=${row.inspection_date.substring(0, 7) + '-' + row.inspection_date.substring(8)}`;

                        html += `<tr class="oos-row">
                            <td>${row.inspection_date}</td>
                            <td><span style="color: #e2e8f0; font-weight: 500;">${row.model_name}</span></td>
                            <td><span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 6px; border-radius: 4px;">${row.line_name}</span></td>
                            <td><span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 6px; border-radius: 4px;">${row.section_name}</span></td>
                            <td><span style="color: #cbd5e1;"><i class="fa-solid fa-cogs" style="margin-right: 4px; color: #94a3b8;"></i> ${row.process_name}</span></td>
                            <td style="color: var(--accent); font-weight: bold;">${row.item_check_name} [${row.data_type}]</td>
                            <td>${row.lsl}</td>
                            <td>${row.usl}</td>
                            <td>${minHtml}</td>
                            <td>${maxHtml}</td>
                            <td><span style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 12px;">${row.oos_type}</span></td>
                            <td>
                                <a href="${link}" class="btn btn-sm" style="font-size: 11px; background-color: #3b82f6; color: white; border: none; padding: 4px 10px; border-radius: 4px;"><i class="fa-solid fa-arrow-right"></i> Detail</a>
                            </td>
                        </tr>`;
                    });

                    $("#oos-tbody").html(html);

                    currentTable = $('#oos-table').DataTable({
                        responsive: true,
                        pageLength: 25,
                        order: [[0, 'desc']], // Sort by date descending
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search OOS..."
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal Memuat Data!', text: res.message, background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({ icon: 'error', title: 'Koneksi Gagal!', text: 'Server error while loading OOS data.', background: '#1e293b', color: '#f8fafc', confirmButtonColor: '#ef4444' });
            }
        });
    }

    $("#oos_month").change(function() {
        loadOOSData();
    });

    // Initial load
    loadOOSData();
});
