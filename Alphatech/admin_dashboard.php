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

// ✅ Reusable count function
function safeCount($query, $conn) {
    $result = $conn->query($query);
    return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
}

// ✅ Dashboard stats based on your actual database tables
$totalDepartments = safeCount("SELECT COUNT(*) AS total FROM departments", $conn);
$totalManagers = safeCount("SELECT COUNT(*) AS total FROM company_staffs WHERE role='manager'", $conn);
$totalStaffs = safeCount("SELECT COUNT(*) AS total FROM company_staffs WHERE role='staff'", $conn);
$totalTeamLeaders = safeCount("SELECT COUNT(*) AS total FROM company_staffs WHERE role='team_leader'", $conn);
$totalProjects = safeCount("SELECT COUNT(*) AS total FROM projects", $conn);
$activeProjects = safeCount("SELECT COUNT(*) AS total FROM projects WHERE status='active'", $conn);
$totalPresent = safeCount("SELECT COUNT(*) AS total FROM attendance WHERE work_date = CURDATE() AND status='present'", $conn);
$totalStaffAll = safeCount("SELECT COUNT(*) AS total FROM company_staffs WHERE role IN ('manager','staff','team_leader')", $conn);

$attendanceRate = $totalStaffAll > 0 ? round(($totalPresent / $totalStaffAll) * 100, 1) : 0;

