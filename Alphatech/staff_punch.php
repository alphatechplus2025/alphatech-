<?php
session_start();
include('config.php');

// ✅ Enhanced Security: Only Staff Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff' || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

// ✅ CSRF Token Verification
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security token invalid. Please try again.";
    header("Location: staff_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['user_name']);
$today = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');

// ✅ Validate action
if (!isset($_POST['action']) || !in_array($_POST['action'], ['punch_in', 'punch_out'])) {
    $_SESSION['error'] = "Invalid action requested.";
    header("Location: staff_dashboard.php");
    exit();
}

$action = $_POST['action'];

try {
    // ✅ Check if attendance record exists for today
    $check_query = $conn->prepare("SELECT * FROM attendance WHERE staff_id = ? AND work_date = ?");
    $check_query->bind_param("is", $user_id, $today);
    $check_query->execute();
    $attendance = $check_query->get_result()->fetch_assoc();
    $check_query->close();

    if ($action === 'punch_in') {
        // ✅ Punch In Logic
        if ($attendance) {
            $_SESSION['error'] = "You have already punched in today.";
            header("Location: staff_dashboard.php");
            exit();
        }

        // ✅ Determine status based on time (Late after 9:30 AM)
        $punch_in_time = strtotime($current_time);
        $late_threshold = strtotime(date('Y-m-d 09:30:00'));
        $status = ($punch_in_time > $late_threshold) ? 'late' : 'present';

        // ✅ Insert new attendance record
        $insert_query = $conn->prepare("
            INSERT INTO attendance (staff_id, punch_in, work_date, status, work_hours) 
            VALUES (?, ?, ?, ?, 0)
        ");
        $insert_query->bind_param("isss", $user_id, $current_time, $today, $status);
        
        if ($insert_query->execute()) {
            // ✅ Log the activity
            $log_message = $status === 'late' ? 
                "Punched in late at " . date('h:i A', $punch_in_time) : 
                "Punched in at " . date('h:i A', $punch_in_time);
            
            $log_query = $conn->prepare("
                INSERT INTO notifications (user_id, message, type) 
                VALUES (?, ?, 'attendance')
            ");
            $log_query->bind_param("is", $user_id, $log_message);
            $log_query->execute();
            $log_query->close();
            
            $_SESSION['success'] = "Successfully punched in! " . ($status === 'late' ? "You are marked as late." : "");
        } else {
            throw new Exception("Failed to record punch in.");
        }
        $insert_query->close();

    } elseif ($action === 'punch_out') {
        // ✅ Punch Out Logic
        if (!$attendance) {
            $_SESSION['error'] = "You haven't punched in today.";
            header("Location: staff_dashboard.php");
            exit();
        }

        if ($attendance['punch_out']) {
            $_SESSION['error'] = "You have already punched out today.";
            header("Location: staff_dashboard.php");
            exit();
        }

        // ✅ Calculate work hours
        $punch_in = strtotime($attendance['punch_in']);
        $punch_out = strtotime($current_time);
        $work_hours = round(($punch_out - $punch_in) / 3600, 2); // Convert seconds to hours

        // ✅ Determine if it's a half-day (less than 4 hours)
        $new_status = $attendance['status'];
        if ($work_hours < 4 && $attendance['status'] === 'present') {
            $new_status = 'half-day';
        }

        // ✅ Update attendance record
        $update_query = $conn->prepare("
            UPDATE attendance 
            SET punch_out = ?, work_hours = ?, status = ? 
            WHERE staff_id = ? AND work_date = ?
        ");
        $update_query->bind_param("sdsis", $current_time, $work_hours, $new_status, $user_id, $today);
        
        if ($update_query->execute()) {
            // ✅ Log the activity
            $log_message = "Punched out at " . date('h:i A', $punch_out) . 
                         " (Worked: " . $work_hours . " hours)";
            
            $log_query = $conn->prepare("
                INSERT INTO notifications (user_id, message, type) 
                VALUES (?, ?, 'attendance')
            ");
            $log_query->bind_param("is", $user_id, $log_message);
            $log_query->execute();
            $log_query->close();

            // ✅ Notify team leader about completion
            $staff_query = $conn->prepare("SELECT team_leader_id FROM company_staffs WHERE id = ?");
            $staff_query->bind_param("i", $user_id);
            $staff_query->execute();
            $staff_result = $staff_query->get_result()->fetch_assoc();
            $staff_query->close();

            if ($staff_result && $staff_result['team_leader_id']) {
                $leader_message = $username . " completed work for " . $work_hours . " hours today";
                $notify_query = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type) 
                    VALUES (?, ?, 'attendance')
                ");
                $notify_query->bind_param("is", $staff_result['team_leader_id'], $leader_message);
                $notify_query->execute();
                $notify_query->close();
            }
            
            $_SESSION['success'] = "Successfully punched out! You worked for " . $work_hours . " hours today.";
        } else {
            throw new Exception("Failed to record punch out.");
        }
        $update_query->close();
    }

} catch (Exception $e) {
    error_log("Attendance error for user $user_id: " . $e->getMessage());
    $_SESSION['error'] = "System error occurred. Please try again or contact administrator.";
}

// ✅ Regenerate CSRF token for security
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ✅ Redirect back to dashboard
header("Location: staff_dashboard.php");
exit();
?>