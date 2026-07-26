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
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$leader_id = $_SESSION['user_id'];
$leader_name = htmlspecialchars($_SESSION['user_name']);

// ✅ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Fetch Team Leader's Projects
try {
    // Project Statistics
    $stats_query = $conn->prepare("
        SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
            SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning_projects
        FROM projects 
        WHERE manager_id = ?
    ");
    $stats_query->bind_param("i", $leader_id);
    $stats_query->execute();
    $project_stats = $stats_query->get_result()->fetch_assoc();
    $stats_query->close();

    // Fetch Projects with Progress
    $projects_query = $conn->prepare("
        SELECT 
            p.id, p.project_name, p.description, p.status, p.start_date, p.end_date,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            AVG(t.progress) as avg_progress,
            DATEDIFF(p.end_date, CURDATE()) as days_remaining
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.manager_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $projects_query->bind_param("i", $leader_id);
    $projects_query->execute();
    $projects = $projects_query->get_result();
    $projects_query->close();

    // Fetch team members for task assignment
    $team_query = $conn->prepare("
        SELECT id, full_name, company_id 
        FROM company_staffs 
        WHERE team_leader_id = ? AND status = 'active'
    ");
    $team_query->bind_param("i", $leader_id);
    $team_query->execute();
    $team_members = $team_query->get_result();
    $team_query->close();

} catch (Exception $e) {
    error_log("Database error in projects: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects | Alpha Tech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #00ADB5;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--dark-bg); color: #fff; }
        .card { background: var(--card-bg); border: 1px solid #334155; border-radius: 12px; }
        .gradient-text { background: linear-gradient(90deg, #00ADB5, #00FFF5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-planning { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-completed { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
        .status-testing { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .progress-bar { background: #334155; border-radius: 10px; overflow: hidden; height: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #00ADB5, #00FFF5); transition: width 0.3s ease; }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- ✅ Sidebar -->
    <?php include 'team_leader_sidebar.php'; ?>

    <!-- ✅ Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- ✅ Topbar -->
        <header class="bg-[#1e293b] border-b border-[#334155] py-4 px-8 flex justify-between items-center sticky top-0 z-50">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold gradient-text">My Projects</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300">Welcome, <strong class="text-cyan-300"><?php echo $leader_name; ?></strong></span>
            </div>
        </header>

        <!-- ✅ Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Project Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-project-diagram text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Total Projects</h2>
                    <p class="text-3xl font-bold mt-2 text-blue-300"><?php echo $project_stats['total_projects'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-play-circle text-2xl text-green-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Active</h2>
                    <p class="text-3xl font-bold mt-2 text-green-300"><?php echo $project_stats['active_projects'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-2xl text-purple-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Completed</h2>
                    <p class="text-3xl font-bold mt-2 text-purple-300"><?php echo $project_stats['completed_projects'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-clock text-2xl text-amber-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Planning</h2>
                    <p class="text-3xl font-bold mt-2 text-amber-300"><?php echo $project_stats['planning_projects'] ?? 0; ?></p>
                </div>
            </div>

            <!-- ✅ Projects List -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">All Projects</h3>
                    <div class="flex space-x-3">
                        <input type="text" id="searchProjects" placeholder="Search projects..." 
                               class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                        <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="planning">Planning</option>
                            <option value="testing">Testing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php if ($projects->num_rows > 0): ?>
                        <?php while ($project = $projects->fetch_assoc()): 
                            $progress = $project['avg_progress'] ?? 0;
                            $completion_rate = $project['total_tasks'] > 0 ? 
                                round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                        ?>
                            <div class="border border-slate-700 rounded-lg p-6 hover:border-slate-600 transition-colors">
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="font-semibold text-cyan-400 text-lg"><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                    <span class="status-badge status-<?php echo $project['status']; ?>">
                                        <?php echo ucfirst($project['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-slate-400 text-sm mb-4 line-clamp-2">
                                    <?php echo htmlspecialchars($project['description'] ?? 'No description available'); ?>
                                </p>
                                
                                <div class="space-y-3 mb-4">
                                    <div class="flex justify-between text-sm text-slate-400">
                                        <span>Timeline:</span>
                                        <span><?php echo date('M d, Y', strtotime($project['start_date'])); ?> - <?php echo date('M d, Y', strtotime($project['end_date'])); ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between text-sm text-slate-400">
                                        <span>Tasks:</span>
                                        <span><?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> completed</span>
                                    </div>
                                    
                                    <?php if ($project['days_remaining'] !== null): ?>
                                        <div class="flex justify-between text-sm <?php echo $project['days_remaining'] < 0 ? 'text-red-400' : ($project['days_remaining'] < 7 ? 'text-amber-400' : 'text-green-400'); ?>">
                                            <span>Days remaining:</span>
                                            <span><?php echo $project['days_remaining'] < 0 ? abs($project['days_remaining']) . ' days overdue' : $project['days_remaining'] . ' days left'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Progress Bars -->
                                <div class="space-y-2">
                                    <div class="flex justify-between text-xs text-slate-400">
                                        <span>Task Completion</span>
                                        <span><?php echo $completion_rate; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                    
                                    <div class="flex justify-between text-xs text-slate-400">
                                        <span>Overall Progress</span>
                                        <span><?php echo round($progress); ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between mt-4 pt-4 border-t border-slate-700">
                                    <button onclick="viewProject(<?php echo $project['id']; ?>)" 
                                            class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center space-x-1">
                                        <i class="fas fa-eye"></i>
                                        <span>View Details</span>
                                    </button>
                                    <button onclick="assignTaskToProject(<?php echo $project['id']; ?>)" 
                                            class="text-green-400 hover:text-green-300 text-sm flex items-center space-x-1">
                                        <i class="fas fa-tasks"></i>
                                        <span>Assign Task</span>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-3 text-center py-12 text-slate-400">
                            <i class="fas fa-project-diagram text-4xl mb-3 opacity-50"></i>
                            <p class="text-lg">No projects assigned to you yet.</p>
                            <p class="text-sm mt-2">Contact administrator to get assigned to projects.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search and Filter functionality
        document.getElementById('searchProjects').addEventListener('input', filterProjects);
        document.getElementById('statusFilter').addEventListener('change', filterProjects);

        function filterProjects() {
            const searchTerm = document.getElementById('searchProjects').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const projects = document.querySelectorAll('.border-slate-700');
            
            projects.forEach(project => {
                const projectName = project.querySelector('h4').textContent.toLowerCase();
                const status = project.querySelector('.status-badge').textContent.toLowerCase();
                const matchesSearch = projectName.includes(searchTerm);
                const matchesStatus = !statusFilter || status.includes(statusFilter);
                
                project.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
            });
        }

        function viewProject(projectId) {
            window.location.href = `project_details.php?id=${projectId}`;
        }

        function assignTaskToProject(projectId) {
            window.location.href = `assign_task.php?project=${projectId}`;
        }
    </script>
</body>
</html>