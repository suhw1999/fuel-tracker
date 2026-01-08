<?php
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/classes/AuthHelper.php';
require_once dirname(dirname(__DIR__)) . '/classes/ApiResponse.php';
require_once dirname(dirname(__DIR__)) . '/classes/VehicleManager.php';

try {
    AuthHelper::validateRequest();

    $vehicleId = intval($_POST['vehicle_id'] ?? 0);
    if ($vehicleId <= 0) {
        throw new Exception('无效的车辆ID');
    }

    $vehicleManager = new VehicleManager();
    $vehicle = $vehicleManager->getVehicleById($vehicleId);

    if (!$vehicle) {
        throw new Exception('车辆不存在');
    }

    if ($vehicle['is_active'] != 1) {
        throw new Exception('车辆已停用，无法切换');
    }

    $_SESSION['current_vehicle_id'] = $vehicleId;
    setcookie('current_vehicle_id', $vehicleId, time() + 7*24*3600, '/');

    ApiResponse::success([
        'vehicle_id' => $vehicleId,
        'vehicle_name' => $vehicle['name']
    ], '车辆切换成功');
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
