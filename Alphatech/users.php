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

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $company_id = $conn->real_escape_string($_POST['company_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role']);
        $team_leader_id = !empty($_POST['team_leader_id']) ? intval($_POST['team_leader_id']) : NULL;
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : NULL;
        
        $sql = "INSERT INTO company_staffs (company_id, full_name, email, password, role, team_leader_id) 
                VALUES ('$company_id', '$full_name', '$email', '$password', '$role', $team_leader_id)";
        
        if ($conn->query($sql)) {
            $user_id = $conn->insert_id;
            
            // Create user profile
            $profile_sql = "INSERT INTO staff_profiles (staff_id, department_id) VALUES ($user_id, $department_id)";
            $conn->query($profile_sql);
            
            $_SESSION['success'] = "User added successfully!";
        } else {
            $_SESSION['error'] = "Error adding user: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $company_id = $conn->real_escape_string($_POST['company_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        $team_leader_id = !empty($_POST['team_leader_id']) ? intval($_POST['team_leader_id']) : NULL;
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE company_staffs SET 
                company_id = '$company_id',
                full_name = '$full_name',
                email = '$email',
                role = '$role',
                team_leader_id = $team_leader_id,
                status = '$status'
                WHERE id = $user_id";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "User updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating user: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        $sql = "DELETE FROM company_staffs WHERE id = $user_id";
        if ($conn->query($sql)) {
            $_SESSION['success'] = "User deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting user: " . $conn->error;
        }
    }
    
    header("Location: users.php");
    exit();
}

