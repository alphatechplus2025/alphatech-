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
$task_id = intval($_POST['task_id']);
$progress = intval($_POST['progress']);
$status = $conn->real_escape_string($_POST['status']);
$remarks = $conn->real_escape_string(trim($_POST['remarks'] ?? ''));

// ✅ Validate progress range
if ($progress < 0 || $progress > 100) {
    $_SESSION['error'] = "Progress must be between 0 and 100.";
    header("Location: staff_dashboard.php");
    exit();
}

// ✅ Validate status
if (!in_array($status, ['pending', 'in-progress', 'completed'])) {
    $_SESSION['error'] = "Invalid status selected.";
    header("Location: staff_dashboard.php");
    exit();
}

try {
    // ✅ Verify task belongs to user
    $verify_query = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
    $verify_query->bind_param("ii", $task_id, $user_id);
    $verify_query->execute();
    
    if (!$verify_query->get_result()->num_rows) {
        $_SESSION['error'] = "Task not found or access denied.";
        header("Location: staff_dashboard.php");
        exit();
    }
    $verify_query->close();

    // ✅ Handle file upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/task_attachments/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = basename($_FILES['attachment']['name']);
        $file_size = $_FILES['attachment']['size'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'zip'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            header("Location: staff_dashboard.php");
            exit();
        }
        
        if ($file_size > 10 * 1024 * 1024) { // 10MB limit
            $_SESSION['error'] = "File size too large. Maximum size is 10MB.";
            header("Location: staff_dashboard.php");
            exit();
        }

        // Generate unique filename
        $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
        $file_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            $attachment_path = $file_path;
        }
    }

    // ✅ Update task progress and status
    $update_query = $conn->prepare("
        UPDATE tasks 
        SET progress = ?, status = ?, updated_at = NOW() 
        WHERE id = ? AND assigned_to = ?
    ");
    $update_query->bind_param("isii", $progress, $status, $task_id, $user_id);
    
    if ($update_query->execute()) {
        // ✅ Record task update
        $update_record_query = $conn->prepare("
            INSERT INTO task_updates (task_id, updated_by, progress, remarks, attachment) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $update_record_query->bind_param("iiiss", $task_id, $user_id, $progress, $remarks, $attachment_path);
        $update_record_query->execute();
        $update_record_query->close();

        // ✅ Notify team leader about task update
        $task_query = $conn->prepare("
            SELECT t.title, t.assigned_by, cs.full_name 
            FROM tasks t 
            JOIN company_staffs cs ON t.assigned_to = cs.id 
            WHERE t.id = ?
        ");
        $task_query->bind_param("i", $task_id);
        $task_query->execute();
        $task_result = $task_query->get_result()->fetch_assoc();
        $task_query->close();

        if ($task_result) {
            $message = $_SESSION['user_name'] . " updated task '" . $task_result['title'] . "' to " . $progress . "% (" . $status . ")";
            
            $notify_query = $conn->prepare("
                INSERT INTO notifications (user_id, message, type) 
                VALUES (?, ?, 'task')
            ");
            $notify_query->bind_param("is", $task_result['assigned_by'], $message);
            $notify_query->execute();
            $notify_query->close();
        }

        $_SESSION['success'] = "Task updated successfully!";
    } else {
        throw new Exception("Failed to update task.");
    }
    $update_query->close();

} catch (Exception $e) {
    error_log("Task update error for user $user_id: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update task. Please try again.";
}

// ✅ Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header("Location: staff_dashboard.php");
exit();
?>