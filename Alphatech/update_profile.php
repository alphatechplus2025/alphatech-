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
$phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
$address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
$bio = $conn->real_escape_string(trim($_POST['bio'] ?? ''));

try {
    // ✅ Handle profile picture upload
    $profile_photo = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/profile_pics/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = basename($_FILES['profile_photo']['name']);
        $file_size = $_FILES['profile_photo']['size'];
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Only image files are allowed (JPG, PNG, GIF).";
            header("Location: staff_dashboard.php");
            exit();
        }
        
        if ($file_size > 2 * 1024 * 1024) { // 2MB limit
            $_SESSION['error'] = "Image size too large. Maximum size is 2MB.";
            header("Location: staff_dashboard.php");
            exit();
        }

        // Generate unique filename
        $new_filename = 'staff_' . $user_id . '_' . uniqid() . '.' . $file_type;
        $file_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            $profile_photo = $new_filename;
        }
    }

    // ✅ Check if profile exists
    $check_query = $conn->prepare("SELECT id FROM staff_profiles WHERE staff_id = ?");
    $check_query->bind_param("i", $user_id);
    $check_query->execute();
    $profile_exists = $check_query->get_result()->num_rows > 0;
    $check_query->close();

    if ($profile_exists) {
        // ✅ Update existing profile
        if ($profile_photo) {
            $update_query = $conn->prepare("
                UPDATE staff_profiles 
                SET phone = ?, address = ?, bio = ?, profile_photo = ? 
                WHERE staff_id = ?
            ");
            $update_query->bind_param("ssssi", $phone, $address, $bio, $profile_photo, $user_id);
        } else {
            $update_query = $conn->prepare("
                UPDATE staff_profiles 
                SET phone = ?, address = ?, bio = ? 
                WHERE staff_id = ?
            ");
            $update_query->bind_param("sssi", $phone, $address, $bio, $user_id);
        }
    } else {
        // ✅ Create new profile
        $department_id = 1; // Default department, adjust as needed
        
        if ($profile_photo) {
            $update_query = $conn->prepare("
                INSERT INTO staff_profiles (staff_id, department_id, phone, address, bio, profile_photo) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $update_query->bind_param("iissss", $user_id, $department_id, $phone, $address, $bio, $profile_photo);
        } else {
            $update_query = $conn->prepare("
                INSERT INTO staff_profiles (staff_id, department_id, phone, address, bio) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $update_query->bind_param("iisss", $user_id, $department_id, $phone, $address, $bio);
        }
    }

    if ($update_query->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        throw new Exception("Failed to update profile.");
    }
    $update_query->close();

} catch (Exception $e) {
    error_log("Profile update error for user $user_id: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update profile. Please try again.";
}

// ✅ Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header("Location: staff_dashboard.php");
exit();
?>