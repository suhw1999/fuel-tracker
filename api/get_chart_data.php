<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/ApiResponse.php';
require_once dirname(__DIR__) . '/classes/VehicleManager.php';
require_once dirname(__DIR__) . '/classes/StatisticsManager.php';

try {
    AuthHelper::validateLogin();
    $vehicleId = VehicleManager::getCurrentVehicleId();

    $months = isset($_GET['months']) ? intval($_GET['months']) : 3;

    if ($months < 0 || $months > 120) {
        throw new Exception('无效的时间范围参数');
    }

    $statsManager = new StatisticsManager();
    $chartData = $statsManager->getChartData($months, $vehicleId);

    ApiResponse::success(['chartData' => $chartData]);

} catch (Exception $e) {
    error_log('Get chart data error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
