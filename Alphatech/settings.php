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

// Handle Settings Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $user_id = $_SESSION['user_id']; // Assuming user_id is stored in session
        
        $sql = "UPDATE company_staffs SET full_name = '$full_name', email = '$email' WHERE id = $user_id";
        
        if ($conn->query($sql)) {
            $_SESSION['username'] = $full_name;
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile: " . $conn->error;
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['user_id'];
        
        // Verify current password
        $user_query = $conn->query("SELECT password FROM company_staffs WHERE id = $user_id");
        $user = $user_query->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE company_staffs SET password = '$hashed_password' WHERE id = $user_id";
                
                if ($conn->query($sql)) {
                    $_SESSION['success'] = "Password changed successfully!";
                } else {
                    $_SESSION['error'] = "Error changing password: " . $conn->error;
                }
            } else {
                $_SESSION['error'] = "New passwords do not match!";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect!";
        }
    }
    
    if (isset($_POST['update_system_settings'])) {
        // Handle system settings update
        $_SESSION['success'] = "System settings updated successfully!";
    }
    
    header("Location: settings.php");
    exit();
}

// Fetch current user data
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM company_staffs WHERE id = $user_id");
$current_user = $user_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA TECH - Settings</title>
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
.settings-nav {
    display: flex;
    border-bottom: 1px solid #334155;
    margin-bottom: 24px;
}
.settings-nav button {
    padding: 12px 24px;
    background: none;
    border: none;
    color: #cbd5e1;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}
.settings-nav button.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.settings-nav button:hover {
    color: var(--primary);
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
    .settings-nav {
        flex-direction: column;
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
            <a href="announcements.php" class="nav-link">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <a href="settings.php" class="nav-link active">
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
            <h2 class="topbar-title font-bold text-xl">Settings</h2>
        </div>
        
        <div class="flex items-center space-x-4">
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

        <!-- Settings Navigation -->
        <div class="settings-nav">
            <button class="active" onclick="showSection('profile')">Profile Settings</button>
            <button onclick="showSection('security')">Security</button>
            <button onclick="showSection('system')">System Settings</button>
            <button onclick="showSection('notifications')">Notifications</button>
        </div>

        <!-- Profile Settings -->
        <section id="profile" class="settings-section">
            <div class="card p-6">
                <h3 class="font-semibold text-lg mb-6">Profile Information</h3>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Full Name</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name']) ?>" required 
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($current_user['email']) ?>" required 
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Company ID</label>
                            <input type="text" value="<?= htmlspecialchars($current_user['company_id']) ?>" disabled 
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Role</label>
                            <input type="text" value="<?= ucfirst($current_user['role']) ?>" disabled 
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-400">
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button type="submit" name="update_profile" 
                                class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Security Settings -->
        <section id="security" class="settings-section hidden">
            <div class="card p-6">
                <h3 class="font-semibold text-lg mb-6">Change Password</h3>
                <form method="POST">
                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">New Password</label>
                            <input type="password" name="new_password" required 
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required 
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button type="submit" name="change_password" 
                                class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- System Settings -->
        <section id="system" class="settings-section hidden">
            <div class="card p-6">
                <h3 class="font-semibold text-lg mb-6">System Configuration</h3>
                <form method="POST">
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-medium text-slate-300 mb-4">Company Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Company Name</label>
                                    <input type="text" value="ALPHA TECH" disabled 
                                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-400">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Timezone</label>
                                    <select class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                        <option>UTC</option>
                                        <option selected>Asia/Kolkata</option>
                                        <option>America/New_York</option>
                                        <option>Europe/London</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-slate-300 mb-4">Attendance Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Work Start Time</label>
                                    <input type="time" value="09:00" 
                                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Work End Time</label>
                                    <input type="time" value="18:00" 
                                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-slate-300 mb-4">Notification Settings</h4>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500" checked>
                                    <span class="ml-2 text-sm text-slate-300">Email notifications for new tasks</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500" checked>
                                    <span class="ml-2 text-sm text-slate-300">Push notifications for announcements</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500">
                                    <span class="ml-2 text-sm text-slate-300">Weekly performance reports</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button type="submit" name="update_system_settings" 
                                class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                            Save System Settings
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Notification Settings -->
        <section id="notifications" class="settings-section hidden">
            <div class="card p-6">
                <h3 class="font-semibold text-lg mb-6">Notification Preferences</h3>
                <div class="space-y-6">
                    <div>
                        <h4 class="font-medium text-slate-300 mb-4">Email Notifications</h4>
                        <div class="space-y-3">
                            <label class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                                <div>
                                    <p class="font-medium text-sm">Task Assignments</p>
                                    <p class="text-slate-400 text-xs">Get notified when new tasks are assigned to you</p>
                                </div>
                                <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500" checked>
                            </label>
                            <label class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                                <div>
                                    <p class="font-medium text-sm">Project Updates</p>
                                    <p class="text-slate-400 text-xs">Receive updates about project progress</p>
                                </div>
                                <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500" checked>
                            </label>
                            <label class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                                <div>
                                    <p class="font-medium text-sm">Announcements</p>
                                    <p class="text-slate-400 text-xs">Company-wide announcements and updates</p>
                                </div>
                                <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500">
                            </label>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium text-slate-300 mb-4">Push Notifications</h4>
                        <div class="space-y-3">
                            <label class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                                <div>
                                    <p class="font-medium text-sm">Deadline Reminders</p>
                                    <p class="text-slate-400 text-xs">Reminders for upcoming task deadlines</p>
                                </div>
                                <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500" checked>
                            </label>
                            <label class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
                                <div>
                                    <p class="font-medium text-sm">Team Messages</p>
                                    <p class="text-slate-400 text-xs">Notifications for new team messages</p>
                                </div>
                                <input type="checkbox" class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500">
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors">
                        Save Preferences
                    </button>
                </div>
            </div>
        </section>
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
});

// Settings section navigation
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.settings-nav button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected section and activate button
    document.getElementById(sectionId).classList.remove('hidden');
    event.target.classList.add('active');
}

// Password confirmation validation
document.querySelector('form[action*="change_password"]')?.addEventListener('submit', function(e) {
    const newPassword = document.querySelector('input[name="new_password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
</script>
</body>
</html>