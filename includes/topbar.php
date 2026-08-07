    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left" style="display: flex; align-items: center; gap: 15px;">
                <button id="btn-toggle-sidebar" style="background: transparent; border: none; color: var(--text-light); font-size: 20px; cursor: pointer;">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <?php 
                        if($page == 'dtc') echo "Digital Time Check";
                        else if($page == 'dtc_dashboard' || $page == 'dtc_detail' || $page == 'dtc_matrix_qualitative') echo "";
                        else if($page == 'missing_data') echo "Missing Data Tracker";
                        else echo ucfirst(str_replace('_', ' ', $page)); 
                    ?>
                </h1>
                
            </div>
            
            <div class="topbar-center" style="position: absolute; left: 50%; transform: translateX(-50%); font-size: 22px; font-weight: 800; color: var(--accent); letter-spacing: 1.5px; text-transform: uppercase; display: flex; align-items: center; gap: 12px;">
                <img src="logo.png" alt="LG Logo" style="height: 32px; object-fit: contain;"> System Digital Time Check
            </div>

            <div class="topbar-right">
                <style>
                .theme-btn {
                    background: rgba(255,255,255,0.05);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: var(--text-light);
                    border-radius: 50%;
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .theme-btn:hover {
                    background: rgba(255,255,255,0.1);
                    color: var(--primary);
                }
                body.theme-robot #theme-toggle i:before {
                    content: "\f544"; /* fa-robot */
                }
                body:not(.theme-robot) #theme-toggle i:before {
                    content: "\f186"; /* fa-moon */
                }
                </style>
                <div style="display: flex; gap: 10px; margin-right: 20px;">
                    <button id="fullscreen-toggle" class="theme-btn" title="Toggle Full Screen">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                    <button id="theme-toggle" class="theme-btn" title="Toggle Theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                </div>
                
                <div class="user-profile">
                    <div class="user-info" style="text-align: right;">
<?php
    $fullName = $_SESSION['full_name'] ?? 'User';
    $role = $_SESSION['role'] ?? 'Guest';
    
    // Generate initials (up to 2 characters)
    $words = explode(' ', $fullName);
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    $initials = substr($initials, 0, 2);
?>
                        <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
                        <span class="user-role"><?= htmlspecialchars($role) ?></span>
                    </div>
                    <div class="user-avatar" style="overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center;">
                        <?php if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($_SESSION['profile_picture']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="Profile">
                        <?php else: ?>
                            <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeBtn = document.getElementById('theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    document.body.classList.toggle('theme-robot');
                    const isRobot = document.body.classList.contains('theme-robot');
                    localStorage.setItem('dtq-theme', isRobot ? 'robot' : 'default');
                    
                    // Dispatch event for charts
                    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: isRobot ? 'robot' : 'default' } }));
                });
            }

            const fullscreenBtn = document.getElementById('fullscreen-toggle');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(err => {
                            console.error(`Error attempting to enable fullscreen: ${err.message}`);
                        });
                        fullscreenBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                            fullscreenBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
                        }
                    }
                });
                
                document.addEventListener('fullscreenchange', () => {
                    if (!document.fullscreenElement) {
                        fullscreenBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
                    } else {
                        fullscreenBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
                    }
                });
            }
        });
        </script>

        <!-- CONTENT AREA START -->
        <main class="content-area">
