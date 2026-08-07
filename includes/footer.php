<?php
// includes/footer.php
?>
        </main>
        <!-- CONTENT AREA END -->
        
    </div>
    <!-- MAIN WRAPPER END -->

    <!-- Global JS Libraries -->
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/kendo.all.min.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script src="assets/js/select2.min.js"></script>
    <script src="assets/js/xlsx.full.min.js"></script>
    
    <?php if($page == 'dashboard' || $page == 'dtc_dashboard' || $page == 'dtc_detail'): ?>
        <script src="Script/js/dtc/js_dtc_detail.js?v=<?= time() ?>"></script>
        <?php if(isset($headerData) && isset($headerData['measuring_item']) && $headerData['measuring_item'] == 'Qualitative'): ?>
            <script src="Script/js/dtc/js_dtc_qualitative.js?v=<?= time() ?>"></script>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if($page == 'dtc' || $page == 'dtc_list' || $page == 'dtc_matrix_qualitative'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_list.js?v=<?= time() ?>"></script>
    <?php endif; ?>
    
    <?php if($page == 'dtc_matrix_qualitative'): ?>
        <script src="Script/js/dtc/js_dtc_matrix_qualitative.js?v=<?= time() ?>"></script>
    <?php endif; ?>
    
    <?php if($page == 'dtc_history'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_history.js?v=<?= time() ?>"></script>
    <?php endif; ?>
    
    <?php if($page == 'master_spec'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_master_spec.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <?php if($page == 'missing_data'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_missing_data.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <?php if($page == 'oos_summary'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_oos_summary.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <?php if($page == 'settings'): ?>
        <script src="Script/js/dtc/js_dtc_settings.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <?php if($page == 'users'): ?>
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="Script/js/dtc/js_dtc_users.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            // GPU-accelerated smooth auto-scaling for TV 32" / Monitoring Mode
            function applyFitScreen() {
                const mainWrapper = document.querySelector('.main-wrapper');
                const contentArea = document.querySelector('.content-area');
                const topbar = document.querySelector('.topbar');
                
                if (!contentArea) return;
                
                const topbarH = topbar ? topbar.offsetHeight : 52;
                const contentH = contentArea.offsetHeight || (mainWrapper ? mainWrapper.offsetHeight - topbarH : 0);
                
                const naturalHeight = Math.max(topbarH + contentH + 20, 500);
                const winHeight = window.innerHeight;
                
                let targetScale = (winHeight / naturalHeight) - 0.005;
                
                if (targetScale > 1.0) targetScale = 1.0;
                if (targetScale < 0.65) targetScale = 0.65;
                
                requestAnimationFrame(() => {
                    const body = document.body;
                    
                    body.style.willChange = 'transform, zoom';
                    if ('zoom' in body.style) {
                        body.style.transition = 'zoom 0.7s cubic-bezier(0.25, 1, 0.5, 1)';
                        body.style.zoom = targetScale;
                    } else {
                        body.style.transition = 'transform 0.7s cubic-bezier(0.25, 1, 0.5, 1), width 0.7s cubic-bezier(0.25, 1, 0.5, 1)';
                        body.style.transformOrigin = 'top left';
                        body.style.transform = `scale(${targetScale})`;
                        body.style.width = `${(100 / targetScale)}%`;
                    }
                    
                    const scaledHeight = naturalHeight * targetScale;
                    if (scaledHeight <= winHeight + 10) {
                        document.documentElement.style.overflowY = 'hidden';
                        body.style.overflowY = 'hidden';
                    } else {
                        document.documentElement.style.overflowY = 'auto';
                        body.style.overflowY = 'auto';
                    }

                    if (window.kendo) {
                        setTimeout(() => {
                            kendo.resize($(".chart-container, .dense-dashboard, .k-chart"));
                        }, 120);
                    }
                });
            }
            
            applyFitScreen();
            setTimeout(applyFitScreen, 200);
            
            $(window).on('resize orientationchange', applyFitScreen);

            $(document).on('draw.dt ajaxComplete kendoRendered', function() {
                setTimeout(applyFitScreen, 150);
            });

            // Sidebar toggle logic
            $('#btn-toggle-sidebar').click(function() {
                if ($(window).width() <= 768) {
                    $('.sidebar').toggleClass('active-mobile');
                } else {
                    $('.sidebar').toggleClass('collapsed');
                }
                setTimeout(applyFitScreen, 320);
            });

            // Close sidebar on mobile when clicking outside
            $(document).click(function(event) {
                if ($(window).width() <= 768) {
                    if (!$(event.target).closest('.sidebar, #btn-toggle-sidebar').length) {
                        $('.sidebar').removeClass('active-mobile');
                    }
                }
            });
        });
    </script>
</body>
</html>
