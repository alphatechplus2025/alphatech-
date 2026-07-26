<?php
session_start();
include 'config.php';

// ✅ Only Admin Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = trim($_POST['company_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']); // ✅ Dynamic role selection (manager or team_leader)
    $status = 'active';

    // Validation
    if (empty($company_id) || empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $message = '<div class="bg-red-600 text-white p-3 rounded-lg">⚠️ Please fill in all fields.</div>';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM company_staffs WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = '<div class="bg-yellow-500 text-black p-3 rounded-lg">⚠️ Email already exists!</div>';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO company_staffs (company_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $company_id, $full_name, $email, $hashedPassword, $role, $status);

            if ($stmt->execute()) {
                $message = '<div class="bg-green-600 text-white p-3 rounded-lg">✅ ' . ucfirst(str_replace('_', ' ', $role)) . ' added successfully!</div>';
            } else {
                $message = '<div class="bg-red-600 text-white p-3 rounded-lg">❌ Database error. Try again.</div>';
            }

            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff | Alpha Tech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #fff; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .btn-primary {
            background: linear-gradient(135deg, #00ADB5 0%, #00FFF5 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0, 173, 181, 0.4); }
        input, select {
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #00ADB5;
            box-shadow: 0 0 0 2px rgba(0,173,181,0.2);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-lg p-8 card">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold gradient-text">Add Manager / Team Leader</h1>
            <p class="text-gray-400 text-sm">Only Admin can access this page</p>
        </div>

        <!-- Message -->
        <?php if (!empty($message)) echo $message; ?>

        <form action="" method="POST" class="space-y-5 mt-6">
            <div>
                <label class="block text-sm mb-2"><i class="fas fa-id-badge text-cyan-500 mr-2"></i>Company ID</label>
                <input type="text" name="company_id" placeholder="Enter Company ID" required>
            </div>

            <div>
                <label class="block text-sm mb-2"><i class="fas fa-user text-cyan-500 mr-2"></i>Full Name</label>
                <input type="text" name="full_name" placeholder="Enter Full Name" required>
            </div>

            <div>
                <label class="block text-sm mb-2"><i class="fas fa-envelope text-cyan-500 mr-2"></i>Email Address</label>
                <input type="email" name="email" placeholder="Enter Email" required>
            </div>

            <div>
                <label class="block text-sm mb-2"><i class="fas fa-lock text-cyan-500 mr-2"></i>Password</label>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>

            <div>
                <label class="block text-sm mb-2"><i class="fas fa-briefcase text-cyan-500 mr-2"></i>Role</label>
                <select name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="manager">Manager</option>
                    <option value="team_leader">Team Leader</option>
                </select>
            </div>

            <button type="submit" class="btn-primary w-full py-3 rounded-lg font-semibold text-black mt-4">
                <i class="fas fa-user-plus mr-2"></i>Add Staff
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="admin_dashboard.php" class="text-cyan-400 hover:text-cyan-300 text-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
