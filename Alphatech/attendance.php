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

// Handle manual attendance entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $staff_id = intval($_POST['staff_id']);
    $work_date = $conn->real_escape_string($_POST['work_date']);
    $punch_in = $conn->real_escape_string($_POST['punch_in']);
    $punch_out = $conn->real_escape_string($_POST['punch_out']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Calculate work hours
    $work_hours = 0;
    if ($punch_in && $punch_out) {
        $start = new DateTime($punch_in);
        $end = new DateTime($punch_out);
        $diff = $start->diff($end);
        $work_hours = $diff->h + ($diff->i / 60);
    }
    
    $sql = "INSERT INTO attendance (staff_id, punch_in, punch_out, work_hours, work_date, status) 
            VALUES ($staff_id, '$punch_in', '$punch_out', $work_hours, '$work_date', '$status')";
    
    if ($conn->query($sql)) {
        $_SESSION['success'] = "Attendance record added successfully!";
    } else {
        $_SESSION['error'] = "Error adding attendance: " . $conn->error;
    }
    
    header("Location: attendance.php");
    exit();
}

// Date filter
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch attendance data
$attendance_query = "
    SELECT a.*, cs.full_name, cs.company_id, d.dept_name,
           TIMESTAMPDIFF(HOUR, a.punch_in, a.punch_out) as hours_worked
    FROM attendance a
    JOIN company_staffs cs ON a.staff_id = cs.id
    LEFT JOIN staff_profiles sp ON cs.id = sp.staff_id
    LEFT JOIN departments d ON sp.department_id = d.id
    WHERE a.work_date = '$filter_date'
    ORDER BY a.punch_in DESC
";

$attendance = $conn->query($attendance_query);

// Fetch all staff for dropdown
$staff_members = $conn->query("SELECT id, full_name, company_id FROM company_staffs WHERE role IN ('staff', 'team_leader') AND status='active'");

// Attendance statistics
$totalPresent = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE work_date = '$filter_date' AND status='present'")->fetch_assoc()['total'];
$totalAbsent = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role IN ('staff', 'team_leader') AND status='active'")->fetch_assoc()['total'] - $totalPresent;
$totalLate = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE work_date = '$filter_date' AND status='late'")->fetch_assoc()['total'];
$avgHours = $conn->query("SELECT AVG(work_hours) as avg FROM attendance WHERE work_date = '$filter_date' AND punch_out IS NOT NULL")->fetch_assoc()['avg'];
$avgHours = $avgHours ? round($avgHours, 1) : 0;

