<?php
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/classes/AuthHelper.php';
require_once dirname(dirname(__DIR__)) . '/classes/ApiResponse.php';
require_once dirname(dirname(__DIR__)) . '/classes/VehicleManager.php';

try {
    AuthHelper::validateLogin();

    $vehicleManager = new VehicleManager();

    $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';
    $vehicles = $vehicleManager->getAllVehicles($includeInactive);

    $currentVehicleId = $_SESSION['current_vehicle_id'] ?? null;
    if (!$currentVehicleId) {
        $defaultVehicle = $vehicleManager->getDefaultVehicle();
        $currentVehicleId = $defaultVehicle ? $defaultVehicle['id'] : null;
    }

    ApiResponse::success([
        'vehicles' => $vehicles,
        'currentVehicleId' => $currentVehicleId
    ]);
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
