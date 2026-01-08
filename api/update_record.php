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

    $data = [
        'refuel_date' => $_POST['refuel_date'] ?? '',
        'fuel_amount' => floatval($_POST['fuel_amount'] ?? 0),
        'current_mileage' => intval($_POST['current_mileage'] ?? 0),
        'fuel_price' => floatval($_POST['fuel_price'] ?? 0),
        'total_cost' => floatval($_POST['total_cost'] ?? 0),
        'notes' => trim($_POST['notes'] ?? '')
    ];

    $manager = new FuelRecordManager();
    if ($manager->updateRecord($id, $data)) {
        ApiResponse::success([], '更新成功');
    } else {
        throw new Exception('更新失败');
    }

} catch (Exception $e) {
    error_log('Update record error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
