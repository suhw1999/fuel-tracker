// 全局变量
let consumptionChart = null;
let editingRecordId = null; // 用于标记是否在编辑模式
let resizeTimeout = null; // Chart.js resize 防抖定时器

// DOM缓存 - 统计卡片元素引用
let statElements = null;

// ==================== AJAX 刷新函数 ====================

/**
 * 刷新页面数据（无需整页刷新）
 * @param {Object} options - 刷新选项
 */
async function refreshPageData(options = {}) {
    const {refreshStats = true, refreshChart = true, refreshRecords = true, refreshVehicleSelector = false} = options;

    if (refreshStats) {
        try {
            const statsData = await fetch('api/get_statistics.php').then(r => r.json());
            if (statsData.success) updateStatisticsPanel(statsData.stats);
        } catch (e) {
            console.error('刷新统计数据失败:', e);
        }
    }

    if (refreshChart) {
        try {
            const months = document.querySelector('.time-range-btn.btn-primary')?.dataset.months || 3;
            const chartData = await fetch(`api/get_chart_data.php?months=${months}`).then(r => r.json());
            if (chartData.success) {
                if (consumptionChart) consumptionChart.destroy();
                initChart(chartData.chartData);
            }
        } catch (e) {
            console.error('刷新图表失败:', e);
        }
    }

    if (refreshRecords) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const perPage = urlParams.get('per_page') || 20;
            const recordsData = await fetch(`api/get_records.php?page=${page}&per_page=${perPage}`).then(r => r.json());
            if (recordsData.success) {
                updateRecordsTable(recordsData.records, recordsData.totalPages, recordsData.currentPage, recordsData.totalRecords, recordsData.perPage);
            }
        } catch (e) {
            console.error('刷新记录失败:', e);
        }
    }

    if (refreshVehicleSelector) {
        try {
            const vehiclesData = await fetch('api/vehicles/list.php').then(r => r.json());
            if (vehiclesData.success) {
                updateVehicleSelector(vehiclesData.vehicles, vehiclesData.currentVehicleId);
            }
        } catch (e) {
            console.error('刷新车辆选择器失败:', e);
        }
    }
}

/**
 * 更新统计面板
 */
function updateStatisticsPanel(stats) {
    // 初始化或获取缓存的DOM引用
    if (!statElements) {
        statElements = {
            average_consumption: document.getElementById('stat-average-consumption'),
            total_mileage: document.getElementById('stat-total-mileage'),
            total_fuel: document.getElementById('stat-total-fuel'),
            total_cost: document.getElementById('stat-total-cost'),
            average_price: document.getElementById('stat-average-price'),
            record_count: document.getElementById('stat-record-count'),
            average_cost_per_day: document.getElementById('stat-average-cost-per-day'),
            average_mileage_per_day: document.getElementById('stat-average-mileage-per-day')
        };
    }

    // 直接更新缓存的DOM元素，避免重复查询
    statElements.average_consumption.textContent = stats.average_consumption;
    statElements.total_mileage.textContent = stats.total_mileage;
    statElements.total_fuel.textContent = stats.total_fuel;
    statElements.total_cost.textContent = stats.total_cost;
    statElements.average_price.textContent = stats.average_price;
    statElements.record_count.textContent = stats.record_count;
    statElements.average_cost_per_day.textContent = stats.average_cost_per_day;
    statElements.average_mileage_per_day.textContent = stats.average_mileage_per_day;
}

/**
 * 更新记录表格
 */
