<?php
session_start();
include 'config.php';

// ✅ Enhanced Security: Only Team Leader Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'team_leader' || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

// ✅ Session security regeneration
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$leader_id = $_SESSION['user_id'];
$leader_name = htmlspecialchars($_SESSION['user_name']);

// ✅ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Handle Chat Messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: team_chat.php");
        exit;
    }

    if (isset($_POST['send_message'])) {
        $message = $conn->real_escape_string(trim($_POST['message']));
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : null;
        
        if (!empty($message)) {
            $chat_query = $conn->prepare("
                INSERT INTO chat_messages (project_id, sender_id, receiver_id, message) 
                VALUES (?, ?, ?, ?)
            ");
            $chat_query->bind_param("iiis", $project_id, $leader_id, $receiver_id, $message);
            
            if ($chat_query->execute()) {
                $_SESSION['success'] = "Message sent successfully!";
            } else {
                $_SESSION['error'] = "Error sending message.";
            }
            $chat_query->close();
        }
    }
    
    header("Location: team_chat.php");
    exit;
}

// ✅ Fetch Data for Chat
try {
    // Fetch team members
    $team_query = $conn->prepare("
        SELECT id, full_name, company_id 
        FROM company_staffs 
        WHERE team_leader_id = ? AND status = 'active'
        ORDER BY full_name
    ");
    $team_query->bind_param("i", $leader_id);
    $team_query->execute();
    $team_members = $team_query->get_result();
    $team_query->close();

    // Fetch projects for group chat
    $projects_query = $conn->prepare("
        SELECT id, project_name 
        FROM projects 
        WHERE manager_id = ? AND status IN ('active', 'planning')
        ORDER BY project_name
    ");
    $projects_query->bind_param("i", $leader_id);
    $projects_query->execute();
    $projects = $projects_query->get_result();
    $projects_query->close();

    // Fetch recent messages
    $messages_query = $conn->prepare("
        SELECT 
            cm.*, 
            cs.full_name as sender_name,
            p.project_name,
            rc.full_name as receiver_name
        FROM chat_messages cm
        JOIN company_staffs cs ON cm.sender_id = cs.id
        LEFT JOIN projects p ON cm.project_id = p.id
        LEFT JOIN company_staffs rc ON cm.receiver_id = rc.id
        WHERE cm.sender_id = ? OR cm.receiver_id = ? OR (cm.receiver_id IS NULL AND cm.project_id IN (
            SELECT id FROM projects WHERE manager_id = ?
        ))
        ORDER BY cm.sent_at DESC
        LIMIT 50
    ");
    $messages_query->bind_param("iii", $leader_id, $leader_id, $leader_id);
    $messages_query->execute();
    $recent_messages = $messages_query->get_result();
    $messages_query->close();

} catch (Exception $e) {
    error_log("Database error in team_chat: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat | Alpha Tech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #00ADB5;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--dark-bg); color: #fff; }
        .card { background: var(--card-bg); border: 1px solid #334155; border-radius: 12px; }
        .gradient-text { background: linear-gradient(90deg, #00ADB5, #00FFF5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .chat-container { height: 500px; overflow-y: auto; }
        .message-self { background: #00ADB5; color: white; border-radius: 18px 18px 4px 18px; }
        .message-other { background: #334155; color: white; border-radius: 18px 18px 18px 4px; }
        .online-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- ✅ Sidebar -->
    <?php include 'team_leader_sidebar.php'; ?>

    <!-- ✅ Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- ✅ Topbar -->
        <header class="bg-[#1e293b] border-b border-[#334155] py-4 px-8 flex justify-between items-center sticky top-0 z-50">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold gradient-text">Team Chat</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300">Welcome, <strong class="text-cyan-300"><?php echo $leader_name; ?></strong></span>
            </div>
        </header>

        <!-- ✅ Content -->
        <main class="flex-1 p-8">
            <!-- ✅ Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- ✅ Chat Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Team Members -->
                    <div class="card p-6">
                        <h3 class="font-semibold mb-4 flex items-center justify-between">
                            <span>Team Members</span>
                            <span class="text-green-400 text-sm flex items-center">
                                <span class="online-dot mr-2"></span>
                                Online
                            </span>
                        </h3>
                        <div class="space-y-3">
                            <?php if ($team_members->num_rows > 0): ?>
                                <?php while ($member = $team_members->fetch_assoc()): ?>
                                    <div class="flex items-center space-x-3 p-2 hover:bg-slate-700/50 rounded-lg cursor-pointer" 
                                         onclick="selectUser(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>')">
                                        <div class="w-8 h-8 rounded-full bg-cyan-500/20 flex items-center justify-center relative">
                                            <i class="fas fa-user text-cyan-400 text-sm"></i>
                                            <span class="online-dot absolute -top-1 -right-1"></span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($member['full_name']); ?></p>
                                            <p class="text-xs text-slate-400"><?php echo $member['company_id']; ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-slate-400 text-sm text-center py-4">No team members</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Project Groups -->
                    <div class="card p-6">
                        <h3 class="font-semibold mb-4">Project Groups</h3>
                        <div class="space-y-3">
                            <?php if ($projects->num_rows > 0): ?>
                                <?php while ($project = $projects->fetch_assoc()): ?>
                                    <div class="flex items-center space-x-3 p-2 hover:bg-slate-700/50 rounded-lg cursor-pointer"
                                         onclick="selectProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['project_name']); ?>')">
                                        <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center">
                                            <i class="fas fa-users text-purple-400 text-sm"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($project['project_name']); ?></p>
                                            <p class="text-xs text-slate-400">Project Group</p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-slate-400 text-sm text-center py-4">No projects</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ✅ Chat Area -->
                <div class="lg:col-span-3">
                    <div class="card p-6">
                        <!-- Chat Header -->
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-700">
                            <div>
                                <h3 id="chatTitle" class="text-xl font-semibold text-cyan-400">Select a chat</h3>
                                <p id="chatSubtitle" class="text-sm text-slate-400">Choose a team member or project group to start chatting</p>
                            </div>
                            <div class="flex items-center space-x-2 text-slate-400">
                                <i class="fas fa-shield-alt text-green-400"></i>
                                <span class="text-sm">End-to-end encrypted</span>
                            </div>
                        </div>

                        <!-- Messages Container -->
                        <div id="messagesContainer" class="chat-container mb-4 space-y-4 p-4 bg-slate-800/50 rounded-lg">
                            <?php if ($recent_messages->num_rows > 0): ?>
                                <?php while ($message = $recent_messages->fetch_assoc()): 
                                    $is_sender = $message['sender_id'] == $leader_id;
                                ?>
                                    <div class="flex <?php echo $is_sender ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-xs lg:max-w-md <?php echo $is_sender ? 'message-self' : 'message-other'; ?> p-3">
                                            <?php if (!$is_sender): ?>
                                                <p class="text-xs font-medium mb-1"><?php echo htmlspecialchars($message['sender_name']); ?></p>
                                            <?php endif; ?>
                                            <p class="text-sm"><?php echo htmlspecialchars($message['message']); ?></p>
                                            <p class="text-xs opacity-70 mt-1 text-right">
                                                <?php echo date('H:i', strtotime($message['sent_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fas fa-comments text-4xl mb-3 opacity-50"></i>
                                    <p>No messages yet</p>
                                    <p class="text-sm mt-2">Start a conversation with your team</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <form method="POST" id="messageForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="project_id" id="project_id">
                            <input type="hidden" name="receiver_id" id="receiver_id">
                            
                            <div class="flex space-x-3">
                                <input type="text" name="message" id="messageInput" 
                                       placeholder="Type your message..." 
                                       class="flex-1 bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-cyan-500"
                                       required>
                                <button type="submit" name="send_message" 
                                        class="bg-cyan-600 text-white rounded-lg px-6 py-3 hover:bg-cyan-700 transition-colors flex items-center space-x-2">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Send</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <div class="card p-4 text-center hover:bg-slate-700/50 transition-colors cursor-pointer">
                            <i class="fas fa-file-upload text-cyan-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">Share File</p>
                        </div>
                        <div class="card p-4 text-center hover:bg-slate-700/50 transition-colors cursor-pointer">
                            <i class="fas fa-video text-green-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">Start Call</p>
                        </div>
                        <div class="card p-4 text-center hover:bg-slate-700/50 transition-colors cursor-pointer">
                            <i class="fas fa-calendar text-purple-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">Schedule Meeting</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let selectedUser = null;
        let selectedProject = null;

        function selectUser(userId, userName) {
            selectedUser = userId;
            selectedProject = null;
            
            document.getElementById('chatTitle').textContent = userName;
            document.getElementById('chatSubtitle').textContent = 'Private conversation';
            document.getElementById('receiver_id').value = userId;
            document.getElementById('project_id').value = '';
            
            // Reset styles
            document.querySelectorAll('.bg-slate-700\\/50').forEach(el => {
                el.classList.remove('bg-slate-700/50');
            });
            event.currentTarget.classList.add('bg-slate-700/50');
            
            // Enable message input
            document.getElementById('messageInput').disabled = false;
            document.getElementById('messageInput').placeholder = `Message ${userName}...`;
        }

        function selectProject(projectId, projectName) {
            selectedProject = projectId;
            selectedUser = null;
            
            document.getElementById('chatTitle').textContent = projectName;
            document.getElementById('chatSubtitle').textContent = 'Project group chat';
            document.getElementById('project_id').value = projectId;
            document.getElementById('receiver_id').value = '';
            
            // Reset styles
            document.querySelectorAll('.bg-slate-700\\/50').forEach(el => {
                el.classList.remove('bg-slate-700/50');
            });
            event.currentTarget.classList.add('bg-slate-700/50');
            
            // Enable message input
            document.getElementById('messageInput').disabled = false;
            document.getElementById('messageInput').placeholder = `Message ${projectName} group...`;
        }

        // Auto-scroll to bottom of messages
        const messagesContainer = document.getElementById('messagesContainer');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Handle form submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            if (!selectedUser && !selectedProject) {
                e.preventDefault();
                alert('Please select a chat first.');
                return false;
            }
        });

        // Auto-refresh messages every 10 seconds
        setInterval(() => {
            if (selectedUser || selectedProject) {
                // In a real application, you would fetch new messages via AJAX
                console.log('Refreshing messages...');
            }
        }, 10000);
    </script>
</body>
</html>