<?php
function sendNotification($conn, $user_id, $message, $type = 'system') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $message, $type);
    $stmt->execute();
}

include('notify_user.php');
sendNotification($conn, $staff_id, "You’ve been assigned a new task: $title", "task");

?>
