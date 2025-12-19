<?php
require_once 'config.php';

// Security Check
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    // Validate CSRF Token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        logError('CSRF token validation failed', ['action' => $action, 'user_id' => $userId]);
        header("Location: index.php?view=admin&error=csrf_error");
        exit;
    }

    // Validate User ID
    if ($userId <= 0) {
        header("Location: index.php?view=admin&error=invalid_user");
        exit;
    }

    // Prevent Self-Action
    if ($userId === $_SESSION['user_id']) {
        header("Location: index.php?view=admin&error=cannot_modify_self");
        exit;
    }

    $conn = getDBConnection();

    if ($action === 'delete_user') {
        // Delete User
        $stmt = $conn->prepare("DELETE FROM br_users WHERE id = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            header("Location: index.php?view=admin&msg=user_deleted");
        } else {
            header("Location: index.php?view=admin&error=db_error");
        }
    } elseif ($action === 'toggle_role') {
        // Toggle Admin Role
        // First check current role
        $check = $conn->prepare("SELECT is_admin FROM br_users WHERE id = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        $result = $check->get_result();

        if ($row = $result->fetch_assoc()) {
            $newRole = $row['is_admin'] ? 0 : 1;
            $update = $conn->prepare("UPDATE br_users SET is_admin = ? WHERE id = ?");
            $update->bind_param("ii", $newRole, $userId);

            if ($update->execute()) {
                $msg = $newRole ? "user_promoted" : "user_demoted";
                header("Location: index.php?view=admin&msg=$msg");
            } else {
                header("Location: index.php?view=admin&error=db_error");
            }
        } else {
            header("Location: index.php?view=admin&error=user_not_found");
        }
    } elseif ($action === 'update_user') {
        // Update User Details
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email)) {
            header("Location: index.php?view=edit_user&id=$userId&error=empty_fields");
            exit;
        }

        // Build Update Query
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE br_users SET username = ?, email = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $email, $hashed, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE br_users SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $email, $userId);
        }

        if ($stmt->execute()) {
            header("Location: index.php?view=admin&msg=user_updated");
        } else {
            header("Location: index.php?view=edit_user&id=$userId&error=db_error");
        }
    } else {
        header("Location: index.php?view=admin&error=unknown_action");
    }
} else {
    header("Location: index.php?view=admin");
}
?>