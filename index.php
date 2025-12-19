<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Determine which view to show
$view = $_GET['view'] ?? 'default';
$session_id = $_GET['session_id'] ?? null;

// Handle reading view
if ($view === 'read' && $session_id) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    $sessionData = getReadingData($session_id);
    if (!$sessionData) {
        die("Session not found or access denied.");
    }
}
// Handle dashboard view
elseif ($view === 'dashboard') {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    $sessions = getReadingSessions();
}
// Handle Admin view
elseif ($view === 'admin') {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: index.php");
        exit;
    }

    // Fetch Stats
    $conn = getDBConnection();

    // Count Users
    $result = $conn->query("SELECT COUNT(*) as count FROM br_users");
    $userCount = $result->fetch_assoc()['count'];

    // Count Documents
    $result = $conn->query("SELECT COUNT(*) as count FROM br_documents");
    $docCount = $result->fetch_assoc()['count'];

    // Count Sessions
    $result = $conn->query("SELECT COUNT(*) as count FROM br_reading_sessions");
    $sessionCount = $result->fetch_assoc()['count'];

    // Fetch Users List (limit 50)
    $usersList = [];
    $result = $conn->query("SELECT id, username, email, created_at, is_admin FROM br_users ORDER BY created_at DESC LIMIT 50");
    while ($row = $result->fetch_assoc()) {
        $usersList[] = $row;
    }
}
// Handle Edit User view
elseif ($view === 'edit_user') {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: index.php");
        exit;
    }

    $editUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id, username, email FROM br_users WHERE id = ?");
    $stmt->bind_param("i", $editUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: index.php?view=admin&error=user_not_found");
        exit;
    }

    $editUser = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bionic Reading Tool</title>
    <?php include 'includes/css_loader.php'; ?>
</head>

