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

// ✅ Handle Team Member Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: view_team.php");
        exit;
    }

    if (isset($_POST['update_member'])) {
        $member_id = intval($_POST['member_id']);
        $department_id = intval($_POST['department_id']);
        
        $update_query = $conn->prepare("UPDATE staff_profiles SET department_id = ? WHERE staff_id = ?");
        $update_query->bind_param("ii", $department_id, $member_id);
        
        if ($update_query->execute()) {
            $_SESSION['success'] = "Team member updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating team member.";
        }
        $update_query->close();
    }
    
    header("Location: view_team.php");
    exit;
}

// ✅ Fetch Team Members with Details
try {
    $team_query = $conn->prepare("
        SELECT 
            cs.id, cs.full_name, cs.email, cs.company_id, cs.status,
            sp.phone, sp.department_id, d.dept_name,
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = cs.id AND t.status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = cs.id AND t.status = 'pending') as pending_tasks,
            (SELECT COUNT(*) FROM attendance a WHERE a.staff_id = cs.id AND a.work_date = CURDATE() AND a.status = 'present') as today_attendance
        FROM company_staffs cs
        LEFT JOIN staff_profiles sp ON cs.id = sp.staff_id
        LEFT JOIN departments d ON sp.department_id = d.id
        WHERE cs.team_leader_id = ? AND cs.role = 'staff'
        ORDER BY cs.full_name
    ");
    $team_query->bind_param("i", $leader_id);
    $team_query->execute();
    $team_members = $team_query->get_result();
    $team_query->close();

    // ✅ Fetch departments for dropdown
    $dept_query = $conn->prepare("SELECT id, dept_name FROM departments WHERE status = 'active'");
    $dept_query->execute();
    $departments = $dept_query->get_result();
    $dept_query->close();

    // ✅ Team Statistics
    $stats_query = $conn->prepare("
        SELECT 
            COUNT(*) as total_members,
            SUM((SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = cs.id AND t.status = 'completed')) as total_completed_tasks,
            AVG((SELECT AVG(progress) FROM tasks t WHERE t.assigned_to = cs.id)) as avg_productivity
        FROM company_staffs cs
        WHERE cs.team_leader_id = ? AND cs.role = 'staff'
    ");
    $stats_query->bind_param("i", $leader_id);
    $stats_query->execute();
    $team_stats = $stats_query->get_result()->fetch_assoc();
    $stats_query->close();

} catch (Exception $e) {
    error_log("Database error in view_team: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team | Alpha Tech</title>
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
        .status-inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
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
                <h1 class="text-2xl font-bold gradient-text">My Team Members</h1>
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

            <!-- ✅ Team Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-2xl text-cyan-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Team Members</h2>
                    <p class="text-3xl font-bold mt-2 text-cyan-300"><?php echo $team_stats['total_members'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-tasks text-2xl text-green-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Completed Tasks</h2>
                    <p class="text-3xl font-bold mt-2 text-green-300"><?php echo $team_stats['total_completed_tasks'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-chart-line text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Avg Productivity</h2>
                    <p class="text-3xl font-bold mt-2 text-blue-300"><?php echo round($team_stats['avg_productivity'] ?? 0); ?>%</p>
                </div>
            </div>

            <!-- ✅ Team Members Table -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Team Members Overview</h3>
                    <div class="flex space-x-3">
                        <input type="text" id="searchTeam" placeholder="Search team members..." 
                               class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                        <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-700">
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Member</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Department</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Tasks</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Today</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Productivity</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Status</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($team_members->num_rows > 0): ?>
                                <?php while ($member = $team_members->fetch_assoc()): 
                                    $total_tasks = $member['completed_tasks'] + $member['pending_tasks'];
                                    $productivity = $total_tasks > 0 ? round(($member['completed_tasks'] / $total_tasks) * 100) : 0;
                                ?>
                                    <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition-colors" data-member='<?php echo htmlspecialchars(json_encode($member), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-slate-300"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></p>
                                                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($member['email']); ?></p>
                                                    <p class="text-xs text-slate-500">ID: <?php echo htmlspecialchars($member['company_id']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="text-sm"><?php echo htmlspecialchars($member['dept_name'] ?? 'Not assigned'); ?></span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="text-sm">
                                                <span class="text-green-400"><?php echo $member['completed_tasks']; ?> done</span>
                                                <span class="text-slate-400"> / </span>
                                                <span class="text-amber-400"><?php echo $member['pending_tasks']; ?> pending</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php if ($member['today_attendance'] > 0): ?>
                                                <span class="text-green-400 text-sm flex items-center">
                                                    <i class="fas fa-check-circle mr-1"></i> Present
                                                </span>
                                            <?php else: ?>
                                                <span class="text-red-400 text-sm flex items-center">
                                                    <i class="fas fa-times-circle mr-1"></i> Absent
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="progress-bar flex-1">
                                                    <div class="progress-fill" style="width: <?php echo $productivity; ?>%"></div>
                                                </div>
                                                <span class="text-xs text-slate-400 w-10"><?php echo $productivity; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="status-badge status-<?php echo $member['status']; ?>">
                                                <?php echo ucfirst($member['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex space-x-2">
                                                <button onclick="editMember(<?php echo $member['id']; ?>)" 
                                                        class="text-blue-400 hover:text-blue-300 transition-colors"
                                                        title="Edit Member">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="viewProfile(<?php echo $member['id']; ?>)" 
                                                        class="text-green-400 hover:text-green-300 transition-colors"
                                                        title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="sendMessage(<?php echo $member['id']; ?>)" 
                                                        class="text-cyan-400 hover:text-cyan-300 transition-colors"
                                                        title="Send Message">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400">
                                        <i class="fas fa-users text-4xl mb-3 opacity-50"></i>
                                        <p>No team members assigned to you yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- ✅ Edit Member Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-slate-800 rounded-xl w-full max-w-md mx-4">
            <div class="flex justify-between items-center p-6 border-b border-slate-700">
                <h3 class="text-xl font-semibold">Edit Team Member</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="member_id" id="edit_member_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Department</label>
                        <select name="department_id" id="edit_department" required 
                                class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                            <option value="">Select Department</option>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-4 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="update_member" 
                            class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                        Update Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search and Filter functionality
        document.getElementById('searchTeam').addEventListener('input', filterTeam);
        document.getElementById('statusFilter').addEventListener('change', filterTeam);

        function filterTeam() {
            const searchTerm = document.getElementById('searchTeam').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const memberData = JSON.parse(row.getAttribute('data-member'));
                const memberName = memberData.full_name.toLowerCase();
                const memberStatus = memberData.status;
                const matchesSearch = memberName.includes(searchTerm);
                const matchesStatus = !statusFilter || memberStatus === statusFilter;
                
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        // Modal functions
        function editMember(memberId) {
            const row = document.querySelector(`tr[data-member*='"id":${memberId}']`);
            if (row) {
                const memberData = JSON.parse(row.getAttribute('data-member'));
                document.getElementById('edit_member_id').value = memberData.id;
                document.getElementById('edit_department').value = memberData.department_id || '';
                document.getElementById('editModal').classList.remove('hidden');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function viewProfile(memberId) {
            window.location.href = `member_profile.php?id=${memberId}`;
        }

        function sendMessage(memberId) {
            window.location.href = `team_chat.php?user=${memberId}`;
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>