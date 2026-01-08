<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/DatabaseManager.php';

class StatisticsManager {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance()->getConnection();
    }

    /**
     * 执行带车辆过滤的查询
     * @param string $sql SQL语句，使用 {WHERE} 占位符
     * @param int|null $vehicleId 车辆ID
     * @param array $extraParams 额外的参数
     * @return PDOStatement
     */
    private function queryWithVehicle(string $sql, ?int $vehicleId, array $extraParams = []): PDOStatement {
        $params = [];
        $whereClause = '';

        if ($vehicleId) {
            $whereClause = 'WHERE vehicle_id = ?';
            $params[] = $vehicleId;
        }

        $sql = str_replace('{WHERE}', $whereClause, $sql);
        $sql = str_replace('{AND_VEHICLE}', $vehicleId ? 'AND vehicle_id = ?' : '', $sql);

        if ($vehicleId && strpos($sql, '{AND_VEHICLE}') !== false) {
            $params[] = $vehicleId;
        }

        $params = array_merge($params, $extraParams);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * 获取所有统计数据（优化版：4次查询代替原来的9次）
     * @param int|null $vehicleId 车辆ID
     * @return array 统计数据
     */
    public function getAllStatistics(?int $vehicleId = null): array {
        // 第1次查询：获取主要统计数据
        $sql = <<<SQL
            SELECT COUNT(*) as record_count,
                   AVG(calculated_consumption) as avg_consumption,
                   SUM(fuel_amount) as total_fuel,
                   SUM(total_cost) as total_cost,
                   AVG(fuel_price) as avg_price,
                   MAX(current_mileage) as max_mileage,
                   MIN(current_mileage) as min_mileage
            FROM fuel_records {WHERE}
        SQL;
        $mainStats = $this->queryWithVehicle($sql, $vehicleId)->fetch();

        // 第2次查询：获取最后一次加油信息
        $sql = <<<SQL
            SELECT refuel_date, current_mileage, fuel_amount
            FROM fuel_records {WHERE}
            ORDER BY refuel_date DESC, id DESC LIMIT 1
        SQL;
        $lastRefuel = $this->queryWithVehicle($sql, $vehicleId)->fetch();

        // 第3次查询：获取近90天花费（用于日均花费计算）
        $sql = $vehicleId
            ? "SELECT SUM(total_cost) as total_cost_90d FROM fuel_records WHERE vehicle_id = ? AND refuel_date >= date('now', '-90 days')"
            : "SELECT SUM(total_cost) as total_cost_90d FROM fuel_records WHERE refuel_date >= date('now', '-90 days')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($vehicleId ? [$vehicleId] : []);
        $cost90d = $stmt->fetch();

        // 第4次查询：获取近3个月里程统计（用于日均里程计算）
        $sql = $vehicleId
            ? "SELECT MIN(refuel_date) as first_date, MAX(refuel_date) as last_date, MAX(current_mileage) as max_mileage, MIN(current_mileage) as min_mileage FROM fuel_records WHERE vehicle_id = ? AND refuel_date >= date('now', '-3 months')"
            : "SELECT MIN(refuel_date) as first_date, MAX(refuel_date) as last_date, MAX(current_mileage) as max_mileage, MIN(current_mileage) as min_mileage FROM fuel_records WHERE refuel_date >= date('now', '-3 months')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($vehicleId ? [$vehicleId] : []);
        $mileage3m = $stmt->fetch();

        // 计算日均花费
        $avgCostPerDay = 0;
        if ($cost90d['total_cost_90d']) {
            $avgCostPerDay = round($cost90d['total_cost_90d'] / 90, 2);
        }

        // 计算日均里程
        $avgMileagePerDay = 0;
        if ($mileage3m['first_date'] && $mileage3m['last_date'] &&
            $mileage3m['max_mileage'] !== null && $mileage3m['min_mileage'] !== null) {
            $firstDate = new DateTime($mileage3m['first_date']);
            $lastDate = new DateTime($mileage3m['last_date']);
            $interval = $firstDate->diff($lastDate);
            $totalDays = max(1, $interval->days);
            $totalMileage = $mileage3m['max_mileage'] - $mileage3m['min_mileage'];
            $avgMileagePerDay = round($totalMileage / $totalDays, 2);
        }

        // 组装返回数据
        return [
            'average_consumption' => $mainStats['avg_consumption'] ? round($mainStats['avg_consumption'], 2) : 0,
            'total_mileage' => ($mainStats['max_mileage'] && $mainStats['min_mileage']) ?
                               ($mainStats['max_mileage'] - $mainStats['min_mileage']) : 0,
            'total_fuel' => $mainStats['total_fuel'] ? round($mainStats['total_fuel'], 2) : 0,
            'total_cost' => $mainStats['total_cost'] ? round($mainStats['total_cost'], 2) : 0,
            'average_price' => $mainStats['avg_price'] ? round($mainStats['avg_price'], 2) : 0,
            'last_refuel' => $lastRefuel ?: null,
            'record_count' => $mainStats['record_count'],
            'average_cost_per_day' => $avgCostPerDay,
            'average_mileage_per_day' => $avgMileagePerDay
        ];
    }

    /**
     * 获取图表数据(用于油耗趋势图)
     * @param int $months 显示最近几个月的数据，0表示全部
     * @param int $vehicleId 车辆ID（可选）
     */
    public function getChartData($months = 3, $vehicleId = null) {
        $params = [];
        $whereConditions = ["calculated_consumption IS NOT NULL"];

        if ($vehicleId) {
            $whereConditions[] = "vehicle_id = ?";
            $params[] = $vehicleId;
        }

        if ($months > 0) {
            $whereConditions[] = "refuel_date >= date('now', '-' || ? || ' months')";
            $params[] = $months;
        }

        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        $sql = "SELECT refuel_date, calculated_consumption, fuel_price
                FROM fuel_records
                {$whereClause}
                ORDER BY refuel_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 获取月度统计
     * @param int|null $vehicleId 车辆ID
     * @return array 月度统计数据
     */
    public function getMonthlyStatistics(?int $vehicleId = null): array {
        $sql = <<<SQL
            SELECT strftime('%Y-%m', refuel_date) as month,
                   COUNT(*) as refuel_count,
                   SUM(fuel_amount) as total_fuel,
                   SUM(total_cost) as total_cost,
                   AVG(calculated_consumption) as avg_consumption
            FROM fuel_records {WHERE}
            GROUP BY strftime('%Y-%m', refuel_date)
            ORDER BY month DESC
        SQL;
        return $this->queryWithVehicle($sql, $vehicleId)->fetchAll();
    }
}
