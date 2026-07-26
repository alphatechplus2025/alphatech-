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

// ✅ File upload directory
$upload_dir = "uploads/documents/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ✅ Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: documents.php");
        exit;
    }

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['document']['name']);
        $file_size = $_FILES['document']['size'];
        $file_tmp = $_FILES['document']['tmp_name'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
        } elseif ($file_size > 10 * 1024 * 1024) { // 10MB limit
            $_SESSION['error'] = "File size too large. Maximum size is 10MB.";
        } else {
            // Generate unique filename
            $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $title = $conn->real_escape_string(trim($_POST['title']));
                $description = $conn->real_escape_string(trim($_POST['description']));
                $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
                
                // In a real application, you would insert into a documents table
                $_SESSION['success'] = "Document uploaded successfully!";
                
                // Log the upload
                $log_query = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type) 
                    VALUES (?, 'New document uploaded: {$title}', 'document')
                ");
                $log_query->bind_param("i", $leader_id);
                $log_query->execute();
                $log_query->close();
                
            } else {
                $_SESSION['error'] = "Error uploading file. Please try again.";
            }
        }
    } else {
        $_SESSION['error'] = "Please select a valid file to upload.";
    }
    
    header("Location: documents.php");
    exit;
}

// ✅ Fetch Documents Data
try {
    // Fetch team leader's projects
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

    // In a real application, you would fetch from a documents table
    // For demo, we'll create sample data
    $sample_documents = [
        ['id' => 1, 'title' => 'Project Requirements Document', 'type' => 'pdf', 'size' => '2.4 MB', 'upload_date' => '2024-01-15', 'uploaded_by' => $leader_name],
        ['id' => 2, 'title' => 'Team Meeting Notes', 'type' => 'docx', 'size' => '1.1 MB', 'upload_date' => '2024-01-14', 'uploaded_by' => $leader_name],
        ['id' => 3, 'title' => 'Q1 Performance Report', 'type' => 'xlsx', 'size' => '3.2 MB', 'upload_date' => '2024-01-10', 'uploaded_by' => $leader_name],
        ['id' => 4, 'title' => 'System Architecture Diagram', 'type' => 'png', 'size' => '4.7 MB', 'upload_date' => '2024-01-08', 'uploaded_by' => $leader_name],
    ];

} catch (Exception $e) {
    error_log("Database error in documents: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Documents | Alpha Tech</title>
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
        .file-type-pdf { color: #ef4444; }
        .file-type-doc { color: #3b82f6; }
        .file-type-xls { color: #22c55e; }
        .file-type-img { color: #8b5cf6; }
        .file-type-other { color: #94a3b8; }
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
                <h1 class="text-2xl font-bold gradient-text">Team Documents</h1>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- ✅ Upload Document Form -->
                <div class="card p-6 lg:col-span-1">
                    <h3 class="text-xl font-semibold mb-6">Upload New Document</h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="space-y-4">
                            <!-- Document Title -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Document Title <span class="text-red-400">*</span>
                                </label>
                                <input type="text" name="title" required maxlength="255"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                                       placeholder="Enter document title">
                            </div>

                            <!-- Project Association -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Related Project</label>
                                <select name="project_id" 
                                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500">
                                    <option value="">Select Project (Optional)</option>
                                    <?php if ($projects->num_rows > 0): ?>
                                        <?php while ($project = $projects->fetch_assoc()): ?>
                                            <option value="<?php echo $project['id']; ?>">
                                                <?php echo htmlspecialchars($project['project_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                                <textarea name="description" rows="3" maxlength="500"
                                          class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                                          placeholder="Describe the document..."></textarea>
                            </div>

                            <!-- File Upload -->
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Select File <span class="text-red-400">*</span>
                                </label>
                                <input type="file" name="document" required 
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png"
                                       class="w-full text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                                <p class="text-xs text-slate-400 mt-1">
                                    Max size: 10MB | Allowed: PDF, DOC, XLS, PPT, Images, TXT
                                </p>
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full mt-6 bg-cyan-600 text-white rounded-lg py-3 hover:bg-cyan-700 transition-colors flex items-center justify-center space-x-2">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Upload Document</span>
                        </button>
                    </form>

                    <!-- Upload Guidelines -->
                    <div class="mt-6 p-4 bg-slate-800/50 rounded-lg">
                        <h4 class="font-semibold text-sm mb-2 text-cyan-400">Upload Guidelines</h4>
                        <ul class="text-xs text-slate-400 space-y-1">
                            <li>• Maximum file size: 10MB</li>
                            <li>• Supported formats: PDF, DOC, XLS, PPT, Images</li>
                            <li>• Use descriptive titles for easy searching</li>
                            <li>• Associate with projects for better organization</li>
                        </ul>
                    </div>
                </div>

                <!-- ✅ Documents List -->
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-semibold">Team Documents</h3>
                            <div class="flex space-x-3">
                                <input type="text" id="searchDocuments" placeholder="Search documents..." 
                                       class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                                <select id="typeFilter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500">
                                    <option value="">All Types</option>
                                    <option value="pdf">PDF</option>
                                    <option value="doc">Word</option>
                                    <option value="xls">Excel</option>
                                    <option value="img">Images</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php if (!empty($sample_documents)): ?>
                                <?php foreach ($sample_documents as $doc): 
                                    $file_icon = getFileIcon($doc['type']);
                                    $file_color = getFileColor($doc['type']);
                                ?>
                                    <div class="border border-slate-700 rounded-lg p-4 hover:border-slate-600 transition-colors">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-start space-x-4 flex-1">
                                                <div class="w-12 h-12 rounded-lg <?php echo $file_color; ?> bg-opacity-10 flex items-center justify-center">
                                                    <i class="<?php echo $file_icon; ?> text-xl <?php echo $file_color; ?>"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="font-semibold text-cyan-400"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                                    <div class="flex items-center space-x-4 text-sm text-slate-400 mt-1">
                                                        <span class="flex items-center space-x-1">
                                                            <i class="fas fa-file"></i>
                                                            <span><?php echo strtoupper($doc['type']); ?> • <?php echo $doc['size']; ?></span>
                                                        </span>
                                                        <span class="flex items-center space-x-1">
                                                            <i class="fas fa-calendar"></i>
                                                            <span><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></span>
                                                        </span>
                                                        <span class="flex items-center space-x-1">
                                                            <i class="fas fa-user"></i>
                                                            <span><?php echo htmlspecialchars($doc['uploaded_by']); ?></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button onclick="downloadDocument(<?php echo $doc['id']; ?>)" 
                                                        class="text-green-400 hover:text-green-300 p-2 rounded-lg hover:bg-slate-700/50 transition-colors"
                                                        title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button onclick="shareDocument(<?php echo $doc['id']; ?>)" 
                                                        class="text-blue-400 hover:text-blue-300 p-2 rounded-lg hover:bg-slate-700/50 transition-colors"
                                                        title="Share">
                                                    <i class="fas fa-share"></i>
                                                </button>
                                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                        class="text-red-400 hover:text-red-300 p-2 rounded-lg hover:bg-slate-700/50 transition-colors"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-12 text-slate-400">
                                    <i class="fas fa-folder-open text-4xl mb-3 opacity-50"></i>
                                    <p>No documents uploaded yet</p>
                                    <p class="text-sm mt-2">Upload your first document using the form</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Storage Usage -->
                        <div class="mt-6 pt-6 border-t border-slate-700">
                            <div class="flex justify-between items-center text-sm text-slate-400 mb-2">
                                <span>Storage Usage</span>
                                <span>5.2 MB of 100 MB used</span>
                            </div>
                            <div class="w-full bg-slate-700 rounded-full h-2">
                                <div class="h-2 rounded-full bg-cyan-500" style="width: 5.2%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-6">
                        <div class="card p-4 text-center">
                            <i class="fas fa-file-pdf text-red-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">PDF Files</p>
                            <p class="text-2xl font-bold text-red-400">12</p>
                        </div>
                        <div class="card p-4 text-center">
                            <i class="fas fa-file-word text-blue-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">Word Docs</p>
                            <p class="text-2xl font-bold text-blue-400">8</p>
                        </div>
                        <div class="card p-4 text-center">
                            <i class="fas fa-file-excel text-green-400 text-xl mb-2"></i>
                            <p class="text-sm font-medium">Excel Files</p>
                            <p class="text-2xl font-bold text-green-400">5</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search and Filter functionality
        document.getElementById('searchDocuments').addEventListener('input', filterDocuments);
        document.getElementById('typeFilter').addEventListener('change', filterDocuments);

        function filterDocuments() {
            const searchTerm = document.getElementById('searchDocuments').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const documents = document.querySelectorAll('.border-slate-700');
            
            documents.forEach(doc => {
                const title = doc.querySelector('h4').textContent.toLowerCase();
                const fileType = doc.querySelector('.fa-file').nextSibling.textContent.trim().toLowerCase();
                const matchesSearch = title.includes(searchTerm);
                const matchesType = !typeFilter || fileType.includes(typeFilter);
                
                doc.style.display = (matchesSearch && matchesType) ? 'block' : 'none';
            });
        }

        function downloadDocument(docId) {
            alert('Downloading document ID: ' + docId);
            // In real application: window.location.href = `download_document.php?id=${docId}`;
        }

        function shareDocument(docId) {
            alert('Sharing document ID: ' + docId);
            // In real application: open share modal
        }

        function deleteDocument(docId) {
            if (confirm('Are you sure you want to delete this document?')) {
                alert('Deleting document ID: ' + docId);
                // In real application: window.location.href = `delete_document.php?id=${docId}`;
            }
        }

        // File type helper functions
        function getFileIcon(fileType) {
            const icons = {
                'pdf': 'fas fa-file-pdf',
                'doc': 'fas fa-file-word',
                'docx': 'fas fa-file-word',
                'xls': 'fas fa-file-excel',
                'xlsx': 'fas fa-file-excel',
                'ppt': 'fas fa-file-powerpoint',
                'pptx': 'fas fa-file-powerpoint',
                'jpg': 'fas fa-file-image',
                'jpeg': 'fas fa-file-image',
                'png': 'fas fa-file-image',
                'txt': 'fas fa-file-alt'
            };
            return icons[fileType.toLowerCase()] || 'fas fa-file';
        }

        function getFileColor(fileType) {
            const colors = {
                'pdf': 'file-type-pdf',
                'doc': 'file-type-doc',
                'docx': 'file-type-doc',
                'xls': 'file-type-xls',
                'xlsx': 'file-type-xls',
                'ppt': 'file-type-other',
                'pptx': 'file-type-other',
                'jpg': 'file-type-img',
                'jpeg': 'file-type-img',
                'png': 'file-type-img',
                'txt': 'file-type-other'
            };
            return colors[fileType.toLowerCase()] || 'file-type-other';
        }
    </script>
</body>
</html>

<?php
// Helper function for file icons and colors
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'txt' => 'fas fa-file-alt'
    ];
    return $icons[strtolower($fileType)] ?? 'fas fa-file';
}

function getFileColor($fileType) {
    $colors = [
        'pdf' => 'text-red-400',
        'doc' => 'text-blue-400',
        'docx' => 'text-blue-400',
        'xls' => 'text-green-400',
        'xlsx' => 'text-green-400',
        'ppt' => 'text-amber-400',
        'pptx' => 'text-amber-400',
        'jpg' => 'text-purple-400',
        'jpeg' => 'text-purple-400',
        'png' => 'text-purple-400',
        'txt' => 'text-slate-400'
    ];
    return $colors[strtolower($fileType)] ?? 'text-slate-400';
}
?>