<?php
// Database setup
$db_file = 'checklist.db';

// Create database connection
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS checklists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    items TEXT NOT NULL,
    password TEXT NOT NULL,
    locked INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_checklist') {
    $checklist_id = (int)$_POST['checklist_id'];
    $title = trim($_POST['title']);
    $items_input = trim($_POST['items']);
    $password = trim($_POST['password']);
    
    try {
        // Verify password before editing and get current items
        $stmt = $pdo->prepare("SELECT password, items FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            if ($password === $checklist['password']) {
                // Get current items with their checked status
                $current_items = json_decode($checklist['items'], true);
                
                // Process new items list
                $new_items_array = [];
                if (!empty($items_input)) {
                    $items_list = explode(',', $items_input);
                    foreach ($items_list as $item) {
                        $item_trimmed = trim($item);
                        if (!empty($item_trimmed)) {
                            // Preserve checked status if item exists, otherwise set to 0
                            $new_items_array[$item_trimmed] = isset($current_items[$item_trimmed]) 
                                ? $current_items[$item_trimmed] 
                                : 0;
                        }
                    }
                }
                
                // Convert to JSON
                $items_json = json_encode($new_items_array);
                
                // Update database
                $stmt = $pdo->prepare("UPDATE checklists SET title = ?, items = ? WHERE id = ?");
                $stmt->execute([$title, $items_json, $checklist_id]);
                
                header('Location: index.php?id=' . $checklist_id . '&message=Checklist+updated+successfully');
                exit;
            } else {
                header('Location: index.php?id=' . $checklist_id . '&error=Invalid+password');
                exit;
            }
        } else {
            header('Location: index.php?error=Checklist+not+found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?error=Error+updating+checklist:' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle lock/unlock request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock') {
    $checklist_id = (int)$_POST['checklist_id'];
    $password = trim($_POST['password']);
    
    try {
        // Verify password and get current lock status
        $stmt = $pdo->prepare("SELECT password, locked FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            if ($password === $checklist['password']) {
                $new_lock_status = $checklist['locked'] ? 0 : 1;
                
                $stmt = $pdo->prepare("UPDATE checklists SET locked = ? WHERE id = ?");
                $stmt->execute([$new_lock_status, $checklist_id]);
                
                header('Location: index.php?id=' . $checklist_id . '&message=Checklist+' . ($new_lock_status ? 'locked' : 'unlocked') . '+successfully');
                exit;
            } else {
                header('Location: index.php?id=' . $checklist_id . '&error=Invalid+password');
                exit;
            }
        } else {
            header('Location: index.php?error=Checklist+not+found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?error=Error+updating+checklist:' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_checklist') {
    $checklist_id = (int)$_POST['checklist_id'];
    $password = trim($_POST['password']);
    
    try {
        // Verify password and get current items
        $stmt = $pdo->prepare("SELECT password, items, locked FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            if ($password === $checklist['password']) {
                // Reset all items to unchecked (0)
                $items = json_decode($checklist['items'], true);
                foreach ($items as $key => $value) {
                    $items[$key] = 0;
                }
                $new_items_json = json_encode($items);
                
                // Update database - reset items and unlock if it was locked
                $stmt = $pdo->prepare("UPDATE checklists SET items = ?, locked = 0 WHERE id = ?");
                $stmt->execute([$new_items_json, $checklist_id]);
                
                header('Location: index.php?id=' . $checklist_id . '&message=Checklist+reset+successfully');
                exit;
            } else {
                header('Location: index.php?id=' . $checklist_id . '&error=Invalid+password');
                exit;
            }
        } else {
            header('Location: index.php?error=Checklist+not+found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?error=Error+resetting+checklist:' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_checklist') {
    $checklist_id = (int)$_POST['checklist_id'];
    $password = trim($_POST['password']);
    
    try {
        // Verify password before deleting
        $stmt = $pdo->prepare("SELECT password FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            if ($password === $checklist['password']) {
                $stmt = $pdo->prepare("DELETE FROM checklists WHERE id = ?");
                $stmt->execute([$checklist_id]);
                
                if ($stmt->rowCount() > 0) {
                    header('Location: index.php?message=Checklist+deleted+successfully');
                    exit;
                } else {
                    header('Location: index.php?error=Checklist+not+found');
                    exit;
                }
            } else {
                header('Location: index.php?id=' . $checklist_id . '&error=Invalid+password');
                exit;
            }
        } else {
            header('Location: index.php?error=Checklist+not+found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?error=Error+deleting+checklist:' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle clone request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clone_checklist') {
    $checklist_id = (int)$_POST['checklist_id'];
    
    try {
        // Get current checklist data
        $stmt = $pdo->prepare("SELECT title, items FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            // Generate new random password (12 characters)
            $new_password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12);
            
            // Replace the 11th character with a random special character (more secure)
            $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
            $new_password[10] = $specialChars[random_int(0, strlen($specialChars) - 1)];
            
            // Reset all items to unchecked (0)
            $items = json_decode($checklist['items'], true);
            foreach ($items as $key => $value) {
                $items[$key] = 0;
            }
            $new_items_json = json_encode($items);
            
            // Insert cloned checklist into database
            $stmt = $pdo->prepare("INSERT INTO checklists (title, items, password) VALUES (?, ?, ?)");
            $stmt->execute([$checklist['title'] . ' (Copy)', $new_items_json, $new_password]);
            
            // Get the ID of the newly created checklist
            $new_checklist_id = $pdo->lastInsertId();
            
            // Store password in session to show on the next page
            session_start();
            $_SESSION['new_checklist_password'] = $new_password;
            $_SESSION['new_checklist_id'] = $new_checklist_id;
            
            header('Location: index.php?id=' . $new_checklist_id . '&message=Checklist+cloned+successfully');
            exit;
        } else {
            header('Location: index.php?error=Checklist+not+found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?error=Error+cloning+checklist:' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle checkbox toggle via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_item') {
    $checklist_id = (int)$_POST['checklist_id'];
    $item_name = trim($_POST['item_name']);
    $checked = (int)$_POST['checked'];
    
    try {
        // Check if checklist is locked
        $stmt = $pdo->prepare("SELECT items, locked FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checklist) {
            if ($checklist['locked']) {
                echo json_encode(['success' => false, 'error' => 'Checklist is locked']);
                exit;
            }
            
            $items = json_decode($checklist['items'], true);
            if (isset($items[$item_name])) {
                $items[$item_name] = $checked;
                $new_items_json = json_encode($items);
                
                // Update database
                $stmt = $pdo->prepare("UPDATE checklists SET items = ? WHERE id = ?");
                $stmt->execute([$new_items_json, $checklist_id]);
                
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Checklist or item not found']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submission for new checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && !isset($_POST['action'])) {
    $title = trim($_POST['title']);
    $items_input = trim($_POST['items']);
    
    if (!empty($title)) {
        // Process items
        $items_array = [];
        if (!empty($items_input)) {
            $items_list = explode(',', $items_input);
            foreach ($items_list as $item) {
                $item_trimmed = trim($item);
                if (!empty($item_trimmed)) {
                    $items_array[$item_trimmed] = 0;
                }
            }
        }
        
        // Generate random password (12 characters)
        $password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12);
        
        // Replace the 11th character with a random special character (more secure)
        $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password[10] = $specialChars[random_int(0, strlen($specialChars) - 1)];
        
        // Convert to JSON
        $items_json = json_encode($items_array);
        
        // Insert into database
        try {
            $stmt = $pdo->prepare("INSERT INTO checklists (title, items, password) VALUES (?, ?, ?)");
            $stmt->execute([$title, $items_json, $password]);
            
            // Get the ID of the newly created checklist
            $new_checklist_id = $pdo->lastInsertId();
            
            // Store password in session to show on the next page
            session_start();
            $_SESSION['new_checklist_password'] = $password;
            $_SESSION['new_checklist_id'] = $new_checklist_id;
            
            // Redirect to the new checklist page
            header('Location: index.php?id=' . $new_checklist_id);
            exit;
            
        } catch (PDOException $e) {
            $error = "Error saving checklist: " . $e->getMessage();
        }
    } else {
        $error = "Title is required!";
    }
}

// Check if we're viewing a specific checklist
$viewing_checklist = null;
$editing_checklist = null;
if (isset($_GET['id'])) {
    $checklist_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM checklists WHERE id = ?");
        $stmt->execute([$checklist_id]);
        $viewing_checklist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($viewing_checklist) {
            $viewing_checklist['items'] = json_decode($viewing_checklist['items'], true);
            $viewing_checklist['locked'] = (bool)$viewing_checklist['locked'];
            
            // Calculate completion status
            $total_items = count($viewing_checklist['items']);
            $completed_items = 0;
            if ($total_items > 0) {
                foreach ($viewing_checklist['items'] as $status) {
                    if ($status) {
                        $completed_items++;
                    }
                }
                $viewing_checklist['completed'] = ($completed_items === $total_items);
                $viewing_checklist['completion_percentage'] = ($completed_items / $total_items) * 100;
            } else {
                $viewing_checklist['completed'] = false;
                $viewing_checklist['completion_percentage'] = 0;
            }
            
            // If we're in edit mode, prepare the items for the form
            if (isset($_GET['edit'])) {
                $editing_checklist = $viewing_checklist;
                $editing_checklist['items_string'] = implode(', ', array_keys($editing_checklist['items']));
            }
        }
    } catch (PDOException $e) {
        $error = "Error loading checklist: " . $e->getMessage();
    }
}

// Check for messages from redirects
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Check if we should show the password for a newly created checklist
session_start();
$new_checklist_password = $_SESSION['new_checklist_password'] ?? null;
$new_checklist_id = $_SESSION['new_checklist_id'] ?? null;

// Clear the session variables after displaying them
if ($new_checklist_password && $viewing_checklist && $viewing_checklist['id'] == $new_checklist_id) {
    unset($_SESSION['new_checklist_password']);
    unset($_SESSION['new_checklist_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        if ($editing_checklist) {
            echo 'Edit: ' . htmlspecialchars($editing_checklist['title']);
        } elseif ($viewing_checklist) {
            echo htmlspecialchars($viewing_checklist['title']);
        } else {
            echo 'Checklist Manager';
        }
    ?></title>
    <style>
        :root {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #3d3d3d;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --border-color: #444444;
            --accent-color: #4CAF50;
            --accent-hover: #45a049;
            --danger-color: #f44336;
            --danger-hover: #da190b;
            --warning-color: #ff9800;
            --warning-hover: #e68900;
            --info-color: #2196F3;
            --info-hover: #0b7dda;
            --edit-color: #9C27B0;
            --edit-hover: #7B1FA2;
            --warning-bg: #332900;
            --warning-border: #665200;
            --warning-text: #ffd700;
            --info-bg: #0c2e34;
            --info-border: #1a5c6b;
            --info-text: #87ceeb;
            --success-bg: #1a331f;
            --success-border: #2d6b3d;
            --success-text: #90ee90;
            --error-bg: #331a1a;
            --error-border: #6b2d2d;
            --error-text: #ee9090;
            --incomplete-bg: #332900;
            --incomplete-border: #665200;
            --incomplete-text: #ffd700;
            --complete-bg: #1a331f;
            --complete-border: #2d6b3d;
            --complete-text: #90ee90;
        }

        .light-theme {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f5f5;
            --bg-tertiary: #e0e0e0;
            --text-primary: #333333;
            --text-secondary: #666666;
            --border-color: #dddddd;
            --accent-color: #4CAF50;
            --accent-hover: #45a049;
            --danger-color: #f44336;
            --danger-hover: #da190b;
            --warning-color: #ff9800;
            --warning-hover: #e68900;
            --info-color: #2196F3;
            --info-hover: #0b7dda;
            --edit-color: #9C27B0;
            --edit-hover: #7B1FA2;
            --warning-bg: #fff3cd;
            --warning-border: #ffeaa7;
            --warning-text: #856404;
            --info-bg: #d1ecf1;
            --info-border: #bee5eb;
            --info-text: #0c5460;
            --success-bg: #d4edda;
            --success-border: #c3e6cb;
            --success-text: #155724;
            --error-bg: #f8d7da;
            --error-border: #f5c6cb;
            --error-text: #721c24;
            --incomplete-bg: #fff3cd;
            --incomplete-border: #ffeaa7;
            --incomplete-text: #856404;
            --complete-bg: #d4edda;
            --complete-border: #c3e6cb;
            --complete-text: #155724;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        .container { 
            max-width: 800px; 
            margin: 0 auto; 
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }

        .theme-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: transform 0.3s;
        }

        .sun { background: #ffd700; color: #333; }
        .moon { background: #666; color: #fff; }

        .form-group { margin-bottom: 15px; }
        
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: var(--text-primary);
        }
        
        input[type="text"], textarea, input[type="password"] { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid var(--border-color); 
            border-radius: 4px; 
            box-sizing: border-box;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: border-color 0.3s;
        }

        input[type="text"]:focus, textarea:focus, input[type="password"]:focus {
            border-color: var(--accent-color);
            outline: none;
        }
        
        textarea { height: 100px; }
        
        button { 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin: 5px;
            transition: background-color 0.3s;
        }
        
        .btn-primary { 
            background-color: var(--accent-color); 
        }
        
        .btn-primary:hover { 
            background-color: var(--accent-hover); 
        }
        
        .btn-warning { 
            background-color: var(--warning-color); 
        }
        
        .btn-warning:hover { 
            background-color: var(--warning-hover); 
        }
        
        .btn-info { 
            background-color: var(--info-color); 
        }
        
        .btn-info:hover { 
            background-color: var(--info-hover); 
        }
        
        .btn-danger { 
            background-color: var(--danger-color); 
        }
        
        .btn-danger:hover { 
            background-color: var(--danger-hover); 
        }
        
        .btn-edit { 
            background-color: var(--edit-color); 
        }
        
        .btn-edit:hover { 
            background-color: var(--edit-hover); 
        }
        
        .message { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px; 
            border: 1px solid;
        }
        
        .success { 
            background-color: var(--success-bg); 
            color: var(--success-text); 
            border-color: var(--success-border); 
        }
        
        .error { 
            background-color: var(--error-bg); 
            color: var(--error-text); 
            border-color: var(--error-border); 
        }
        
        .info { 
            background-color: var(--info-bg); 
            color: var(--info-text); 
            border-color: var(--info-border); 
        }
        
        .back-link { 
            display: inline-block; 
            margin-bottom: 20px; 
            color: var(--accent-color); 
            text-decoration: none; 
        }
        
        .back-link:hover { text-decoration: underline; }
        
        .checklist-item { 
            margin: 10px 0; 
            padding: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 4px;
            display: flex;
            align-items: center;
            background-color: var(--bg-secondary);
            transition: background-color 0.3s;
        }

        .checklist-item.incomplete {
            background-color: var(--incomplete-bg);
            border-color: var(--incomplete-border);
        }

        .checklist-item.completed-item {
            background-color: var(--complete-bg);
            border-color: var(--complete-border);
        }

        .checklist-item.locked {
            opacity: 0.7;
            background-color: var(--bg-tertiary);
        }
        
        .checklist-item label { 
            margin-left: 10px; 
            font-weight: normal;
            flex-grow: 1;
            color: var(--text-primary);
        }
        
        .checklist-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .checklist-item input[type="checkbox"]:disabled {
            cursor: not-allowed;
        }
        
        .completed { 
            background-color: var(--success-bg);
            border-color: var(--success-border);
        }
        
        .checklist-header { 
            border-bottom: 2px solid var(--accent-color); 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }

        .status-badges {
            margin: 10px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .completion-status {
            background-color: var(--incomplete-bg);
            color: var(--incomplete-text);
            border: 1px solid var(--incomplete-border);
        }

        .completion-status.complete {
            background-color: var(--complete-bg);
            color: var(--complete-text);
            border: 1px solid var(--complete-border);
        }

        .lock-status {
            background-color: var(--warning-bg);
            color: var(--warning-text);
            border: 1px solid var(--warning-border);
        }

        .lock-status.unlocked {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }
        
        .actions-section { 
            margin-top: 30px; 
            padding-top: 20px; 
            border-top: 2px solid var(--border-color); 
            text-align: center;
        }

        .actions-section h3 {
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .completion-bar {
            width: 100%;
            height: 8px;
            background-color: var(--bg-tertiary);
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }

        .completion-fill {
            height: 100%;
            background-color: var(--accent-color);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .password-warning {
			background-color: var(--error-bg);
			border: 1px solid var(--error-border);
			color: var(--error-text);
			padding: 10px;
			border-radius: 4px;
			margin: 10px 0;
			text-align: center;
			font-weight: bold;
		}
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .modal-buttons {
            margin-top: 20px;
            text-align: center;
        }
        
        .password-input {
            margin: 15px 0;
        }
        
        .password-input label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
    </style>
</head>
<body class="dark-theme">
    <div class="theme-toggle" id="themeToggle">
        <div class="theme-icon sun">☀️</div>
        <div class="theme-icon moon">🌙</div>
    </div>

    <div class="container">
        <?php if ($editing_checklist): ?>
            <!-- Edit Checklist Page -->
            <a href="index.php?id=<?php echo htmlspecialchars($editing_checklist['id']); ?>" class="back-link">← Back to Checklist</a>
            
            <h1>Edit Checklist</h1>
            
            <?php if (isset($message)): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit_checklist">
                <input type="hidden" name="checklist_id" value="<?php echo htmlspecialchars($editing_checklist['id']); ?>">
                
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editing_checklist['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="items">Items (comma-separated):</label>
                    <textarea id="items" name="items" placeholder="item1, item2, item3"><?php echo htmlspecialchars($editing_checklist['items_string']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="password">Password (required to edit):</label>
                    <input type="password" id="password" name="password" placeholder="Enter checklist password" required>
                </div>
                
                <button type="submit" class="btn-edit">✏️ Apply Changes</button>
                <a href="index.php?id=<?php echo htmlspecialchars($editing_checklist['id']); ?>" class="btn-primary">Cancel</a>
            </form>

        <?php elseif ($viewing_checklist): ?>
            <!-- Checklist View Page -->
            <a href="index.php" class="back-link">← Home</a>
            
            <div class="checklist-header">
                <h1><?php echo htmlspecialchars($viewing_checklist['title']); ?></h1>
                
                <div class="status-badges">
                    <span class="status-badge completion-status <?php echo $viewing_checklist['completed'] ? 'complete' : ''; ?>">
                        <?php echo $viewing_checklist['completed'] ? '✅ Completed' : '🟡 Incomplete'; ?>
                    </span>
                    <span class="status-badge lock-status <?php echo $viewing_checklist['locked'] ? '' : 'unlocked'; ?>">
                        <?php echo $viewing_checklist['locked'] ? '🔒 Locked' : '🔓 Unlocked'; ?>
                    </span>
                </div>
                
                <p><strong>ID:</strong> <?php echo htmlspecialchars($viewing_checklist['id']); ?> | 
                   <strong>Created:</strong> <?php echo htmlspecialchars($viewing_checklist['created_at']); ?></p>
                
                <?php if (count($viewing_checklist['items']) > 0): ?>
                <div class="completion-bar">
                    <div class="completion-fill" style="width: <?php echo $viewing_checklist['completion_percentage']; ?>%"></div>
                </div>
                <p><strong>Progress:</strong> <?php echo round($viewing_checklist['completion_percentage']); ?>% complete 
                   (<?php echo array_sum($viewing_checklist['items']); ?>/<?php echo count($viewing_checklist['items']); ?> items)</p>
                <?php endif; ?>
            </div>
            
            <?php if ($new_checklist_password): ?>
                <div class="password-warning">
                    <strong>Important:</strong> Save this password to manage this checklist later: 
                    <span style="font-family: monospace; font-size: 1.2em;"><?php echo htmlspecialchars($new_checklist_password); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($message)): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (empty($viewing_checklist['items'])): ?>
                <p>No items in this checklist.</p>
            <?php else: ?>
                <div class="items-container">
                    <?php foreach ($viewing_checklist['items'] as $item_name => $status): ?>
                        <div class="checklist-item <?php echo $status ? 'completed-item' : 'incomplete'; ?> <?php echo $viewing_checklist['locked'] ? 'locked' : ''; ?>">
                            <input type="checkbox" 
                                   id="item_<?php echo htmlspecialchars($item_name); ?>" 
                                   data-checklist-id="<?php echo htmlspecialchars($viewing_checklist['id']); ?>"
                                   data-item-name="<?php echo htmlspecialchars($item_name); ?>"
                                   <?php echo $status ? 'checked' : ''; ?>
                                   <?php echo $viewing_checklist['locked'] ? 'disabled' : ''; ?>>
                            <label for="item_<?php echo htmlspecialchars($item_name); ?>">
                                <?php echo htmlspecialchars($item_name); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Actions Section -->
            <div class="actions-section">
                <h3>Checklist Management</h3>
                
                <button type="button" class="btn-edit" onclick="showEditModal()">✏️ Edit Checklist</button>
                <button type="button" class="btn-warning" onclick="showLockModal()">
                    <?php echo $viewing_checklist['locked'] ? '🔓 Unlock Checklist' : '🔒 Lock Checklist'; ?>
                </button>
                <button type="button" class="btn-info" onclick="showResetModal()">🔄 Reset Checklist</button>
				<button type="button" class="btn-primary" onclick="showCloneModal()">📋 Clone Checklist</button>
                <button type="button" class="btn-danger" onclick="showDeleteModal()">🗑️ Delete Checklist</button>
            </div>
            
            <!-- Edit Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h3>Edit Checklist</h3>
                    <p>To edit the checklist "<strong><?php echo htmlspecialchars($viewing_checklist['title']); ?></strong>", please enter the password.</p>
                    <p>You will be able to change the title and items.</p>
                    
                    <div class="password-input">
                        <label for="editPassword">Enter password to continue:</label>
                        <input type="password" id="editPassword" name="password" placeholder="Enter checklist password" required>
                    </div>
                    
                    <div class="modal-buttons">
                        <form id="editForm" method="GET" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($viewing_checklist['id']); ?>">
                            <input type="hidden" name="edit" value="1">
                            <input type="hidden" name="password" id="hiddenEditPassword">
                            <button type="button" onclick="submitEditForm()" class="btn-edit">Continue to Edit</button>
                        </form>
                        <button type="button" onclick="hideEditModal()">Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Lock/Unlock Modal -->
            <div id="lockModal" class="modal">
                <div class="modal-content">
                    <h3><?php echo $viewing_checklist['locked'] ? 'Unlock Checklist' : 'Lock Checklist'; ?></h3>
                    <p>Are you sure you want to <?php echo $viewing_checklist['locked'] ? 'unlock' : 'lock'; ?> the checklist "<strong><?php echo htmlspecialchars($viewing_checklist['title']); ?></strong>"?</p>
                    <p><?php echo $viewing_checklist['locked'] ? 'Unlocking will allow changes to checkboxes.' : 'Locking will prevent any changes to checkboxes.'; ?></p>
                    
                    <div class="password-input">
                        <label for="lockPassword">Enter password to confirm:</label>
                        <input type="password" id="lockPassword" name="password" placeholder="Enter checklist password" required>
                    </div>
                    
                    <div class="modal-buttons">
                        <form id="lockForm" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_lock">
                            <input type="hidden" name="checklist_id" value="<?php echo htmlspecialchars($viewing_checklist['id']); ?>">
                            <input type="hidden" name="password" id="hiddenLockPassword">
                            <button type="button" onclick="submitLockForm()" class="btn-warning"><?php echo $viewing_checklist['locked'] ? 'Unlock Checklist' : 'Lock Checklist'; ?></button>
                        </form>
                        <button type="button" onclick="hideLockModal()">Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Reset Modal -->
            <div id="resetModal" class="modal">
                <div class="modal-content">
                    <h3>Reset Checklist</h3>
                    <p>Are you sure you want to reset the checklist "<strong><?php echo htmlspecialchars($viewing_checklist['title']); ?></strong>"?</p>
                    <p>This will uncheck all items and unlock the checklist.</p>
                    <p><strong>This action cannot be undone.</strong></p>
                    
                    <div class="password-input">
                        <label for="resetPassword">Enter password to confirm:</label>
                        <input type="password" id="resetPassword" name="password" placeholder="Enter checklist password" required>
                    </div>
                    
                    <div class="modal-buttons">
                        <form id="resetForm" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reset_checklist">
                            <input type="hidden" name="checklist_id" value="<?php echo htmlspecialchars($viewing_checklist['id']); ?>">
                            <input type="hidden" name="password" id="hiddenResetPassword">
                            <button type="button" onclick="submitResetForm()" class="btn-info">Reset Checklist</button>
                        </form>
                        <button type="button" onclick="hideResetModal()">Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <h3>Confirm Deletion</h3>
                    <p>Are you sure you want to delete the checklist "<strong><?php echo htmlspecialchars($viewing_checklist['title']); ?></strong>"?</p>
                    <p>This action cannot be undone.</p>
                    
                    <div class="password-input">
                        <label for="deletePassword">Enter password to confirm:</label>
                        <input type="password" id="deletePassword" name="password" placeholder="Enter checklist password" required>
                    </div>
                    
                    <div class="modal-buttons">
                        <form id="deleteForm" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_checklist">
                            <input type="hidden" name="checklist_id" value="<?php echo htmlspecialchars($viewing_checklist['id']); ?>">
                            <input type="hidden" name="password" id="hiddenPassword">
                            <button type="button" onclick="submitDeleteForm()" class="btn-danger">Delete Checklist</button>
                        </form>
                        <button type="button" onclick="hideDeleteModal()">Cancel</button>
                    </div>
                </div>
            </div>
			
			<!-- Clone Modal -->
			<div id="cloneModal" class="modal">
				<div class="modal-content">
					<h3>Clone Checklist</h3>
					<p>Are you sure you want to clone the checklist "<strong><?php echo htmlspecialchars($viewing_checklist['title']); ?></strong>"?</p>
					<p>This will create a new checklist with the same title (with "Copy" added), same items (all unchecked), and a new password.</p>
					
					<div class="modal-buttons">
						<form id="cloneForm" method="POST" style="display: inline;">
							<input type="hidden" name="action" value="clone_checklist">
							<input type="hidden" name="checklist_id" value="<?php echo htmlspecialchars($viewing_checklist['id']); ?>">
							<button type="submit" class="btn-primary">Clone Checklist</button>
						</form>
						<button type="button" onclick="hideCloneModal()">Cancel</button>
					</div>
				</div>
			</div>
            
            <script>
            // Theme functionality
            function initializeTheme() {
                const savedTheme = localStorage.getItem('theme') || 'dark';
                const body = document.body;
                const themeToggle = document.getElementById('themeToggle');
                
                // Set initial theme
                if (savedTheme === 'light') {
                    body.classList.remove('dark-theme');
                    body.classList.add('light-theme');
                    themeToggle.style.transform = 'rotate(180deg)';
                } else {
                    body.classList.remove('light-theme');
                    body.classList.add('dark-theme');
                    themeToggle.style.transform = 'rotate(0deg)';
                }
                
                // Toggle theme
                themeToggle.addEventListener('click', function() {
                    if (body.classList.contains('dark-theme')) {
                        body.classList.remove('dark-theme');
                        body.classList.add('light-theme');
                        localStorage.setItem('theme', 'light');
                        themeToggle.style.transform = 'rotate(180deg)';
                    } else {
                        body.classList.remove('light-theme');
                        body.classList.add('dark-theme');
                        localStorage.setItem('theme', 'dark');
                        themeToggle.style.transform = 'rotate(0deg)';
                    }
                });
            }

            // Checkbox toggle functionality
            document.addEventListener('DOMContentLoaded', function() {
                initializeTheme();
                
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const checklistId = this.dataset.checklistId;
                        const itemName = this.dataset.itemName;
                        const checked = this.checked ? 1 : 0;
                        
                        // Update visual state immediately
                        const itemDiv = this.closest('.checklist-item');
                        if (checked) {
                            itemDiv.classList.remove('incomplete');
                            itemDiv.classList.add('completed-item');
                        } else {
                            itemDiv.classList.remove('completed-item');
                            itemDiv.classList.add('incomplete');
                        }
                        
                        // Send AJAX request to update database
                        fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=toggle_item&checklist_id=${checklistId}&item_name=${encodeURIComponent(itemName)}&checked=${checked}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                // Revert on error
                                this.checked = !this.checked;
                                if (this.checked) {
                                    itemDiv.classList.remove('incomplete');
                                    itemDiv.classList.add('completed-item');
                                } else {
                                    itemDiv.classList.remove('completed-item');
                                    itemDiv.classList.add('incomplete');
                                }
                                if (data.error === 'Checklist is locked') {
                                    alert('Checklist is locked. Unlock it to make changes.');
                                }
                                console.error('Error updating item:', data.error);
                            } else {
                                // Update completion status visually
                                updateCompletionStatus();
                            }
                        })
                        .catch(error => {
                            // Revert on network error
                            this.checked = !this.checked;
                            if (this.checked) {
                                itemDiv.classList.remove('incomplete');
                                itemDiv.classList.add('completed-item');
                            } else {
                                itemDiv.classList.remove('completed-item');
                                itemDiv.classList.add('incomplete');
                            }
                            console.error('Network error:', error);
                        });
                    });
                });
            });

            // Function to update completion status (would need page refresh for full update)
            function updateCompletionStatus() {
                // This would require more complex AJAX to update without refresh
                // For now, the page will show updated status on next load
            }
            
            // Edit modal functionality
            function showEditModal() {
                document.getElementById('editModal').style.display = 'block';
                document.getElementById('editPassword').value = '';
                document.getElementById('editPassword').focus();
            }
            
            function hideEditModal() {
                document.getElementById('editModal').style.display = 'none';
            }
            
            function submitEditForm() {
                const password = document.getElementById('editPassword').value;
                if (!password) {
                    alert('Please enter the password to edit this checklist.');
                    return;
                }
                
                // Set the password in the hidden field
                document.getElementById('hiddenEditPassword').value = password;
                
                // Submit the form
                document.getElementById('editForm').submit();
            }
            
            // Lock modal functionality
            function showLockModal() {
                document.getElementById('lockModal').style.display = 'block';
                document.getElementById('lockPassword').value = '';
                document.getElementById('lockPassword').focus();
            }
            
            function hideLockModal() {
                document.getElementById('lockModal').style.display = 'none';
            }
            
            function submitLockForm() {
                const password = document.getElementById('lockPassword').value;
                if (!password) {
                    alert('Please enter the password to <?php echo $viewing_checklist['locked'] ? 'unlock' : 'lock'; ?> this checklist.');
                    return;
                }
                
                // Set the password in the hidden field
                document.getElementById('hiddenLockPassword').value = password;
                
                // Submit the form
                document.getElementById('lockForm').submit();
            }
            
            // Reset modal functionality
            function showResetModal() {
                document.getElementById('resetModal').style.display = 'block';
                document.getElementById('resetPassword').value = '';
                document.getElementById('resetPassword').focus();
            }
            
            function hideResetModal() {
                document.getElementById('resetModal').style.display = 'none';
            }
            
            function submitResetForm() {
                const password = document.getElementById('resetPassword').value;
                if (!password) {
                    alert('Please enter the password to reset this checklist.');
                    return;
                }
                
                // Set the password in the hidden field
                document.getElementById('hiddenResetPassword').value = password;
                
                // Submit the form
                document.getElementById('resetForm').submit();
            }
            
            // Delete modal functionality
            function showDeleteModal() {
                document.getElementById('deleteModal').style.display = 'block';
                document.getElementById('deletePassword').value = '';
                document.getElementById('deletePassword').focus();
            }
            
            function hideDeleteModal() {
                document.getElementById('deleteModal').style.display = 'none';
            }
            
            function submitDeleteForm() {
                const password = document.getElementById('deletePassword').value;
                if (!password) {
                    alert('Please enter the password to delete this checklist.');
                    return;
                }
                
                // Set the password in the hidden field
                document.getElementById('hiddenPassword').value = password;
                
                // Submit the form
                document.getElementById('deleteForm').submit();
            }
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                const editModal = document.getElementById('editModal');
				const cloneModal = document.getElementById('cloneModal');
                const lockModal = document.getElementById('lockModal');
                const resetModal = document.getElementById('resetModal');
                const deleteModal = document.getElementById('deleteModal');
                
                if (event.target === editModal) hideEditModal();
				if (event.target === cloneModal) hideCloneModal();
                if (event.target === lockModal) hideLockModal();
                if (event.target === resetModal) hideResetModal();
                if (event.target === deleteModal) hideDeleteModal();
            }
            
            // Allow pressing Enter to submit forms
            document.getElementById('editPassword')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') submitEditForm();
            });
            
            document.getElementById('lockPassword')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') submitLockForm();
            });
            
            document.getElementById('resetPassword')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') submitResetForm();
            });
            
            document.getElementById('deletePassword')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') submitDeleteForm();
            });
			
			document.getElementById('clonePassword')?.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') submitCloneForm();
			});
			
			// Clone modal functionality
			function showCloneModal() {
				document.getElementById('cloneModal').style.display = 'block';
			}

			function hideCloneModal() {
				document.getElementById('cloneModal').style.display = 'none';
			}

			
            </script>
            
        <?php else: ?>
            <!-- Main Page (Home) -->
            <h1>Checklist Manager</h1>
            
            <?php if (isset($message)): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="items">Items (comma-separated):</label>
                    <textarea id="items" name="items" placeholder="item1, item2, item3"></textarea>
                </div>
                
                <button type="submit" class="btn-primary">Create Checklist</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
    // Initialize theme for main page (when no checklist is being viewed)
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');
        
        // Set initial theme
        if (savedTheme === 'light') {
            body.classList.remove('dark-theme');
            body.classList.add('light-theme');
            themeToggle.style.transform = 'rotate(180deg)';
        } else {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            themeToggle.style.transform = 'rotate(0deg)';
        }
        
        // Toggle theme
        themeToggle.addEventListener('click', function() {
            if (body.classList.contains('dark-theme')) {
                body.classList.remove('dark-theme');
                body.classList.add('light-theme');
                localStorage.setItem('theme', 'light');
                themeToggle.style.transform = 'rotate(180deg)';
            } else {
                body.classList.remove('light-theme');
                body.classList.add('dark-theme');
                localStorage.setItem('theme', 'dark');
                themeToggle.style.transform = 'rotate(0deg)';
            }
        });
    });
    </script>
</body>
</html>
