<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    /* DataTables Dark Theme Overrides */
    table.dataTable, table.dataTable th, table.dataTable td {
        color: var(--text-light) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        font-size: 11px !important;
    }
    table.dataTable tbody td {
        padding: 6px 10px !important;
    }
    table.dataTable thead th, table.dataTable thead td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    table.dataTable tbody tr {
        background-color: transparent !important;
    }
    table.dataTable tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing, .dataTables_wrapper .dataTables_paginate {
        color: var(--text-muted) !important;
        margin-bottom: 10px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text-muted) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current, .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--primary) !important;
        color: white !important;
        border-color: var(--primary) !important;
    }
    .dataTables_wrapper input, .dataTables_wrapper select {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: var(--text-light) !important;
        border-radius: 4px !important;
        padding: 4px 8px;
        margin-left: 5px;
    }
    .dataTables_wrapper .dataTables_length select {
        padding: 4px 20px 4px 8px;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
    <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: var(--text-light); display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-clock-rotate-left" style="color: #f59e0b;"></i> DTC History
        <span style="font-size: 12px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 3px 10px; border-radius: 20px;">
            <i class="fa-solid fa-history"></i> Bulan Lalu & Lainnya
        </span>
    </h2>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <!-- Filter Tabs -->
    <div class="dtc-filter-tabs" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <button class="filter-tab-btn active" data-filter="">All</button>
        <button class="filter-tab-btn" data-filter="CTQ">CTQ</button>
        <button class="filter-tab-btn" data-filter="CTP">CTP</button>
        <button class="filter-tab-btn" data-filter="Time Check">Time Check</button>
        <button class="filter-tab-btn" data-filter="F/Proof">F/Proof</button>
        
        <!-- Dropdown Filters -->
        <select id="filter-month" style="margin-left: 10px; padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.3); background: rgba(15,23,42,0.8); color: #f59e0b; min-width: 130px;">
            <option value="">Semua Bulan Lalu</option>
        </select>
        <select id="filter-line" style="padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Lines</option>
        </select>
        <select id="filter-section" style="padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Sections</option>
        </select>
        <select id="filter-item-check" style="padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.8); color: white; min-width: 120px;">
            <option value="">All Item Checks</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table id="dtc-history-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Month</th>
                    <th>Line & Section</th>
                    <th>Model Name</th>
                    <th>Item Check & Process</th>
                    <th>Specification</th>
                    <th>Operator</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

<script src="Script/js/dtc/js_dtc_history.js"></script>
