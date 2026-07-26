<?php
session_start();
include('config.php');

$user_id = $_SESSION['id'] ?? 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$sql = "SELECT id, message, type, created_at FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}

// Mark them as read (optional: comment out if you want manual marking)
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");

echo json_encode(['success' => true, 'notifications' => $notifications]);