// ✅ Fetch Recent Announcements
$announcements = $conn->query("SELECT a.*, s.full_name FROM announcements a 
                               JOIN company_staffs s ON a.posted_by = s.id 
                               ORDER BY a.created_at DESC LIMIT 4");

// ✅ Department Distribution Data
$deptData = $conn->query("SELECT d.dept_name, COUNT(s.id) AS total_staff 
                          FROM departments d 
                          LEFT JOIN company_staffs s ON d.id = s.team_leader_id 
                          GROUP BY d.dept_name");
$deptLabels = [];
$deptCounts = [];
while ($row = $deptData->fetch_assoc()) {
    $deptLabels[] = $row['dept_name'];
    $deptCounts[] = $row['total_staff'];
}

// ✅ Project Status Distribution
$projectStatusData = $conn->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status");
$projectStatusLabels = [];
$projectStatusCounts = [];
while ($row = $projectStatusData->fetch_assoc()) {
    $projectStatusLabels[] = ucfirst($row['status']);
    $projectStatusCounts[] = $row['count'];
}

// ✅ Recent Activity (combining multiple sources)
$recentActivities = $conn->query("
    (SELECT 'task' as type, t.title as description, t.updated_at as activity_date, cs.full_name 
     FROM tasks t 
     JOIN company_staffs cs ON t.assigned_to = cs.id 
     ORDER BY t.updated_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'project' as type, p.project_name as description, p.created_at as activity_date, cs.full_name 
     FROM projects p 
     JOIN company_staffs cs ON p.manager_id = cs.id 
     ORDER BY p.created_at DESC LIMIT 2)
    ORDER BY activity_date DESC LIMIT 4
");

// ✅ Weekly Attendance Data (sample data for chart)
$attendanceWeekly = [85, 88, 92, 90, 87, 82, 89]; // This would normally come from your attendance table
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Admin Dashboard</title>
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
    .topbar-title {
        font-size: 18px;
    }
    .content-area {
        padding: 16px;
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
            <a href="admin_dashboard.php" class="nav-link active">
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
            <a href="analytics.php" class="nav-link">
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
            <h2 class="topbar-title font-bold text-xl">Dashboard Overview</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button class="text-slate-300 hover:text-white relative">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">3</span>
                </button>
            </div>
            
            <div class="relative">
                <button class="text-slate-300 hover:text-white relative">
                    <i class="fas fa-envelope text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-cyan-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">5</span>
                </button>
            </div>
            
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
            <h1 class="text-2xl font-bold mb-2">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin User') ?> 👋</h1>
            <p class="text-slate-400">Here's what's happening with your company today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-8">
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Departments</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalDepartments ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                        <i class="fas fa-building text-cyan-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 2.5%</span> from last month</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Managers</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalManagers ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-user-tie text-blue-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 5.2%</span> from last month</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Staff Members</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalStaffs ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-users text-purple-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 3.7%</span> from last month</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Active Projects</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $activeProjects ?>/<?= $totalProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <i class="fas fa-tasks text-amber-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 8.1%</span> from last month</p>
            </div>
            
            <div class="card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Today's Attendance</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $attendanceRate ?>%</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-calendar-check text-green-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><span class="text-green-400"><i class="fas fa-arrow-up"></i> 4.1%</span> from yesterday</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Department Distribution</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="deptChart" height="250"></canvas>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Weekly Attendance Trend</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="attendanceChart" height="250"></canvas>
            </div>
        </div>

        <!-- Project Status & Team Leaders -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Project Status</h3>
                    <a href="projects.php" class="text-sm text-cyan-400 hover:text-cyan-300">View All</a>
                </div>
                <canvas id="projectStatusChart" height="250"></canvas>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Team Leaders</h3>
                    <span class="text-3xl font-bold text-cyan-400"><?= $totalTeamLeaders ?></span>
                </div>
                <div class="space-y-4 mt-4">
                    <?php
                    $teamLeaders = $conn->query("SELECT full_name, email FROM company_staffs WHERE role='team_leader' LIMIT 4");
                    if ($teamLeaders->num_rows > 0):
                        while ($tl = $teamLeaders->fetch_assoc()):
                    ?>
                    <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-cyan-500/10 flex items-center justify-center mr-3">
                                <i class="fas fa-user-shield text-cyan-400"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($tl['full_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($tl['email']) ?></p>
                            </div>
                        </div>
                        <span class="px-2 py-1 bg-cyan-500/20 text-cyan-400 text-xs rounded-full">Team Lead</span>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <p class="text-slate-400 text-sm text-center py-4">No team leaders assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Announcements & Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Recent Announcements</h3>
                    <a href="announcements.php" class="text-sm text-cyan-400 hover:text-cyan-300">View All</a>
                </div>
                <div class="space-y-4">
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while ($a = $announcements->fetch_assoc()): ?>
                            <div class="border-b border-slate-700 pb-4 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-cyan-400"><?= htmlspecialchars($a['title']) ?></h4>
                                    <span class="text-xs text-slate-500"><?= date('M d', strtotime($a['created_at'])) ?></span>
                                </div>
                                <p class="text-slate-400 text-sm mt-1"><?= htmlspecialchars($a['content']) ?></p>
                                <p class="text-xs text-slate-500 mt-2">
                                    Posted by <span class="text-slate-300"><?= htmlspecialchars($a['full_name']) ?></span>
                                </p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-slate-400 text-sm text-center py-4">No announcements yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Recent Activity</h3>
                    <a href="activity.php" class="text-sm text-cyan-400 hover:text-cyan-300">View All</a>
                </div>
                <div class="space-y-4">
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full 
                                    <?= $activity['type'] == 'task' ? 'bg-green-500/10' : 'bg-blue-500/10' ?> 
                                    flex items-center justify-center mr-3 mt-1">
                                    <i class="fas <?= $activity['type'] == 'task' ? 'fa-tasks text-green-400' : 'fa-project-diagram text-blue-400' ?> text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm">
                                        <?= $activity['type'] == 'task' ? 'Task updated:' : 'New project:' ?>
                                        <span class="text-slate-300"><?= htmlspecialchars($activity['description']) ?></span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        By <?= htmlspecialchars($activity['full_name']) ?> • 
                                        <?= date('M d, H:i', strtotime($activity['activity_date'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center mr-3 mt-1">
                                <i class="fas fa-info text-slate-400 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm text-slate-400">No recent activity</p>
                            </div>
                        </div>
                    <?php endif; ?>
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
    
    // Close sidebar when clicking on nav links on mobile
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    });
});

// Active nav link management
document.querySelectorAll('.nav-link').forEach(link => {
    if (link.href === window.location.href) {
        link.classList.add('active');
    }
});

// Charts
const deptCtx = document.getElementById('deptChart');
if (deptCtx) {
    new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: [{
                data: <?= json_encode($deptCounts) ?>,
                backgroundColor: ['#00ADB5','#3B82F6','#8B5CF6','#F59E0B','#10B981','#EF4444'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: { 
            plugins: { 
                legend: { 
                    position: 'right',
                    labels: {
                        color: '#e2e8f0',
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                } 
            },
            cutout: '65%'
        }
    });
}

const attCtx = document.getElementById('attendanceChart');
if (attCtx) {
    new Chart(attCtx, {
        type: 'line',
        data: {
            labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
            datasets: [{
                label: 'Attendance %',
                data: <?= json_encode($attendanceWeekly) ?>,
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
                    min: 75, 
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

const projectStatusCtx = document.getElementById('projectStatusChart');
if (projectStatusCtx) {
    new Chart(projectStatusCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($projectStatusLabels) ?>,
            datasets: [{
                data: <?= json_encode($projectStatusCounts) ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(0, 173, 181, 0.7)',
                    'rgba(148, 163, 184, 0.7)'
                ],
                borderColor: [
                    'rgb(59, 130, 246)',
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(0, 173, 181)',
                    'rgb(148, 163, 184)'
                ],
                borderWidth: 1,
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
</script>
</body>
</html>