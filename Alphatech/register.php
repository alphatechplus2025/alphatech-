<?php
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $companyId = trim($_POST['companyId'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (!$fullName || !$email || !$companyId || !$password || !$role) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM company_staffs WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO company_staffs (company_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssss", $companyId, $fullName, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Try again later.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
