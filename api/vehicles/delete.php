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
    if ($vehicleManager->deleteVehicle($id)) {
        ApiResponse::success([], '车辆删除成功');
    } else {
        throw new Exception('车辆删除失败');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
