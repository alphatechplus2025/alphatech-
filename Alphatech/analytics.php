<?php
session_start();
include('config.php');

// Ensure only admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch analytics data
$totalStaff = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role IN ('staff', 'team_leader')")->fetch_assoc()['total'];
$totalManagers = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role='manager'")->fetch_assoc()['total'];
$totalProjects = $conn->query("SELECT COUNT(*) as total FROM projects")->fetch_assoc()['total'];
$activeProjects = $conn->query("SELECT COUNT(*) as total FROM projects WHERE status='active'")->fetch_assoc()['total'];

// Department-wise staff count
$deptStaff = $conn->query("
    SELECT d.dept_name, COUNT(cs.id) as staff_count
    FROM departments d
    LEFT JOIN staff_profiles sp ON d.id = sp.department_id
    LEFT JOIN company_staffs cs ON sp.staff_id = cs.id AND cs.role IN ('staff', 'team_leader')
    GROUP BY d.id
");

// Project status distribution
$projectStatus = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM projects 
    GROUP BY status
");

// Monthly attendance trend
$monthlyAttendance = $conn->query("
    SELECT DATE_FORMAT(work_date, '%Y-%m') as month, 
           COUNT(*) as present_days,
           COUNT(DISTINCT staff_id) as unique_staff
    FROM attendance 
    WHERE work_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(work_date, '%Y-%m')
    ORDER BY month
");

// Task completion rate
$taskStats = $conn->query("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        AVG(progress) as avg_progress
    FROM tasks
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Analytics Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #00ADB5;
    --primary-dark: #00969c;
    --dark-bg: #0f172a;
    --card-bg: #1e293b;
    --sidebar-width: 260px;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body { 
    font-family: 'Inter', sans-serif; 
    background: var(--dark-bg); 
    color: #e2e8f0; 
    overflow-x: hidden;
}
.sidebar { 
    background: var(--card-bg); 
    position: fixed;
    height: 100vh;
    width: var(--sidebar-width);
    left: 0;
    top: 0;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    overflow-y: auto;
}
.card { 
    background: var(--card-bg); 
    border: 1px solid #334155; 
    border-radius: 12px; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.2);
}
.gradient-text { 
    background: linear-gradient(135deg, var(--primary), #00FFF5); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent;
    font-weight: 700;
}
.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 8px;
    transition: all 0.2s ease;
    color: #cbd5e1;
    margin-bottom: 8px;
    text-decoration: none;
}
.nav-link:hover, .nav-link.active {
    background: rgba(0, 173, 181, 0.1);
    color: var(--primary);
    border-left: 3px solid var(--primary);
}
.nav-link i {
    width: 24px;
    margin-right: 12px;
    font-size: 18px;
}
.topbar {
    background: var(--card-bg);
    border-bottom: 1px solid #334155;
    position: sticky;
    top: 0;
    z-index: 100;
    padding: 16px 24px;
    backdrop-filter: blur(10px);
}
.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
}
.content-area {
    flex: 1;
    padding: 24px;
}
.footer {
    background: var(--card-bg);
    border-top: 1px solid #334155;
    padding: 16px 24px;
    margin-left: var(--sidebar-width);
    transition: all 0.3s ease;
}
.mobile-menu-btn {
    display: none;
}
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}
.stat-card {
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), #00FFF5);
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .main-content, .footer {
        margin-left: 0;
    }
    .mobile-menu-btn {
        display: block;
    }
    .sidebar-overlay.active {
        display: block;
    }
}
</style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar p-6 flex flex-col justify-between">
    <div>
        <div class="flex items-center mb-10">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center mr-3">
                <i class="fas fa-rocket text-white"></i>
            </div>
            <h1 class="text-xl font-bold gradient-text">ALPHA TECH</h1>
        </div>
        
        <nav class="space-y-1">
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="departments.php" class="nav-link">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <a href="projects.php" class="nav-link">
                <i class="fas fa-folder"></i>
                <span>Projects</span>
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="analytics.php" class="nav-link active">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="announcements.php" class="nav-link">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </div>
    
    <div class="border-t border-slate-700 pt-4">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                <i class="fas fa-user text-slate-300"></i>
            </div>
            <div>
                <p class="font-medium"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin User') ?></p>
                <p class="text-xs text-slate-400">Administrator</p>
            </div>
        </div>
        <a href="logout.php" class="nav-link text-red-400 hover:text-red-300">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Topbar -->
    <header class="topbar flex justify-between items-center">
        <div class="flex items-center">
            <button class="mobile-menu-btn mr-4 text-slate-300 hover:text-white" id="mobileMenuBtn">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="topbar-title font-bold text-xl">Analytics Dashboard</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="border-l border-slate-600 pl-4 flex items-center">
                <div class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center mr-2">
                    <i class="fas fa-user text-slate-300 text-sm"></i>
                </div>
                <span class="font-medium"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <main class="content-area">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold mb-2">Analytics & Insights 📊</h1>
            <p class="text-slate-400">Comprehensive overview of company performance and metrics</p>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Total Staff</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalStaff ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-users text-blue-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 12%</span> from last quarter</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Managers</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalManagers ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                        <i class="fas fa-user-tie text-cyan-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 5%</span> from last quarter</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Total Projects</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-folder text-purple-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><?= $activeProjects ?> active projects</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Task Completion</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $taskStats['total_tasks'] > 0 ? round(($taskStats['completed_tasks'] / $taskStats['total_tasks']) * 100) : 0 ?>%</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-tasks text-green-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4">Avg. progress: <?= round($taskStats['avg_progress'] ?? 0) ?>%</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Department Distribution -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Department Distribution</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="deptChart" height="250"></canvas>
            </div>
            
            <!-- Project Status -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Project Status</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="projectStatusChart" height="250"></canvas>
            </div>
        </div>

        <!-- Additional Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Monthly Attendance Trend -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Monthly Attendance Trend</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="attendanceTrendChart" height="250"></canvas>
            </div>
            
            <!-- Task Progress -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Task Progress Overview</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="taskProgressChart" height="250"></canvas>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Productivity Score -->
            <div class="card p-6">
                <h3 class="font-semibold mb-4">Productivity Score</h3>
                <div class="text-center">
                    <div class="relative inline-block">
                        <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 36 36">
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none" stroke="#334155" stroke-width="3"/>
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none" stroke="#00ADB5" stroke-width="3" stroke-dasharray="78, 100"/>
                            <text x="18" y="20.5" text-anchor="middle" fill="#e2e8f0" font-size="8" font-weight="bold">78%</text>
                        </svg>
                    </div>
                    <p class="text-slate-400 text-sm mt-4">Overall team productivity score</p>
                </div>
            </div>
            
            <!-- Recent Performance -->
            <div class="card p-6 lg:col-span-2">
                <h3 class="font-semibold mb-4">Performance Insights</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm">Attendance Rate</span>
                            <span class="text-sm font-medium text-green-400">94%</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="h-2 rounded-full bg-green-500" style="width: 94%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm">Project Completion</span>
                            <span class="text-sm font-medium text-cyan-400">82%</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="h-2 rounded-full bg-cyan-500" style="width: 82%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm">Task Efficiency</span>
                            <span class="text-sm font-medium text-purple-400">76%</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="h-2 rounded-full bg-purple-500" style="width: 76%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm">Team Collaboration</span>
                            <span class="text-sm font-medium text-amber-400">88%</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="h-2 rounded-full bg-amber-500" style="width: 88%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="text-slate-400 text-sm">
            © 2023 ALPHA TECH. All rights reserved. | v1.0.0
        </div>
        <div class="flex space-x-4 mt-2 md:mt-0">
            <a href="#" class="text-slate-400 hover:text-white text-sm">Privacy Policy</a>
            <a href="#" class="text-slate-400 hover:text-white text-sm">Terms of Service</a>
            <a href="#" class="text-slate-400 hover:text-white text-sm">Help Center</a>
        </div>
    </div>
