<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be between 3 and 50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/", $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character (@$!%*?&#).';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM br_users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password and insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO br_users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $password_hash);

            if ($stmt->execute()) {
                header("Location: login.php?msg=registered");
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
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
    <title>Register - Bionic Reading Converter</title>
    <?php include 'includes/css_loader.php'; ?>
</head>

<body>
    <div class="auth-container">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" minlength="3" maxlength="50">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>

                <button type="submit" style="width: 100%; padding: 12px; font-size: 1em;">
                    Register
                </button>
            </form>
        <?php endif; ?>

        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <p><a href="index.php">Back to Home</a></p>
        </div>
    </div>

    <script>
        function isStrongPassword(password) {

            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/;
            return regex.test(password);
        }

        document.querySelector('form').addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            let errorMessage = '';

            if (password !== confirmPassword) {
                errorMessage = 'Passwords do not match.';
            } else if (!isStrongPassword(password)) {
                errorMessage = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character (@$!%*?&#).';
            }

            if (errorMessage) {
                e.preventDefault();

                // create or update error div
                let errorDiv = document.querySelector('.error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    const h2 = document.querySelector('h2');
                    h2.parentNode.insertBefore(errorDiv, h2.nextSibling);
                }
                errorDiv.textContent = errorMessage;

                // Clear success message if exists
                const successDiv = document.querySelector('.success');
                if (successDiv) successDiv.remove();
            }
        });
    </script>
</body>

</html>