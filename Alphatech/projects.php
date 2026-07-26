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

// Handle Project Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_project'])) {
        $project_name = $conn->real_escape_string($_POST['project_name']);
        $description = $conn->real_escape_string($_POST['description']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $manager_id = intval($_POST['manager_id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "INSERT INTO projects (project_name, description, start_date, end_date, manager_id, status) 
                VALUES ('$project_name', '$description', '$start_date', '$end_date', $manager_id, '$status')";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Project added successfully!";
        } else {
            $_SESSION['error'] = "Error adding project: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_project'])) {
        $project_id = intval($_POST['project_id']);
        $project_name = $conn->real_escape_string($_POST['project_name']);
        $description = $conn->real_escape_string($_POST['description']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $manager_id = intval($_POST['manager_id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE projects SET 
                project_name = '$project_name',
                description = '$description',
                start_date = '$start_date',
                end_date = '$end_date',
                manager_id = $manager_id,
                status = '$status'
                WHERE id = $project_id";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Project updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating project: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_project'])) {
        $project_id = intval($_POST['project_id']);
        
        $sql = "DELETE FROM projects WHERE id = $project_id";
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Project deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting project: " . $conn->error;
        }
    }
    
    header("Location: projects.php");
    exit();
}

// Fetch Projects with Manager Details
$projects = $conn->query("
    SELECT p.*, m.full_name as manager_name, 
           COUNT(t.id) as task_count,
           DATEDIFF(p.end_date, CURDATE()) as days_remaining
    FROM projects p
    LEFT JOIN company_staffs m ON p.manager_id = m.id
    LEFT JOIN tasks t ON p.id = t.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

// Fetch Managers for dropdown
$managers = $conn->query("SELECT id, full_name FROM company_staffs WHERE role IN ('manager', 'team_leader') AND status='active'");

// Project Statistics
$totalProjects = $conn->query("SELECT COUNT(*) as total FROM projects")->fetch_assoc()['total'];
$activeProjects = $conn->query("SELECT COUNT(*) as total FROM projects WHERE status='active'")->fetch_assoc()['total'];
$completedProjects = $conn->query("SELECT COUNT(*) as total FROM projects WHERE status='completed'")->fetch_assoc()['total'];
$planningProjects = $conn->query("SELECT COUNT(*) as total FROM projects WHERE status='planning'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Projects Management</title>
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
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-active { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.status-planning { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
.status-completed { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
.status-testing { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.status-on-hold { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
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
            <a href="projects.php" class="nav-link active">
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
            <h2 class="topbar-title font-bold text-xl">Projects Management</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <button onclick="openAddProjectModal()" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Project
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Total Projects</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-folder text-blue-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Active Projects</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $activeProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-play-circle text-green-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Completed</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $completedProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-check-circle text-purple-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">In Planning</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $planningProjects ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <i class="fas fa-clock text-amber-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-semibold text-lg">All Projects</h3>
                <div class="flex space-x-2">
                    <input type="text" id="searchProjects" placeholder="Search projects..." class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                    <select id="statusFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                        <option value="">All Status</option>
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="testing">Testing</option>
                        <option value="completed">Completed</option>
                        <option value="on-hold">On Hold</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Project Name</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Manager</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Timeline</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Tasks</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Status</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($projects->num_rows > 0): ?>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                                <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition-colors">
                                    <td class="py-4 px-4">
                                        <div>
                                            <p class="font-medium"><?= htmlspecialchars($project['project_name']) ?></p>
                                            <p class="text-slate-400 text-sm mt-1"><?= htmlspecialchars(substr($project['description'], 0, 60)) ?>...</p>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="font-medium"><?= htmlspecialchars($project['manager_name']) ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div>
                                            <p class="text-sm"><?= date('M d, Y', strtotime($project['start_date'])) ?></p>
                                            <p class="text-sm text-slate-400">to <?= date('M d, Y', strtotime($project['end_date'])) ?></p>
                                            <?php if ($project['days_remaining'] > 0): ?>
                                                <p class="text-xs text-amber-400 mt-1"><?= $project['days_remaining'] ?> days left</p>
                                            <?php elseif ($project['days_remaining'] == 0): ?>
                                                <p class="text-xs text-red-400 mt-1">Due today</p>
                                            <?php else: ?>
                                                <p class="text-xs text-red-400 mt-1">Overdue by <?= abs($project['days_remaining']) ?> days</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="bg-slate-700 px-3 py-1 rounded-full text-sm">
                                            <?= $project['task_count'] ?> tasks
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="status-badge status-<?= $project['status'] ?>">
                                            <?= ucfirst($project['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex space-x-2">
                                            <button onclick="openEditProjectModal(<?= $project['id'] ?>)" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewProject(<?= $project['id'] ?>)" class="text-green-400 hover:text-green-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this project?')">
                                                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                                <button type="submit" name="delete_project" class="text-red-400 hover:text-red-300">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 px-4 text-center text-slate-400">
                                    <i class="fas fa-folder-open text-4xl mb-3 opacity-50"></i>
                                    <p>No projects found. Create your first project!</p>
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

<!-- Add/Edit Project Modal -->
<div id="projectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-xl w-full max-w-2xl mx-4">
        <div class="flex justify-between items-center p-6 border-b border-slate-700">
            <h3 class="text-xl font-semibold" id="modalTitle">Add New Project</h3>
            <button onclick="closeProjectModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" id="projectForm" class="p-6">
            <input type="hidden" name="project_id" id="project_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Project Name</label>
                    <input type="text" name="project_name" id="project_name" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Manager</label>
                    <select name="manager_id" id="manager_id" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="">Select Manager</option>
                        <?php while ($manager = $managers->fetch_assoc()): ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['full_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">End Date</label>
                    <input type="date" name="end_date" id="end_date" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <textarea name="description" id="description" rows="3" required 
                              class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                    <select name="status" id="status" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="testing">Testing</option>
                        <option value="completed">Completed</option>
                        <option value="on-hold">On Hold</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeProjectModal()" class="px-4 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_project" id="submitBtn" 
                        class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                    Add Project
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

// Project Modal Functions
function openAddProjectModal() {
    document.getElementById('modalTitle').textContent = 'Add New Project';
    document.getElementById('submitBtn').textContent = 'Add Project';
    document.getElementById('submitBtn').name = 'add_project';
    document.getElementById('projectForm').reset();
    document.getElementById('project_id').value = '';
    document.getElementById('projectModal').classList.remove('hidden');
}

function openEditProjectModal(projectId) {
    // In a real application, you would fetch project data via AJAX
    // For now, we'll redirect to an edit page or show a placeholder
    alert('Edit functionality would fetch project data for ID: ' + projectId);
    // You can implement AJAX to populate the form
}

function closeProjectModal() {
    document.getElementById('projectModal').classList.add('hidden');
}

function viewProject(projectId) {
    window.location.href = 'project_details.php?id=' + projectId;
}

// Search and Filter functionality
document.getElementById('searchProjects').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    filterProjects();
});

document.getElementById('statusFilter').addEventListener('change', filterProjects);

function filterProjects() {
    const searchTerm = document.getElementById('searchProjects').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const projectName = row.cells[0].textContent.toLowerCase();
        const status = row.cells[4].textContent.toLowerCase();
        const matchesSearch = projectName.includes(searchTerm);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}
</script>
</body>
</html>