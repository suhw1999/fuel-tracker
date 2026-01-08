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
    $vehicle = $vehicleManager->getVehicleById($id);

    if (!$vehicle) {
        throw new Exception('车辆不存在');
    }

    if ($vehicleManager->toggleVehicleStatus($id)) {
        $newStatus = $vehicle['is_active'] == 1 ? '停用' : '激活';
        ApiResponse::success([
            'new_status' => $vehicle['is_active'] == 1 ? 0 : 1
        ], "车辆已{$newStatus}");
    } else {
        throw new Exception('状态切换失败');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
