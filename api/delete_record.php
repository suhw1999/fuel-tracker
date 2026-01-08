<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/ApiResponse.php';
require_once dirname(__DIR__) . '/classes/FuelRecordManager.php';

try {
    AuthHelper::validateRequest();

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('无效的记录ID');
    }

    $manager = new FuelRecordManager();
    if ($manager->deleteRecord($id)) {
        ApiResponse::success([], '删除成功');
    } else {
        throw new Exception('删除失败');
    }

} catch (Exception $e) {
    error_log('Delete record error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
