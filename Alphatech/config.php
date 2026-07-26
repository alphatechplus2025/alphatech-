<?php
$host = "localhost"; // or use your host provider's hostname if given
$user = "u726595168_alpha_tech"; // MySQL username
$pass = "Poova@2004"; // 🔒 replace with your actual DB password
$dbname = "u726595168_alpha_tech"; // database name

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}
?>
