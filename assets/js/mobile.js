/**
 * 白屿云平台 BotAPI - 手机端二次元交互脚本 v1.0.0
 * 包含：侧滑抽屉、底部导航、星屑粒子、触控增强
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); return; }
        document.addEventListener('DOMContentLoaded', fn);
    }

    /* ===== 轻量星屑粒子（移动端优化） ===== */
    function initSparkles() {
        var canvas = document.createElement('canvas');
        canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;';
        canvas.id = 'sparkle-canvas';
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var sparks = [];
        var maxSparks = 15;
        var w, h;

        function resize() { w = canvas.width = window.innerWidth; h = canvas.height = window.innerHeight; }
        resize();
        window.addEventListener('resize', resize);

        function createSpark() {
            return {
                x: Math.random() * w,
                y: Math.random() * h,
                size: 1.5 + Math.random() * 3,
                speedY: 0.2 + Math.random() * 0.5,
                speedX: (Math.random() - 0.5) * 0.3,
                opacity: 0.2 + Math.random() * 0.3,
                twinkle: Math.random() * Math.PI * 2,
                twinkleSpeed: 0.02 + Math.random() * 0.03
            };
        }

        for (var i = 0; i < maxSparks; i++) sparks.push(createSpark());

        function animate() {
            ctx.clearRect(0, 0, w, h);
            for (var i = 0; i < sparks.length; i++) {
                var s = sparks[i];
                s.y += s.speedY;
                s.x += s.speedX;
                s.twinkle += s.twinkleSpeed;
                if (s.y > h + 10) { s.y = -10; s.x = Math.random() * w; }
                if (s.x > w + 10) s.x = -10;
                if (s.x < -10) s.x = w + 10;

                var alpha = s.opacity * (0.5 + 0.5 * Math.sin(s.twinkle));
                ctx.globalAlpha = alpha;

                // 星形
                ctx.fillStyle = Math.random() < 0.5 ? '#f9a8d4' : '#c4b5fd';
                ctx.shadowColor = Math.random() < 0.5 ? 'rgba(244,114,182,0.5)' : 'rgba(192,132,252,0.5)';
                ctx.shadowBlur = 3;
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.globalAlpha = 1;
            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            requestAnimationFrame(animate);
        }
        animate();
    }

    /* ===== 侧滑抽屉 ===== */
    function initDrawer() {
        var drawer = document.getElementById('mobileDrawer');
        var overlay = document.getElementById('mobileDrawerOverlay');
        var menuBtn = document.getElementById('mobileMenuBtn');
        var closeBtn = document.getElementById('mobileDrawerClose');

        if (!drawer) return;

        function openDrawer() {
            drawer.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            drawer.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (menuBtn) menuBtn.addEventListener('click', openDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (overlay) overlay.addEventListener('click', closeDrawer);

        // 滑动手势关闭
        var touchStartX = 0;
        drawer.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        drawer.addEventListener('touchmove', function (e) {
            var diff = e.touches[0].clientX - touchStartX;
            if (diff < -50) { closeDrawer(); }
        }, { passive: true });
    }

    /* ===== 底部导航高亮 ===== */
    function initBottomNav() {
        var nav = document.getElementById('mobileBottomNav');
        if (!nav) return;

        var currentPath = window.location.pathname;
        var links = nav.querySelectorAll('a[data-nav]');

        links.forEach(function (link) {
            link.classList.remove('active');
            var navPage = link.getAttribute('data-nav');
            if (!navPage) return;

            if (navPage === 'home' && (currentPath === '/' || currentPath.endsWith('/index.php'))) {
                link.classList.add('active');
            } else if (navPage === 'api_add' && currentPath.includes('/api_add')) {
                link.classList.add('active');
            } else if (navPage === 'bot' && currentPath.includes('/bot_management')) {
                link.classList.add('active');
            } else if (navPage === 'msg_list' && (currentPath.includes('/msg_list') || currentPath.includes('/message_logs'))) {
                link.classList.add('active');
            } else if (navPage === 'profile' && currentPath.includes('/profile')) {
                link.classList.add('active');
            }
        });
    }

    /* ===== 移动端暗色模式 ===== */
    function initMobileDarkMode() {
        var toggle = document.getElementById('mobileThemeToggle');
        if (!toggle) return;

        updateThemeIcon();

        toggle.addEventListener('click', function () {
            var isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('yunhu-theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
            if (window.showToast) {
                showToast(isDark ? '已切换到星空暗色模式 🌙' : '已切换到樱花亮色模式 ☀️', 'info');
            }
        });

        function updateThemeIcon() {
            var isDark = document.documentElement.classList.contains('dark');
            var moon = toggle.querySelector('.fa-moon');
            var sun = toggle.querySelector('.fa-sun');
            if (moon) moon.style.display = isDark ? 'none' : '';
            if (sun) sun.style.display = isDark ? '' : 'none';
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            var saved = localStorage.getItem('yunhu-theme');
            if (!saved) {
                if (e.matches) document.documentElement.classList.add('dark');
                else document.documentElement.classList.remove('dark');
                updateThemeIcon();
            }
        });
    }

    /* ===== 列表项滑动操作 ===== */
    function initSwipeActions() {
        var touchEl = null;
        var startX = 0, startY = 0;

        document.addEventListener('touchstart', function (e) {
            var row = e.target.closest('.item-card, .data-table tbody tr');
            if (!row) return;
            touchEl = row;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!touchEl) return;
            var diffX = e.touches[0].clientX - startX;
            var diffY = e.touches[0].clientY - startY;
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) < 100) {
                touchEl.style.transform = 'translateX(' + diffX + 'px)';
                touchEl.style.transition = 'none';
            }
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (touchEl) {
                touchEl.style.transform = '';
                touchEl.style.transition = 'transform 0.3s ease';
                touchEl = null;
            }
        });
    }

    /* ===== 长按振动 ===== */
    function initLongPress() {
        var timer = null;
        document.addEventListener('touchstart', function (e) {
            var row = e.target.closest('.data-table tbody tr');
            if (!row) return;
            timer = setTimeout(function () {
                if (navigator.vibrate) navigator.vibrate(15);
            }, 500);
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (timer) { clearTimeout(timer); timer = null; }
        });
        document.addEventListener('touchmove', function () {
            if (timer) { clearTimeout(timer); timer = null; }
        }, { passive: true });
    }

    /* ===== Viewport 高度修正 ===== */
    function fixViewportHeight() {
        var vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', vh + 'px');
    }
    window.addEventListener('resize', fixViewportHeight);
    window.addEventListener('orientationchange', function () { setTimeout(fixViewportHeight, 100); });

    /* ===== 主入口 ===== */
    ready(function () {
        fixViewportHeight();
        initSparkles();
        initDrawer();
        initBottomNav();
        initMobileDarkMode();
        initSwipeActions();
        initLongPress();
    });

})();
