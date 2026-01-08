<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/AuthHelper.php';
require_once dirname(__DIR__) . '/classes/VehicleManager.php';
require_once dirname(__DIR__) . '/classes/FuelRecordManager.php';

try {
    AuthHelper::validateLogin();

    $vehicleId = VehicleManager::getCurrentVehicleId();
    $vehicleManager = new VehicleManager();
    $vehicle = $vehicleManager->getVehicleById($vehicleId);
    $vehicleName = $vehicle ? $vehicle['name'] : '所有车辆';

    $manager = new FuelRecordManager();
    $records = $manager->getAllRecords($vehicleId);

    $filename = "fuel_records_{$vehicleName}_" . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        '日期', '加油量(升)', '当前里程(公里)', '油价(元/升)',
        '总金额(元)', '油耗(升/百公里)', '备注'
    ]);

    foreach ($records as $record) {
        fputcsv($output, [
            $record['refuel_date'],
            $record['fuel_amount'],
            $record['current_mileage'],
            $record['fuel_price'],
            $record['total_cost'],
            $record['calculated_consumption'] ?? '-',
            $record['notes'] ?? ''
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    error_log('Export CSV error: ' . $e->getMessage());
    http_response_code(500);
    die('导出失败: ' . $e->getMessage());
}
