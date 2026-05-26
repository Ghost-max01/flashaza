<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Monitoring | Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #6C37F2;
            --primary-light: #8C65F5;
            --primary-dark: #5A2BD9;
            --secondary: #805AD5;
            --white: #FFFFFF;
            --light-gray: #F5F7FA;
            --medium-gray: #E4E7EB;
            --dark-gray: #2D3748;
            --text: #2D3748;
            --text-light: #718096;
            --success: #38A169;
            --warning: #DD6B20;
            --danger: #E53E3E;
            --info: #3182CE;
        }
        
        body {
            background-color: var(--light-gray);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--white);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            padding: 25px 0;
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .logo {
            display: flex;
            align-items: center;
            padding: 0 25px 30px;
            border-bottom: 1px solid var(--medium-gray);
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 28px;
            color: var(--primary);
            margin-right: 12px;
        }
        
        .logo h1 {
            font-size: 24px;
            color: var(--dark-gray);
            font-weight: 700;
        }
        
        .nav-links {
            list-style: none;
            padding: 0 15px;
        }
        
        .nav-links li {
            margin-bottom: 8px;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(108, 55, 242, 0.1);
            color: var(--primary);
        }
        
        .nav-links a i {
            font-size: 20px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            font-size: 28px;
            color: var(--dark-gray);
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info h3 {
            font-size: 16px;
            color: var(--dark-gray);
            font-weight: 600;
        }
        
        .user-info p {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        /* Server Monitoring Styles */
        .monitoring-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .monitoring-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid var(--medium-gray);
            display: flex;
            flex-direction: column;
        }
        
        .monitoring-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .monitoring-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(108, 55, 242, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .monitoring-card-icon i {
            font-size: 24px;
            color: var(--primary);
        }
        
        .monitoring-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .monitoring-card-value {
            font-size: 28px;
            font-weight: 700;
            margin: 10px 0;
            color: var(--dark-gray);
        }
        
        .monitoring-card-subtitle {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .progress-bar {
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            margin-top: 15px;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            border-radius: 5px;
        }
        
        .progress-success {
            background: var(--success);
        }
        
        .progress-warning {
            background: var(--warning);
        }
        
        .progress-danger {
            background: var(--danger);
        }
        
        .chart-container {
            height: 250px;
            margin-top: 20px;
        }
        
        .server-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .server-info-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .server-info-card h3 {
            font-size: 20px;
            color: var(--dark-gray);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .server-info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .server-info-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .server-info-value {
            color: var(--dark-gray);
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-up {
            background: var(--success);
        }
        
        .status-warning {
            background: var(--warning);
        }
        
        .status-down {
            background: var(--danger);
        }
        
        .service-status {
            display: flex;
            align-items: center;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .monitoring-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .server-info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .monitoring-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-server"></i>
            <h1>AdminPortal</h1>
        </div>
        <ul class="nav-links">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="user-list.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-wallet"></i>
                    <span>Subscriptions</span>
                </a>
            </li>
            <li>
                <a href="server-monitor.php" class="active">
                    <i class="fas fa-server"></i>
                    <span>Server Monitor</span>
                </a>
            </li>
            <li>
                <a href="payment-request.php">
                    <i class="fas fa-money-check"></i>
                    <span>Payment Requests</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2>Server Monitoring</h2>
            <div class="header-actions">
                <div class="user-profile">
                    <div class="user-info">
                        <h3>Admin User</h3>
                        <p>System Administrator</p>
                    </div>
                    <div class="avatar">A</div>
                </div>
            </div>
        </div>
        
        <div class="monitoring-container">
            <!-- CPU Usage Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="monitoring-card-title">CPU Usage</div>
                </div>
                <div class="monitoring-card-value">42%</div>
                <div class="monitoring-card-subtitle">Average load: 1.24 / 4.00</div>
                <div class="progress-bar">
                    <div class="progress progress-success" style="width: 42%"></div>
                </div>
                <div class="chart-container">
                    <canvas id="cpuChart"></canvas>
                </div>
            </div>
            
            <!-- Memory Usage Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-memory"></i>
                    </div>
                    <div class="monitoring-card-title">Memory Usage</div>
                </div>
                <div class="monitoring-card-value">3.2 GB / 8.0 GB</div>
                <div class="monitoring-card-subtitle">40% of total memory</div>
                <div class="progress-bar">
                    <div class="progress progress-success" style="width: 40%"></div>
                </div>
                <div class="chart-container">
                    <canvas id="memoryChart"></canvas>
                </div>
            </div>
            
            <!-- Disk Usage Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="monitoring-card-title">Disk Usage</div>
                </div>
                <div class="monitoring-card-value">120 GB / 256 GB</div>
                <div class="monitoring-card-subtitle">47% of disk space used</div>
                <div class="progress-bar">
                    <div class="progress progress-success" style="width: 47%"></div>
                </div>
                <div class="chart-container">
                    <canvas id="diskChart"></canvas>
                </div>
            </div>
            
            <!-- Network Traffic Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="monitoring-card-title">Network Traffic</div>
                </div>
                <div class="monitoring-card-value">1.24 Gbps</div>
                <div class="monitoring-card-subtitle">In: 640 Mbps | Out: 600 Mbps</div>
                <div class="progress-bar">
                    <div class="progress progress-info" style="width: 62%"></div>
                </div>
                <div class="chart-container">
                    <canvas id="networkChart"></canvas>
                </div>
            </div>
            
            <!-- Uptime Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="monitoring-card-title">Server Uptime</div>
                </div>
                <div class="monitoring-card-value">42 days</div>
                <div class="monitoring-card-subtitle">Last reboot: 2023-07-01 14:30:00</div>
                <div class="progress-bar">
                    <div class="progress progress-success" style="width: 100%"></div>
                </div>
            </div>
            
            <!-- Service Status Card -->
            <div class="monitoring-card">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="monitoring-card-title">Service Status</div>
                </div>
                <div class="monitoring-card-value">All Systems Operational</div>
                <div class="server-info-item">
                    <div class="service-status">
                        <span class="status-indicator status-up"></span>
                        <span class="server-info-label">Web Server</span>
                    </div>
                    <div class="server-info-value">Running</div>
                </div>
                <div class="server-info-item">
                    <div class="service-status">
                        <span class="status-indicator status-up"></span>
                        <span class="server-info-label">Database</span>
                    </div>
                    <div class="server-info-value">Running</div>
                </div>
                <div class="server-info-item">
                    <div class="service-status">
                        <span class="status-indicator status-up"></span>
                        <span class="server-info-label">Mail Server</span>
                    </div>
                    <div class="server-info-value">Running</div>
                </div>
                <div class="server-info-item">
                    <div class="service-status">
                        <span class="status-indicator status-warning"></span>
                        <span class="server-info-label">Backup Service</span>
                    </div>
                    <div class="server-info-value">Pending</div>
                </div>
            </div>
        </div>
        
        <div class="server-info-grid">
            <div class="server-info-card">
                <h3>Server Information</h3>
                <div class="server-info-item">
                    <div class="server-info-label">Server Name</div>
                    <div class="server-info-value">web-server-01</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Operating System</div>
                    <div class="server-info-value">Ubuntu 22.04 LTS</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Kernel Version</div>
                    <div class="server-info-value">5.15.0-78-generic</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">CPU Model</div>
                    <div class="server-info-value">Intel Xeon E5-2680 v4</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">CPU Cores</div>
                    <div class="server-info-value">4 Cores / 8 Threads</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Last Updated</div>
                    <div class="server-info-value">2023-08-10 09:15:00</div>
                </div>
            </div>
            
            <div class="server-info-card">
                <h3>Application Information</h3>
                <div class="server-info-item">
                    <div class="server-info-label">PHP Version</div>
                    <div class="server-info-value">8.1.22</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Database Version</div>
                    <div class="server-info-value">MySQL 8.0.34</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Web Server</div>
                    <div class="server-info-value">Apache 2.4.52</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Application Version</div>
                    <div class="server-info-value">v3.2.1</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Uptime</div>
                    <div class="server-info-value">42 days 12:34:21</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">Active Users</div>
                    <div class="server-info-value">1,243</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // CPU Usage Chart
            const cpuCtx = document.getElementById('cpuChart').getContext('2d');
            const cpuChart = new Chart(cpuCtx, {
                type: 'line',
                data: {
                    labels: ['1m', '5m', '10m', '15m', '20m', '25m', '30m'],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [35, 42, 38, 45, 50, 48, 42],
                        borderColor: '#6C37F2',
                        backgroundColor: 'rgba(108, 55, 242, 0.1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    }
                }
            });
            
            // Memory Usage Chart
            const memoryCtx = document.getElementById('memoryChart').getContext('2d');
            const memoryChart = new Chart(memoryCtx, {
                type: 'bar',
                data: {
                    labels: ['Used', 'Cached', 'Free'],
                    datasets: [{
                        label: 'Memory (GB)',
                        data: [3.2, 2.1, 2.7],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.7)',
                            'rgba(49, 130, 206, 0.7)',
                            'rgba(229, 62, 62, 0.7)'
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(49, 130, 206, 1)',
                            'rgba(229, 62, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 8
                        }
                    }
                }
            });
            
            // Disk Usage Chart
            const diskCtx = document.getElementById('diskChart').getContext('2d');
            const diskChart = new Chart(diskCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Used', 'Free'],
                    datasets: [{
                        data: [120, 136],
                        backgroundColor: [
                            'rgba(221, 107, 32, 0.7)',
                            'rgba(229, 231, 235, 0.7)'
                        ],
                        borderColor: [
                            'rgba(221, 107, 32, 1)',
                            'rgba(209, 213, 219, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Network Traffic Chart
            const networkCtx = document.getElementById('networkChart').getContext('2d');
            const networkChart = new Chart(networkCtx, {
                type: 'line',
                data: {
                    labels: ['1m', '5m', '10m', '15m', '20m', '25m', '30m'],
                    datasets: [
                        {
                            label: 'Incoming (Mbps)',
                            data: [420, 510, 480, 550, 600, 580, 640],
                            borderColor: '#3182CE',
                            backgroundColor: 'rgba(49, 130, 206, 0.1)',
                            borderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Outgoing (Mbps)',
                            data: [380, 450, 420, 480, 520, 550, 600],
                            borderColor: '#38A169',
                            backgroundColor: 'rgba(56, 161, 105, 0.1)',
                            borderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Simulate real-time updates
            setInterval(() => {
                // Update CPU usage
                const newCpuData = cpuChart.data.datasets[0].data;
                newCpuData.shift();
                newCpuData.push(Math.floor(Math.random() * 30) + 30);
                cpuChart.update();
                
                // Update network traffic
                const inData = networkChart.data.datasets[0].data;
                inData.shift();
                inData.push(Math.floor(Math.random() * 200) + 400);
                
                const outData = networkChart.data.datasets[1].data;
                outData.shift();
                outData.push(Math.floor(Math.random() * 200) + 350);
                
                networkChart.update();
            }, 5000);
        });
    </script>
</body>
</html>