function updateRecordsTable(records, totalPages, currentPage, totalRecords, perPage) {
    const tbody = document.querySelector('.records-table tbody');
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">暂无记录，请添加第一条加油记录</td></tr>';
        return;
    }
    tbody.innerHTML = records.map(record => `
        <tr data-id="${record.id}">
            <td>${escapeHtml(record.refuel_date)}</td>
            <td>${parseFloat(record.fuel_amount).toFixed(2)}</td>
            <td>${parseInt(record.current_mileage).toLocaleString()}</td>
            <td>${parseFloat(record.fuel_price).toFixed(2)}</td>
            <td>${parseFloat(record.total_cost).toFixed(2)}</td>
            <td>${record.calculated_consumption ? parseFloat(record.calculated_consumption).toFixed(2) : '-'}</td>
            <td>${escapeHtml(record.notes || '')}</td>
            <td>
                <button class="action-btn" onclick="editRecord(${record.id})" title="编辑">✏️</button>
                <button class="action-btn delete" onclick="deleteRecord(${record.id})" title="删除">🗑️</button>
            </td>
        </tr>
    `).join('');

    // 更新分页 HTML
    updatePaginationHTML(totalPages, currentPage, totalRecords, perPage);
}

/**
 * 更新分页 HTML
 * @param {number} totalPages - 总页数
 * @param {number} currentPage - 当前页码
 * @param {number} totalRecords - 总记录数
 * @param {number} perPage - 每页显示数量
 */
function updatePaginationHTML(totalPages, currentPage, totalRecords, perPage) {
    const paginationContainer = document.querySelector('.pagination-container');
    if (!paginationContainer) return;

    if (totalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }

    // 1. 生成分页信息
    const startRecord = (currentPage - 1) * perPage + 1;
    const endRecord = Math.min(currentPage * perPage, totalRecords);
    const infoHTML = `显示第 ${startRecord}-${endRecord} 条，共 ${totalRecords} 条记录`;

    // 2. 生成分页按钮（省略号逻辑：前后各 2 页）
    let buttonsHTML = '';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    // 首页/上一页
    if (currentPage > 1) {
        buttonsHTML += `<a href="#" class="pagination-btn" data-page="1">首页</a>`;
        buttonsHTML += `<a href="#" class="pagination-btn" data-page="${currentPage - 1}">上一页</a>`;
    } else {
        buttonsHTML += `<span class="pagination-btn disabled">首页</span>`;
        buttonsHTML += `<span class="pagination-btn disabled">上一页</span>`;
    }

    // 省略号和页码
    if (startPage > 1) {
        buttonsHTML += `<span class="pagination-ellipsis">...</span>`;
    }
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            buttonsHTML += `<span class="pagination-btn active">${i}</span>`;
        } else {
            buttonsHTML += `<a href="#" class="pagination-btn" data-page="${i}">${i}</a>`;
        }
    }
    if (endPage < totalPages) {
        buttonsHTML += `<span class="pagination-ellipsis">...</span>`;
    }

    // 下一页/尾页
    if (currentPage < totalPages) {
        buttonsHTML += `<a href="#" class="pagination-btn" data-page="${currentPage + 1}">下一页</a>`;
        buttonsHTML += `<a href="#" class="pagination-btn" data-page="${totalPages}">尾页</a>`;
    } else {
        buttonsHTML += `<span class="pagination-btn disabled">下一页</span>`;
        buttonsHTML += `<span class="pagination-btn disabled">尾页</span>`;
    }

    // 3. 生成每页数量选择器
    const pageSizes = [10, 20, 50, 100];
    const selectHTML = `
        <select id="perPageSelect" class="per-page-select">
            ${pageSizes.map(size =>
                `<option value="${size}" ${size == perPage ? 'selected' : ''}>${size} 条</option>`
            ).join('')}
        </select>
    `;

    // 4. 组合完整 HTML
    paginationContainer.innerHTML = `
        <div style="padding: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="color: var(--text-secondary); font-size: 0.875rem;">${infoHTML}</div>
            <div class="pagination">${buttonsHTML}</div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="color: var(--text-secondary); font-size: 0.875rem;">每页显示：</label>
                ${selectHTML}
            </div>
        </div>
    `;

    // 重新绑定选择器事件（DOM 重建后需要）
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            changePage(1, parseInt(this.value));
        });
    }
}

