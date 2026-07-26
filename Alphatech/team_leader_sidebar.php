<?php
// Team Leader Sidebar Component
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'team_leader') {
    header("Location: login.php");
    exit;
}
?>
<!-- Sidebar Navigation -->
<aside class="w-64 bg-[#1e293b] border-r border-[#334155] p-6">
    <div class="flex items-center space-x-3 mb-8">
        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center">
            <i class="fas fa-rocket text-white"></i>
        </div>
        <h2 class="text-xl font-bold gradient-text">Alpha Tech</h2>
    </div>
    
    <nav class="space-y-2">
        <a href="team_leader_dashboard.php" class="flex items-center space-x-3 p-3 text-gray-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
            <i class="fas fa-home w-5"></i>
            <span>Dashboard</span>
        </a>
        <a href="view_team.php" class="flex items-center space-x-3 p-3 bg-cyan-500/10 text-cyan-400 rounded-lg border border-cyan-500/20">
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
    
    <div class="mt-8 pt-6 border-t border-slate-700">
        <div class="flex items-center space-x-3 p-3 text-sm text-slate-400">
            <i class="fas fa-shield-alt text-green-400"></i>
            <span>Secure Session</span>
        </div>
        <div class="flex items-center space-x-3 p-3 text-sm text-slate-400">
            <i class="fas fa-clock text-amber-400"></i>
            <span>Last login: <?php echo date('H:i'); ?></span>
        </div>
    </div>
</aside>