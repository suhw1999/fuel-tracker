<?php
require_once 'config.php';
require_once 'classes/FuelRecordManager.php';
require_once 'classes/StatisticsManager.php';
require_once 'classes/VehicleManager.php';

// 简单密码验证
$isAuthenticated = false;
if (isset($_SESSION['fuel_authenticated'])) {
    $isAuthenticated = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (verifyPassword($_POST['password'])) {
        $_SESSION['fuel_authenticated'] = true;
        $isAuthenticated = true;
    }
}

if (!$isAuthenticated) {
    // 显示登录表单
    include 'templates/login.php';
    exit;
}

// 初始化车辆管理
$vehicleManager = new VehicleManager();

// 获取当前车辆ID（优先级：SESSION > Cookie > 默认车辆）
$currentVehicleId = $_SESSION['current_vehicle_id'] ?? null;

if (!$currentVehicleId && isset($_COOKIE['current_vehicle_id'])) {
    $currentVehicleId = intval($_COOKIE['current_vehicle_id']);
}

if (!$currentVehicleId) {
    $defaultVehicle = $vehicleManager->getDefaultVehicle();
    if ($defaultVehicle) {
        $currentVehicleId = $defaultVehicle['id'];
        $_SESSION['current_vehicle_id'] = $currentVehicleId;
    }
}

// 验证当前车辆是否存在
$currentVehicle = null;
if ($currentVehicleId) {
    $currentVehicle = $vehicleManager->getVehicleById($currentVehicleId);
    if (!$currentVehicle || $currentVehicle['is_active'] != 1) {
        // 车辆不存在或已停用，切换到默认车辆
        $defaultVehicle = $vehicleManager->getDefaultVehicle();
        if ($defaultVehicle) {
            $currentVehicleId = $defaultVehicle['id'];
            $currentVehicle = $defaultVehicle;
            $_SESSION['current_vehicle_id'] = $currentVehicleId;
        }
    }
}

// 获取所有激活的车辆（用于下拉列表）
$vehicles = $vehicleManager->getActiveVehicles();

// 获取统计数据（带车辆过滤）
$statsManager = new StatisticsManager();
$stats = $statsManager->getAllStatistics($currentVehicleId);
$chartData = $statsManager->getChartData(3, $currentVehicleId);

// 分页参数处理
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : RECORDS_PER_PAGE;

// 验证每页显示数量是否在允许范围内
if (!in_array($perPage, RECORDS_PAGE_SIZES)) {
    $perPage = RECORDS_PER_PAGE;
}

