<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/DatabaseManager.php';

class FuelRecordManager {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance()->getConnection();
    }

    /**
     * 验证记录数据
     */
    public function validateRecord($data) {
        $errors = [];

        // 验证加油日期
        if (empty($data['refuel_date'])) {
            $errors[] = '加油日期不能为空';
        }

        // 验证加油量
        if (!isset($data['fuel_amount']) ||
            $data['fuel_amount'] < MIN_FUEL_AMOUNT ||
            $data['fuel_amount'] > MAX_FUEL_AMOUNT) {
            $errors[] = '加油量必须在 ' . MIN_FUEL_AMOUNT . '-' . MAX_FUEL_AMOUNT . ' 升之间';
        }

        // 验证当前里程
        if (!isset($data['current_mileage']) ||
            $data['current_mileage'] <= 0 ||
            $data['current_mileage'] > MAX_MILEAGE) {
            $errors[] = '里程数无效';
        }

        // 验证油价
        if (!isset($data['fuel_price']) ||
            $data['fuel_price'] < MIN_FUEL_PRICE ||
            $data['fuel_price'] > MAX_FUEL_PRICE) {
            $errors[] = '油价必须在 ' . MIN_FUEL_PRICE . '-' . MAX_FUEL_PRICE . ' 元/升之间';
        }

        // 验证总金额
        if (!isset($data['total_cost']) || $data['total_cost'] <= 0) {
            $errors[] = '总金额无效';
        }

        // 验证备注长度
        if (!empty($data['notes']) && mb_strlen($data['notes']) > MAX_NOTES_LENGTH) {
            $errors[] = '备注长度超过限制';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 添加加油记录
     */
    public function addRecord($data, $vehicleId = null) {
        // 验证数据
        $validation = $this->validateRecord($data);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        // 车辆ID不能为空
        if (!$vehicleId) {
            throw new Exception('车辆ID不能为空');
        }

        // 检查里程是否小于上一次记录（同一车辆）
        $lastRecord = $this->getLastRecord($vehicleId);
        if ($lastRecord && $data['current_mileage'] <= $lastRecord['current_mileage']) {
            throw new Exception('当前里程必须大于上一次记录(' . $lastRecord['current_mileage'] . '公里)');
        }

        // 计算油耗（使用上次加油量 / 里程差）
        $consumption = null;
        if ($lastRecord) {
            $mileageDiff = $data['current_mileage'] - $lastRecord['current_mileage'];
            $consumption = ($lastRecord['fuel_amount'] / $mileageDiff) * 100;
        }

        $sql = "INSERT INTO fuel_records
                (refuel_date, fuel_amount, current_mileage, fuel_price,
                 total_cost, notes, calculated_consumption, vehicle_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['refuel_date'],
            $data['fuel_amount'],
            $data['current_mileage'],
            $data['fuel_price'],
            $data['total_cost'],
            $data['notes'] ?? null,
            $consumption,
            $vehicleId
        ]);
    }

    /**
     * 更新记录
     */
    public function updateRecord($id, $data) {
        $validation = $this->validateRecord($data);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        // 获取记录的车辆ID和原始里程
        $record = $this->getRecordById($id);
        if (!$record) {
            throw new Exception('记录不存在');
        }

        $sql = "UPDATE fuel_records
                SET refuel_date = ?, fuel_amount = ?, current_mileage = ?,
                    fuel_price = ?, total_cost = ?, notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['refuel_date'],
            $data['fuel_amount'],
            $data['current_mileage'],
            $data['fuel_price'],
            $data['total_cost'],
            $data['notes'] ?? null,
            $id
        ]);

        // 更新后重新计算该车辆的油耗（增量重算：从受影响的里程开始）
        if ($result) {
            // 从原始里程和新里程中取较小值，确保覆盖所有受影响的记录
            $fromMileage = min($record['current_mileage'], $data['current_mileage']);
            $this->recalculateConsumption($record['vehicle_id'], $fromMileage);
        }

        return $result;
    }

    /**
     * 删除记录
     */
    public function deleteRecord($id) {
        // 获取记录的车辆ID和里程
        $record = $this->getRecordById($id);
        if (!$record) {
            throw new Exception('记录不存在');
        }

        $deletedMileage = $record['current_mileage'];

        $sql = "DELETE FROM fuel_records WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id]);

        // 删除后重新计算该车辆的油耗（增量重算：从被删除记录的里程开始）
        if ($result) {
            $this->recalculateConsumption($record['vehicle_id'], $deletedMileage);
        }

        return $result;
    }

    /**
     * 获取所有记录(按日期倒序)
     */
    public function getAllRecords($vehicleId = null) {
        if ($vehicleId) {
            $sql = "SELECT * FROM fuel_records WHERE vehicle_id = ? ORDER BY refuel_date DESC, id DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$vehicleId]);
            return $stmt->fetchAll();
        } else {
            $sql = "SELECT * FROM fuel_records ORDER BY refuel_date DESC, id DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        }
    }

    /**
     * 分页获取记录
     * @param int $page 当前页码（从1开始）
     * @param int $perPage 每页显示数量
     * @param int $vehicleId 车辆ID（可选）
     * @return array 记录列表
     */
    public function getRecordsPaginated($page = 1, $perPage = RECORDS_PER_PAGE, $vehicleId = null) {
        $page = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset = ($page - 1) * $perPage;

        if ($vehicleId) {
            $sql = "SELECT * FROM fuel_records
                    WHERE vehicle_id = ?
                    ORDER BY refuel_date DESC, id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$vehicleId, $perPage, $offset]);
        } else {
            $sql = "SELECT * FROM fuel_records
                    ORDER BY refuel_date DESC, id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$perPage, $offset]);
        }
        return $stmt->fetchAll();
    }

    /**
     * 获取总页数
     * @param int $perPage 每页显示数量
     * @param int $vehicleId 车辆ID（可选）
     * @return int 总页数
     */
    public function getTotalPages($perPage = RECORDS_PER_PAGE, $vehicleId = null) {
        $totalRecords = $this->getRecordCount($vehicleId);
        return max(1, ceil($totalRecords / $perPage));
    }

    /**
     * 获取指定ID的记录
     */
    public function getRecordById($id) {
        $sql = "SELECT * FROM fuel_records WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * 获取最后一条记录
     */
    public function getLastRecord($vehicleId = null) {
        if ($vehicleId) {
            $sql = "SELECT * FROM fuel_records
                    WHERE vehicle_id = ?
                    ORDER BY current_mileage DESC, refuel_date DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$vehicleId]);
            return $stmt->fetch();
        } else {
            $sql = "SELECT * FROM fuel_records
                    ORDER BY current_mileage DESC, refuel_date DESC LIMIT 1";
            $stmt = $this->db->query($sql);
            return $stmt->fetch();
        }
    }

    /**
     * 重新计算记录的油耗（增量重算优化版）
     * @param int|null $vehicleId 车辆ID（null表示所有车辆）
     * @param int|null $fromMileage 起始里程（null表示从头开始，指定值则只重算该里程及之后的记录）
     */
    private function recalculateConsumption($vehicleId = null, $fromMileage = null) {
        // 获取需要重算的记录
        if ($vehicleId) {
            if ($fromMileage !== null) {
                // 增量重算：只获取指定里程及之后的记录
                $sql = "SELECT * FROM fuel_records
                        WHERE vehicle_id = ? AND current_mileage >= ?
                        ORDER BY current_mileage ASC, refuel_date ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$vehicleId, $fromMileage]);
                $records = $stmt->fetchAll();

                // 获取起始记录的前一条记录（作为计算基准）
                $sql = "SELECT * FROM fuel_records
                        WHERE vehicle_id = ? AND current_mileage < ?
                        ORDER BY current_mileage DESC, refuel_date DESC
                        LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$vehicleId, $fromMileage]);
                $prevRecord = $stmt->fetch();
            } else {
                // 全量重算：获取该车辆所有记录
                $sql = "SELECT * FROM fuel_records
                        WHERE vehicle_id = ?
                        ORDER BY current_mileage ASC, refuel_date ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$vehicleId]);
                $records = $stmt->fetchAll();
                $prevRecord = null;
            }
        } else {
            // 重算所有记录（按车辆分组）
            $records = $this->db->query(
                "SELECT * FROM fuel_records ORDER BY vehicle_id ASC, current_mileage ASC, refuel_date ASC"
            )->fetchAll();
            $prevRecord = null;
        }

        if (empty($records)) {
            return;
        }

        // 第一步：在 PHP 中计算所有油耗值
        $updates = [];
        foreach ($records as $record) {
            $consumption = null;

            // 只有同一车辆的前一条记录才能用于计算油耗（使用上次加油量）
            if ($prevRecord && $prevRecord['vehicle_id'] == $record['vehicle_id']) {
                $mileageDiff = $record['current_mileage'] - $prevRecord['current_mileage'];
                if ($mileageDiff > 0) {
                    $consumption = ($prevRecord['fuel_amount'] / $mileageDiff) * 100;
                }
            }

            $updates[] = [
                'id' => $record['id'],
                'consumption' => $consumption
            ];
            $prevRecord = $record;
        }

        // 第二步：使用单一 SQL 的 CASE WHEN 批量更新
        $this->db->beginTransaction();
        try {
            $whenClauses = [];
            $ids = [];

            foreach ($updates as $update) {
                $id = intval($update['id']);
                $consumption = $update['consumption'] === null ? 'NULL' : floatval($update['consumption']);
                $whenClauses[] = "WHEN {$id} THEN {$consumption}";
                $ids[] = $id;
            }

            $whenSql = implode(' ', $whenClauses);
            $idsList = implode(',', $ids);

            $sql = "UPDATE fuel_records
                    SET calculated_consumption = CASE id {$whenSql} END
                    WHERE id IN ({$idsList})";

            $this->db->exec($sql);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 获取记录总数
     */
    public function getRecordCount($vehicleId = null) {
        if ($vehicleId) {
            $sql = "SELECT COUNT(*) as count FROM fuel_records WHERE vehicle_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$vehicleId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM fuel_records";
            $stmt = $this->db->query($sql);
        }
        $result = $stmt->fetch();
        return $result['count'];
    }
}