/**
 * 更新车辆选择器
 */
function updateVehicleSelector(vehicles, currentVehicleId) {
    const select = document.getElementById('vehicleSelect');
    if (!select) return;
    select.innerHTML = vehicles.map(v => `
        <option value="${v.id}" ${v.id == currentVehicleId ? 'selected' : ''}>
            ${escapeHtml(v.name)}${v.plate_number ? ` (${escapeHtml(v.plate_number)})` : ''}${v.is_default == 1 ? ' [默认]' : ''}
        </option>
    `).join('');
}

/**
 * 刷新车辆选择器（从服务器获取最新数据）
 */
function refreshVehicleSelector() {
    fetch('api/vehicles/list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateVehicleSelector(data.vehicles, data.currentVehicleId);
            }
        });
}

// ==================== 工具函数 ====================

/**
 * 统一的 fetch 请求处理
 * @param {string} url - 请求URL
 * @param {FormData} formData - 表单数据
 * @param {string} successMessage - 成功提示消息
 * @param {Function} onSuccess - 成功回调函数
 */
function fetchAPI(url, formData, successMessage, onSuccess) {
    // 确保添加 CSRF token
    if (!formData.has('csrf_token')) {
        formData.append('csrf_token', csrfToken);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (successMessage) {
                showNotification(successMessage, 'success');
            }
            if (onSuccess) {
                onSuccess(data);
            }
        } else {
            showNotification(data.message || '操作失败', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('操作失败，请稍后重试', 'error');
    });
}

/**
 * 创建 FormData 对象
 * @param {Object} data - 数据对象
 * @returns {FormData}
 */
function createFormData(data) {
    const formData = new FormData();
    for (const key in data) {
        if (data.hasOwnProperty(key)) {
            formData.append(key, data[key]);
        }
    }
    return formData;
}

/**
 * 初始化油耗趋势图表
 */
function initChart(chartData) {
    const chartContainer = document.getElementById('consumptionChart')?.parentElement;
    if (!chartContainer) return;

    // 隐藏加载动画
    const loadingEl = document.getElementById('chartLoading');
    if (loadingEl) loadingEl.style.display = 'none';

    // 确保 canvas 元素存在
    let canvas = document.getElementById('consumptionChart');
    if (!canvas) {
        // 如果 canvas 被删除了，重新创建
        chartContainer.innerHTML = '<canvas id="consumptionChart"></canvas>';
        canvas = document.getElementById('consumptionChart');
    }

    if (!chartData || chartData.length === 0) {
        canvas.style.display = 'none';
        // 检查是否已有提示消息
        let noDataMsg = chartContainer.querySelector('.no-data-message');
        if (!noDataMsg) {
            noDataMsg = document.createElement('p');
            noDataMsg.className = 'no-data-message';
            noDataMsg.style.cssText = 'text-align: center; color: var(--text-secondary); padding: 2rem;';
            noDataMsg.textContent = '暂无数据，请添加至少两条记录以查看趋势图';
            chartContainer.appendChild(noDataMsg);
        }
        return;
    }

    // 有数据时，显示 canvas 并移除提示消息
    canvas.style.display = '';
    const noDataMsg = chartContainer.querySelector('.no-data-message');
    if (noDataMsg) noDataMsg.remove();

    const ctx = canvas.getContext('2d');

    // 准备数据
    const labels = chartData.map(item => item.refuel_date);
    const consumptions = chartData.map(item => parseFloat(item.calculated_consumption));
    const prices = chartData.map(item => parseFloat(item.fuel_price));

    consumptionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '油耗',
                data: consumptions,
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96, 165, 250, 0.1)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y'
            }, {
                label: '油价',
                data: prices,
                borderColor: '#fbbf24',
                backgroundColor: 'rgba(251, 191, 36, 0.1)',
                tension: 0.3,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#f8fafc'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    titleColor: '#f8fafc',
                    bodyColor: '#f8fafc',
                    borderColor: '#fff',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#d1d5db'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '油耗 (升/百公里)',
                        color: '#d1d5db'
                    },
                    ticks: {
                        color: '#d1d5db'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: '油价 (元/升)',
                        color: '#d1d5db'
                    },
                    ticks: {
                        color: '#d1d5db'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

/**
 * 图表响应式调整（带防抖）
 */
function handleChartResize() {
    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }

    resizeTimeout = setTimeout(() => {
        if (consumptionChart) {
            consumptionChart.resize();
        }
    }, 250); // 250ms 防抖
}