// 获取分页记录（带车辆过滤）
$recordManager = new FuelRecordManager();
$records = $recordManager->getRecordsPaginated($currentPage, $perPage, $currentVehicleId);
$totalPages = $recordManager->getTotalPages($perPage, $currentVehicleId);
$totalRecords = $recordManager->getRecordCount($currentVehicleId);

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>油耗统计</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
    <script defer src="https://cdn.bootcdn.net/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">

        <!-- 车辆切换器 -->
        <div class="vehicle-selector-container">
            <div class="vehicle-selector">
                <div class="vehicle-selector-title">
                    <h1 class="vehicle-title">油耗统计</h1>
                </div>
                <div class="vehicle-selector-controls">
                    <label for="vehicleSelect">当前车辆:</label>
                    <select id="vehicleSelect" class="vehicle-select" onchange="switchVehicle(this.value)">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>"
                                    <?php echo ($vehicle['id'] == $currentVehicleId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vehicle['name']); ?>
                                <?php if ($vehicle['plate_number']): ?>
                                    (<?php echo htmlspecialchars($vehicle['plate_number']); ?>)
                                <?php endif; ?>
                                <?php if ($vehicle['is_default']): ?>
                                    [默认]
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="openVehicleManager()">管理车辆</button>
                </div>
            </div>
        </div>

        <!-- 统计数据面板 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-title">平均油耗</div>
                <div class="stat-card-value" id="stat-average-consumption"><?php echo $stats['average_consumption']; ?></div>
                <div class="stat-card-unit">升/百公里</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">总里程</div>
                <div class="stat-card-value" id="stat-total-mileage"><?php echo $stats['total_mileage']; ?></div>
                <div class="stat-card-unit">公里</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">总加油量</div>
                <div class="stat-card-value" id="stat-total-fuel"><?php echo $stats['total_fuel']; ?></div>
                <div class="stat-card-unit">升</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">总花费</div>
                <div class="stat-card-value" id="stat-total-cost"><?php echo $stats['total_cost']; ?></div>
                <div class="stat-card-unit">元</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">平均油价</div>
                <div class="stat-card-value" id="stat-average-price"><?php echo $stats['average_price']; ?></div>
                <div class="stat-card-unit">元/升</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">加油次数</div>
                <div class="stat-card-value" id="stat-record-count"><?php echo $stats['record_count']; ?></div>
                <div class="stat-card-unit">次</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">平均油费每天</div>
                <div class="stat-card-value" id="stat-average-cost-per-day"><?php echo $stats['average_cost_per_day']; ?></div>
                <div class="stat-card-unit">元/天</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">平均里程每天</div>
                <div class="stat-card-value" id="stat-average-mileage-per-day"><?php echo $stats['average_mileage_per_day']; ?></div>
                <div class="stat-card-unit">公里/天</div>
            </div>
        </div>

        <!-- 油耗趋势图表 -->
        <div class="chart-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 class="section-title" style="margin-bottom: 0;">油耗与油价趋势</h2>
                <div class="chart-time-range" style="display: flex; gap: 0.5rem;">
                    <button class="btn time-range-btn" data-months="1">近1月</button>
                    <button class="btn time-range-btn btn-primary" data-months="3">近3月</button>
                    <button class="btn time-range-btn" data-months="6">近半年</button>
                    <button class="btn time-range-btn" data-months="0">全部</button>
                </div>
            </div>
            <canvas id="consumptionChart"></canvas>
        </div>

        <!-- 添加记录表单 -->
        <div class="file-list" style="margin-bottom: 2rem;">
            <div class="file-list-header">添加加油记录</div>
            <form id="addRecordForm" style="padding: 1.5rem;">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="refuel_date">加油日期 *</label>
                        <input type="date" id="refuel_date" name="refuel_date" required>
                    </div>

                    <div class="form-group">
                        <label for="fuel_amount">加油量(升) *</label>
                        <input type="number" id="fuel_amount" name="fuel_amount"
                               step="0.01" min="<?php echo MIN_FUEL_AMOUNT; ?>"
                               max="<?php echo MAX_FUEL_AMOUNT; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="current_mileage">当前里程(公里) *</label>
                        <input type="number" id="current_mileage" name="current_mileage"
                               min="1" max="<?php echo MAX_MILEAGE; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="fuel_price">油价(元/升) *</label>
                        <input type="number" id="fuel_price" name="fuel_price"
                               step="0.01" min="<?php echo MIN_FUEL_PRICE; ?>"
                               max="<?php echo MAX_FUEL_PRICE; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="total_cost">总金额(元) *</label>
                        <input type="number" id="total_cost" name="total_cost"
                               step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="notes">备注</label>
                    <textarea id="notes" name="notes" rows="2"
                              maxlength="<?php echo MAX_NOTES_LENGTH; ?>"></textarea>
                </div>

                <div class="toolbar" style="justify-content: space-between;">
                    <button type="submit" class="btn btn-success">添加记录</button>
                    <button type="button" onclick="exportCSV()" class="btn btn-primary">导出CSV</button>
                </div>
            </form>
        </div>

        <!-- 历史记录列表 -->
        <div class="file-list">
            <div class="file-list-header">历史记录</div>
            <div style="overflow-x: auto;">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>加油量(L)</th>
                            <th>里程(km)</th>
                            <th>油价(元/L)</th>
                            <th>金额(元)</th>
                            <th>油耗(L/100km)</th>
                            <th>备注</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                暂无记录，请添加第一条加油记录
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $record): ?>
                        <tr data-id="<?php echo $record['id']; ?>">
                            <td><?php echo htmlspecialchars($record['refuel_date']); ?></td>
                            <td><?php echo number_format($record['fuel_amount'], 2); ?></td>
                            <td><?php echo number_format($record['current_mileage'], 0); ?></td>
                            <td><?php echo number_format($record['fuel_price'], 2); ?></td>
                            <td><?php echo number_format($record['total_cost'], 2); ?></td>
                            <td><?php echo $record['calculated_consumption']
                                ? number_format($record['calculated_consumption'], 2)
                                : '-'; ?></td>
                            <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                            <td>
                                <button class="action-btn" onclick="editRecord(<?php echo $record['id']; ?>)" title="编辑">
                                    ✏️
                                </button>
                                <button class="action-btn delete" onclick="deleteRecord(<?php echo $record['id']; ?>)" title="删除">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分页器 -->
            <div class="pagination-container">
                <?php if ($totalPages > 1): ?>
                <div style="padding: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <!-- 分页信息 -->
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">
                        显示第 <?php echo (($currentPage - 1) * $perPage + 1); ?>-<?php echo min($currentPage * $perPage, $totalRecords); ?> 条，
                        共 <?php echo $totalRecords; ?> 条记录
                    </div>

                    <!-- 分页按钮 -->
                    <div class="pagination">
                        <?php
                        // 构建URL参数
                        $urlParams = http_build_query(['per_page' => $perPage]);

                        // 首页和上一页
                        if ($currentPage > 1):
                        ?>
                            <a href="?page=1&<?php echo $urlParams; ?>" class="pagination-btn" data-page="1">首页</a>
                            <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo $urlParams; ?>" class="pagination-btn" data-page="<?php echo $currentPage - 1; ?>">上一页</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">首页</span>
                            <span class="pagination-btn disabled">上一页</span>
                        <?php endif; ?>

                        <?php
                        // 页码按钮（显示当前页前后各2页）
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);

                        // 如果开始页不是1，显示省略号
                        if ($startPage > 1):
                            echo '<span class="pagination-ellipsis">...</span>';
                        endif;

                        for ($i = $startPage; $i <= $endPage; $i++):
                            if ($i == $currentPage):
                        ?>
                            <span class="pagination-btn active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo $urlParams; ?>" class="pagination-btn" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php
                            endif;
                        endfor;

                        // 如果结束页不是最后一页，显示省略号
                        if ($endPage < $totalPages):
                            echo '<span class="pagination-ellipsis">...</span>';
                        endif;
                        ?>

                        <?php
                        // 下一页和尾页
                        if ($currentPage < $totalPages):
                        ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo $urlParams; ?>" class="pagination-btn" data-page="<?php echo $currentPage + 1; ?>">下一页</a>
                            <a href="?page=<?php echo $totalPages; ?>&<?php echo $urlParams; ?>" class="pagination-btn" data-page="<?php echo $totalPages; ?>">尾页</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">下一页</span>
                            <span class="pagination-btn disabled">尾页</span>
                        <?php endif; ?>
                    </div>

                    <!-- 每页显示数量选择器 -->
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="color: var(--text-secondary); font-size: 0.875rem;">每页显示：</label>
                        <select id="perPageSelect" class="per-page-select" onchange="changePerPage(this.value)">
                            <?php foreach (RECORDS_PAGE_SIZES as $size): ?>
                                <option value="<?php echo $size; ?>" <?php echo $size == $perPage ? 'selected' : ''; ?>>
                                    <?php echo $size; ?> 条
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 车辆管理模态框 -->
    <div id="vehicleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>车辆管理</h2>
                <button class="modal-close" onclick="closeVehicleModal()">&times;</button>
            </div>

            <div class="modal-body">
                <!-- 车辆列表 -->
                <div class="file-list" style="margin-bottom: 2rem;">
                    <div class="file-list-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>我的车辆</span>
                        <label style="font-size: 0.875rem; font-weight: normal; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="showInactiveVehicles" onchange="loadVehicleList()">
                            显示停用车辆
                        </label>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="vehicles-table">
                            <thead>
                                <tr>
                                    <th>车辆名称</th>
                                    <th>车牌号</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="vehicleTableBody">
                                <!-- 动态加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 添加/编辑车辆表单 -->
                <div class="file-list">
                    <div class="file-list-header" id="vehicleFormTitle">添加车辆</div>
                    <form id="vehicleForm" onsubmit="saveVehicle(event)" style="padding: 1.5rem;">
                        <input type="hidden" id="editVehicleId" value="">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="vehicleName">车辆名称 *</label>
                                <input type="text" id="vehicleName" name="name" required
                                       placeholder="例如：标致508" maxlength="50">
                            </div>

                            <div class="form-group">
                                <label for="vehiclePlateNumber">车牌号</label>
                                <input type="text" id="vehiclePlateNumber" name="plate_number"
                                       placeholder="例如：豫A12345" maxlength="20">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="vehicleNotes">备注</label>
                            <textarea id="vehicleNotes" name="notes" rows="2"
                                      placeholder="选填"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">保存</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEditVehicle()">取消</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 传递 PHP 变量到 JavaScript
        const csrfToken = '<?php echo $csrfToken; ?>';
        const chartData = <?php echo json_encode($chartData); ?>;
        const currentVehicleId = <?php echo $currentVehicleId ?? 'null'; ?>;
    </script>
    <script src="js/fuel-tracker.js?v=<?php echo filemtime(__DIR__ . '/js/fuel-tracker.js'); ?>"></script>
</body>
</html>

