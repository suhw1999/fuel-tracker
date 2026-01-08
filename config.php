<?php
// 加载 .env 文件
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!getenv($name)) {
            putenv("$name=$value");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// 应用配置（从环境变量读取敏感配置）
define('APP_PASSWORD_HASH', getenv('APP_PASSWORD_HASH') ?: '');
define('DATABASE_FILE', getenv('DATABASE_FILE') ?: 'fuel.db');
define('BASE_URL', getenv('BASE_URL') ?: '/fuel-tracker');
define('FILE_PERMISSIONS', 0644);

// 数据验证配置
define('MAX_FUEL_AMOUNT', 50);      // 最大加油量(升)
define('MIN_FUEL_AMOUNT', 0.1);     // 最小加油量(升)
define('MAX_MILEAGE', 999999);      // 最大里程(公里)
define('MAX_FUEL_PRICE', 20);       // 最大油价(元/升)
define('MIN_FUEL_PRICE', 1);        // 最小油价(元/升)
define('MAX_NOTES_LENGTH', 200);    // 最大备注长度

// 车辆管理配置
define('MAX_VEHICLE_NAME_LENGTH', 50);      // 最大车辆名称长度
define('MAX_PLATE_NUMBER_LENGTH', 20);      // 最大车牌号长度
define('CURRENT_SCHEMA_VERSION', 1);        // 当前数据库版本

// 分页配置
define('RECORDS_PER_PAGE', 10);     // 默认每页显示记录数
define('RECORDS_PAGE_SIZES', [10, 20, 50, 100]);  // 可选的每页显示数量

// 安全配置
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token函数
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// 密码验证函数（使用 password_verify 验证哈希）
function verifyPassword($password) {
    if (empty(APP_PASSWORD_HASH)) {
        return false;
    }
    return password_verify($password, APP_PASSWORD_HASH);
}