/**
 * 重新加载图表数据
 */
function reloadChart(months) {
    // 销毁旧图表
    if (consumptionChart) {
        consumptionChart.destroy();
    }

    // 获取新数据并重新初始化
    fetch(`api/get_chart_data.php?months=${months}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                initChart(data.chartData);
            } else {
                console.error('Failed to load chart data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });
}

/**
 * 表单提交处理（统一处理添加和更新）
 */
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        // 根据editingRecordId判断是添加还是更新
        if (editingRecordId) {
            // 更新模式
            formData.append('id', editingRecordId);
            fetch('api/update_record.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('更新成功', 'success');
                    refreshPageData({refreshStats: true, refreshChart: true, refreshRecords: true});
                    // 退出编辑模式
                    editingRecordId = null;
                    const form = document.getElementById('addRecordForm');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.textContent = '添加记录';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-success');
                    document.getElementById('cancelEditBtn')?.remove();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('更新失败，请稍后重试', 'error');
            });
        } else {
            // 添加模式
            fetch('api/add_record.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('添加成功', 'success');
                    refreshPageData({refreshStats: true, refreshChart: true, refreshRecords: true});
                    // 重置表单
                    document.getElementById('addRecordForm').reset();
                    document.getElementById('refuel_date').valueAsDate = new Date();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('添加失败，请稍后重试', 'error');
            });
        }
    });
});

/**
 * 删除记录
 */
function deleteRecord(id) {
    if (!confirm('确定要删除这条记录吗？删除后将重新计算所有油耗数据。')) {
        return;
    }

    const formData = createFormData({ id: id });
    fetchAPI('api/delete_record.php', formData, '删除成功', () => {
        refreshPageData({refreshStats: true, refreshChart: true, refreshRecords: true});
    });
}

/**
 * 编辑记录(简化版:填充表单)
 */
function editRecord(id) {
    // 找到对应行
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;

    const cells = row.querySelectorAll('td');

    // 设置编辑模式
    editingRecordId = id;

    // 填充表单（移除千位分隔符再解析数字）
    document.getElementById('refuel_date').value = cells[0].textContent.trim();
    document.getElementById('fuel_amount').value = parseFloat(cells[1].textContent.replace(/,/g, ''));
    document.getElementById('current_mileage').value = parseInt(cells[2].textContent.replace(/,/g, ''));
    document.getElementById('fuel_price').value = parseFloat(cells[3].textContent.replace(/,/g, ''));
    document.getElementById('total_cost').value = parseFloat(cells[4].textContent.replace(/,/g, ''));
    document.getElementById('notes').value = cells[6].textContent.trim();

    // 滚动到表单
    document.getElementById('addRecordForm').scrollIntoView({ behavior: 'smooth' });

    // 修改UI为更新模式
    const form = document.getElementById('addRecordForm');
    const submitBtn = form.querySelector('button[type="submit"]');

    // 修改按钮文字
    submitBtn.textContent = '更新记录';
    submitBtn.classList.remove('btn-success');
    submitBtn.classList.add('btn-primary');

    // 添加取消按钮
    let cancelBtn = document.getElementById('cancelEditBtn');
    if (!cancelBtn) {
        cancelBtn = document.createElement('button');
        cancelBtn.id = 'cancelEditBtn';
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-danger';
        cancelBtn.textContent = '取消编辑';
        cancelBtn.onclick = function() {
            // 重置编辑模式
            editingRecordId = null;
            form.reset();
            submitBtn.textContent = '添加记录';
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-success');
            cancelBtn.remove();
            // 设置今天的日期为默认值
            document.getElementById('refuel_date').valueAsDate = new Date();
        };
        submitBtn.parentNode.insertBefore(cancelBtn, submitBtn);
    }
}

/**
 * 导出CSV
 */
function exportCSV() {
    window.location.href = 'api/export_csv.php';
}

/**
 * 显示通知
 */
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// ==================== 车辆管理功能 ====================

/**
 * 切换车辆
 */
function switchVehicle(vehicleId) {
    const formData = createFormData({ vehicle_id: vehicleId });
    fetchAPI('api/vehicles/switch.php', formData, null, () => {
        refreshPageData({refreshStats: true, refreshChart: true, refreshRecords: true, refreshVehicleSelector: true});
    });
}

/**
 * 打开车辆管理模态框
 */
function openVehicleManager() {
    // 加载车辆列表
    loadVehicleList();

    // 显示模态框
    document.getElementById('vehicleModal').style.display = 'flex';
}

/**
 * 关闭车辆管理模态框
 */
function closeVehicleModal() {
    document.getElementById('vehicleModal').style.display = 'none';
    cancelEditVehicle();
}

/**
 * 加载车辆列表
 */
function loadVehicleList() {
    const showInactive = document.getElementById('showInactiveVehicles')?.checked ? '1' : '0';
    fetch(`api/vehicles/list.php?include_inactive=${showInactive}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderVehicleList(data.vehicles);
            } else {
                showNotification(data.message || '加载车辆列表失败', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('加载车辆列表失败', 'error');
        });
}

