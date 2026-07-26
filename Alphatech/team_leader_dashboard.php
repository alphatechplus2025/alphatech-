<?php
session_start();
include 'config.php';

// ✅ Enhanced Security: Only Team Leader Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'team_leader' || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

// ✅ Session security regeneration
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// ✅ CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$leader_id = $_SESSION['user_id'];
$leader_name = htmlspecialchars($_SESSION['user_name']);

// ✅ Initialize counters with prepared statements
$team_count = 0;
$task_count = 0;
$active_projects_count = 0;
$pending_tasks_count = 0;

try {
    // Count team members
    $team_query = $conn->prepare("SELECT COUNT(*) AS total FROM company_staffs WHERE team_leader_id = ? AND status = 'active'");
    $team_query->bind_param("i", $leader_id);
    $team_query->execute();
    $team_result = $team_query->get_result()->fetch_assoc();
    $team_count = $team_result['total'] ?? 0;
    $team_query->close();

    // Count tasks assigned by this leader
    $task_query = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE assigned_by = ?");
    $task_query->bind_param("i", $leader_id);
    $task_query->execute();
    $task_result = $task_query->get_result()->fetch_assoc();
    $task_count = $task_result['total'] ?? 0;
    $task_query->close();

    // Count active projects managed by this leader
    $project_query = $conn->prepare("SELECT COUNT(*) AS total FROM projects p 
                                   JOIN company_staffs cs ON p.manager_id = cs.id 
                                   WHERE cs.id = ? AND p.status = 'active'");
    $project_query->bind_param("i", $leader_id);
    $project_query->execute();
    $project_result = $project_query->get_result()->fetch_assoc();
    $active_projects_count = $project_result['total'] ?? 0;
    $project_query->close();

    // Count pending tasks
    $pending_query = $conn->prepare("SELECT COUNT(*) AS total FROM tasks 
                                   WHERE assigned_by = ? AND status = 'pending'");
    $pending_query->bind_param("i", $leader_id);
    $pending_query->execute();
    $pending_result = $pending_query->get_result()->fetch_assoc();
    $pending_tasks_count = $pending_result['total'] ?? 0;
    $pending_query->close();

    // Fetch recent projects
    $projects_query = $conn->prepare("SELECT p.id, p.project_name, p.status, p.end_date, 
                                    COUNT(t.id) as task_count,
                                    AVG(t.progress) as avg_progress
                                    FROM projects p
                                    LEFT JOIN tasks t ON p.id = t.project_id
                                    WHERE p.manager_id = ?
                                    GROUP BY p.id
                                    ORDER BY p.created_at DESC
                                    LIMIT 5");
    $projects_query->bind_param("i", $leader_id);
    $projects_query->execute();
    $recent_projects = $projects_query->get_result();
    $projects_query->close();

    // Fetch team members
    $team_members_query = $conn->prepare("SELECT id, full_name, email, company_id 
                                        FROM company_staffs 
                                        WHERE team_leader_id = ? AND status = 'active'");
    $team_members_query->bind_param("i", $leader_id);
    $team_members_query->execute();
    $team_members = $team_members_query->get_result();
    $team_members_query->close();

    // Fetch recent tasks
    $recent_tasks_query = $conn->prepare("SELECT t.id, t.title, t.status, t.progress, 
                                        t.deadline, cs.full_name as assigned_to
                                        FROM tasks t
                                        JOIN company_staffs cs ON t.assigned_to = cs.id
                                        WHERE t.assigned_by = ?
                                        ORDER BY t.updated_at DESC
                                        LIMIT 5");
    $recent_tasks_query->bind_param("i", $leader_id);
    $recent_tasks_query->execute();
    $recent_tasks = $recent_tasks_query->get_result();
    $recent_tasks_query->close();

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    // Don't expose database errors to users
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Leader Dashboard | Alpha Tech</title>
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
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--dark-bg); 
            color: #fff; 
        }
        .card { 
            background: var(--card-bg); 
            border: 1px solid #334155; 
            border-radius: 16px; 
            transition: 0.3s; 
        }
        .card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 4px 20px rgba(0, 173, 181, 0.3); 
        }
        .gradient-text { 
            background: linear-gradient(90deg, #00ADB5, #00FFF5); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .btn { 
            background: linear-gradient(135deg, #00ADB5, #00FFF5); 
            color: #000; 
            font-weight: 600; 
            border-radius: 10px; 
            transition: 0.3s; 
        }
        .btn:hover { 
            transform: scale(1.05); 
            box-shadow: 0 0 20px rgba(0,173,181,0.4); 
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-pending { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-completed { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
        .status-testing { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
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
<body class="min-h-screen flex flex-col">

    <!-- ✅ Enhanced Topbar with Security -->
    <header class="bg-[#1e293b] border-b border-[#334155] py-4 px-8 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center">
                <i class="fas fa-rocket text-white"></i>
            </div>
            <h1 class="text-2xl font-bold gradient-text">Alpha Tech</h1>
            <span class="text-gray-400">Team Leader Dashboard</span>
        </div>
        
        <div class="flex items-center space-x-6">
            <div class="flex items-center space-x-2 text-sm">
                <div class="w-8 h-8 rounded-full bg-cyan-500/20 flex items-center justify-center">
                    <i class="fas fa-user-shield text-cyan-400 text-sm"></i>
                </div>
                <div>
                    <span class="text-gray-300">Welcome,</span>
                    <strong class="text-cyan-300"><?php echo $leader_name; ?></strong>
                </div>
            </div>
            
            <div class="relative group">
                <button class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">3</span>
                </button>
            </div>
            
            <a href="logout.php" class="text-red-400 hover:text-red-300 transition-colors flex items-center space-x-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- ✅ Main Content -->
    <div class="flex flex-1">
        <!-- ✅ Sidebar Navigation -->
        <aside class="w-64 bg-[#1e293b] border-r border-[#334155] p-6">
            <nav class="space-y-2">
                <a href="#" class="flex items-center space-x-3 p-3 bg-cyan-500/10 text-cyan-400 rounded-lg border border-cyan-500/20">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="view_team.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-users w-5"></i>
                    <span>My Team</span>
                </a>
                <a href="assign_task.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-tasks w-5"></i>
                    <span>Assign Tasks</span>
                </a>
                <a href="tprojects.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-folder w-5"></i>
                    <span>Projects</span>
                </a>
                <a href="team_chat.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-comments w-5"></i>
                    <span>Team Chat</span>
                </a>
                <a href="tattendance.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-calendar-check w-5"></i>
                    <span>Attendance</span>
                </a>
                <a href="reports.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span>Reports</span>
                </a>
                <a href="documents.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-file-upload w-5"></i>
                    <span>Documents</span>
                </a>
            </nav>
        </aside>

        <!-- ✅ Dashboard Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Welcome Section -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold mb-2">Welcome back, <?php echo $leader_name; ?>! 👋</h2>
                <p class="text-gray-400">Here's what's happening with your team today.</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- ✅ Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-2xl text-cyan-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Team Members</h2>
                    <p class="text-3xl font-bold mt-2 text-cyan-300"><?php echo $team_count; ?></p>
                    <p class="text-sm text-gray-400 mt-2">Active team members</p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-tasks text-2xl text-green-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Total Tasks</h2>
                    <p class="text-3xl font-bold mt-2 text-green-300"><?php echo $task_count; ?></p>
                    <p class="text-sm text-gray-400 mt-2">Tasks assigned</p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-project-diagram text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Active Projects</h2>
                    <p class="text-3xl font-bold mt-2 text-blue-300"><?php echo $active_projects_count; ?></p>
                    <p class="text-sm text-gray-400 mt-2">Projects in progress</p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-clock text-2xl text-amber-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Pending Tasks</h2>
                    <p class="text-3xl font-bold mt-2 text-amber-300"><?php echo $pending_tasks_count; ?></p>
                    <p class="text-sm text-gray-400 mt-2">Awaiting completion</p>
                </div>
            </div>

            <!-- ✅ Projects & Tasks Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- ✅ Assigned Projects Summary -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold">Assigned Projects</h3>
                        <a href="projects.php" class="text-cyan-400 hover:text-cyan-300 text-sm">View All</a>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($recent_projects->num_rows > 0): ?>
                            <?php while ($project = $recent_projects->fetch_assoc()): ?>
                                <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-cyan-400"><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                        <span class="status-badge status-<?php echo $project['status']; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-sm text-gray-400 mb-3">
                                        <span><?php echo $project['task_count']; ?> tasks</span>
                                        <span>Due: <?php echo date('M d, Y', strtotime($project['end_date'])); ?></span>
                                    </div>
                                    
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $project['avg_progress'] ?? 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-xs text-gray-400 mt-1">
                                        <span>Progress</span>
                                        <span><?php echo round($project['avg_progress'] ?? 0); ?>%</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <i class="fas fa-folder-open text-4xl mb-3 opacity-50"></i>
                                <p>No projects assigned yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ✅ Recent Tasks -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold">Recent Tasks</h3>
                        <a href="assign_task.php" class="text-cyan-400 hover:text-cyan-300 text-sm">View All</a>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($recent_tasks->num_rows > 0): ?>
                            <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                                <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-green-400"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <span class="status-badge status-<?php echo $task['status']; ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-sm text-gray-400 mb-3">
                                        <span>Assigned to: <?php echo htmlspecialchars($task['assigned_to']); ?></span>
                                        <span>Due: <?php echo date('M d', strtotime($task['deadline'])); ?></span>
                                    </div>
                                    
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-xs text-gray-400 mt-1">
                                        <span>Completion</span>
                                        <span><?php echo $task['progress']; ?>%</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <i class="fas fa-tasks text-4xl mb-3 opacity-50"></i>
                                <p>No tasks assigned yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ✅ Team Members & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- ✅ Team Members -->
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-6">Team Members</h3>
                    <div class="space-y-4">
                        <?php if ($team_members->num_rows > 0): ?>
                            <?php while ($member = $team_members->fetch_assoc()): ?>
                                <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                    <div class="w-10 h-10 rounded-full bg-cyan-500/20 flex items-center justify-center">
                                        <i class="fas fa-user text-cyan-400"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></p>
                                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($member['company_id']); ?></p>
                                    </div>
                                    <span class="text-xs bg-green-500/20 text-green-400 px-2 py-1 rounded-full">Active</span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <i class="fas fa-users text-4xl mb-3 opacity-50"></i>
                                <p>No team members assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ✅ Quick Actions -->
                <div class="card p-6 lg:col-span-2">
                    <h3 class="text-xl font-semibold mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="assign_task.php" class="p-4 border border-slate-700 rounded-lg hover:border-cyan-500 hover:bg-cyan-500/5 transition-all group">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center group-hover:bg-green-500/20 transition-colors">
                                    <i class="fas fa-tasks text-green-400 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white">Assign New Task</h4>
                                    <p class="text-sm text-gray-400">Create and assign tasks to team</p>
                                </div>
                            </div>
                        </a>

                        <a href="team_chat.php" class="p-4 border border-slate-700 rounded-lg hover:border-blue-500 hover:bg-blue-500/5 transition-all group">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition-colors">
                                    <i class="fas fa-comments text-blue-400 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white">Team Chat</h4>
                                    <p class="text-sm text-gray-400">Communicate with your team</p>
                                </div>
                            </div>
                        </a>

                        <a href="documents.php" class="p-4 border border-slate-700 rounded-lg hover:border-purple-500 hover:bg-purple-500/5 transition-all group">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
                                    <i class="fas fa-file-upload text-purple-400 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white">Upload Documents</h4>
                                    <p class="text-sm text-gray-400">Share files with your team</p>
                                </div>
                            </div>
                        </a>

                        <a href="attendance.php" class="p-4 border border-slate-700 rounded-lg hover:border-amber-500 hover:bg-amber-500/5 transition-all group">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition-colors">
                                    <i class="fas fa-calendar-check text-amber-400 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-white">View Attendance</h4>
                                    <p class="text-sm text-gray-400">Check team attendance</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ✅ Footer -->
    <footer class="bg-[#1e293b] border-t border-[#334155] py-4 px-8 text-center text-gray-400 text-sm">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                © <?php echo date('Y'); ?> Alpha Tech. All rights reserved. | Team Leader Dashboard v2.0
            </div>
            <div class="flex space-x-4 mt-2 md:mt-0">
                <span class="text-green-400"><i class="fas fa-shield-alt"></i> Secure Session</span>
                <span>Last login: <?php echo date('M d, Y H:i'); ?></span>
            </div>
        </div>
    </footer>

    <!-- ✅ CSRF Token for AJAX requests -->
    <script>
        const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";
        
        // Auto-logout after 30 minutes of inactivity
        let inactivityTime = function() {
            let time;
            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            
            function logout() {
                window.location.href = 'logout.php?reason=inactivity';
            }
            
            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, 1800000); // 30 minutes
            }
        };
        inactivityTime();
    </script>
</body>
</html>