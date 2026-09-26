<?php
/**
 * 分类相关API接口
 */
require_once '../config.php';
require_once '../functions.php';
requireLogin();

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'delete':
        // 删除分类（允许删除自己的分类 + 系统的公共分类 user_id=0）
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM api_categories WHERE id = ? AND (user_id = ? OR user_id = 0)");
        $stmt->execute([$id, $userId]);
        $cat = $stmt->fetch();

        if ($cat) {
            // 检查该分类下是否有API
            $count = countTable('apis', 'category_id = ?', [$id]);
            if ($count > 0) {
                error('该分类下存在API，无法删除');
                exit;
            }
            $stmt = db()->prepare("DELETE FROM api_categories WHERE id = ?");
            $stmt->execute([$id]);
            success('删除成功');
        } else {
            error('分类不存在');
        }
        break;
        
    case 'delete_template':
        // 删除模板
        $id = (int)($_GET['id'] ?? 0);
        $stmt = db()->prepare("DELETE FROM templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) {
            success('删除成功');
        } else {
            error('模板不存在');
        }
        break;
        
    default:
        error('未知操作');
}
