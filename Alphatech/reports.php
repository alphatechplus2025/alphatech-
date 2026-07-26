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

// ✅ Date range for reports
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// ✅ Fetch Report Data
try {
    // Team Performance Summary
    $performance_query = $conn->prepare("
        SELECT 
            COUNT(DISTINCT cs.id) as team_size,
            COUNT(DISTINCT t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            AVG(t.progress) as avg_progress,
            COUNT(DISTINCT a.staff_id) as avg_daily_attendance
        FROM company_staffs cs
        LEFT JOIN tasks t ON cs.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
        LEFT JOIN attendance a ON cs.id = a.staff_id AND a.work_date BETWEEN ? AND ?
        WHERE cs.team_leader_id = ? AND cs.status = 'active'
    ");
    $performance_query->bind_param("ssssi", $start_date, $end_date, $start_date, $end_date, $leader_id);
    $performance_query->execute();
    $performance_stats = $performance_query->get_result()->fetch_assoc();
    $performance_query->close();

    // Task Completion Rate by Member
    $member_performance_query = $conn->prepare("
        SELECT 
            cs.full_name,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            AVG(t.progress) as avg_progress,
            (SELECT COUNT(*) FROM attendance a WHERE a.staff_id = cs.id AND a.work_date BETWEEN ? AND ? AND a.status = 'present') as days_present
        FROM company_staffs cs
        LEFT JOIN tasks t ON cs.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
        WHERE cs.team_leader_id = ? AND cs.status = 'active'
        GROUP BY cs.id
        ORDER BY avg_progress DESC
    ");
    $member_performance_query->bind_param("sssi", $start_date, $end_date, $start_date, $end_date, $leader_id);
    $member_performance_query->execute();
    $member_performance = $member_performance_query->get_result();
    $member_performance_query->close();

    // Project Progress
    $project_progress_query = $conn->prepare("
        SELECT 
            p.project_name,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            AVG(t.progress) as avg_progress,
            p.status
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.manager_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $project_progress_query->bind_param("i", $leader_id);
    $project_progress_query->execute();
    $project_progress = $project_progress_query->get_result();
    $project_progress_query->close();

} catch (Exception $e) {
    error_log("Database error in reports: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Reports | Alpha Tech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1 class="text-2xl font-bold gradient-text">Team Reports & Analytics</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300">Welcome, <strong class="text-cyan-300"><?php echo $leader_name; ?></strong></span>
            </div>
        </header>

        <!-- ✅ Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Date Range Filter -->
            <div class="card p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <h3 class="font-semibold text-lg">Report Period</h3>
                    <form method="GET" class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3">
                        <div class="flex space-x-3">
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                                   class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                                   class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium">
                                Generate Report
                            </button>
                            <button type="button" onclick="exportToPDF()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-file-pdf mr-2"></i>Export PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ✅ Performance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-2xl text-cyan-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Team Size</h2>
                    <p class="text-3xl font-bold mt-2 text-cyan-300"><?php echo $performance_stats['team_size'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-tasks text-2xl text-green-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Task Completion</h2>
                    <p class="text-3xl font-bold mt-2 text-green-300">
                        <?php echo $performance_stats['total_tasks'] > 0 ? 
                            round(($performance_stats['completed_tasks'] / $performance_stats['total_tasks']) * 100) : 0; ?>%
                    </p>
                    <p class="text-sm text-gray-400 mt-2">
                        <?php echo $performance_stats['completed_tasks'] ?? 0; ?>/<?php echo $performance_stats['total_tasks'] ?? 0; ?> tasks
                    </p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-chart-line text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Avg Progress</h2>
                    <p class="text-3xl font-bold mt-2 text-blue-300"><?php echo round($performance_stats['avg_progress'] ?? 0); ?>%</p>
                    <p class="text-sm text-gray-400 mt-2">Overall progress rate</p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-calendar-check text-2xl text-purple-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Attendance Rate</h2>
                    <p class="text-3xl font-bold mt-2 text-purple-300">
                        <?php echo $performance_stats['team_size'] > 0 ? 
                            round(($performance_stats['avg_daily_attendance'] / $performance_stats['team_size']) * 100) : 0; ?>%
                    </p>
                    <p class="text-sm text-gray-400 mt-2">Daily average</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- ✅ Team Member Performance -->
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-6">Team Member Performance</h3>
                    <div class="space-y-4">
                        <?php if ($member_performance->num_rows > 0): ?>
                            <?php while ($member = $member_performance->fetch_assoc()): 
                                $completion_rate = $member['total_tasks'] > 0 ? 
                                    round(($member['completed_tasks'] / $member['total_tasks']) * 100) : 0;
                            ?>
                                <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-semibold text-cyan-400"><?php echo htmlspecialchars($member['full_name']); ?></h4>
                                        <span class="text-sm <?php echo $completion_rate >= 80 ? 'text-green-400' : ($completion_rate >= 60 ? 'text-amber-400' : 'text-red-400'); ?>">
                                            <?php echo $completion_rate; ?>%
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 gap-4 text-sm text-slate-400 mb-3">
                                        <div>
                                            <p>Tasks</p>
                                            <p class="font-semibold text-white"><?php echo $member['total_tasks']; ?></p>
                                        </div>
                                        <div>
                                            <p>Completed</p>
                                            <p class="font-semibold text-green-400"><?php echo $member['completed_tasks']; ?></p>
                                        </div>
                                        <div>
                                            <p>Present</p>
                                            <p class="font-semibold text-blue-400"><?php echo $member['days_present']; ?> days</p>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i class="fas fa-users text-4xl mb-3 opacity-50"></i>
                                <p>No performance data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ✅ Project Progress -->
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-6">Project Progress</h3>
                    <div class="space-y-4">
                        <?php if ($project_progress->num_rows > 0): ?>
                            <?php while ($project = $project_progress->fetch_assoc()): 
                                $progress = $project['avg_progress'] ?? 0;
                            ?>
                                <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-semibold text-green-400"><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            <?php echo $project['status'] === 'completed' ? 'bg-green-500/20 text-green-400' : 
                                                   ($project['status'] === 'active' ? 'bg-blue-500/20 text-blue-400' : 
                                                   'bg-amber-500/20 text-amber-400'); ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 text-sm text-slate-400 mb-3">
                                        <div>
                                            <p>Total Tasks</p>
                                            <p class="font-semibold text-white"><?php echo $project['total_tasks']; ?></p>
                                        </div>
                                        <div>
                                            <p>Completed</p>
                                            <p class="font-semibold text-green-400"><?php echo $project['completed_tasks']; ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                                        <span>Progress</span>
                                        <span><?php echo round($progress); ?>%</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i class="fas fa-project-diagram text-4xl mb-3 opacity-50"></i>
                                <p>No project data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ✅ Export Options -->
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-6">Export Reports</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="exportToPDF()" class="p-4 border border-red-500/30 rounded-lg hover:bg-red-500/10 transition-colors text-red-400">
                        <i class="fas fa-file-pdf text-2xl mb-2"></i>
                        <p class="font-semibold">Export as PDF</p>
                        <p class="text-sm text-slate-400">Download detailed report</p>
                    </button>
                    
                    <button onclick="exportToExcel()" class="p-4 border border-green-500/30 rounded-lg hover:bg-green-500/10 transition-colors text-green-400">
                        <i class="fas fa-file-excel text-2xl mb-2"></i>
                        <p class="font-semibold">Export as Excel</p>
                        <p class="text-sm text-slate-400">Spreadsheet format</p>
                    </button>
                    
                    <button onclick="printReport()" class="p-4 border border-blue-500/30 rounded-lg hover:bg-blue-500/10 transition-colors text-blue-400">
                        <i class="fas fa-print text-2xl mb-2"></i>
                        <p class="font-semibold">Print Report</p>
                        <p class="text-sm text-slate-400">Hard copy version</p>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
            // In real application: window.location.href = `export_pdf.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>`;
        }

        function exportToExcel() {
            alert('Excel export functionality would be implemented here');
            // In real application: window.location.href = `export_excel.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>`;
        }

        function printReport() {
            window.print();
        }

        // Performance Charts
        const performanceCtx = document.createElement('canvas');
        document.querySelector('.grid').appendChild(performanceCtx);
        
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['Task Completion', 'Average Progress', 'Attendance Rate'],
                datasets: [{
                    label: 'Performance Metrics (%)',
                    data: [
                        <?php echo $performance_stats['total_tasks'] > 0 ? round(($performance_stats['completed_tasks'] / $performance_stats['total_tasks']) * 100) : 0; ?>,
                        <?php echo round($performance_stats['avg_progress'] ?? 0); ?>,
                        <?php echo $performance_stats['team_size'] > 0 ? round(($performance_stats['avg_daily_attendance'] / $performance_stats['team_size']) * 100) : 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.7)',
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(168, 85, 247, 0.7)'
                    ]
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#e2e8f0'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>