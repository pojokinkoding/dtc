<?php
// dtc_oos_summary.php
// Loaded via index.php?page=oos_summary
?>

<style>
    .val-danger {
        color: #ff6b6b; /* Brighter red for dark theme readability */
        font-weight: 800;
        font-size: 1.1em;
    }
    
    table.dataTable tbody tr {
        background-color: var(--bg-dark);
        color: #f8fafc;
    }
    table.dataTable tbody tr:hover {
        background-color: #334155 !important;
    }
    
    #oos-table th, #oos-table td {
        vertical-align: middle;
        text-align: center;
        white-space: nowrap;
    }
    #oos-table td:nth-child(5) { /* Process */
        text-align: left;
        white-space: normal;
    }
    
    /* DataTable Dark mode adjustments */
    .dataTables_wrapper {
        display: flow-root;
        padding-bottom: 10px;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: var(--text-light) !important;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 15px;
    }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background-color: var(--bg-dark);
        color: var(--text-light);
        border: 1px solid #334155;
        border-radius: 4px;
        padding: 4px 8px;
    }
    .dataTables_wrapper .dataTables_filter {
        float: right;
    }
    .dataTables_wrapper .dataTables_length {
        float: left;
    }
    .dataTables_wrapper .dataTables_paginate {
        float: right;
        margin-top: 15px;
    }
    .dataTables_wrapper .dataTables_info {
        float: left;
        margin-top: 15px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text-light) !important;
        padding: 6px 12px;
        margin: 0 4px;
        border: 1px solid #334155;
        border-radius: 4px;
        cursor: pointer;
        background-color: var(--bg-dark);
        text-decoration: none;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: var(--primary);
        color: #ffffff !important;
        border-color: var(--primary);
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> Out of Spec (OOS) Summary
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="oos_month" style="margin: 0; font-size: 14px; color: var(--text-light);">Month:</label>
                    <input type="month" id="oos_month" class="form-control" style="width: auto; background: var(--bg-dark); color: white; border: 1px solid var(--border-color);">
                </div>
            </div>
            <div class="card-body" style="padding: 20px;">
                <div class="table-responsive">
                    <table id="oos-table" class="display responsive nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Model</th>
                                <th>Line</th>
                                <th>Section</th>
                                <th>Process</th>
                                <th>DTC Name</th>
                                <th>LSL</th>
                                <th>USL</th>
                                <th>Min Val</th>
                                <th>Max Val</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="oos-tbody">
                            <!-- Data populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
