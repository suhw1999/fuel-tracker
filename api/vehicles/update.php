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

    $data = [
        'name' => $_POST['name'] ?? '',
        'plate_number' => $_POST['plate_number'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $vehicleManager = new VehicleManager();
    if ($vehicleManager->updateVehicle($id, $data)) {
        ApiResponse::success([], '车辆更新成功');
    } else {
        throw new Exception('车辆更新失败');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