// Monthly attendance data for chart
$monthly_data = $conn->query("
    SELECT DATE(work_date) as date, 
           COUNT(*) as present_count,
           (SELECT COUNT(*) FROM company_staffs WHERE role IN ('staff', 'team_leader') AND status='active') as total_staff
    FROM attendance 
    WHERE work_date BETWEEN DATE_SUB('$filter_date', INTERVAL 30 DAY) AND '$filter_date'
    AND status IN ('present', 'late')
    GROUP BY work_date
    ORDER BY work_date
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Attendance Management</title>
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
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-present { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.status-absent { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.status-late { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.status-half-day { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
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
            <a href="attendance.php" class="nav-link active">
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
            <h2 class="topbar-title font-bold text-xl">Attendance Management</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <button onclick="openAddAttendanceModal()" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Record
            </button>
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
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Date Filter -->
        <div class="card p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <h3 class="font-semibold text-lg">Attendance for <?= date('F d, Y', strtotime($filter_date)) ?></h3>
                <form method="GET" class="flex space-x-3">
                    <input type="date" name="date" value="<?= $filter_date ?>" 
                           class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium">
                        Filter
                    </button>
                    <a href="attendance.php" class="bg-slate-600 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-medium">
                        Today
                    </a>
                </form>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Present Today</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalPresent ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-user-check text-green-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4"><?= round(($totalPresent/($totalPresent+$totalAbsent))*100, 1) ?>% attendance rate</p>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Absent Today</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalAbsent ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-red-500/10 flex items-center justify-center">
                        <i class="fas fa-user-times text-red-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4">Staff not checked in</p>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Late Arrivals</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalLate ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <i class="fas fa-clock text-amber-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4">Marked as late</p>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Avg. Hours</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $avgHours ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-business-time text-blue-400"></i>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4">Average work hours</p>
            </div>
        </div>

        <!-- Charts & Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Attendance Trend Chart -->
            <div class="card p-6 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">30-Day Attendance Trend</h3>
                    <button class="text-slate-400 hover:text-white">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <canvas id="attendanceTrendChart" height="250"></canvas>
            </div>
            
            <!-- Department-wise Attendance -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Today's Summary</h3>
                </div>
                <div class="space-y-4">
                    <?php
                    $dept_summary = $conn->query("
                        SELECT d.dept_name, 
                               COUNT(a.id) as present_count,
                               (SELECT COUNT(*) FROM company_staffs cs 
                                JOIN staff_profiles sp ON cs.id = sp.staff_id 
                                WHERE sp.department_id = d.id AND cs.role IN ('staff', 'team_leader') AND cs.status='active') as total_staff
                        FROM departments d
                        LEFT JOIN staff_profiles sp ON d.id = sp.department_id
                        LEFT JOIN attendance a ON sp.staff_id = a.staff_id AND a.work_date = '$filter_date' AND a.status IN ('present', 'late')
                        GROUP BY d.id
                    ");
                    
                    while ($dept = $dept_summary->fetch_assoc()):
                        $percentage = $dept['total_staff'] > 0 ? round(($dept['present_count'] / $dept['total_staff']) * 100, 1) : 0;
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm"><?= $dept['dept_name'] ?></span>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-slate-400"><?= $dept['present_count'] ?>/<?= $dept['total_staff'] ?></span>
                            <span class="text-xs <?= $percentage >= 80 ? 'text-green-400' : ($percentage >= 60 ? 'text-amber-400' : 'text-red-400') ?>">
                                <?= $percentage ?>%
                            </span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="h-2 rounded-full <?= $percentage >= 80 ? 'bg-green-500' : ($percentage >= 60 ? 'bg-amber-500' : 'bg-red-500') ?>" 
                             style="width: <?= $percentage ?>%"></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-semibold text-lg">Attendance Records</h3>
                <div class="flex space-x-2">
                    <input type="text" id="searchAttendance" placeholder="Search records..." class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                    <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                        <option value="">All Status</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="half-day">Half Day</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Employee</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Department</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Punch In</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Punch Out</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Hours</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Status</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance->num_rows > 0): ?>
                            <?php while ($record = $attendance->fetch_assoc()): ?>
                                <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition-colors">
                                    <td class="py-4 px-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-slate-300"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium"><?= htmlspecialchars($record['full_name']) ?></p>
                                                <p class="text-slate-400 text-sm"><?= htmlspecialchars($record['company_id']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm"><?= htmlspecialchars($record['dept_name'] ?? 'Not assigned') ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm"><?= $record['punch_in'] ? date('H:i', strtotime($record['punch_in'])) : '--' ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm"><?= $record['punch_out'] ? date('H:i', strtotime($record['punch_out'])) : '--' ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="bg-slate-700 px-3 py-1 rounded-full text-sm">
                                            <?= $record['work_hours'] ? number_format($record['work_hours'], 1) : '0' ?>h
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="status-badge status-<?= $record['status'] ?>">
                                            <?= ucfirst($record['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex space-x-2">
                                            <button onclick="editAttendance(<?= $record['id'] ?>)" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewDetails(<?= $record['id'] ?>)" class="text-green-400 hover:text-green-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 px-4 text-center text-slate-400">
                                    <i class="fas fa-calendar-times text-4xl mb-3 opacity-50"></i>
                                    <p>No attendance records found for <?= date('F d, Y', strtotime($filter_date)) ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

<!-- Add Attendance Modal -->
<div id="attendanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-xl w-full max-w-md mx-4">
        <div class="flex justify-between items-center p-6 border-b border-slate-700">
            <h3 class="text-xl font-semibold">Add Attendance Record</h3>
            <button onclick="closeAttendanceModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Employee</label>
                    <select name="staff_id" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="">Select Employee</option>
                        <?php while ($staff = $staff_members->fetch_assoc()): ?>
                            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['full_name']) ?> (<?= $staff['company_id'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Work Date</label>
                    <input type="date" name="work_date" value="<?= $filter_date ?>" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Punch In</label>
                        <input type="time" name="punch_in" 
                               class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Punch Out</label>
                        <input type="time" name="punch_out" 
                               class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                    <select name="status" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="half-day">Half Day</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeAttendanceModal()" class="px-4 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_attendance" 
                        class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                    Add Record
                </button>
            </div>
        </form>
    </div>
</div>

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

// Attendance Modal Functions
function openAddAttendanceModal() {
    document.getElementById('attendanceModal').classList.remove('hidden');
}

function closeAttendanceModal() {
    document.getElementById('attendanceModal').classList.add('hidden');
}

function editAttendance(recordId) {
    alert('Edit functionality for record ID: ' + recordId);
    // Implement edit functionality
}

function viewDetails(recordId) {
    alert('View details for record ID: ' + recordId);
    // Implement view details functionality
}

// Search and Filter functionality
document.getElementById('searchAttendance').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    filterAttendance();
});

document.getElementById('statusFilter').addEventListener('change', filterAttendance);

function filterAttendance() {
    const searchTerm = document.getElementById('searchAttendance').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const employeeName = row.cells[0].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        const matchesSearch = employeeName.includes(searchTerm);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

// Attendance Trend Chart
const trendCtx = document.getElementById('attendanceTrendChart');
if (trendCtx) {
    // Sample data - in real application, fetch from database
    const dates = [];
    const presentData = [];
    const attendanceRate = [];
    
    for (let i = 29; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        dates.push(date.getDate() + '/' + (date.getMonth() + 1));
        presentData.push(Math.floor(Math.random() * 20) + 15); // Random data
        attendanceRate.push(Math.floor(Math.random() * 30) + 70); // Random data
    }
    
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Present Staff',
                    data: presentData,
                    borderColor: '#00ADB5',
                    backgroundColor: 'rgba(0,173,181,0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Attendance Rate %',
                    data: attendanceRate,
                    borderColor: '#8B5CF6',
                    borderWidth: 2,
                    tension: 0.4,
                    borderDash: [5, 5],
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Staff'
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Attendance Rate %'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: '#94a3b8'
                    },
                    min: 50,
                    max: 100
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
</script>
</body>
</html>