/**
 * 渲染车辆列表
 */
function renderVehicleList(vehicles) {
    const tbody = document.getElementById('vehicleTableBody');
    if (vehicles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">暂无车辆</td></tr>';
        return;
    }

    tbody.innerHTML = vehicles.map(v => {
        const isActive = v.is_active == 1;
        const isDefault = v.is_default == 1;

        let actionButtons = `<button class="btn btn-sm btn-secondary" onclick="editVehicleItem(${v.id})">编辑</button>`;

        if (!isDefault) {
            if (isActive) {
                actionButtons += `<button class="btn btn-sm btn-primary" onclick="setDefaultVehicleItem(${v.id})">设为默认</button>`;
                actionButtons += `<button class="btn btn-sm btn-warning" onclick="toggleVehicleStatus(${v.id}, '${escapeHtml(v.name)}', 1)">停用</button>`;
            } else {
                actionButtons += `<button class="btn btn-sm btn-success" onclick="toggleVehicleStatus(${v.id}, '${escapeHtml(v.name)}', 0)">激活</button>`;
                actionButtons += `<button class="btn btn-sm btn-danger" onclick="deleteVehicleItem(${v.id})">删除</button>`;
            }
        }

        return `
            <tr class="${!isActive ? 'inactive-row' : ''}">
                <td>${escapeHtml(v.name)}</td>
                <td>${v.plate_number ? escapeHtml(v.plate_number) : '-'}</td>
                <td>
                    ${isDefault ? '<span class="badge badge-primary">默认</span>' : ''}
                    ${isActive ? '<span class="badge badge-success">激活</span>' : '<span class="badge badge-secondary">已停用</span>'}
                </td>
                <td class="table-actions">${actionButtons}</td>
            </tr>
        `;
    }).join('');
}

/**
 * 保存车辆（添加或更新）
 */
