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

// ✅ Handle Task Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: assign_task.php");
        exit;
    }

    if (isset($_POST['assign_task'])) {
        $project_id = intval($_POST['project_id']);
        $assigned_to = intval($_POST['assigned_to']);
        $title = $conn->real_escape_string(trim($_POST['title']));
        $description = $conn->real_escape_string(trim($_POST['description']));
        $deadline = $conn->real_escape_string($_POST['deadline']);
        $priority = $conn->real_escape_string($_POST['priority']);
        
        // Validate inputs
        if (empty($title) || empty($assigned_to) || empty($project_id)) {
            $_SESSION['error'] = "Please fill in all required fields.";
        } else {
            $assign_query = $conn->prepare("
                INSERT INTO tasks (project_id, assigned_to, assigned_by, title, description, deadline, priority, status, progress) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0)
            ");
            $assign_query->bind_param("iiissss", $project_id, $assigned_to, $leader_id, $title, $description, $deadline, $priority);
            
            if ($assign_query->execute()) {
                $_SESSION['success'] = "Task assigned successfully!";
                
                // ✅ Log activity (optional)
                $log_query = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type) 
                    VALUES (?, 'New task assigned: {$title}', 'task')
                ");
                $log_query->bind_param("i", $assigned_to);
                $log_query->execute();
                $log_query->close();
                
            } else {
                $_SESSION['error'] = "Error assigning task: " . $conn->error;
            }
            $assign_query->close();
        }
    }
    
    header("Location: assign_task.php");
    exit;
}

