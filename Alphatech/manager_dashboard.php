<?php
// manager_dashboard.php
session_start();
include('config.php');

// Allow only manager or admin (admin can also access)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager','admin'])) {
    header('Location: ../login.php');
    exit();
}

// get current manager id
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$userId) {
    // fallback: if username exists but id not in session, block
    header('Location: ../login.php');
    exit();
}

// Error display for dev (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper - json response
function json_out($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// ---------- AJAX endpoints ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1) Create Project
    if ($action === 'create_project') {
        $name = trim($_POST['project_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;

        if (!$name) json_out(['success' => false, 'msg' => 'Project name required']);

        $stmt = $conn->prepare("INSERT INTO projects (project_name, description, start_date, end_date, manager_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'planning', NOW())");
        $stmt->bind_param('sssis', $name, $desc, $start, $end, $userId);
        if ($stmt->execute()) {
            json_out(['success' => true, 'msg' => 'Project created', 'project_id' => $stmt->insert_id]);
        } else {
            json_out(['success' => false, 'msg' => 'DB error: '.$conn->error]);
        }
    }

    // 2) Assign Task
    if ($action === 'assign_task') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline = $_POST['deadline'] ?? null;

        if (!$project_id || !$assigned_to || !$title) json_out(['success' => false, 'msg' => 'Missing fields']);

        $stmt = $conn->prepare("INSERT INTO tasks (assigned_by, project_id, assigned_to, title, description, deadline, progress, status) VALUES (?, ?, ?, ?, ?, ?, 0, 'pending')");
        $stmt->bind_param('iiisss', $userId, $project_id, $assigned_to, $title, $description, $deadline);
        if ($stmt->execute()) {
            json_out(['success' => true, 'msg' => 'Task assigned', 'task_id' => $stmt->insert_id]);
        } else {
            json_out(['success' => false, 'msg' => 'DB error: '.$conn->error]);
        }
    }

    // 3) Post Chat Message
    if ($action === 'post_message') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if (!$project_id || !$message) json_out(['success' => false, 'msg' => 'Missing data']);

        $stmt = $conn->prepare("INSERT INTO chat_messages (project_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iis', $project_id, $userId, $message);
        if ($stmt->execute()) json_out(['success' => true, 'msg' => 'Message sent']);
        else json_out(['success' => false, 'msg' => 'DB error: '.$conn->error]);
    }

    // 4) Fetch Chat Messages (latest N for a project)
    if ($action === 'fetch_messages') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 50);
        if (!$project_id) json_out(['success' => false, 'msg' => 'Missing project id']);

        $stmt = $conn->prepare("SELECT cm.id, cm.message, cm.sent_at, s.full_name, s.role FROM chat_messages cm JOIN company_staffs s ON cm.sender_id = s.id WHERE cm.project_id = ? ORDER BY cm.id DESC LIMIT ?");
        $stmt->bind_param('ii', $project_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while ($r = $res->fetch_assoc()) {
            $msgs[] = $r;
        }
        // return in chrono order oldest->newest
        echo json_encode(array_reverse($msgs));
        exit();
    }

    // 5) Fetch Tasks for manager's projects
    if ($action === 'fetch_tasks') {
        // get all tasks for projects owned by this manager
        $stmt = $conn->prepare("SELECT t.*, p.project_name, s.full_name AS assignee_name FROM tasks t JOIN projects p ON t.project_id = p.id JOIN company_staffs s ON t.assigned_to = s.id WHERE p.manager_id = ? ORDER BY t.updated_at DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        json_out(['success' => true, 'tasks' => $rows]);
    }

    // 6) Fetch Team Attendance (today) for staff working on manager's projects
    if ($action === 'fetch_attendance') {
        // find distinct staff assigned to projects of this manager
        $stmt = $conn->prepare("
            SELECT DISTINCT s.id, s.full_name,
                (SELECT a.status FROM attendance a WHERE a.staff_id = s.id AND a.work_date = CURDATE() LIMIT 1) AS today_status,
                (SELECT a.punch_in FROM attendance a WHERE a.staff_id = s.id AND a.work_date = CURDATE() LIMIT 1) AS punch_in,
                (SELECT a.punch_out FROM attendance a WHERE a.staff_id = s.id AND a.work_date = CURDATE() LIMIT 1) AS punch_out
            FROM company_staffs s
            JOIN tasks t ON t.assigned_to = s.id
            JOIN projects p ON t.project_id = p.id
            WHERE p.manager_id = ?
            ORDER BY s.full_name ASC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        json_out(['success' => true, 'attendance' => $rows]);
    }

    // Unknown action
    json_out(['success' => false, 'msg' => 'Unknown action']);
}

// ---------- End AJAX endpoints ----------
// From here: render the manager dashboard UI and initial data

// Fetch projects for this manager
$projectsRes = $conn->prepare("SELECT * FROM projects WHERE manager_id = ? ORDER BY created_at DESC");
$projectsRes->bind_param('i', $userId);
$projectsRes->execute();
$projects = $projectsRes->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch staff list (all active staffs)
$staffRes = $conn->query("SELECT id, full_name, role FROM company_staffs WHERE status='active' ORDER BY full_name ASC");
$staffList = $staffRes->fetch_all(MYSQLI_ASSOC);

// Some summary counts
$totalProjects = count($projects);
$totalTasksStmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ?");
$totalTasksStmt->bind_param('i', $userId);
$totalTasksStmt->execute();
$totalTasks = $totalTasksStmt->get_result()->fetch_assoc()['total'] ?? 0;

// render page
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manager Dashboard - ALPHA TECH</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root{--bg:#0f172a; --card:#1e293b; --muted:#94a3b8; --accent:#00ADB5}
    body{background:var(--bg); color:#e2e8f0; font-family:Inter, system-ui, Arial;}
    .card{background:var(--card); border:1px solid rgba(255,255,255,0.04); border-radius:10px;}
    .btn{background:linear-gradient(135deg,var(--accent),#00FFF5); color:#072; padding:.5rem 1rem; border-radius:8px;}
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="w-64 p-6 sidebar card flex flex-col justify-between">
    <div>
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center"><i class="fas fa-briefcase text-white"></i></div>
            <div>
                <h2 class="text-lg font-bold">ALPHA TECH</h2>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($_SESSION['username'] ?? 'Manager') ?></div>
            </div>
        </div>

        <nav class="space-y-2 text-sm">
            <button onclick="showTab('overview')" class="w-full text-left p-2 rounded hover:bg-slate-800/40">Overview</button>
            <button onclick="showTab('projects')" class="w-full text-left p-2 rounded hover:bg-slate-800/40">Projects</button>
            <button onclick="showTab('tasks')" class="w-full text-left p-2 rounded hover:bg-slate-800/40">Tasks</button>
            <button onclick="showTab('team')" class="w-full text-left p-2 rounded hover:bg-slate-800/40">Team & Attendance</button>
            <button onclick="showTab('chat')" class="w-full text-left p-2 rounded hover:bg-slate-800/40">Project Chat</button>
        </nav>
    </div>
    

    <div class="text-sm text-slate-400">
        <div class="mb-3">Role: <span class="font-semibold"><?= htmlspecialchars($_SESSION['role']) ?></span></div>
        <a href="logout.php" class="inline-block mt-2 px-3 py-2 rounded bg-slate-700 hover:bg-slate-600">Logout</a>
    </div>
    
</div>


<!-- Main content -->
<div class="flex-1 p-6 overflow-auto">
    <header class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Manager Dashboard</h1>
            <p class="text-sm text-slate-400">Manage projects, assign tasks, and view team attendance</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right">
                <div class="text-xs text-slate-400">Projects</div>
                <div class="font-semibold text-lg"><?= $totalProjects ?></div>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-400">Total Tasks</div>
                <div class="font-semibold text-lg"><?= $totalTasks ?></div>
            </div>
        </div>
    </header>

    <!-- Tabs / Sections -->
    <section id="overview" class="tab">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card p-4">
                <h3 class="font-semibold mb-2">Create New Project</h3>
                <form id="createProjectForm" onsubmit="return createProject(event)">
                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Project name</label>
                        <input name="project_name" required class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700" />
                    </div>
                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Description</label>
                        <textarea name="description" class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <input type="date" name="start_date" class="p-2 bg-transparent border rounded border-slate-700" />
                        <input type="date" name="end_date" class="p-2 bg-transparent border rounded border-slate-700" />
                    </div>
                    <div class="mt-3">
                        <button class="btn" type="submit">Create Project</button>
                    </div>
                </form>
            </div>

            <div class="card p-4">
                <h3 class="font-semibold mb-2">Quick Assign Task</h3>
                <form id="assignTaskForm" onsubmit="return assignTask(event)">
                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Project</label>
                        <select name="project_id" id="projectSelect" required class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700">
                            <option value="">Select project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Assign To</label>
                        <select name="assigned_to" required class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700">
                            <option value="">Select staff</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Title</label>
                        <input name="title" required class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700" />
                    </div>

                    <div class="mb-2">
                        <label class="text-xs text-slate-400">Deadline</label>
                        <input type="date" name="deadline" class="w-full mt-1 p-2 bg-transparent border rounded border-slate-700" />
                    </div>

                    <div class="mt-3">
                        <button class="btn" type="submit">Assign Task</button>
                    </div>
                </form>
            </div>

            <div class="card p-4">
                <h3 class="font-semibold mb-2">My Projects</h3>
                <div class="space-y-2 max-h-56 overflow-auto">
                    <?php if (!empty($projects)): ?>
                        <?php foreach ($projects as $p): ?>
                            <div class="p-2 border border-slate-700 rounded">
                                <div class="flex justify-between">
                                    <div>
                                        <div class="font-semibold text-sm"><?= htmlspecialchars($p['project_name']) ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($p['status']) ?> • <?= htmlspecialchars($p['start_date']) ?> → <?= htmlspecialchars($p['end_date']) ?></div>
                                    </div>
                                    <div>
                                        <button onclick="openProjectChat(<?= $p['id'] ?>)" class="px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-xs">Chat</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-slate-400">No projects yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="projects" class="tab hidden mt-6">
        <div class="card p-4">
            <h3 class="font-semibold mb-3">All Projects (yours)</h3>
            <div class="overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-400">
                        <tr><th class="p-2">#</th><th>Project</th><th>Dates</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $idx => $p): ?>
                            <tr class="border-t border-slate-800">
                                <td class="p-2"><?= $idx+1 ?></td>
                                <td class="p-2"><?= htmlspecialchars($p['project_name']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($p['start_date']) ?> → <?= htmlspecialchars($p['end_date']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($p['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="tasks" class="tab hidden mt-6">
        <div class="card p-4">
            <h3 class="font-semibold mb-3">Tasks (latest)</h3>
            <div id="tasksList" class="space-y-2">
                <!-- populated via AJAX -->
            </div>
        </div>
    </section>

    <section id="team" class="tab hidden mt-6">
        <div class="card p-4">
            <h3 class="font-semibold mb-3">Team Today Attendance</h3>
            <div id="attendanceList" class="space-y-2">
                <!-- populated via AJAX -->
            </div>
        </div>
    </section>

    <section id="chat" class="tab hidden mt-6">
        <div id="chatBox" class="card p-4">
  <h3 class="font-semibold mb-2">Team Chat</h3>
  <select id="chatProjectSelect" class="p-2 bg-transparent border rounded border-slate-700 mb-2">
    <option value="">Select project</option>
    <?php
    $projects = $conn->query("SELECT id, project_name FROM projects WHERE manager_id = ".$_SESSION['id']);
    while ($p = $projects->fetch_assoc()) {
        echo "<option value='{$p['id']}'>{$p['project_name']}</option>";
    }
    ?>
  </select>

  <div id="chatMessages" class="bg-slate-900 p-3 rounded-lg h-64 overflow-y-auto text-sm"></div>

  <form id="chatForm" class="mt-3 flex" onsubmit="return sendChatMessage(event)">
    <input id="chatInput" class="flex-1 bg-slate-800 p-2 rounded-l-lg border border-slate-700" placeholder="Type message..." />
    <button class="bg-cyan-600 px-3 py-2 rounded-r-lg">Send</button>
  </form>
</div>

    </section>
</div>

<script>
    
let chatProject = null;

document.getElementById('chatProjectSelect').addEventListener('change', function(){
  chatProject = this.value;
  loadChat();
  if (chatProject) setInterval(loadChat, 2000);
});

async function sendChatMessage(e) {
  e.preventDefault();
  const msg = document.getElementById('chatInput').value.trim();
  if (!msg || !chatProject) return;
  const form = new FormData();
  form.append('project_id', chatProject);
  form.append('message', msg);
  const res = await fetch('chat_send.php', { method: 'POST', body: form });
  const data = await res.json();
  if (data.success) {
    document.getElementById('chatInput').value = '';
    loadChat();
  }
}

async function loadChat() {
  if (!chatProject) return;
  const res = await fetch('chat_fetch.php?project_id=' + chatProject);
  const data = await res.json();
  const box = document.getElementById('chatMessages');
  if (data.success) {
    box.innerHTML = '';
    data.messages.forEach(m => {
      const roleColor = m.role === 'manager' ? 'text-green-400' : 'text-blue-400';
      const el = document.createElement('div');
      el.className = 'mb-2';
      el.innerHTML = `
        <div class="text-xs text-slate-500">
          <span class="${roleColor} font-semibold">${m.full_name}</span>
          <span class="text-slate-400">(${m.role})</span> - ${m.sent_at}
        </div>
        <div>${m.message}</div>`;
      box.appendChild(el);
    });
    box.scrollTop = box.scrollHeight;
  }
}
/* UI helpers */
function showTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    // load content when switching
    if (id === 'tasks') loadTasks();
    if (id === 'team') loadAttendance();
}
/* default tab */
showTab('overview');

/* AJAX helper */
async function postFormData(data) {
    const res = await fetch('', { method: 'POST', headers: {'Accept':'application/json'}, body: data });
    return res.json();
}

/* Create Project */
async function createProject(e) {
    e.preventDefault();
    const form = document.getElementById('createProjectForm');
    const fd = new FormData(form);
    fd.append('action','create_project');
    const r = await postFormData(fd);
    alert(r.msg || (r.success ? 'Created' : 'Error'));
    if (r.success) {
        // append to project selects for quick use
        const sel = document.getElementById('projectSelect');
        const chatSel = document.getElementById('chatProjectSelect');
        const opt = document.createElement('option'); opt.value = r.project_id; opt.textContent = fd.get('project_name');
        sel.appendChild(opt); chatSel.appendChild(opt.cloneNode(true));
        form.reset();
    }
    return false;
}

/* Assign Task */
async function assignTask(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('assignTaskForm'));
    fd.append('action','assign_task');
    const r = await postFormData(fd);
    alert(r.msg || (r.success ? 'Assigned' : 'Error'));
    if (r.success) {
        loadTasks();
    }
    return false;
}

/* Load Tasks */
async function loadTasks() {
    const fd = new FormData();
    fd.append('action','fetch_tasks');
    const r = await postFormData(fd);
    const list = document.getElementById('tasksList');
    list.innerHTML = '';
    if (r.success) {
        if (r.tasks.length === 0) list.innerHTML = '<div class="text-slate-400">No tasks yet.</div>';
        else {
            r.tasks.forEach(t => {
                const el = document.createElement('div'); el.className='p-2 border border-slate-800 rounded flex justify-between';
                el.innerHTML = `<div>
                    <div class="font-semibold">${escapeHtml(t.title)}</div>
                    <div class="text-xs text-slate-400">${escapeHtml(t.project_name)} • ${escapeHtml(t.assignee_name)}</div>
                </div>
                <div class="text-sm text-slate-400">${t.status} • ${t.progress}%</div>`;
                list.appendChild(el);
            });
        }
    } else {
        list.innerHTML = '<div class="text-red-500">Failed to load tasks</div>';
    }
}

/* Load Team Attendance */
async function loadAttendance() {
    const fd = new FormData();
    fd.append('action','fetch_attendance');
    const r = await postFormData(fd);
    const el = document.getElementById('attendanceList');
    el.innerHTML = '';
    if (r.success) {
        if (r.attendance.length === 0) el.innerHTML = '<div class="text-slate-400">No team members assigned to your projects yet.</div>';
        else {
            r.attendance.forEach(a => {
                const status = a.today_status ? a.today_status : 'absent';
                const badge = status === 'present' ? '<span class="text-green-400">Present</span>' : '<span class="text-red-400">'+escapeHtml(status)+'</span>';
                const row = document.createElement('div'); row.className='p-2 border border-slate-800 rounded flex justify-between';
                row.innerHTML = `<div>
                    <div class="font-semibold">${escapeHtml(a.full_name)}</div>
                    <div class="text-xs text-slate-400">In: ${escapeHtml(a.punch_in || '-') } Out: ${escapeHtml(a.punch_out || '-') }</div>
                </div>
                <div>${badge}</div>`;
                el.appendChild(row);
            });
        }
    } else {
        el.innerHTML = '<div class="text-red-500">Failed to load attendance</div>';
    }
}

/* Chat logic */
let chatProject = null;
let chatPoller = null;
function openProjectChat(pid) {
    document.getElementById('chatProjectSelect').value = pid;
    startChat();
}
function startChat() {
    const select = document.getElementById('chatProjectSelect');
    const pid = select.value;
    if (!pid) return alert('Select a project');
    chatProject = pid;
    document.getElementById('chatBox').classList.remove('hidden');
    loadMessages();
    if (chatPoller) clearInterval(chatPoller);
    chatPoller = setInterval(loadMessages, 2000); // poll every 2s
}
async function postMessage(e) {
    e.preventDefault();
    const msg = document.getElementById('chatMessage').value.trim();
    if (!msg || !chatProject) return;
    const fd = new FormData();
    fd.append('action','post_message');
    fd.append('project_id', chatProject);
    fd.append('message', msg);
    const r = await postFormData(fd);
    if (r.success) {
        document.getElementById('chatMessage').value = '';
        loadMessages();
    } else alert(r.msg || 'Failed to send');
    return false;
}
async function loadMessages() {
    if (!chatProject) return;
    const fd = new FormData();
    fd.append('action','fetch_messages');
    fd.append('project_id', chatProject);
    fd.append('limit', 100);
    const r = await postFormData(fd);
    const box = document.getElementById('chatMessages');
    if (r && Array.isArray(r)) {
        // older versions returned raw array; handle both shapes
        const messages = r;
        box.innerHTML = '';
        messages.forEach(m => {
            const div = document.createElement('div');
            div.className = 'mb-2';
            div.innerHTML = `<div class="text-xs text-slate-400">${escapeHtml(m.full_name)} • <span class="text-xs">${m.role}</span> <span class="text-xs text-slate-500"> ${m.sent_at}</span></div>
                             <div class="mt-1">${escapeHtml(m.message)}</div>`;
            box.appendChild(div);
        });
        box.scrollTop = box.scrollHeight;
    } else if (r.success && Array.isArray(r)) {
        // when wrapped inside {success: true, messages: [...]}
        const messages = r.messages || [];
        box.innerHTML = '';
        messages.forEach(m => {
            const div = document.createElement('div');
            div.className = 'mb-2';
            div.innerHTML = `<div class="text-xs text-slate-400">${escapeHtml(m.full_name)} • <span class="text-xs">${m.role}</span> <span class="text-xs text-slate-500"> ${m.sent_at}</span></div>
                             <div class="mt-1">${escapeHtml(m.message)}</div>`;
            box.appendChild(div);
        });
        box.scrollTop = box.scrollHeight;
    } else {
        // try to handle raw json array from endpoint
        if (Array.isArray(r)) {
            const messages = r;
            box.innerHTML = '';
            messages.forEach(m => {
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `<div class="text-xs text-slate-400">${escapeHtml(m.full_name)} • <span class="text-xs">${m.role}</span> <span class="text-xs text-slate-500"> ${m.sent_at}</span></div>
                                 <div class="mt-1">${escapeHtml(m.message)}</div>`;
                box.appendChild(div);
            });
            box.scrollTop = box.scrollHeight;
        }
    }
}

/* small escape util */
function escapeHtml(s){ return (s===null||s===undefined)?'':String(s).replace(/[&<>"'`=\/]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]; });}

/* initialize: load tasks summary */
loadTasks();
</script>
</body>
</html>