function saveVehicle(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    formData.append('csrf_token', csrfToken);

    const vehicleId = document.getElementById('editVehicleId').value;
    const url = vehicleId ? 'api/vehicles/update.php' : 'api/vehicles/add.php';

    if (vehicleId) {
        formData.append('id', vehicleId);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || '保存成功', 'success');
            loadVehicleList();
            refreshVehicleSelector();
            cancelEditVehicle();
        } else {
            showNotification(data.message || '保存失败', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('保存失败', 'error');
    });
}

/**
 * 编辑车辆
 */
function editVehicleItem(id) {
    fetch('api/vehicles/list.php?include_inactive=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const vehicle = data.vehicles.find(v => v.id == id);
                if (vehicle) {
                    document.getElementById('editVehicleId').value = vehicle.id;
                    document.getElementById('vehicleName').value = vehicle.name;
                    document.getElementById('vehiclePlateNumber').value = vehicle.plate_number || '';
                    document.getElementById('vehicleNotes').value = vehicle.notes || '';
                    document.getElementById('vehicleFormTitle').textContent = '编辑车辆';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('加载车辆信息失败', 'error');
        });
}

/**
 * 取消编辑车辆
 */
function cancelEditVehicle() {
    document.getElementById('editVehicleId').value = '';
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicleFormTitle').textContent = '添加车辆';
}

/**
 * 删除车辆
 */
function deleteVehicleItem(id) {
    if (!confirm('确定要删除这辆车吗？如果有加油记录，将只能停用。')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);

    fetch('api/vehicles/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || '删除成功', 'success');
            loadVehicleList();
            refreshVehicleSelector();
        } else {
            showNotification(data.message || '删除失败', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('删除失败', 'error');
    });
}

/**
 * 切换车辆状态（激活/停用）
 */
function toggleVehicleStatus(id, name, currentStatus) {
    const action = currentStatus == 1 ? '停用' : '激活';
    if (!confirm(`确定要${action}车辆"${name}"吗？`)) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);

    fetch('api/vehicles/toggle_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadVehicleList();
            refreshVehicleSelector();
        } else {
            showNotification(data.message || '操作失败', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('操作失败', 'error');
    });
}

/**
 * 设置默认车辆
 */
function setDefaultVehicleItem(id) {
    const formData = createFormData({ id: id });
    fetchAPI('api/vehicles/set_default.php', formData, '设置成功', () => {
        loadVehicleList();
        refreshPageData({refreshVehicleSelector: true});
    });
}

/**
 * HTML转义函数
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== 页面初始化和工具函数 ====================

/**
 * 智能自动计算：根据任意两个字段计算第三个字段
 */
function initAutoCalculate() {
    let lastModified = null;

    document.getElementById('fuel_amount').addEventListener('input', function() {
        lastModified = 'amount';
        autoCalculate();
    });

    document.getElementById('fuel_price').addEventListener('input', function() {
        lastModified = 'price';
        autoCalculate();
    });

    document.getElementById('total_cost').addEventListener('input', function() {
        lastModified = 'cost';
        autoCalculate();
    });

    function autoCalculate() {
        const amount = parseFloat(document.getElementById('fuel_amount').value) || 0;
        const price = parseFloat(document.getElementById('fuel_price').value) || 0;
        const cost = parseFloat(document.getElementById('total_cost').value) || 0;

        // 根据最后修改的字段和已有数据，计算缺失的字段
        if (lastModified === 'cost') {
            // 用户输入了总金额
            if (amount > 0 && cost > 0) {
                // 总金额 ÷ 加油量 = 油价
                document.getElementById('fuel_price').value = (cost / amount).toFixed(2);
            } else if (price > 0 && cost > 0) {
                // 总金额 ÷ 油价 = 加油量
                document.getElementById('fuel_amount').value = (cost / price).toFixed(2);
            }
        } else if (lastModified === 'amount') {
            // 用户输入了加油量
            if (price > 0) {
                // 加油量 × 油价 = 总金额
                document.getElementById('total_cost').value = (amount * price).toFixed(2);
            } else if (cost > 0 && amount > 0) {
                // 总金额 ÷ 加油量 = 油价
                document.getElementById('fuel_price').value = (cost / amount).toFixed(2);
            }
        } else if (lastModified === 'price') {
            // 用户输入了油价
            if (amount > 0) {
                // 加油量 × 油价 = 总金额
                document.getElementById('total_cost').value = (amount * price).toFixed(2);
            } else if (cost > 0 && price > 0) {
                // 总金额 ÷ 油价 = 加油量
                document.getElementById('fuel_amount').value = (cost / price).toFixed(2);
            }
        }
    }
}

