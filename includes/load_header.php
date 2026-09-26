<?php
/**
 * 模板头部加载器 - 根据设备自动选择桌面/手机模板
 * 直接 require 此文件即可，变量在页面全局作用域可用
 */
if (isMobile()) {
    require __DIR__ . '/mobile_header.php';
} else {
    require __DIR__ . '/header.php';
}
