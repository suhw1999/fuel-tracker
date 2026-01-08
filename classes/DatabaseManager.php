<?php
require_once dirname(__DIR__) . '/config.php';

class DatabaseManager {
    private static ?DatabaseManager $instance = null;
    private PDO $db;

    private function __construct() {
        $this->initDatabase();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initDatabase(): void {
        try {
            $dbPath = dirname(__DIR__) . '/' . DATABASE_FILE;
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // SQLite 性能优化
            $this->db->exec("PRAGMA journal_mode = WAL");
            $this->db->exec("PRAGMA synchronous = NORMAL");
            $this->db->exec("PRAGMA cache_size = 10000");
            $this->db->exec("PRAGMA temp_store = MEMORY");

            $this->createTables();

            if (file_exists($dbPath)) {
                chmod($dbPath, FILE_PERMISSIONS);
            }
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('数据库连接失败');
        }
    }

    private function createTables(): void {
        // 删除旧的冗余索引（如果存在）
        $this->db->exec("DROP INDEX IF EXISTS idx_current_mileage");

        // 创建 vehicles 表
        $sqlVehicles = "
            CREATE TABLE IF NOT EXISTS vehicles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL,
                plate_number VARCHAR(20),
                is_active TINYINT DEFAULT 1,
                is_default TINYINT DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_vehicles_default
                ON vehicles(is_default) WHERE is_default = 1;
            CREATE INDEX IF NOT EXISTS idx_vehicles_is_active
                ON vehicles(is_active);
        ";
        $this->db->exec($sqlVehicles);

        // 创建 fuel_records 表
        $sqlRecords = "
            CREATE TABLE IF NOT EXISTS fuel_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER,
                refuel_date DATE NOT NULL,
                fuel_amount REAL NOT NULL,
                current_mileage INTEGER NOT NULL,
                fuel_price REAL NOT NULL,
                total_cost REAL NOT NULL,
                notes TEXT,
                calculated_consumption REAL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_refuel_date
                ON fuel_records(refuel_date DESC);
            CREATE INDEX IF NOT EXISTS idx_fuel_records_vehicle
                ON fuel_records(vehicle_id);
            CREATE INDEX IF NOT EXISTS idx_fuel_records_vehicle_date
                ON fuel_records(vehicle_id, refuel_date DESC);
            CREATE INDEX IF NOT EXISTS idx_fuel_records_vehicle_mileage
                ON fuel_records(vehicle_id, current_mileage ASC);
        ";
        $this->db->exec($sqlRecords);
    }

    public function getConnection(): PDO {
        return $this->db;
    }
}