/**
 * 初始化时间范围按钮
 */
function initTimeRangeButtons() {
    document.querySelectorAll('.time-range-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // 移除所有按钮的激活状态
            document.querySelectorAll('.time-range-btn').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-secondary');
            });

            // 激活当前按钮
            this.classList.remove('btn-secondary');
            this.classList.add('btn-primary');

            // 重新加载图表
            const months = parseInt(this.getAttribute('data-months'));
            reloadChart(months);
        });
    });
}

/**
 * 分页跳转（AJAX 方式，不刷新整页）
 * @param {number} page - 目标页码
 * @param {number} perPageOverride - 可选：覆盖每页数量（用于切换每页显示数量）
 */
async function changePage(page, perPageOverride) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentPerPage = perPageOverride || parseInt(urlParams.get('per_page')) || 20;

    try {
        const response = await fetch(`api/get_records.php?page=${page}&per_page=${currentPerPage}`);
        const data = await response.json();

        if (data.success) {
            // 更新表格和分页 HTML
            updateRecordsTable(data.records, data.totalPages, data.currentPage, data.totalRecords, data.perPage);

            // 更新 URL（History API）
            const newUrl = `?page=${data.currentPage}&per_page=${data.perPage}`;
            history.pushState({ page: data.currentPage, perPage: data.perPage }, '', newUrl);

            // 滚动到表格顶部（可选）
            const recordsTable = document.querySelector('.records-table');
            if (recordsTable) {
                recordsTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } else {
            showNotification(data.message || '加载失败', 'error');
        }
    } catch (error) {
        console.error('分页加载失败:', error);
        showNotification('加载失败，请稍后重试', 'error');
    }
}

/**
 * 切换每页显示数量
 */
function changePerPage(perPage) {
    changePage(1, perPage); // 重置到第一页
}

/**
 * 页面加载完成后的初始化
 */
function initPageFeatures() {
    // 设置今天的日期为默认值
    const dateInput = document.getElementById('refuel_date');
    if (dateInput && !dateInput.value) {
        dateInput.valueAsDate = new Date();
    }

    // 初始化自动计算
    initAutoCalculate();

    // 初始化时间范围按钮
    initTimeRangeButtons();

    // 初始化图表（如果有数据）
    if (typeof chartData !== 'undefined') {
        initChart(chartData);
    }

    // 添加窗口 resize 监听（带防抖）
    window.addEventListener('resize', handleChartResize);

    // 分页按钮事件委托
    document.addEventListener('click', function(e) {
        if (e.target.matches('.pagination-btn[data-page]') &&
            !e.target.classList.contains('disabled') &&
            !e.target.classList.contains('active')) {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            // 从下拉菜单获取当前 perPage，确保与用户选择一致
            const perPageSelect = document.getElementById('perPageSelect');
            const perPage = perPageSelect ? parseInt(perPageSelect.value) : null;
            changePage(page, perPage);
        }
    });

    // 监听浏览器前进后退
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.page) {
            changePage(e.state.page, e.state.perPage);
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            const page = parseInt(urlParams.get('page')) || 1;
            const perPage = parseInt(urlParams.get('per_page')) || 20;
            changePage(page, perPage);
        }
    });
}

// 页面加载完成后执行初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageFeatures);
} else {
    initPageFeatures();
}