// ✅ Fetch Data for Forms
try {
    // Fetch team members
    $team_query = $conn->prepare("
        SELECT id, full_name, company_id 
        FROM company_staffs 
        WHERE team_leader_id = ? AND status = 'active' AND role = 'staff'
        ORDER BY full_name
    ");
    $team_query->bind_param("i", $leader_id);
    $team_query->execute();
    $team_members = $team_query->get_result();
    $team_query->close();

    // Fetch projects managed by this leader
    $projects_query = $conn->prepare("
        SELECT p.id, p.project_name, p.status 
        FROM projects p 
        WHERE p.manager_id = ? AND p.status IN ('active', 'planning')
        ORDER BY p.project_name
    ");
    $projects_query->bind_param("i", $leader_id);
    $projects_query->execute();
    $projects = $projects_query->get_result();
    $projects_query->close();

    // Fetch recent tasks for preview
    $recent_tasks_query = $conn->prepare("
        SELECT t.id, t.title, t.status, t.deadline, t.priority,
               cs.full_name as assigned_to, p.project_name
        FROM tasks t
        JOIN company_staffs cs ON t.assigned_to = cs.id
        JOIN projects p ON t.project_id = p.id
        WHERE t.assigned_by = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $recent_tasks_query->bind_param("i", $leader_id);
    $recent_tasks_query->execute();
    $recent_tasks = $recent_tasks_query->get_result();
    $recent_tasks_query->close();

} catch (Exception $e) {
    error_log("Database error in assign_task: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Task | Alpha Tech</title>
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
        .priority-high { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .priority-medium { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .priority-low { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
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
                <h1 class="text-2xl font-bold gradient-text">Assign New Task</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300">Welcome, <strong class="text-cyan-300"><?php echo $leader_name; ?></strong></span>
            </div>
        </header>

        <!-- ✅ Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- ✅ Task Assignment Form -->
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-6">Task Details</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="space-y-6">
                            <!-- Project Selection -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Project <span class="text-red-400">*</span>
                                </label>
                                <select name="project_id" required 
                                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                    <option value="">Select Project</option>
                                    <?php if ($projects->num_rows > 0): ?>
                                        <?php while ($project = $projects->fetch_assoc()): ?>
                                            <option value="<?php echo $project['id']; ?>">
                                                <?php echo htmlspecialchars($project['project_name']); ?> (<?php echo ucfirst($project['status']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No projects available</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Assign To -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Assign To <span class="text-red-400">*</span>
                                </label>
                                <select name="assigned_to" required 
                                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                    <option value="">Select Team Member</option>
                                    <?php if ($team_members->num_rows > 0): ?>
                                        <?php while ($member = $team_members->fetch_assoc()): ?>
                                            <option value="<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo $member['company_id']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No team members available</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Task Title -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Task Title <span class="text-red-400">*</span>
                                </label>
                                <input type="text" name="title" required maxlength="150"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                                       placeholder="Enter task title">
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                                <textarea name="description" rows="4" maxlength="500"
                                          class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                                          placeholder="Describe the task details..."></textarea>
                                <p class="text-xs text-slate-400 mt-1">Max 500 characters</p>
                            </div>

                            <!-- Deadline & Priority -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">
                                        Deadline <span class="text-red-400">*</span>
                                    </label>
                                    <input type="date" name="deadline" required min="<?php echo date('Y-m-d'); ?>"
                                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Priority</label>
                                    <select name="priority" 
                                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="reset" 
                                    class="px-6 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                                Clear Form
                            </button>
                            <button type="submit" name="assign_task" 
                                    class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors flex items-center space-x-2">
                                <i class="fas fa-paper-plane"></i>
                                <span>Assign Task</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ✅ Recent Tasks & Team Info -->
                <div class="space-y-6">
                    <!-- Recent Tasks -->
                    <div class="card p-6">
                        <h3 class="text-xl font-semibold mb-6">Recently Assigned Tasks</h3>
                        
                        <div class="space-y-4">
                            <?php if ($recent_tasks->num_rows > 0): ?>
                                <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                                    <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-semibold text-green-400"><?php echo htmlspecialchars($task['title']); ?></h4>
                                            <span class="priority-<?php echo $task['priority']; ?> text-xs px-2 py-1 rounded-full">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="text-sm text-slate-400 space-y-1">
                                            <p>Project: <?php echo htmlspecialchars($task['project_name']); ?></p>
                                            <p>Assigned to: <?php echo htmlspecialchars($task['assigned_to']); ?></p>
                                            <p>Due: <?php echo date('M d, Y', strtotime($task['deadline'])); ?></p>
                                        </div>
                                        
                                        <div class="flex justify-between items-center text-xs text-slate-500 mt-2">
                                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                                <?php echo ucfirst($task['status']); ?>
                                            </span>
                                            <span>ID: #<?php echo $task['id']; ?></span>
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

                    <!-- Quick Team Stats -->
                    <div class="card p-6">
                        <h3 class="text-xl font-semibold mb-4">Team Availability</h3>
                        <?php if ($team_members->num_rows > 0): ?>
                            <div class="space-y-3">
                                <?php 
                                $team_members->data_seek(0); // Reset pointer
                                while ($member = $team_members->fetch_assoc()): 
                                    // Get current task count for this member
                                    $task_count_query = $conn->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE assigned_to = ? AND status = 'pending'");
                                    $task_count_query->bind_param("i", $member['id']);
                                    $task_count_query->execute();
                                    $task_count = $task_count_query->get_result()->fetch_assoc()['task_count'];
                                    $task_count_query->close();
                                    
                                    $availability = $task_count < 3 ? 'High' : ($task_count < 6 ? 'Medium' : 'Low');
                                    $availability_color = $task_count < 3 ? 'text-green-400' : ($task_count < 6 ? 'text-amber-400' : 'text-red-400');
                                ?>
                                    <div class="flex justify-between items-center p-2 hover:bg-slate-700/50 rounded">
                                        <span class="text-sm"><?php echo htmlspecialchars($member['full_name']); ?></span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs text-slate-400"><?php echo $task_count; ?> tasks</span>
                                            <span class="text-xs <?php echo $availability_color; ?>"><?php echo $availability; ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-slate-400 text-center py-4">No team members available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const project = document.querySelector('select[name="project_id"]').value;
            const assignee = document.querySelector('select[name="assigned_to"]').value;
            const deadline = document.querySelector('input[name="deadline"]').value;
            
            if (!title || !project || !assignee || !deadline) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Check if deadline is in the future
            const today = new Date().toISOString().split('T')[0];
            if (deadline < today) {
                e.preventDefault();
                alert('Deadline must be in the future.');
                return false;
            }
        });

        // Character counter for description
        const descriptionField = document.querySelector('textarea[name="description"]');
        const charCounter = document.createElement('p');
        charCounter.className = 'text-xs text-slate-400 text-right mt-1';
        descriptionField.parentNode.appendChild(charCounter);

        descriptionField.addEventListener('input', function() {
            const remaining = 500 - this.value.length;
            charCounter.textContent = `${remaining} characters remaining`;
            
            if (remaining < 50) {
                charCounter.className = 'text-xs text-amber-400 text-right mt-1';
            } else if (remaining < 0) {
                charCounter.className = 'text-xs text-red-400 text-right mt-1';
            } else {
                charCounter.className = 'text-xs text-slate-400 text-right mt-1';
            }
        });

        // Set minimum date for deadline to today
        const deadlineField = document.querySelector('input[name="deadline"]');
        deadlineField.min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>