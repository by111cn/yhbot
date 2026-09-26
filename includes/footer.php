            </div><!-- .content-wrapper -->
        </main><!-- .main-content -->
    </div><!-- .app-container -->

    <!-- 回到顶部 -->
    <button class="back-to-top" id="backToTop" title="回到顶部">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Toast 通知容器 -->
    <div class="toast-container" id="toastContainer"></div>

    <?php if (isset($_SESSION['show_announcement'])): 
        $announcement = getSetting('site_notice'); 
        unset($_SESSION['show_announcement']);
        if ($announcement): 
    ?>
    <!-- 网站公告弹窗 -->
    <div id="announcementModal" class="announcement-overlay" style="display:flex;">
        <div class="announcement-modal">
            <div class="announcement-header">
                <span class="announcement-title"><i class="fas fa-bullhorn"></i> 网站公告</span>
            </div>
            <div class="announcement-body">
                <?php echo nl2br(h($announcement)); ?>
            </div>
            <div class="announcement-footer">
                <button type="button" class="announcement-close-btn" onclick="closeAnnouncement()">
                    <i class="fas fa-times"></i> 关闭
                </button>
            </div>
        </div>
    </div>
    <style>
    .announcement-overlay {
        position: fixed; inset: 0; z-index: 10000;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center;
        animation: announceFadeIn 0.25s ease;
    }
    @keyframes announceFadeIn { from{opacity:0} to{opacity:1} }
    .announcement-modal {
        background: var(--bg-card, #fff);
        border-radius: 16px;
        width: 90%; max-width: 460px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: announceSlideUp 0.35s cubic-bezier(0.16,1,0.3,1);
        overflow: hidden;
    }
    @keyframes announceSlideUp { from{opacity:0;transform:translateY(30px) scale(0.95)} to{opacity:1;transform:translateY(0) scale(1)} }
    .announcement-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border, #eee);
        background: linear-gradient(135deg, #6366f1, #818cf8);
    }
    .announcement-title {
        font-size: 16px; font-weight: 600; color: #fff;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .announcement-body {
        padding: 24px;
        font-size: 14px; line-height: 1.8;
        color: var(--text, #333);
        max-height: 50vh; overflow-y: auto;
        white-space: pre-wrap;
    }
    .announcement-footer {
        padding: 12px 24px 20px;
        display: flex; justify-content: flex-end;
    }
    .announcement-close-btn {
        background: var(--primary, #6366f1);
        color: #fff; border: none; cursor: pointer;
        padding: 8px 24px; border-radius: 8px;
        font-size: 14px; font-weight: 500;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    }
    .announcement-close-btn:hover {
        opacity: 0.9; transform: scale(1.03);
    }
    </style>
    <script>
    function closeAnnouncement() {
        var m = document.getElementById('announcementModal');
        if (m) m.remove();
    }
    document.addEventListener('click', function(e) {
        var m = document.getElementById('announcementModal');
        if (m && e.target === m) closeAnnouncement();
    });
    </script>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 全局 JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/main.js'); ?>"></script>
</body>
</html>
