<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

// Initialize login attempt tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Reset counter after 15 minutes
if (time() - $_SESSION['last_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Check if user is locked out
if ($_SESSION['login_attempts'] >= 5) {
    $timeRemaining = 900 - (time() - $_SESSION['last_attempt_time']);
    $minutesRemaining = ceil($timeRemaining / 60);
    $error = "Too many failed login attempts. Please try again in {$minutesRemaining} minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif ($_SESSION['login_attempts'] >= 5) {
        // Already handled above, just skip
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, email, password_hash, is_admin FROM br_users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                // Successful login - reset attempts
                $_SESSION['login_attempts'] = 0;

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];

                // Redirect based on role
                if ($user['is_admin']) {
                    header("Location: index.php?view=admin");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = 'Invalid username or password.';
                logError('Failed login attempt', ['username' => $username, 'attempts' => $_SESSION['login_attempts']]);
            }
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $error = 'Invalid username or password.';
            logError('Failed login attempt - user not found', ['username' => $username]);
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bionic Reading Converter</title>
    <?php include 'includes/css_loader.php'; ?>
</head>

<body>
    <div class="auth-container">
        <h2>Login</h2>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'registered'): ?>
            <div class="success"
                style="padding: 10px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
                Registration successful! You can now login.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required
                    value="<?php echo htmlspecialchars($username ?? ''); ?>" autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" style="width: 100%; padding: 12px; font-size: 1em;">
                Login
            </button>
        </form>

        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="index.php">Back to Home</a></p>
        </div>
    </div>
</body>

</html>