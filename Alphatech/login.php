<?php
// =======================================
// ✅ Alpha Tech - Universal Login Script
// =======================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'config.php';
session_start();

$response = ['success' => false, 'message' => ''];

// ✅ Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// ✅ Get input
$email = trim($_POST['email'] ?? '');
$companyId = trim($_POST['companyId'] ?? '');
$password = trim($_POST['password'] ?? '');

// ✅ Basic validation
if (empty($email) || empty($companyId) || empty($password)) {
    $response['message'] = 'All fields are required.';
    echo json_encode($response);
    exit;
}

// ✅ Fetch user from database
$sql = "SELECT * FROM company_staffs WHERE email = ? AND company_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $companyId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $response['message'] = 'No matching account found.';
    echo json_encode($response);
    exit;
}

$user = $result->fetch_assoc();

// ✅ Normalize role and status (avoid mismatch issues)
$role = strtolower(trim(str_replace(' ', '_', $user['role'])));
$status = strtolower(trim($user['status']));

// ✅ Check password
if (!password_verify($password, $user['password'])) {
    $response['message'] = 'Invalid password.';
    echo json_encode($response);
    exit;
}

// ✅ Check active status
if ($status !== 'active') {
    $response['message'] = 'Your account is inactive. Contact admin.';
    echo json_encode($response);
    exit;
}

// ✅ Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['role'] = $role;

// ✅ Role-based redirection
switch ($role) {
    case 'admin':
        $redirect = 'admin_dashboard.php';
        break;
    case 'team_leader':
        $redirect = 'team_leader_dashboard.php';
        break;
    case 'manager':
        $redirect = 'manager_dashboard.php';
        break;
    case 'staff':
    default:
        $redirect = 'staff_dashboard.php';
        break;
}

// ✅ Successful response
$response['success'] = true;
$response['message'] = 'Login successful.';
$response['redirect'] = $redirect;

// ✅ Return response
echo json_encode($response);
?>
