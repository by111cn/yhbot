/**
 * 白屿云平台 BotAPI 管理系统 - 二次元交互脚本 v4.0
 * 包含：樱花飘落、侧边栏、下拉菜单、模态框等
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); return; }
    document.addEventListener('DOMContentLoaded', fn);
  }

  /* ===== 樱花飘落粒子系统 ===== */
  function initSakura() {
    var canvas = document.getElementById('sakura-canvas');
    if (!canvas) { canvas = document.createElement('canvas'); canvas.id = 'sakura-canvas'; document.body.appendChild(canvas); }
    var ctx = canvas.getContext('2d');
    var petals = [];
    var maxPetals = 35;
    var w, h;

    function resize() { w = canvas.width = window.innerWidth; h = canvas.height = window.innerHeight; }
    resize();
    window.addEventListener('resize', resize);

    // 樱花花瓣 SVG 路径绘制
    function drawPetalShape(ctx, x, y, size, rotation) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(rotation);
      ctx.scale(size / 12, size / 12);

      // 樱花花瓣形状
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.bezierCurveTo(-4, -4, -8, -2, -6, 2);
      ctx.bezierCurveTo(-8, 6, -2, 8, 0, 5);
      ctx.bezierCurveTo(2, 8, 8, 6, 6, 2);
      ctx.bezierCurveTo(8, -2, 4, -4, 0, 0);
      ctx.closePath();
      ctx.restore();
    }

    // 星屑
    function drawStar(ctx, x, y, size) {
      ctx.save();
      ctx.translate(x, y);
      ctx.beginPath();
      for (var i = 0; i < 5; i++) {
        var angle = (i * Math.PI * 2) / 5 - Math.PI / 2;
        var r = i === 0 ? size : size * 0.4;
        ctx.lineTo(Math.cos(angle) * r, Math.sin(angle) * r);
        angle += Math.PI / 5;
        r = size * 0.4;
        ctx.lineTo(Math.cos(angle) * r, Math.sin(angle) * r);
      }
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }

    // 花瓣颜色方案
    var colorSchemes = [
      { fill: 'rgba(244,114,182,0.55)', stroke: 'rgba(244,114,182,0.25)' },
      { fill: 'rgba(251,182,206,0.45)', stroke: 'rgba(251,182,206,0.2)' },
      { fill: 'rgba(219,39,119,0.35)', stroke: 'rgba(219,39,119,0.15)' },
      { fill: 'rgba(255,228,241,0.55)', stroke: 'rgba(255,228,241,0.25)' },
      { fill: 'rgba(192,132,252,0.35)', stroke: 'rgba(192,132,252,0.15)' },
      { fill: 'rgba(244,114,182,0.3)', stroke: 'rgba(244,114,182,0.12)' },
    ];

    // 创建花瓣
    function createPetal() {
      var scheme = colorSchemes[Math.floor(Math.random() * colorSchemes.length)];
      return {
        x: Math.random() * w,
        y: -20 - Math.random() * 100,
        size: 6 + Math.random() * 12,
        speedX: -1 + Math.random() * 2,
        speedY: 0.6 + Math.random() * 1.8,
        rotation: Math.random() * Math.PI * 2,
        rotationSpeed: (Math.random() - 0.5) * 0.04,
        swing: Math.random() * 2,
        swingSpeed: 0.01 + Math.random() * 0.02,
        opacity: 0.3 + Math.random() * 0.5,
        color: scheme,
        time: Math.random() * 100,
        type: Math.random() < 0.85 ? 'petal' : 'star'
      };
    }

    // 初始化花瓣
    for (var i = 0; i < maxPetals; i++) {
      var p = createPetal();
      p.y = Math.random() * h;
      petals.push(p);
    }

    function animate() {
      ctx.clearRect(0, 0, w, h);
      for (var i = 0; i < petals.length; i++) {
        var p = petals[i];
        p.time += 0.016;

        // 水平摆动
        var swing = Math.sin(p.time * p.swingSpeed * 60) * p.swing;
        p.x += p.speedX + swing * 0.3;
        p.y += p.speedY;
        p.rotation += p.rotationSpeed;

        // 循环
        if (p.y > h + 30) { p.y = -30; p.x = Math.random() * w; }
        if (p.x > w + 30) { p.x = -30; }
        if (p.x < -30) { p.x = w + 30; }

        ctx.globalAlpha = p.opacity;
        if (p.type === 'petal') {
          drawPetalShape(ctx, p.x, p.y, p.size, p.rotation);
          ctx.fillStyle = p.color.fill;
          ctx.fill();
          ctx.strokeStyle = p.color.stroke;
          ctx.lineWidth = 0.5;
          ctx.stroke();
        } else {
          drawStar(ctx, p.x, p.y, p.size * 0.4);
          ctx.fillStyle = 'rgba(255,255,255,0.4)';
          ctx.shadowColor = 'rgba(244,114,182,0.5)';
          ctx.shadowBlur = 4;
        }
        ctx.globalAlpha = 1;
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
      }
      requestAnimationFrame(animate);
    }
    animate();

    // 点击产生花瓣爆炸
    document.addEventListener('click', function(e) {
      for (var i = 0; i < 5; i++) {
        var petal = {
          x: e.clientX, y: e.clientY,
          size: 4 + Math.random() * 8,
          speedX: (Math.random() - 0.5) * 4,
          speedY: -Math.random() * 3 - 1,
          rotation: Math.random() * Math.PI * 2,
          rotationSpeed: (Math.random() - 0.5) * 0.1,
          swing: 1, swingSpeed: 0.03,
          opacity: 0.4 + Math.random() * 0.3,
          color: colorSchemes[Math.floor(Math.random() * colorSchemes.length)],
          time: 0, type: Math.random() < 0.6 ? 'petal' : 'star',
          life: 60 + Math.random() * 40
        };
        petals.push(petal);
        if (petals.length > 60) petals.shift();
      }
    });
  }

  /* ===== 侧边栏 ===== */
  function initSidebar() {
    var sidebar = document.getElementById('sidebar');
    var menuToggle = document.getElementById('menuToggle');
    var sidebarToggle = document.getElementById('sidebarToggle');

    var overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
      if (sidebar) sidebar.classList.add('open');
      if (overlay) overlay.classList.add('show');
    }
    function closeSidebar() {
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('show');
    }

    if (menuToggle) {
      menuToggle.addEventListener('click', function () {
        if (sidebar && sidebar.classList.contains('open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', closeSidebar);
    }
    if (overlay) {
      overlay.addEventListener('click', closeSidebar);
    }
    document.addEventListener('click', function (e) {
      if (sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
          closeSidebar();
        }
      }
    });
  }

  /* ===== 导航下拉 ===== */
  function initNavDropdowns() {
    document.querySelectorAll('.dropdown-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        var li = this.parentElement;
        if (li.classList.contains('open')) {
          li.classList.remove('open');
        } else {
          document.querySelectorAll('.nav-dropdown.open').forEach(function (d) { d.classList.remove('open'); });
          li.classList.add('open');
        }
      });
    });
  }

  /* ===== 用户下拉 ===== */
  function initUserDropdown() {
    var userDropdown = document.getElementById('userDropdown');
    var userMenu = document.getElementById('userMenu');
    if (userDropdown && userMenu) {
      userDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
        userMenu.classList.toggle('show');
      });
      document.addEventListener('click', function () {
        userMenu.classList.remove('show');
      });
    }
  }

  /* ===== 标签页 ===== */
  function initTabs() {
    document.querySelectorAll('.tab-item').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = this.getAttribute('data-target');
        var parent = this.closest('.tabs').parentElement;
        parent.querySelectorAll('.tab-item').forEach(function (t) { t.classList.remove('active'); });
        parent.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('active'); });
        this.classList.add('active');
        if (target) {
          var pane = document.getElementById(target);
          if (pane) pane.classList.add('active');
        }
      });
    });
  }

  /* ===== 模态框 ===== */
  function initModals() {
    window.openModal = function (modalId) {
      var modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('show');
        document.body.classList.add('modal-open');
      }
    };
    window.closeModal = function (modalId) {
      var modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
      }
    };
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
          overlay.classList.remove('show');
          document.body.classList.remove('modal-open');
        }
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function (m) {
          m.classList.remove('show');
        });
        document.body.classList.remove('modal-open');
      }
    });
  }

  /* ===== 图标选择器 ===== */
  function initIconPicker() {
    document.querySelectorAll('.icon-option').forEach(function (icon) {
      icon.addEventListener('click', function () {
        var picker = this.closest('.icon-picker');
        picker.querySelectorAll('.icon-option').forEach(function (i) { i.classList.remove('selected'); });
        this.classList.add('selected');
        var input = picker.querySelector('input[type="hidden"]');
        if (input) input.value = this.getAttribute('data-icon');
      });
    });
  }

  /* ===== Toast 通知 ===== */
  window.showToast = function (message, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.id = 'toastContainer';
      document.body.appendChild(container);
    }
    var icons = {
      success: 'fa-circle-check',
      error: 'fa-circle-xmark',
      warning: 'fa-triangle-exclamation',
      info: 'fa-circle-info'
    };
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '" style="font-size:16px;"></i>' +
                      '<span>' + (message || '').replace(/</g, '&lt;').replace(/>/g,'&gt;') + '</span>';
    container.appendChild(toast);

    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(120%)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(function () {
        if (toast.parentNode) toast.remove();
      }, 300);
    }, 3200);
  };

  /* ===== 复制到剪贴板 ===== */
  window.copyToClipboard = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () {
        showToast('已复制到剪贴板 ✨', 'success');
      }).catch(function () {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  };
  function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.cssText = 'position:fixed;opacity:0;left:-9999px;top:-9999px;';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showToast('已复制到剪贴板 ✨', 'success');
    } catch (e) {
      showToast('复制失败，请手动复制', 'error');
    }
    document.body.removeChild(textarea);
  }

  /* ===== 删除确认 ===== */
  window.confirmDelete = function (url, msg) {
    if (confirm(msg || '确定要删除吗？此操作不可恢复！')) {
      fetch(url, { method: 'POST' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.code === 0) {
            showToast(data.msg || '删除成功', 'success');
            setTimeout(function () { location.reload(); }, 800);
          } else {
            showToast(data.msg || '删除失败', 'error');
          }
        })
        .catch(function () {
          showToast('网络错误，请重试', 'error');
        });
    }
    return false;
  };

  /* ===== 表单 AJAX 提交 ===== */
  window.ajaxSubmit = function (form, callback) {
    var formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (callback) callback(data);
      })
      .catch(function (err) { console.error(err); });
    return false;
  };

  /* ===== 回到顶部 ===== */
  function initBackToTop() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(function () {
          btn.classList.toggle('show', window.scrollY > 300);
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ===== Alert 自动关闭 ===== */
  function initAlerts() {
    document.querySelectorAll('.alert').forEach(function (alert) {
      setTimeout(function () {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.4s';
        setTimeout(function () { if (alert.parentNode) alert.remove(); }, 400);
      }, 5000);
    });
  }

  /* ===== 暗色模式 ===== */
  function initDarkMode() {
    var saved = localStorage.getItem('yunhu-theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.classList.add('dark');
    }
    var toggle = document.getElementById('themeToggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('yunhu-theme', isDark ? 'dark' : 'light');
        showToast(isDark ? '已切换到星空暗色模式 🌙' : '已切换到樱花亮色模式 ☀️', 'info');
      });
    }
  }

  /* ===== 页面过渡 ===== */
  function initPageTransitions() {
    var wrapper = document.querySelector('.content-wrapper');
    if (wrapper) {
      wrapper.style.opacity = '0';
      wrapper.style.transform = 'translateY(12px)';
      wrapper.style.transition = 'opacity 0.4s ease, transform 0.4s cubic-bezier(0.25,0.8,0.25,1.2)';
      requestAnimationFrame(function () {
        wrapper.style.opacity = '1';
        wrapper.style.transform = 'translateY(0)';
      });
    }
  }

  /* ===== 输入框聚焦 ===== */
  function initInputFocus() {
    document.querySelectorAll('.form-control').forEach(function (input) {
      input.addEventListener('focus', function () {
        var group = this.closest('.form-group');
        if (group) group.classList.add('focused');
      });
      input.addEventListener('blur', function () {
        var group = this.closest('.form-group');
        if (group) group.classList.remove('focused');
      });
    });
  }

  /* ===== 统计数字滚动动画 ===== */
  function initCountAnimation() {
    document.querySelectorAll('.stat-number').forEach(function (el) {
      var target = parseInt(el.textContent.replace(/,/g, ''), 10);
      if (isNaN(target) || target === 0) return;
      var current = 0;
      var duration = 1000;
      var step = Math.max(1, Math.floor(target / (duration / 16)));
      var timer = setInterval(function () {
        current += step;
        if (current >= target) { current = target; clearInterval(timer); }
        el.textContent = current.toLocaleString();
      }, 16);
    });
  }

  /* ===== 自动高亮导航 ===== */
  function initActiveNav() {
    var currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-nav a[href]').forEach(function (link) {
      if (link.getAttribute('href') === currentPath || currentPath.endsWith(link.getAttribute('href'))) {
        link.classList.add('current-page');
      }
    });
  }

  /* ===== 主入口 ===== */
  ready(function () {
    initSakura();
    initSidebar();
    initNavDropdowns();
    initUserDropdown();
    initTabs();
    initModals();
    initIconPicker();
    initBackToTop();
    initAlerts();
    initDarkMode();
    initPageTransitions();
    initInputFocus();
    initCountAnimation();
    initActiveNav();
  });

})();
