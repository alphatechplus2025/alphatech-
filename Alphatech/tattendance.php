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

// ✅ Date filter
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// ✅ Fetch Team Attendance Data
try {
    // Attendance Statistics
    $stats_query = $conn->prepare("
        SELECT 
            COUNT(DISTINCT cs.id) as total_team_members,
            COUNT(DISTINCT a.staff_id) as present_today,
            AVG(a.work_hours) as avg_work_hours
        FROM company_staffs cs
        LEFT JOIN attendance a ON cs.id = a.staff_id AND a.work_date = ?
        WHERE cs.team_leader_id = ? AND cs.status = 'active'
    ");
    $stats_query->bind_param("si", $filter_date, $leader_id);
    $stats_query->execute();
    $attendance_stats = $stats_query->get_result()->fetch_assoc();
    $stats_query->close();

    // Daily Attendance Records
    $attendance_query = $conn->prepare("
        SELECT 
            cs.id, cs.full_name, cs.company_id,
            a.punch_in, a.punch_out, a.work_hours, a.status,
            TIMESTAMPDIFF(HOUR, a.punch_in, a.punch_out) as hours_worked
        FROM company_staffs cs
        LEFT JOIN attendance a ON cs.id = a.staff_id AND a.work_date = ?
        WHERE cs.team_leader_id = ? AND cs.status = 'active'
        ORDER BY cs.full_name
    ");
    $attendance_query->bind_param("si", $filter_date, $leader_id);
    $attendance_query->execute();
    $attendance_records = $attendance_query->get_result();
    $attendance_query->close();

    // Weekly Attendance Summary
    $weekly_query = $conn->prepare("
        SELECT 
            DAYNAME(work_date) as day_name,
            COUNT(DISTINCT staff_id) as present_count,
            AVG(work_hours) as avg_hours
        FROM attendance 
        WHERE staff_id IN (SELECT id FROM company_staffs WHERE team_leader_id = ?)
        AND work_date BETWEEN DATE_SUB(?, INTERVAL 6 DAY) AND ?
        GROUP BY work_date
        ORDER BY work_date
    ");
    $weekly_query->bind_param("iss", $leader_id, $filter_date, $filter_date);
    $weekly_query->execute();
    $weekly_data = $weekly_query->get_result();
    $weekly_query->close();

} catch (Exception $e) {
    error_log("Database error in attendance: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance | Alpha Tech</title>
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
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-present { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-absent { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-late { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-half-day { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
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
                <h1 class="text-2xl font-bold gradient-text">Team Attendance</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300">Welcome, <strong class="text-cyan-300"><?php echo $leader_name; ?></strong></span>
            </div>
        </header>

        <!-- ✅ Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Date Filter -->
            <div class="card p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <h3 class="font-semibold text-lg">Attendance for <?php echo date('F d, Y', strtotime($filter_date)); ?></h3>
                    <form method="GET" class="flex space-x-3">
                        <input type="date" name="date" value="<?php echo $filter_date; ?>" 
                               class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 px-4 py-2 rounded-lg text-sm font-medium">
                            Filter
                        </button>
                        <a href="attendance.php" class="bg-slate-600 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-medium">
                            Today
                        </a>
                    </form>
                </div>
            </div>

            <!-- ✅ Attendance Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-2xl text-cyan-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Team Size</h2>
                    <p class="text-3xl font-bold mt-2 text-cyan-300"><?php echo $attendance_stats['total_team_members'] ?? 0; ?></p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-user-check text-2xl text-green-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Present Today</h2>
                    <p class="text-3xl font-bold mt-2 text-green-300"><?php echo $attendance_stats['present_today'] ?? 0; ?></p>
                    <p class="text-sm text-gray-400 mt-2">
                        <?php echo $attendance_stats['total_team_members'] > 0 ? 
                            round(($attendance_stats['present_today'] / $attendance_stats['total_team_members']) * 100) : 0; ?>% attendance rate
                    </p>
                </div>

                <div class="card p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-clock text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-300">Avg. Hours</h2>
                    <p class="text-3xl font-bold mt-2 text-blue-300"><?php echo round($attendance_stats['avg_work_hours'] ?? 0, 1); ?></p>
                    <p class="text-sm text-gray-400 mt-2">Average work hours</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- ✅ Attendance Chart -->
                <div class="card p-6 lg:col-span-2">
                    <h3 class="text-xl font-semibold mb-6">Weekly Attendance Trend</h3>
                    <canvas id="attendanceChart" height="250"></canvas>
                </div>

                <!-- ✅ Quick Stats -->
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-6">Today's Summary</h3>
                    <div class="space-y-4">
                        <?php
                        $present_count = 0;
                        $absent_count = 0;
                        $late_count = 0;
                        
                        if ($attendance_records->num_rows > 0) {
                            $attendance_records->data_seek(0);
                            while ($record = $attendance_records->fetch_assoc()) {
                                if ($record['status'] === 'present') $present_count++;
                                elseif ($record['status'] === 'absent') $absent_count++;
                                elseif ($record['status'] === 'late') $late_count++;
                            }
                        }
                        ?>
                        
                        <div class="flex justify-between items-center p-3 bg-green-500/10 rounded-lg">
                            <span class="text-green-400">Present</span>
                            <span class="font-semibold"><?php echo $present_count; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-red-500/10 rounded-lg">
                            <span class="text-red-400">Absent</span>
                            <span class="font-semibold"><?php echo $absent_count; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-amber-500/10 rounded-lg">
                            <span class="text-amber-400">Late</span>
                            <span class="font-semibold"><?php echo $late_count; ?></span>
                        </div>
                        
                        <div class="pt-4 border-t border-slate-700">
                            <p class="text-sm text-slate-400 text-center">
                                Last updated: <?php echo date('H:i:s'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ Attendance Records Table -->
            <div class="card p-6 mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Attendance Records</h3>
                    <div class="flex space-x-2">
                        <input type="text" id="searchAttendance" placeholder="Search team members..." 
                               class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-700">
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Team Member</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Punch In</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Punch Out</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Hours</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Status</th>
                                <th class="text-left py-3 px-4 text-slate-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($attendance_records->num_rows > 0): ?>
                                <?php $attendance_records->data_seek(0); ?>
                                <?php while ($record = $attendance_records->fetch_assoc()): ?>
                                    <tr class="border-b border-slate-800 hover:bg-slate-800/50 transition-colors">
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full bg-slate-600 flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-slate-300"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium"><?php echo htmlspecialchars($record['full_name']); ?></p>
                                                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($record['company_id']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <p class="text-sm"><?php echo $record['punch_in'] ? date('H:i', strtotime($record['punch_in'])) : '--' ?></p>
                                        </td>
                                        <td class="py-4 px-4">
                                            <p class="text-sm"><?php echo $record['punch_out'] ? date('H:i', strtotime($record['punch_out'])) : '--' ?></p>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="bg-slate-700 px-3 py-1 rounded-full text-sm">
                                                <?php echo $record['work_hours'] ? number_format($record['work_hours'], 1) : '0' ?>h
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="status-badge status-<?php echo $record['status'] ?? 'absent'; ?>">
                                                <?php echo ucfirst($record['status'] ?? 'absent'); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <button onclick="viewAttendanceDetails(<?php echo $record['id']; ?>)" 
                                                    class="text-cyan-400 hover:text-cyan-300 text-sm">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400">
                                        <i class="fas fa-calendar-times text-4xl mb-3 opacity-50"></i>
                                        <p>No attendance records found for <?php echo date('F d, Y', strtotime($filter_date)); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchAttendance').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const memberName = row.cells[0].textContent.toLowerCase();
                row.style.display = memberName.includes(searchTerm) ? '' : 'none';
            });
        });

        function viewAttendanceDetails(memberId) {
            alert('Viewing attendance details for member ID: ' + memberId);
            // In real application: window.location.href = `attendance_details.php?member=${memberId}&date=<?php echo $filter_date; ?>`;
        }

        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Present Staff',
                    data: [12, 15, 13, 14, 16, 8, 5],
                    backgroundColor: 'rgba(0, 173, 181, 0.7)',
                    borderColor: 'rgba(0, 173, 181, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
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