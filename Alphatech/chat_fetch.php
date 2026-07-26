<?php
session_start();
include('config.php');

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    die(json_encode(['success' => false, 'msg' => 'Invalid project']));
}

$query = "
SELECT c.id, c.message, c.sent_at, s.full_name, s.role 
FROM chat_messages c 
JOIN company_staffs s ON c.sender_id = s.id
WHERE c.project_id = ?
ORDER BY c.id DESC LIMIT 50
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
