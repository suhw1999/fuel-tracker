<?php
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/classes/AuthHelper.php';
require_once dirname(dirname(__DIR__)) . '/classes/ApiResponse.php';
require_once dirname(dirname(__DIR__)) . '/classes/VehicleManager.php';

try {
    AuthHelper::validateRequest();

    $data = [
        'name' => $_POST['name'] ?? '',
        'plate_number' => $_POST['plate_number'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $vehicleManager = new VehicleManager();
    if ($vehicleManager->addVehicle($data)) {
        ApiResponse::success([], '车辆添加成功');
    } else {
        throw new Exception('车辆添加失败');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