// Fetch Users with Details
$users = $conn->query("
    SELECT cs.*, d.dept_name, tl.full_name as team_leader_name,
           (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = cs.id) as task_count,
           (SELECT COUNT(*) FROM attendance a WHERE a.staff_id = cs.id AND a.work_date = CURDATE()) as today_attendance
    FROM company_staffs cs
    LEFT JOIN staff_profiles sp ON cs.id = sp.staff_id
    LEFT JOIN departments d ON sp.department_id = d.id
    LEFT JOIN company_staffs tl ON cs.team_leader_id = tl.id
    WHERE cs.role != 'admin'
    ORDER BY cs.created_at DESC
");

// Fetch Departments for dropdown
$departments = $conn->query("SELECT id, dept_name FROM departments");

// Fetch Team Leaders for dropdown
$teamLeaders = $conn->query("SELECT id, full_name FROM company_staffs WHERE role IN ('manager', 'team_leader') AND status='active'");

// User Statistics
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role != 'admin'")->fetch_assoc()['total'];
$totalManagers = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role='manager'")->fetch_assoc()['total'];
$totalStaff = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role='staff'")->fetch_assoc()['total'];
$totalTeamLeaders = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE role='team_leader'")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as total FROM company_staffs WHERE status='active' AND role != 'admin'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Users Management</title>
<script src="https://cdn.tailwindcss.com"></script>
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
.role-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.role-admin { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.role-manager { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
.role-team_leader { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
.role-staff { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.status-active { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.status-inactive { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }
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
            <a href="users.php" class="nav-link active">
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
            <h2 class="topbar-title font-bold text-xl">Users Management</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <button onclick="openAddUserModal()" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>Add User
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Total Users</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalUsers ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-users text-blue-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Managers</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalManagers ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                        <i class="fas fa-user-tie text-cyan-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Team Leaders</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalTeamLeaders ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-user-shield text-purple-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Staff</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalStaff ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-user text-green-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Active</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $activeUsers ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-semibold text-lg">All Users</h3>
                <div class="flex space-x-2">
                    <input type="text" id="searchUsers" placeholder="Search users..." class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                    <select id="roleFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                        <option value="">All Roles</option>
                        <option value="manager">Manager</option>
                        <option value="team_leader">Team Leader</option>
                        <option value="staff">Staff</option>
                    </select>
                    <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
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
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">User</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Company ID</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Department</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Team Leader</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Tasks</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Role</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Status</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition-colors">
                                    <td class="py-4 px-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-slate-300"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium"><?= htmlspecialchars($user['full_name']) ?></p>
                                                <p class="text-slate-400 text-sm"><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="bg-slate-700 px-3 py-1 rounded-full text-sm font-mono">
                                            <?= htmlspecialchars($user['company_id']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm"><?= htmlspecialchars($user['dept_name'] ?? 'Not assigned') ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm"><?= htmlspecialchars($user['team_leader_name'] ?? 'None') ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="bg-slate-700 px-3 py-1 rounded-full text-sm">
                                            <?= $user['task_count'] ?> tasks
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="role-badge role-<?= $user['role'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="status-badge status-<?= $user['status'] ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                        <?php if ($user['today_attendance'] > 0): ?>
                                            <span class="ml-2 text-xs text-green-400" title="Present today">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex space-x-2">
                                            <button onclick="openEditUserModal(<?= $user['id'] ?>)" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewUser(<?= $user['id'] ?>)" class="text-green-400 hover:text-green-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="delete_user" class="text-red-400 hover:text-red-300">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-8 px-4 text-center text-slate-400">
                                    <i class="fas fa-users text-4xl mb-3 opacity-50"></i>
                                    <p>No users found. Add your first user!</p>
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

<!-- Add/Edit User Modal -->
<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-6 border-b border-slate-700 sticky top-0 bg-slate-800">
            <h3 class="text-xl font-semibold" id="modalTitle">Add New User</h3>
            <button onclick="closeUserModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" id="userForm" class="p-6">
            <input type="hidden" name="user_id" id="user_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Company ID</label>
                    <input type="text" name="company_id" id="company_id" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                           placeholder="AT001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                    <input type="email" name="email" id="email" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                    <input type="password" name="password" id="password" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Role</label>
                    <select name="role" id="role" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                            onchange="toggleTeamLeaderField()">
                        <option value="staff">Staff</option>
                        <option value="team_leader">Team Leader</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Department</label>
                    <select name="department_id" id="department_id" 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="">Select Department</option>
                        <?php while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div id="teamLeaderField" class="hidden">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Team Leader</label>
                    <select name="team_leader_id" id="team_leader_id" 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="">Select Team Leader</option>
                        <?php 
                        $teamLeaders->data_seek(0); // Reset pointer
                        while ($tl = $teamLeaders->fetch_assoc()): ?>
                            <option value="<?= $tl['id'] ?>"><?= htmlspecialchars($tl['full_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div id="statusField" class="hidden">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                    <select name="status" id="status" 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_user" id="submitBtn" 
                        class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                    Add User
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

// User Modal Functions
function openAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('submitBtn').textContent = 'Add User';
    document.getElementById('submitBtn').name = 'add_user';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('statusField').classList.add('hidden');
    document.getElementById('teamLeaderField').classList.add('hidden');
    document.getElementById('userModal').classList.remove('hidden');
}

function openEditUserModal(userId) {
    // In a real application, you would fetch user data via AJAX
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('submitBtn').textContent = 'Update User';
    document.getElementById('submitBtn').name = 'update_user';
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('userModal').classList.remove('hidden');
    // You would populate form fields with user data via AJAX
    alert('Edit functionality would fetch user data for ID: ' + userId);
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}

function toggleTeamLeaderField() {
    const role = document.getElementById('role').value;
    const teamLeaderField = document.getElementById('teamLeaderField');
    
    if (role === 'staff') {
        teamLeaderField.classList.remove('hidden');
    } else {
        teamLeaderField.classList.add('hidden');
    }
}

function viewUser(userId) {
    window.location.href = 'user_profile.php?id=' + userId;
}

// Password confirmation validation
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
});

// Search and Filter functionality
document.getElementById('searchUsers').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    filterUsers();
});

document.getElementById('roleFilter').addEventListener('change', filterUsers);
document.getElementById('statusFilter').addEventListener('change', filterUsers);

function filterUsers() {
    const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const userName = row.cells[0].textContent.toLowerCase();
        const role = row.cells[5].textContent.toLowerCase();
        const status = row.cells[6].textContent.toLowerCase();
        const matchesSearch = userName.includes(searchTerm);
        const matchesRole = !roleFilter || role.includes(roleFilter);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        row.style.display = (matchesSearch && matchesRole && matchesStatus) ? '' : 'none';
    });
}
</script>
</body>
</html>