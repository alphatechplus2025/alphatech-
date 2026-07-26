<?php
include 'config.php';

if (isset($_GET['email']) && isset($_GET['code'])) {
    $email = $_GET['email'];
    $code = $_GET['code'];

    $stmt = $conn->prepare("SELECT id FROM company_staffs WHERE email=? AND verification_code=? AND email_verified=0");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $conn->query("UPDATE company_staffs SET email_verified=1, verification_code=NULL WHERE email='$email'");
        echo "<h2 style='font-family:Arial;text-align:center;color:green;'>✅ Email verified successfully! You can now log in.</h2>";
    } else {
        echo "<h2 style='font-family:Arial;text-align:center;color:red;'>❌ Invalid or expired verification link.</h2>";
    }
} else {
    echo "<h2 style='font-family:Arial;text-align:center;'>⚠️ Invalid request.</h2>";
}
?>
