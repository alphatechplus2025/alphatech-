<?php
session_start();
include('config.php');

// ✅ Enhanced Security: Only Staff Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff' || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

// ✅ Session security regeneration
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['user_name']);

// ✅ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Fetch Staff Data
try {
    // Staff Profile
    $profile_query = $conn->prepare("
        SELECT sp.*, cs.company_id, cs.email, d.dept_name 
        FROM staff_profiles sp
        JOIN company_staffs cs ON sp.staff_id = cs.id
        LEFT JOIN departments d ON sp.department_id = d.id
        WHERE sp.staff_id = ?
    ");
    $profile_query->bind_param("i", $user_id);
    $profile_query->execute();
    $profile = $profile_query->get_result()->fetch_assoc();
    $profile_query->close();

    // Today's Attendance
    $today = date('Y-m-d');
    $attendance_query = $conn->prepare("
        SELECT * FROM attendance 
        WHERE staff_id = ? AND work_date = ?
    ");
    $attendance_query->bind_param("is", $user_id, $today);
    $attendance_query->execute();
    $attendance = $attendance_query->get_result()->fetch_assoc();
    $attendance_query->close();

    // Task Statistics
    $task_stats_query = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN deadline = CURDATE() THEN 1 ELSE 0 END) as due_today
        FROM tasks 
        WHERE assigned_to = ?
    ");
    $task_stats_query->bind_param("i", $user_id);
    $task_stats_query->execute();
    $task_stats = $task_stats_query->get_result()->fetch_assoc();
    $task_stats_query->close();

    // Assigned Tasks with Project Details
    $tasks_query = $conn->prepare("
        SELECT t.*, p.project_name, p.status as project_status,
               cs.full_name as assigned_by_name,
               DATEDIFF(t.deadline, CURDATE()) as days_remaining
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        JOIN company_staffs cs ON t.assigned_by = cs.id
        WHERE t.assigned_to = ?
        ORDER BY 
            CASE 
                WHEN t.status = 'pending' THEN 1
                WHEN t.status = 'in-progress' THEN 2
                ELSE 3
            END,
            t.deadline ASC
    ");
    $tasks_query->bind_param("i", $user_id);
    $tasks_query->execute();
    $tasks = $tasks_query->get_result();
    $tasks_query->close();

    // Notifications
    $notifications_query = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications_query->bind_param("i", $user_id);
    $notifications_query->execute();
    $notifications = $notifications_query->get_result();
    $notifications_query->close();

    // Recent Messages
    $messages_query = $conn->prepare("
        SELECT cm.*, cs.full_name as sender_name, p.project_name
        FROM chat_messages cm
        JOIN company_staffs cs ON cm.sender_id = cs.id
        LEFT JOIN projects p ON cm.project_id = p.id
        WHERE cm.receiver_id = ? OR (cm.receiver_id IS NULL AND cm.project_id IN (
            SELECT project_id FROM tasks WHERE assigned_to = ?
        ))
        ORDER BY cm.sent_at DESC
        LIMIT 10
    ");
    $messages_query->bind_param("ii", $user_id, $user_id);
    $messages_query->execute();
    $recent_messages = $messages_query->get_result();
    $messages_query->close();

} catch (Exception $e) {
    error_log("Database error in staff dashboard: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - ALPHA TECH</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #00ADB5;
    --primary-dark: #00969c;
    --dark-bg: #0f172a;
    --card-bg: #1e293b;
    --sidebar-width: 260px;
}
body { 
    font-family: 'Inter', sans-serif; 
    background: var(--dark-bg); 
    color: #e2e8f0; 
}
.card { 
    background: var(--card-bg); 
    border: 1px solid #334155; 
    border-radius: 12px; 
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
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-pending { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
.status-in-progress { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.status-completed { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.priority-high { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.priority-medium { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.priority-low { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.progress-bar {
    background: #334155;
    border-radius: 10px;
    overflow: hidden;
    height: 8px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #00ADB5, #00FFF5);
    transition: width 0.3s ease;
}
</style>
</head>
<body class="min-h-screen flex">

<!-- ✅ Sidebar -->
<aside class="w-64 bg-[#1e293b] border-r border-[#334155] p-6 flex flex-col justify-between">
    <div>
        <div class="flex items-center mb-10">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center mr-3">
                <i class="fas fa-rocket text-white"></i>
            </div>
            <h1 class="text-xl font-bold gradient-text">ALPHA TECH</h1>
        </div>
        
        <nav class="space-y-1">
            <a href="#" class="nav-link active" onclick="showTab('overview')">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('attendance')">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('tasks')">
                <i class="fas fa-tasks"></i>
                <span>My Tasks</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('chat')">
                <i class="fas fa-comments"></i>
                <span>Team Chat</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('profile')">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('documents')">
                <i class="fas fa-folder"></i>
                <span>Documents</span>
            </a>
        </nav>
    </div>
    
    <div class="border-t border-slate-700 pt-4">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                <i class="fas fa-user text-slate-300"></i>
            </div>
            <div>
                <p class="font-medium"><?= $username ?></p>
                <p class="text-xs text-slate-400">Staff Member</p>
            </div>
        </div>
        <a href="logout.php" class="nav-link text-red-400 hover:text-red-300">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- ✅ Main Content -->
<div class="flex-1 flex flex-col">
    <!-- ✅ Topbar -->
    <header class="bg-[#1e293b] border-b border-[#334155] py-4 px-8 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <h2 class="text-2xl font-bold">Staff Dashboard</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button class="text-slate-300 hover:text-white relative">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                        <?= $notifications->num_rows ?>
                    </span>
                </button>
            </div>
            
            <div class="border-l border-slate-600 pl-4 flex items-center">
                <div class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center mr-2">
                    <i class="fas fa-user text-slate-300 text-sm"></i>
                </div>
                <span class="font-medium"><?= $username ?></span>
            </div>
        </div>
    </header>

    <!-- ✅ Content Area -->
    <main class="flex-1 p-8">
        <!-- ✅ Success/Error Messages -->
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

        <?php if (isset($error_message)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                <?= $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- ✅ Overview Tab -->
        <section id="overview" class="tab-content">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2">Welcome back, <?= $username ?>! 👋</h1>
                <p class="text-slate-400">Here's your work overview for today.</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm">Total Tasks</p>
                            <h3 class="text-3xl font-bold mt-2"><?= $task_stats['total_tasks'] ?? 0 ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <i class="fas fa-tasks text-blue-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4">
                        <?= $task_stats['completed_tasks'] ?? 0 ?> completed • <?= $task_stats['pending_tasks'] ?? 0 ?> pending
                    </p>
                </div>

                <div class="card p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm">Today's Attendance</p>
                            <h3 class="text-3xl font-bold mt-2">
                                <?= $attendance ? ucfirst($attendance['status']) : 'Not Marked' ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                            <i class="fas fa-calendar-check text-green-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4">
                        <?= $attendance ? 'Punched in: ' . date('H:i', strtotime($attendance['punch_in'])) : 'Click to mark attendance' ?>
                    </p>
                </div>

                <div class="card p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm">Due Today</p>
                            <h3 class="text-3xl font-bold mt-2"><?= $task_stats['due_today'] ?? 0 ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <i class="fas fa-clock text-amber-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4">Tasks due today</p>
                </div>

                <div class="card p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm">In Progress</p>
                            <h3 class="text-3xl font-bold mt-2"><?= $task_stats['in_progress_tasks'] ?? 0 ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                            <i class="fas fa-spinner text-cyan-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4">Active tasks</p>
                </div>
            </div>

            <!-- Recent Tasks & Notifications -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Tasks -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold">Recent Tasks</h3>
                        <a href="#" onclick="showTab('tasks')" class="text-cyan-400 hover:text-cyan-300 text-sm">View All</a>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($tasks->num_rows > 0): ?>
                            <?php 
                            $tasks->data_seek(0);
                            $count = 0;
                            while ($task = $tasks->fetch_assoc() && $count < 5): 
                                $count++;
                            ?>
                                <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-cyan-400"><?= htmlspecialchars($task['title']) ?></h4>
                                        <span class="status-badge status-<?= $task['status'] ?>">
                                            <?= ucfirst($task['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-slate-400 text-sm mb-2"><?= htmlspecialchars($task['project_name']) ?></p>
                                    
                                    <div class="flex justify-between items-center text-sm text-slate-400">
                                        <span>Due: <?= date('M d', strtotime($task['deadline'])) ?></span>
                                        <span class="<?= $task['days_remaining'] < 0 ? 'text-red-400' : ($task['days_remaining'] < 3 ? 'text-amber-400' : 'text-green-400') ?>">
                                            <?= $task['days_remaining'] < 0 ? abs($task['days_remaining']) . ' days overdue' : $task['days_remaining'] . ' days left' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="progress-bar mt-2">
                                        <div class="progress-fill" style="width: <?= $task['progress'] ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                                        <span>Progress</span>
                                        <span><?= $task['progress'] ?>%</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i class="fas fa-tasks text-4xl mb-3 opacity-50"></i>
                                <p>No tasks assigned yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications & Messages -->
                <div class="space-y-6">
                    <!-- Notifications -->
                    <div class="card p-6">
                        <h3 class="text-xl font-semibold mb-6">Recent Notifications</h3>
                        
                        <div class="space-y-4">
                            <?php if ($notifications->num_rows > 0): ?>
                                <?php while ($notification = $notifications->fetch_assoc()): ?>
                                    <div class="flex items-start space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                        <div class="w-8 h-8 rounded-full bg-cyan-500/20 flex items-center justify-center mt-1">
                                            <i class="fas fa-bell text-cyan-400 text-sm"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                                            <p class="text-xs text-slate-400 mt-1">
                                                <?= date('M d, H:i', strtotime($notification['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-slate-400 text-center py-4">No notifications</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card p-6">
                        <h3 class="text-xl font-semibold mb-6">Quick Actions</h3>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <button onclick="showTab('attendance')" 
                                    class="p-3 border border-slate-700 rounded-lg hover:border-green-500 hover:bg-green-500/5 transition-all text-center">
                                <i class="fas fa-fingerprint text-green-400 text-xl mb-2"></i>
                                <p class="text-sm font-medium">Mark Attendance</p>
                            </button>
                            
                            <button onclick="showTab('tasks')" 
                                    class="p-3 border border-slate-700 rounded-lg hover:border-blue-500 hover:bg-blue-500/5 transition-all text-center">
                                <i class="fas fa-tasks text-blue-400 text-xl mb-2"></i>
                                <p class="text-sm font-medium">Update Tasks</p>
                            </button>
                            
                            <button onclick="showTab('chat')" 
                                    class="p-3 border border-slate-700 rounded-lg hover:border-purple-500 hover:bg-purple-500/5 transition-all text-center">
                                <i class="fas fa-comments text-purple-400 text-xl mb-2"></i>
                                <p class="text-sm font-medium">Team Chat</p>
                            </button>
                            
                            <button onclick="showTab('profile')" 
                                    class="p-3 border border-slate-700 rounded-lg hover:border-cyan-500 hover:bg-cyan-500/5 transition-all text-center">
                                <i class="fas fa-user text-cyan-400 text-xl mb-2"></i>
                                <p class="text-sm font-medium">My Profile</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ✅ Attendance Tab -->
        <section id="attendance" class="tab-content hidden">
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Attendance Management</h3>
                    <span class="text-sm text-slate-400">Today: <?= date('F d, Y') ?></span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Punch In/Out -->
                    <div>
                        <h4 class="font-semibold mb-4">Daily Attendance</h4>
                        
                        <?php if (!$attendance): ?>
                            <div class="text-center p-8 border-2 border-dashed border-slate-600 rounded-lg">
                                <i class="fas fa-fingerprint text-4xl text-slate-400 mb-4"></i>
                                <p class="text-slate-400 mb-4">You haven't punched in today</p>
                                <form method="POST" action="staff_punch.php">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" name="action" value="punch_in" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                        <i class="fas fa-sign-in-alt mr-2"></i>Punch In
                                    </button>
                                </form>
                            </div>
                        <?php elseif (!$attendance['punch_out']): ?>
                            <div class="text-center p-8 border-2 border-dashed border-green-500 rounded-lg">
                                <i class="fas fa-clock text-4xl text-green-400 mb-4"></i>
                                <p class="text-green-400 mb-2">You punched in at</p>
                                <p class="text-2xl font-bold text-green-400 mb-4">
                                    <?= date('h:i A', strtotime($attendance['punch_in'])) ?>
                                </p>
                                <form method="POST" action="staff_punch.php">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" name="action" value="punch_out" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Punch Out
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-8 border-2 border-dashed border-blue-500 rounded-lg">
                                <i class="fas fa-check-circle text-4xl text-blue-400 mb-4"></i>
                                <p class="text-blue-400 mb-2">Attendance completed for today</p>
                                <p class="text-sm text-slate-400">
                                    In: <?= date('h:i A', strtotime($attendance['punch_in'])) ?> | 
                                    Out: <?= date('h:i A', strtotime($attendance['punch_out'])) ?>
                                </p>
                                <p class="text-sm text-slate-400 mt-2">
                                    Work hours: <?= $attendance['work_hours'] ?> hours
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance History -->
                    <div>
                        <h4 class="font-semibold mb-4">This Week's Attendance</h4>
                        <div class="space-y-3">
                            <?php
                            // Sample weekly data - in real app, fetch from database
                            $week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            $sample_attendance = ['present', 'present', 'late', 'present', 'pending'];
                            ?>
                            
                            <?php foreach ($week_days as $index => $day): ?>
                                <div class="flex justify-between items-center p-3 bg-slate-800/50 rounded-lg">
                                    <span class="text-sm"><?= $day ?></span>
                                    <span class="status-badge status-<?= $sample_attendance[$index] ?>">
                                        <?= ucfirst($sample_attendance[$index]) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-6 p-4 bg-slate-800/30 rounded-lg">
                            <h5 class="font-semibold text-sm mb-2">Attendance Summary</h5>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-green-400">4</p>
                                    <p class="text-xs text-slate-400">Present</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-amber-400">1</p>
                                    <p class="text-xs text-slate-400">Late</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-blue-400">85%</p>
                                    <p class="text-xs text-slate-400">Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ✅ Tasks Tab -->
        <section id="tasks" class="tab-content hidden">
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">My Tasks</h3>
                    <div class="flex space-x-3">
                        <input type="text" id="searchTasks" placeholder="Search tasks..." 
                               class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                        <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-6">
                    <?php if ($tasks->num_rows > 0): ?>
                        <?php $tasks->data_seek(0); ?>
                        <?php while ($task = $tasks->fetch_assoc()): ?>
                            <div class="border border-slate-700 rounded-lg p-6 hover:border-slate-600 transition-colors" 
                                 data-task-status="<?= $task['status'] ?>">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h4 class="text-xl font-semibold text-cyan-400 mb-2">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </h4>
                                        <div class="flex items-center space-x-4 text-sm text-slate-400 mb-2">
                                            <span class="flex items-center">
                                                <i class="fas fa-project-diagram mr-2"></i>
                                                <?= htmlspecialchars($task['project_name']) ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-user-tie mr-2"></i>
                                                Assigned by: <?= htmlspecialchars($task['assigned_by_name']) ?>
                                            </span>
                                            <span class="priority-<?= $task['priority'] ?> px-2 py-1 rounded-full text-xs">
                                                <?= ucfirst($task['priority']) ?> Priority
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <span class="status-badge status-<?= $task['status'] ?>">
                                            <?= ucfirst($task['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="text-slate-300 mb-4"><?= htmlspecialchars($task['description']) ?></p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                    <div>
                                        <div class="flex justify-between text-sm text-slate-400 mb-1">
                                            <span>Deadline</span>
                                            <span class="<?= $task['days_remaining'] < 0 ? 'text-red-400' : ($task['days_remaining'] < 3 ? 'text-amber-400' : 'text-green-400') ?>">
                                                <?= date('M d, Y', strtotime($task['deadline'])) ?>
                                                (<?= $task['days_remaining'] < 0 ? abs($task['days_remaining']) . ' days overdue' : $task['days_remaining'] . ' days left' ?>)
                                            </span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $task['progress'] ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-slate-400 mt-1">
                                            <span>Progress</span>
                                            <span><?= $task['progress'] ?>%</span>
                                        </div>
                                    </div>

                                    <div>
                                        <form method="POST" action="task_update.php" enctype="multipart/form-data" class="space-y-3">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            
                                            <div class="flex space-x-3">
                                                <div class="flex-1">
                                                    <label class="block text-xs text-slate-400 mb-1">Progress %</label>
                                                    <input type="number" name="progress" min="0" max="100" 
                                                           value="<?= $task['progress'] ?>" 
                                                           class="w-full bg-slate-800 border border-slate-600 rounded px-3 py-2 text-white text-sm">
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-xs text-slate-400 mb-1">Status</label>
                                                    <select name="status" class="w-full bg-slate-800 border border-slate-600 rounded px-3 py-2 text-white text-sm">
                                                        <option value="pending" <?= $task['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="in-progress" <?= $task['status'] == 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Remarks</label>
                                                <textarea name="remarks" rows="2" placeholder="Add your remarks..." 
                                                          class="w-full bg-slate-800 border border-slate-600 rounded px-3 py-2 text-white text-sm"></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Attachment</label>
                                                <input type="file" name="attachment" 
                                                       class="w-full text-slate-400 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                                            </div>

                                            <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white py-2 rounded text-sm font-semibold transition-colors">
                                                <i class="fas fa-save mr-2"></i>Update Task
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <?php if (!empty($task['updated_at'])): ?>
                                    <p class="text-xs text-slate-500 text-right">
                                        Last updated: <?= date('M d, Y H:i', strtotime($task['updated_at'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-12 text-slate-400">
                            <i class="fas fa-tasks text-4xl mb-3 opacity-50"></i>
                            <p class="text-lg">No tasks assigned yet</p>
                            <p class="text-sm mt-2">Your team leader will assign tasks to you soon</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ✅ Chat Tab -->
        <section id="chat" class="tab-content hidden">
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-6">Team Communication</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Chat Sidebar -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="p-4 bg-slate-800/50 rounded-lg">
                            <h4 class="font-semibold mb-3">Project Chats</h4>
                            <div class="space-y-2">
                                <?php
                                // Sample projects - in real app, fetch from database
                                $sample_projects = ['Mobile App Development', 'Website Redesign', 'AI Integration'];
                                ?>
                                <?php foreach ($sample_projects as $project): ?>
                                    <div class="flex items-center space-x-3 p-2 hover:bg-slate-700/50 rounded cursor-pointer">
                                        <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center">
                                            <i class="fas fa-users text-purple-400 text-sm"></i>
                                        </div>
                                        <span class="text-sm"><?= $project ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-800/50 rounded-lg">
                            <h4 class="font-semibold mb-3">Team Members</h4>
                            <div class="space-y-2">
                                <?php
                                // Sample team - in real app, fetch from database
                                $sample_team = ['John Smith (TL)', 'Sarah Johnson', 'Mike Chen'];
                                ?>
                                <?php foreach ($sample_team as $member): ?>
                                    <div class="flex items-center space-x-3 p-2 hover:bg-slate-700/50 rounded cursor-pointer">
                                        <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center relative">
                                            <i class="fas fa-user text-green-400 text-sm"></i>
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-green-400 rounded-full"></span>
                                        </div>
                                        <span class="text-sm"><?= $member ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="lg:col-span-3">
                        <div class="border border-slate-700 rounded-lg p-4 h-96 overflow-y-auto mb-4">
                            <!-- Chat messages will be loaded here -->
                            <div class="text-center py-16 text-slate-400">
                                <i class="fas fa-comments text-4xl mb-3 opacity-50"></i>
                                <p>Select a chat to start messaging</p>
                            </div>
                        </div>

                        <form method="POST" action="send_message.php" class="flex space-x-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="text" name="message" placeholder="Type your message..." 
                                   class="flex-1 bg-slate-800 border border-slate-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-cyan-500"
                                   required>
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- ✅ Profile Tab -->
        <section id="profile" class="tab-content hidden">
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-6">My Profile</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Profile Information -->
                    <div class="lg:col-span-2">
                        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Full Name</label>
                                    <input type="text" value="<?= htmlspecialchars($username) ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Company ID</label>
                                    <input type="text" value="<?= htmlspecialchars($profile['company_id'] ?? '') ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                                    <input type="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Department</label>
                                    <input type="text" value="<?= htmlspecialchars($profile['dept_name'] ?? 'Not assigned') ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Phone</label>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Join Date</label>
                                    <input type="text" value="<?= htmlspecialchars($profile['joined_date'] ?? 'Not available') ?>" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white" disabled>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Address</label>
                                <textarea name="address" rows="3"
                                          class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Bio</label>
                                <textarea name="bio" rows="4" placeholder="Tell us about yourself..."
                                          class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                <i class="fas fa-save mr-2"></i>Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Profile Picture & Stats -->
                    <div class="space-y-6">
                        <!-- Profile Picture -->
                        <div class="text-center">
                            <div class="relative inline-block">
                                <img src="uploads/<?= htmlspecialchars($profile['profile_photo'] ?? 'default.png') ?>" 
                                     class="w-32 h-32 rounded-full border-4 border-cyan-500/30 mb-4">
                                <label for="profile_photo" class="cursor-pointer">
                                    <div class="absolute bottom-2 right-2 bg-cyan-600 rounded-full p-2 hover:bg-cyan-700 transition-colors">
                                        <i class="fas fa-camera text-white text-sm"></i>
                                    </div>
                                </label>
                                <input type="file" id="profile_photo" name="profile_photo" class="hidden" 
                                       onchange="document.getElementById('profileForm').submit()">
                            </div>
                            <h4 class="font-semibold text-lg"><?= $username ?></h4>
                            <p class="text-slate-400 text-sm">Staff Member</p>
                        </div>

                        <!-- Quick Stats -->
                        <div class="p-4 bg-slate-800/50 rounded-lg">
                            <h5 class="font-semibold mb-4">Performance Summary</h5>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-400">Task Completion</span>
                                    <span class="font-semibold text-green-400">
                                        <?= $task_stats['total_tasks'] > 0 ? round(($task_stats['completed_tasks'] / $task_stats['total_tasks']) * 100) : 0 ?>%
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-400">Attendance Rate</span>
                                    <span class="font-semibold text-blue-400">92%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-400">On-time Delivery</span>
                                    <span class="font-semibold text-amber-400">88%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="p-4 bg-slate-800/50 rounded-lg">
                            <h5 class="font-semibold mb-3">Change Password</h5>
                            <form action="change_password.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="space-y-3">
                                    <input type="password" name="current_password" placeholder="Current Password" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm" required>
                                    <input type="password" name="new_password" placeholder="New Password" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm" required>
                                    <input type="password" name="confirm_password" placeholder="Confirm Password" 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm" required>
                                    <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-white py-2 rounded text-sm transition-colors">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ✅ Documents Tab -->
        <section id="documents" class="tab-content hidden">
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-6">My Documents</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Upload Document -->
                    <div>
                        <h4 class="font-semibold mb-4">Upload Document</h4>
                        <form action="upload_document.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Document Title</label>
                                <input type="text" name="title" required 
                                       class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                                <textarea name="description" rows="3"
                                          class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Select File</label>
                                <input type="file" name="document" required 
                                       class="w-full text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                                <p class="text-xs text-slate-400 mt-1">Max size: 10MB • PDF, DOC, XLS, Images</p>
                            </div>
                            
                            <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white py-3 rounded-lg font-semibold transition-colors">
                                <i class="fas fa-cloud-upload-alt mr-2"></i>Upload Document
                            </button>
                        </form>
                    </div>

                    <!-- Document List -->
                    <div class="lg:col-span-2">
                        <h4 class="font-semibold mb-4">My Documents</h4>
                        <div class="space-y-4">
                            <!-- Sample documents - in real app, fetch from database -->
                            <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg bg-red-500/10 flex items-center justify-center">
                                            <i class="fas fa-file-pdf text-red-400"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-semibold">Project Report.pdf</h5>
                                            <p class="text-sm text-slate-400">2.4 MB • Uploaded Jan 15, 2024</p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="text-green-400 hover:text-green-300 p-2">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="text-red-400 hover:text-red-300 p-2">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                            <i class="fas fa-file-word text-blue-400"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-semibold">Meeting Notes.docx</h5>
                                            <p class="text-sm text-slate-400">1.1 MB • Uploaded Jan 14, 2024</p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="text-green-400 hover:text-green-300 p-2">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="text-red-400 hover:text-red-300 p-2">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
// Tab navigation
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.remove('hidden');
    
    // Update active nav link
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
}

// Task search and filter
document.getElementById('searchTasks')?.addEventListener('input', filterTasks);
document.getElementById('statusFilter')?.addEventListener('change', filterTasks);

function filterTasks() {
    const searchTerm = document.getElementById('searchTasks').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const tasks = document.querySelectorAll('#tasks .border-slate-700');
    
    tasks.forEach(task => {
        const taskTitle = task.querySelector('h4').textContent.toLowerCase();
        const taskStatus = task.getAttribute('data-task-status');
        const matchesSearch = taskTitle.includes(searchTerm);
        const matchesStatus = !statusFilter || taskStatus === statusFilter;
        
        task.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
    });
}

// Auto-refresh notifications every 30 seconds
setInterval(() => {
    // In a real application, you would fetch new notifications via AJAX
    console.log('Refreshing notifications...');
}, 30000);

// Initialize with overview tab
showTab('overview');
</script>
</body>
</html>