</footer>

<script>
// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }
});

// Department Distribution Chart
const deptCtx = document.getElementById('deptChart');
if (deptCtx) {
    const deptLabels = [];
    const deptData = [];
    
    <?php while ($dept = $deptStaff->fetch_assoc()): ?>
        deptLabels.push('<?= $dept['dept_name'] ?>');
        deptData.push(<?= $dept['staff_count'] ?>);
    <?php endwhile; ?>
    
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: deptLabels,
            datasets: [{
                data: deptData,
                backgroundColor: ['#00ADB5','#3B82F6','#8B5CF6','#F59E0B','#10B981','#EF4444'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    ticks: {
                        color: '#94a3b8',
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Project Status Chart
const projectStatusCtx = document.getElementById('projectStatusChart');
if (projectStatusCtx) {
    const statusLabels = [];
    const statusData = [];
    const statusColors = {
        'planning': '#3B82F6',
        'active': '#10B981',
        'testing': '#F59E0B',
        'completed': '#8B5CF6',
        'on-hold': '#EF4444'
    };
    
    <?php while ($status = $projectStatus->fetch_assoc()): ?>
        statusLabels.push('<?= ucfirst($status['status']) ?>');
        statusData.push(<?= $status['count'] ?>);
    <?php endwhile; ?>
    
    new Chart(projectStatusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusLabels.map(label => statusColors[label.toLowerCase()]),
                borderWidth: 0
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#e2e8f0',
                        padding: 15
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// Attendance Trend Chart
const attendanceTrendCtx = document.getElementById('attendanceTrendChart');
if (attendanceTrendCtx) {
    // Sample data - replace with actual data from database
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
    const attendanceData = [85, 88, 92, 90, 87, 94, 96];
    
    new Chart(attendanceTrendCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Attendance Rate %',
                data: attendanceData,
                borderColor: '#00ADB5',
                backgroundColor: 'rgba(0,173,181,0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#00ADB5',
                pointBorderColor: '#0f172a',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            scales: {
                y: {
                    min: 80,
                    max: 100,
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#e2e8f0'
                    }
                }
            }
        }
    });
}

// Task Progress Chart
const taskProgressCtx = document.getElementById('taskProgressChart');
if (taskProgressCtx) {
    new Chart(taskProgressCtx, {
        type: 'polarArea',
        data: {
            labels: ['Completed', 'In Progress', 'Pending', 'On Hold'],
            datasets: [{
                data: [45, 30, 15, 10],
                backgroundColor: [
                    'rgba(34, 197, 94, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            scales: {
                r: {
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    ticks: {
                        color: '#94a3b8',
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#e2e8f0',
                        padding: 15
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>