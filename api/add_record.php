<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/ApiResponse.php';
require_once dirname(__DIR__) . '/classes/VehicleManager.php';
require_once dirname(__DIR__) . '/classes/FuelRecordManager.php';

try {
    AuthHelper::validateRequest();

    $vehicleId = VehicleManager::getCurrentVehicleId();

    $data = [
        'refuel_date' => $_POST['refuel_date'] ?? '',
        'fuel_amount' => floatval($_POST['fuel_amount'] ?? 0),
        'current_mileage' => intval($_POST['current_mileage'] ?? 0),
        'fuel_price' => floatval($_POST['fuel_price'] ?? 0),
        'total_cost' => floatval($_POST['total_cost'] ?? 0),
        'notes' => trim($_POST['notes'] ?? '')
    ];

    $manager = new FuelRecordManager();
    if ($manager->addRecord($data, $vehicleId)) {
        ApiResponse::success([], '添加成功');
    } else {
        throw new Exception('添加失败');
    }

} catch (Exception $e) {
    error_log('Add record error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
