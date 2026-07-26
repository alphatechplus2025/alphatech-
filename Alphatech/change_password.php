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
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// ✅ Validate passwords
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['error'] = "All password fields are required.";
    header("Location: staff_dashboard.php");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = "New passwords do not match.";
    header("Location: staff_dashboard.php");
    exit();
}

if (strlen($new_password) < 6) {
    $_SESSION['error'] = "New password must be at least 6 characters long.";
    header("Location: staff_dashboard.php");
    exit();
}

try {
    // ✅ Verify current password
    $verify_query = $conn->prepare("SELECT password FROM company_staffs WHERE id = ?");
    $verify_query->bind_param("i", $user_id);
    $verify_query->execute();
    $result = $verify_query->get_result()->fetch_assoc();
    $verify_query->close();

    if (!$result || !password_verify($current_password, $result['password'])) {
        $_SESSION['error'] = "Current password is incorrect.";
        header("Location: staff_dashboard.php");
        exit();
    }

    // ✅ Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // ✅ Update password
    $update_query = $conn->prepare("UPDATE company_staffs SET password = ? WHERE id = ?");
    $update_query->bind_param("si", $hashed_password, $user_id);
    
    if ($update_query->execute()) {
        // ✅ Log password change
        $log_query = $conn->prepare("
            INSERT INTO notifications (user_id, message, type) 
            VALUES (?, 'Password changed successfully', 'security')
        ");
        $log_query->bind_param("i", $user_id);
        $log_query->execute();
        $log_query->close();

        $_SESSION['success'] = "Password changed successfully!";
    } else {
        throw new Exception("Failed to change password.");
    }
    $update_query->close();

} catch (Exception $e) {
    error_log("Password change error for user $user_id: " . $e->getMessage());
    $_SESSION['error'] = "Failed to change password. Please try again.";
}

// ✅ Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header("Location: staff_dashboard.php");
exit();
?>