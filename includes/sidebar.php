    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
        <div class="sidebar-brand">
            <h2 style="width:100%; text-align:center; margin:0; display: flex; align-items: center; justify-content: center; gap: 8px;"><img src="logo.png" alt="LG" class="logo-lg" style="height: 32px; vertical-align: middle;"> <span class="logo-text" style="color: var(--text-primary); font-weight: 800; letter-spacing: 1px;">DTC</span></h2>
        </div>
        <ul class="sidebar-menu">
            <!-- Group 1: Core Features -->
            <li>
                <a href="index.php?page=dtc" class="<?php echo ($page == 'dtc' || $page == 'dtc_list') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-list-check"></i> <span>DTC List</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=missing_data" class="<?php echo ($page == 'missing_data') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-border-none"></i> <span>Data Monitoring</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=dtc_history" class="<?php echo ($page == 'dtc_history') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> <span>DTC History</span>
                </a>
            </li>
            <?php if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
            <li style="display: none;">
                <a href="index.php?page=oos_summary" class="<?php echo ($page == 'oos_summary') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span>OOS Summary Tracker</span>
                </a>
            </li>

            <!-- Group 2: Configuration & Admin -->
            <li style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <a href="index.php?page=master_spec" class="<?php echo ($page == 'master_spec') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-database"></i> <span>Master Data</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=settings" class="<?php echo ($page == 'settings') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gear"></i> <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=users" class="<?php echo ($page == 'users') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> <span>User Management</span>
                </a>
            </li>
            <?php else: ?>
            <!-- Group 2 spacer for non-admin -->
            <li style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; display: none;"></li>
            <?php endif; ?>

            <!-- Group 3: Help & Support -->
            <li style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <a href="index.php?page=docs" class="<?php echo ($page == 'docs') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-book-open"></i> <span>Documentation</span>
                </a>
            </li>

            <!-- Group 4: Logout -->
            <li style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <a href="logout.php" style="color: #ef4444; font-weight: 500;">
                    <i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>
