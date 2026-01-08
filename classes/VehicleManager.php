<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/DatabaseManager.php';

class VehicleManager {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance()->getConnection();
    }

    /**
     * 验证车辆数据
     */
    public function validateVehicle($data) {
        $errors = [];

        // 验证车辆名称
        if (empty($data['name']) || trim($data['name']) === '') {
            $errors[] = '车辆名称不能为空';
        } elseif (mb_strlen($data['name']) > MAX_VEHICLE_NAME_LENGTH) {
            $errors[] = '车辆名称长度不能超过' . MAX_VEHICLE_NAME_LENGTH . '个字符';
        }

        // 验证车牌号长度（可选字段）
        if (!empty($data['plate_number']) && mb_strlen($data['plate_number']) > MAX_PLATE_NUMBER_LENGTH) {
            $errors[] = '车牌号长度不能超过' . MAX_PLATE_NUMBER_LENGTH . '个字符';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 添加车辆
     */
    public function addVehicle($data) {
        // 验证数据
        $validation = $this->validateVehicle($data);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        $sql = "INSERT INTO vehicles (name, plate_number, is_active, notes)
                VALUES (?, ?, 1, ?)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            trim($data['name']),
            !empty($data['plate_number']) ? trim($data['plate_number']) : null,
            $data['notes'] ?? null
        ]);
    }

    /**
     * 更新车辆
     */
    public function updateVehicle($id, $data) {
        // 验证数据
        $validation = $this->validateVehicle($data);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        $sql = "UPDATE vehicles
                SET name = ?, plate_number = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            trim($data['name']),
            !empty($data['plate_number']) ? trim($data['plate_number']) : null,
            $data['notes'] ?? null,
            $id
        ]);
    }

    /**
     * 删除/停用车辆
     * 如果车辆有加油记录，只能停用；如果没有记录，可以删除
     */
    public function deleteVehicle($id) {
        // 检查是否是默认车辆
        $vehicle = $this->getVehicleById($id);
        if ($vehicle && $vehicle['is_default'] == 1) {
            throw new Exception('不能删除默认车辆');
        }

        // 检查是否有加油记录
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM fuel_records WHERE vehicle_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            // 有记录，只能停用
            $sql = "UPDATE vehicles SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } else {
            // 无记录，可以删除
            $sql = "DELETE FROM vehicles WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        }
    }

    /**
     * 获取指定车辆
     */
    public function getVehicleById($id) {
        $sql = "SELECT * FROM vehicles WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * 获取所有车辆
     */
    public function getAllVehicles($includeInactive = false) {
        if ($includeInactive) {
            $sql = "SELECT * FROM vehicles ORDER BY is_default DESC, created_at ASC";
            $stmt = $this->db->query($sql);
        } else {
            $sql = "SELECT * FROM vehicles WHERE is_active = 1 ORDER BY is_default DESC, created_at ASC";
            $stmt = $this->db->query($sql);
        }
        return $stmt->fetchAll();
    }

    /**
     * 获取激活的车辆
     */
    public function getActiveVehicles() {
        return $this->getAllVehicles(false);
    }

    /**
     * 获取默认车辆
     */
    public function getDefaultVehicle() {
        $sql = "SELECT * FROM vehicles WHERE is_default = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        $vehicle = $stmt->fetch();

        // 如果没有默认车辆，返回第一个激活的车辆
        if (!$vehicle) {
            $sql = "SELECT * FROM vehicles WHERE is_active = 1 ORDER BY id ASC LIMIT 1";
            $stmt = $this->db->query($sql);
            $vehicle = $stmt->fetch();
        }

        return $vehicle;
    }

    /**
     * 设置默认车辆
     */
    public function setDefaultVehicle($id) {
        // 检查车辆是否存在且激活
        $vehicle = $this->getVehicleById($id);
        if (!$vehicle) {
            throw new Exception('车辆不存在');
        }
        if ($vehicle['is_active'] != 1) {
            throw new Exception('不能将已停用的车辆设置为默认车辆');
        }

        try {
            $this->db->beginTransaction();

            // 1. 取消所有车辆的默认状态（只更新 is_default = 1 的行）
            $this->db->exec("UPDATE vehicles SET is_default = 0 WHERE is_default = 1");

            // 2. 设置新的默认车辆
            $stmt = $this->db->prepare("UPDATE vehicles SET is_default = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception('设置默认车辆失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取车辆概要统计
     */
    public function getVehicleStatsSummary($vehicleId) {
        $sql = "SELECT
                    COUNT(*) as record_count,
                    SUM(fuel_amount) as total_fuel,
                    SUM(total_cost) as total_cost,
                    AVG(calculated_consumption) as avg_consumption
                FROM fuel_records
                WHERE vehicle_id = ? AND calculated_consumption IS NOT NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$vehicleId]);
        return $stmt->fetch();
    }

    /**
     * 激活车辆
     */
    public function activateVehicle($id) {
        $sql = "UPDATE vehicles SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 停用车辆
     */
    public function deactivateVehicle($id) {
        $vehicle = $this->getVehicleById($id);
        if (!$vehicle) {
            throw new Exception('车辆不存在');
        }
        if ($vehicle['is_default'] == 1) {
            throw new Exception('不能停用默认车辆');
        }
        if ($vehicle['is_active'] == 0) {
            throw new Exception('该车辆已处于停用状态');
        }

        $sql = "UPDATE vehicles SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 切换车辆状态
     */
    public function toggleVehicleStatus($id) {
        $vehicle = $this->getVehicleById($id);
        if (!$vehicle) {
            throw new Exception('车辆不存在');
        }

        if ($vehicle['is_active'] == 1) {
            return $this->deactivateVehicle($id);
        } else {
            return $this->activateVehicle($id);
        }
    }

    /**
     * 获取当前车辆ID（自动处理session和默认车辆）
     * @return int|null
     * @throws Exception
     */
    public static function getCurrentVehicleId(): ?int {
        $vehicleId = $_SESSION['current_vehicle_id'] ?? null;

        if (!$vehicleId) {
            $vehicleManager = new self();
            $defaultVehicle = $vehicleManager->getDefaultVehicle();

            if ($defaultVehicle) {
                $vehicleId = $defaultVehicle['id'];
                $_SESSION['current_vehicle_id'] = $vehicleId;
            } else {
                throw new Exception('未找到可用的车辆');
            }
        }

        return $vehicleId;
    }

    /**
     * 获取当前车辆完整信息
     * @return array|null
     */
    public static function getCurrentVehicle(): ?array {
        try {
            $vehicleId = self::getCurrentVehicleId();
            $vehicleManager = new self();
            return $vehicleManager->getVehicleById($vehicleId);
        } catch (Exception $e) {
            return null;
        }
    }
}
