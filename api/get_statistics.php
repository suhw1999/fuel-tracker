<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/ApiResponse.php';
require_once dirname(__DIR__) . '/classes/VehicleManager.php';
require_once dirname(__DIR__) . '/classes/StatisticsManager.php';

try {
    AuthHelper::validateLogin();
    $vehicleId = VehicleManager::getCurrentVehicleId();

    $statsManager = new StatisticsManager();
    $stats = $statsManager->getAllStatistics($vehicleId);

    ApiResponse::success(['stats' => $stats]);
} catch (Exception $e) {
    error_log('Get statistics error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
