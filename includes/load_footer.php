<?php
/**
 * 模板底部加载器 - 根据设备自动选择桌面/手机模板
 */
if (isMobile()) {
    require __DIR__ . '/mobile_footer.php';
} else {
    require __DIR__ . '/footer.php';
}
