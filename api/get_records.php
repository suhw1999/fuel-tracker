<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/ApiResponse.php';
require_once dirname(__DIR__) . '/classes/VehicleManager.php';
require_once dirname(__DIR__) . '/classes/FuelRecordManager.php';

try {
    AuthHelper::validateLogin();
    $vehicleId = VehicleManager::getCurrentVehicleId();

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : RECORDS_PER_PAGE;

    if (!in_array($perPage, RECORDS_PAGE_SIZES)) {
        $perPage = RECORDS_PER_PAGE;
    }

    $recordManager = new FuelRecordManager();
    $records = $recordManager->getRecordsPaginated($page, $perPage, $vehicleId);
    $totalPages = $recordManager->getTotalPages($perPage, $vehicleId);
    $totalRecords = $recordManager->getRecordCount($vehicleId);

    ApiResponse::success([
        'records' => $records,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'totalRecords' => $totalRecords,
        'perPage' => $perPage
    ]);
} catch (Exception $e) {
    error_log('Get records error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage());
}
