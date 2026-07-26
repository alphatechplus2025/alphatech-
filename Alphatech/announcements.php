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

// Handle Announcement Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $content = $conn->real_escape_string($_POST['content']);
        $target_role = $conn->real_escape_string($_POST['target_role']);
        $posted_by = $_SESSION['user_id']; // Assuming user_id is stored in session
        
        $sql = "INSERT INTO announcements (title, content, posted_by, target_role) 
                VALUES ('$title', '$content', $posted_by, '$target_role')";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Announcement posted successfully!";
        } else {
            $_SESSION['error'] = "Error posting announcement: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        
        $sql = "DELETE FROM announcements WHERE id = $announcement_id";
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Announcement deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting announcement: " . $conn->error;
        }
    }
    
    header("Location: announcements.php");
    exit();
}

// Fetch Announcements
$announcements = $conn->query("
    SELECT a.*, cs.full_name as author_name
    FROM announcements a
    JOIN company_staffs cs ON a.posted_by = cs.id
    ORDER BY a.created_at DESC
");

// Announcement Statistics
$totalAnnouncements = $conn->query("SELECT COUNT(*) as total FROM announcements")->fetch_assoc()['total'];
$todayAnnouncements = $conn->query("SELECT COUNT(*) as total FROM announcements WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$weeklyAnnouncements = $conn->query("SELECT COUNT(*) as total FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Announcements</title>
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
.target-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.target-all { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.target-manager { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.target-staff { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
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
            <a href="analytics.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="announcements.php" class="nav-link active">
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
            <h2 class="topbar-title font-bold text-xl">Announcements</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <button onclick="openAddAnnouncementModal()" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-plus mr-2"></i>New Announcement
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Total Announcements</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalAnnouncements ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-bullhorn text-blue-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">Today</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $todayAnnouncements ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <i class="fas fa-calendar-day text-green-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm">This Week</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $weeklyAnnouncements ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-calendar-week text-purple-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-semibold text-lg">All Announcements</h3>
                <div class="flex space-x-2">
                    <input type="text" id="searchAnnouncements" placeholder="Search announcements..." class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                    <select id="targetFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                        <option value="">All Targets</option>
                        <option value="all">All Users</option>
                        <option value="manager">Managers</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
            </div>
            
            <div class="space-y-6">
                <?php if ($announcements->num_rows > 0): ?>
                    <?php while ($announcement = $announcements->fetch_assoc()): ?>
                        <div class="border border-slate-700 rounded-lg p-6 hover:border-slate-600 transition-colors">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-xl font-semibold text-cyan-400 mb-2"><?= htmlspecialchars($announcement['title']) ?></h4>
                                    <div class="flex items-center space-x-4 text-sm text-slate-400">
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-2"></i>
                                            <?= htmlspecialchars($announcement['author_name']) ?>
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-clock mr-2"></i>
                                            <?= date('M d, Y \a\t H:i', strtotime($announcement['created_at'])) ?>
                                        </span>
                                        <span class="target-badge target-<?= $announcement['target_role'] ?>">
                                            For: <?= ucfirst($announcement['target_role']) ?>
                                        </span>
                                    </div>
                                </div>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?')">
                                    <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                    <button type="submit" name="delete_announcement" class="text-red-400 hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <p class="text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fas fa-bullhorn text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg">No announcements yet</p>
                        <p class="text-sm mt-2">Create your first announcement to keep your team informed</p>
                    </div>
                <?php endif; ?>
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

<!-- Add Announcement Modal -->
<div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-6 border-b border-slate-700 sticky top-0 bg-slate-800">
            <h3 class="text-xl font-semibold">Create New Announcement</h3>
            <button onclick="closeAnnouncementModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Announcement Title</label>
                    <input type="text" name="title" required 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                           placeholder="Enter announcement title">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Target Audience</label>
                    <select name="target_role" required 
                            class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <option value="all">All Users</option>
                        <option value="manager">Managers Only</option>
                        <option value="staff">Staff Only</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Announcement Content</label>
                    <textarea name="content" rows="8" required 
                              class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                              placeholder="Type your announcement here..."></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeAnnouncementModal()" class="px-4 py-2 border border-slate-600 text-slate-300 rounded-lg hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_announcement" 
                        class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                    Publish Announcement
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

// Announcement Modal Functions
function openAddAnnouncementModal() {
    document.getElementById('announcementModal').classList.remove('hidden');
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').classList.add('hidden');
}

// Search and Filter functionality
document.getElementById('searchAnnouncements').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    filterAnnouncements();
});

document.getElementById('targetFilter').addEventListener('change', filterAnnouncements);

function filterAnnouncements() {
    const searchTerm = document.getElementById('searchAnnouncements').value.toLowerCase();
    const targetFilter = document.getElementById('targetFilter').value;
    const announcements = document.querySelectorAll('.border-slate-700');
    
    announcements.forEach(announcement => {
        const title = announcement.querySelector('h4').textContent.toLowerCase();
        const target = announcement.querySelector('.target-badge').textContent.toLowerCase();
        const matchesSearch = title.includes(searchTerm);
        const matchesTarget = !targetFilter || target.includes(targetFilter);
        
        announcement.style.display = (matchesSearch && matchesTarget) ? 'block' : 'none';
    });
}
</script>
</body>
</html>