<body>
    <?php if ($view !== 'dashboard' && $view !== 'read'): ?>
        <div class="navbar">
            <h1>Bionic Reading Converter</h1>
            <div class="navbar-links">
                <?php if (isLoggedIn()): ?>
                    <div class="profile-menu">
                        <?php if (isAdmin()): ?>
                            <a href="index.php?view=admin" class="admin-link"
                                style="margin-right: 15px; font-weight: bold; color: #333;">Admin Panel</a>
                            <a href="logout.php" class="nav-link" style="color: #B93E32;">Logout</a>
                        <?php else: ?>
                            <a href="index.php?view=dashboard" class="profile-icon" title="Dashboard">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </a>
                            <a href="logout.php" class="logout-link">Logout</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($view === 'read' && isset($sessionData)): ?>
        <!-- READING VIEW -->
        <div class="reading-container">
            <div class="reading-header">
                <h2 style="margin-top: 0; color: #1A1A1A;"><?php echo htmlspecialchars($sessionData['material_name']); ?>
                </h2>
                <div class="reading-controls">
                    <div class="progress-info">
                        <div id="progress-text">0% Complete (0 / 0 words)</div>
                        <div style="display: flex; gap: 15px; margin-top: 5px; font-size: 0.95em; color: #666;">
                            <span id="wpm-display" title="Words Per Minute">WPM</span>
                            <span id="reading-time-display" title="Reading Time">0m 0s</span>
                        </div>
                        <div id="status-message"></div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="exportBionicPDF()" class="btn-export" title="Export as PDF">
                            📥 Export PDF
                        </button>
                        <button id="pause-reading-btn" class="btn-pause">Pause & Save</button>
                    </div>
                </div>
            </div>
            <div class="reading-content">
                <div id="output" data-session-id="<?php echo htmlspecialchars($session_id); ?>"
                    data-start-index="<?php echo htmlspecialchars($sessionData['current_word_index']); ?>">
                </div>
                <textarea id="hiddenTextContent"
                    style="display: none;"><?php echo htmlspecialchars($sessionData['content']); ?></textarea>
            </div>
        </div>

    <?php elseif ($view === 'dashboard' && isLoggedIn()): ?>
        <!-- DASHBOARD VIEW -->
        <?php
        $userData = getUserDetails($_SESSION['user_id']);
        include 'views/dashboard.php';
        ?>

    <?php elseif ($view === 'admin'): ?>
        <div class="admin-container">
            <h2>Admin Dashboard</h2>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <?php
                    switch ($_GET['msg']) {
                        case 'user_deleted':
                            echo "User deleted successfully.";
                            break;
                        case 'user_promoted':
                            echo "User promoted to Admin.";
                            break;
                        case 'user_demoted':
                            echo "User privileges revoked.";
                            break;
                        case 'user_updated':
                            echo "User details updated successfully.";
                            break;
                        default:
                            echo "Action completed successfully.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php
                    switch ($_GET['error']) {
                        case 'invalid_user':
                            echo "Invalid user ID.";
                            break;
                        case 'cannot_modify_self':
                            echo "You cannot delete or modify your own account.";
                            break;
                        case 'db_error':
                            echo "Database error occurred.";
                            break;
                        default:
                            echo "An error occurred.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo number_format($userCount); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Documents Processed</h3>
                    <div class="stat-number"><?php echo number_format($docCount); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Reading Sessions</h3>
                    <div class="stat-number"><?php echo number_format($sessionCount); ?></div>
                </div>
            </div>

            <div class="user-table-container">
                <h3>Recent Users</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-user">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <!-- Edit Button -->
                                            <a href="index.php?view=edit_user&id=<?php echo $user['id']; ?>"
                                                class="btn-sm btn-primary" title="Edit User">Edit</a>

                                            <!-- Role Toggle Form -->
                                            <form method="POST" action="admin_action.php" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_role">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <?php if ($user['is_admin']): ?>
                                                    <button type="submit" class="btn-sm btn-warning"
                                                        title="Revoke Admin">Demote</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn-sm btn-primary" title="Make Admin">Promote</button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete Form -->
                                            <form method="POST" action="admin_action.php" style="display:inline;"
                                                onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete User">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Current User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($view === 'edit_user'): ?>
        <div class="admin-container" style="max-width: 600px;">
            <h2>Edit User</h2>

            <div class="user-table-container">
                <form method="POST" action="admin_action.php">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; color:#666;">Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>"
                            required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; color:#666;">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>"
                            required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:5px; color:#666;">New Password <span
                                style="font-size:0.8em; color:#999;">(leave blank to keep current)</span></label>
                        <input type="password" name="password" placeholder="Optional"
                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>

                    <div style="display:flex; gap:10px;">
                        <a href="index.php?view=admin" class="btn-sm"
                            style="text-decoration:none; background:#eee; color:#333; height: 35px; line-height: 25px; text-align:center;">Cancel</a>
                        <button type="submit" class="btn-sm btn-primary" style="flex:1; height: 37px;">Update User</button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>

        <!-- Window 1: Bionic Writing Animation -->
        <section id="window-1" class="story-window" data-window="1">
            <div class="story-content-wrapper">
                <div class="story-overlay">
                    <div class="story-text-content">
                        <h1 class="story-title">Experience <strong>Bio</strong>nic <strong>Rea</strong>ding</h1>
                        <p class="story-description">Revolutionary reading method that guides your eyes through text with
                            artificial fixation points</p>

                        <!-- Live Typing Animation -->
                        <div class="typing-demo">
                            <div class="typing-text" id="typingText"></div>
                            <div class="cursor">|</div>
                        </div>

                        <div class="story-actions">
                            <?php if (isLoggedIn()): ?>

                                <button onclick="showDemo()" class="btn-story-secondary">Try Demo</button>
                            <?php else: ?>
                                <button onclick="showDemo()" class="btn-story-secondary">Try Demo</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Window 2: Document Upload & Reading -->
        <section id="window-2" class="story-window" data-window="2">
            <div class="story-content-wrapper">
                <div class="story-overlay">
                    <div class="story-text-content">
                        <h2 class="story-title">Upload & <span class="highlight-text">Track Your Reading</span></h2>
                        <p class="story-description">A game change for students. All in one dashboard that is curated to
                            keep
                            track your reading
                            progress across sessions. Transform your documents into downloadable Bionic Reading format and
                            get to know
                            your reading speed.
                        </p>

                        <!-- Feature Image -->
                        <div class="study_img">
                            <img src="user_uploads/study_vibe.jpg" class="study_image">
                        </div>

                        <?php if (isLoggedIn()): ?>

                        <?php else: ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Window 3: Multi-Device Experience -->
        <section id="window-3" class="story-window" data-window="3">
            <div class="story-content-wrapper">
                <div class="story-overlay dark-overlay">
                    <div class="device-showcase-section">
                        <div class="showcase-header">
                            <h2>Bionic Reading.</h2>
                            <p class="app-subtitle">Suitable for all your devices</p>
                        </div>

                        <div class="devices-layout">
                            <img src="user_uploads/bionic.png"
                                alt="Bionic Reading on multiple devices - phone, tablet, and laptop" class="bionic_image">
                        </div>

                        <div class="showcase-actions">
                            <a href="https://oxfordlearning.com/what-is-bionic-reading-and-why-should-you-use-it/"
                                class="btn-learn-more" target="_blank" rel="noopener noreferrer">→ Learn more</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hidden Demo Section -->
        <div id="demo-section" class="demo-overlay" style="display: none;">
            <div class="demo-container">
                <div class="demo-header">
                    <h2>Try Bionic Reading Now</h2>
                    <button onclick="hideDemo()" class="demo-close">×</button>
                </div>
                <textarea id="inputText"
                    placeholder="Type or paste text here to see Bionic Reading in action...">The quick brown fox jumps over the lazy dog. Bionic Reading helps you read faster by guiding your eyes through text with artificial fixation points.</textarea>
                <div id="output"></div>
            </div>
        </div>

        <!-- Enhanced Upload Section for Logged In Users -->
        <?php if (isLoggedIn()): ?>
            <div class="upload-section">
                <h2 style="color: #1A1A1A; text-align: center; margin-bottom: 30px;">Upload Your Document</h2>
                <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">

                    <!-- Drag & Drop Upload Area -->
                    <div class="upload-dropzone" id="dropzone">
                        <div class="upload-icon">📄</div>
                        <div class="upload-text">
                            <strong>Import text from PDF or DOCX (Max 5MB)</strong>
                            <p>Drag and drop your file here, or click to browse</p>
                        </div>
                        <input type="file" name="docfile" id="docfile" accept=".docx,.pdf" style="display: none;" />
                    </div>

                    <!-- Upload Options -->
                    <div class="upload-options">
                        <div class="option-group">
                            <label>Fixation (% of Highlighted)</label>
                            <select class="option-select">
                                <option value="medium">Medium - 50%</option>
                                <option value="low">Low - 30%</option>
                                <option value="high">High - 70%</option>
                            </select>
                        </div>
                        <div class="option-group">
                            <label>Contrast</label>
                            <select class="option-select">
                                <option value="none">None</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="option-group">
                            <label>Reading Mode</label>
                            <div class="mode-indicator">Bionic Reading Mode</div>
                        </div>
                    </div>

                    <!-- Material Name Input -->
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="material_name">Material Name:</label>
                        <input type="text" id="material_name" name="material_name"
                            placeholder="Enter a name for this reading material" required>
                    </div>

                    <!-- File Info Card -->
                    <div id="fileCard" class="file-info-card" style="display: none;">
                        <div class="file-details">
                            <strong>Selected File:</strong> <span id="fcName"></span>
                            <div class="file-size" id="fcSize"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="upload-actions">
                        <button type="button" class="btn-reset" onclick="resetUpload()">🔄 Reset</button>
                        <button type="submit" id="convertBtn" disabled class="btn-convert">Convert & Start Reading →</button>
                    </div>

                    <div id="upload-status"></div>
                </form>
            </div>
        <?php else: ?>
            <div
                style="text-align: center; margin: 50px 0; padding: 30px; background: #5B8FA3; color: white; border-radius: 8px;">
                <h2 style="color: white; margin-bottom: 15px;">Ready to Get Started?</h2>
                <p style="margin-bottom: 25px; opacity: 0.9;">Create an account to upload documents and track your reading
                    progress.</p>
                <a href="register.php"
                    style="background: white; color: #5B8FA3; padding: 12px 25px; text-decoration: none; border-radius: 6px; margin: 0 10px; display: inline-block; font-weight: 500;">Create
                    Free Account</a>
                <a href="login.php"
                    style="background: transparent; color: white; border: 2px solid white; padding: 10px 25px; text-decoration: none; border-radius: 6px; margin: 0 10px; display: inline-block;">Sign
                    In</a>
            </div>
        <?php endif; ?>

        <footer>
            <div class="footer">
                <div class="row">
                    <a href="#"><i class="fa fa-facebook"></i></a>
                    <a href="#"><i class="fa fa-instagram"></i></a>
                    <a href="#"><i class="fa fa-youtube"></i></a>
                    <a href="#"><i class="fa fa-twitter"></i></a>
                </div>

                <div class="row">
                    <ul>
                        <li><a href="#">Contact us</a></li>
                        <li><a href="#">Our Services</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                    </ul>
                </div>

                <div class="row">
                    Bionic Reading Converter Copyright © 2025 Bionic Converter - All rights reserved </div>
            </div>
        </footer>
    <?php endif; ?>

    <?php include 'includes/js_loader.php'; ?>
</body>

</html>