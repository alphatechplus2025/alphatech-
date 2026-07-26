<?php
session_start();
include('config.php');

// Ensure only admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Handle Add Department
if (isset($_POST['add_department'])) {
    $name = trim($_POST['dept_name']);
    $code = trim($_POST['dept_code']);
    $desc = trim($_POST['description']);
    if ($name != '') {
        $stmt = $conn->prepare("INSERT INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $code, $desc);
        $stmt->execute();
        $msg = "Department added successfully!";
    } else {
        $msg = "Department name cannot be empty!";
    }
}

// ✅ Handle Delete Department
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM departments WHERE id=$id");
    $msg = "Department deleted!";
}

// ✅ Handle Edit Department
if (isset($_POST['edit_department'])) {
    $id = intval($_POST['dept_id']);
    $name = trim($_POST['dept_name']);
    $code = trim($_POST['dept_code']);
    $desc = trim($_POST['description']);
    $stmt = $conn->prepare("UPDATE departments SET dept_name=?, dept_code=?, description=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $code, $desc, $id);
    $stmt->execute();
    $msg = "Department updated!";
}

// ✅ Fetch Departments
$departments = $conn->query("SELECT * FROM departments ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Departments - ALPHA TECH</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#0f172a;color:#e2e8f0;}
.card{background:#1e293b;border:1px solid #334155;border-radius:10px;}
.btn{background:linear-gradient(135deg,#00ADB5,#00FFF5);color:white;padding:.5rem 1rem;border-radius:8px;}
input,textarea{background:transparent;border:1px solid #334155;color:#e2e8f0;border-radius:8px;padding:.5rem;}
</style>
</head>
<body class="p-6">
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-cyan-400"><i class="fas fa-sitemap mr-2"></i>Departments Management</h1>
        <a href="admin_dashboard.php" class="text-slate-400 hover:text-cyan-400"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (isset($msg)): ?>
        <div class="bg-green-600/20 border border-green-600 text-green-300 p-3 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ADD NEW DEPARTMENT -->
    <div class="card p-6 mb-6">
        <h2 class="font-semibold mb-4">Add New Department</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm text-slate-400">Department Name</label>
                <input type="text" name="dept_name" required class="w-full mt-1">
            </div>
            <div>
                <label class="text-sm text-slate-400">Department Code</label>
                <input type="text" name="dept_code" class="w-full mt-1">
            </div>
            <div class="md:col-span-3">
                <label class="text-sm text-slate-400">Description</label>
                <textarea name="description" rows="2" class="w-full mt-1"></textarea>
            </div>
            <div class="md:col-span-3 text-right">
                <button name="add_department" class="btn">Add Department</button>
            </div>
        </form>
    </div>

    <!-- DEPARTMENT LIST -->
    <div class="card p-6">
        <h2 class="font-semibold mb-4">Department List</h2>
        <table class="w-full text-sm">
            <thead class="text-slate-400 border-b border-slate-700">
                <tr>
                    <th class="p-2 text-left">#</th>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Code</th>
                    <th class="p-2 text-left">Description</th>
                    <th class="p-2 text-left">Created</th>
                    <th class="p-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($departments->num_rows > 0): $i=1; while($d = $departments->fetch_assoc()): ?>
                    <tr class="border-b border-slate-800 hover:bg-slate-800/40">
                        <td class="p-2"><?= $i++ ?></td>
                        <td class="p-2"><?= htmlspecialchars($d['dept_name']) ?></td>
                        <td class="p-2"><?= htmlspecialchars($d['dept_code']) ?></td>
                        <td class="p-2 text-slate-400"><?= htmlspecialchars($d['description']) ?></td>
                        <td class="p-2 text-slate-400"><?= date("M d, Y", strtotime($d['created_at'])) ?></td>
                        <td class="p-2 text-center space-x-2">
                            <button onclick="editDept(<?= $d['id'] ?>, '<?= addslashes($d['dept_name']) ?>', '<?= addslashes($d['dept_code']) ?>', '<?= addslashes($d['description']) ?>')" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Delete this department?')" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="p-3 text-center text-slate-400">No departments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center">
  <div class="card p-6 w-full max-w-lg">
    <h2 class="font-semibold mb-3">Edit Department</h2>
    <form method="POST">
      <input type="hidden" name="dept_id" id="edit_id">
      <div class="mb-3">
        <label class="text-sm text-slate-400">Department Name</label>
        <input type="text" name="dept_name" id="edit_name" class="w-full">
      </div>
      <div class="mb-3">
        <label class="text-sm text-slate-400">Department Code</label>
        <input type="text" name="dept_code" id="edit_code" class="w-full">
      </div>
      <div class="mb-3">
        <label class="text-sm text-slate-400">Description</label>
        <textarea name="description" id="edit_desc" rows="2" class="w-full"></textarea>
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal()" class="bg-slate-600 px-3 py-2 rounded">Cancel</button>
        <button name="edit_department" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editDept(id, name, code, desc) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_code').value = code;
  document.getElementById('edit_desc').value = desc;
  document.getElementById('editModal').classList.remove('hidden');
}
function closeModal() {
  document.getElementById('editModal').classList.add('hidden');
}
</script>
</body>
</html>
