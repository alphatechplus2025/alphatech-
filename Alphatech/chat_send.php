<?php
session_start();
include('config.php');

if (!isset($_SESSION['id'])) {
    die(json_encode(['success' => false, 'msg' => 'Not logged in']));
}

$sender_id = $_SESSION['id'];
$project_id = intval($_POST['project_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($project_id <= 0 || $message === '') {
    die(json_encode(['success' => false, 'msg' => 'Invalid input']));
}

$stmt = $conn->prepare("INSERT INTO chat_messages (project_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $project_id, $sender_id, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => $conn->error]);
}
