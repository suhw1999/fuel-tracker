<?php
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/classes/AuthHelper.php';
require_once dirname(dirname(__DIR__)) . '/classes/ApiResponse.php';
require_once dirname(dirname(__DIR__)) . '/classes/VehicleManager.php';

try {
    AuthHelper::validateRequest();

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('无效的车辆ID');
    }

    $vehicleManager = new VehicleManager();
    if ($vehicleManager->setDefaultVehicle($id)) {
        $_SESSION['current_vehicle_id'] = $id;
        setcookie('current_vehicle_id', $id, time() + 7*24*3600, '/');
        ApiResponse::success([], '默认车辆设置成功');
    } else {
        throw new Exception('默认车辆设置失败');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
