<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any stray output
ob_start();

require_once 'config.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

try {
    // Session check
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in.");
    }

    // Clean buffer before JSON
    ob_end_clean();
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'Invalid action.'];

    $conn = getDBConnection();
    if ($conn->connect_error) {
        throw new Exception("DB Connection Error: " . $conn->connect_error);
    }

    // ACTION: SAVE PROGRESS
    if (isset($_POST['action']) && $_POST['action'] === 'save_progress') {

        $session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        $word_index = isset($_POST['word_index']) ? (int) $_POST['word_index'] : 0;
        $reading_time = isset($_POST['reading_time']) ? (int) $_POST['reading_time'] : 0;
        $user_id = $_SESSION['user_id'];

        if ($session_id <= 0) {
            throw new Exception("Invalid session ID.");
        }

        // Calculate WPM
        $wpm = 0;
        if (isset($_POST['wpm'])) {
            $wpm = (float) $_POST['wpm'];
        } elseif ($reading_time > 0 && $word_index > 0) {
            $wpm = round(($word_index / $reading_time) * 60, 2);
        }
        $wpmInt = (int) $wpm;

        // Determine status
        $status = 'in_progress';
        if (isset($_POST['is_complete']) && $_POST['is_complete'] === 'true') {
            $status = 'completed';
        }

        // Update Query
        $query = "UPDATE br_reading_sessions 
                  SET current_word_index = ?, 
                      total_reading_time = total_reading_time + ?, 
                      average_wpm = ?,
                      status = ?,
                      paused_at = NOW() 
                  WHERE session_id = ? AND user_id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("SQL Prepare Failed: " . $conn->error);
        }

        $stmt->bind_param("iidsii", $word_index, $reading_time, $wpmInt, $status, $session_id, $user_id);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Saved successfully', 'wpm' => $wpmInt];
        } else {
            throw new Exception("Execute Failed: " . $stmt->error);
        }
        $stmt->close();
    }

    // ACTION: DELETE SESSION
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_session') {
        $session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        $user_id = $_SESSION['user_id'];

        if ($session_id <= 0) {
            throw new Exception("Invalid session ID.");
        }

        // 1. Get document info associated with this session
        $query = "SELECT d.doc_id, d.file_path 
                  FROM br_documents d 
                  JOIN br_reading_sessions rs ON d.doc_id = rs.doc_id 
                  WHERE rs.session_id = ? AND rs.user_id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt)
            throw new Exception("Prepare fetch failed: " . $conn->error);

        $stmt->bind_param("ii", $session_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $doc_id = $row['doc_id'];
            $file_path = $row['file_path'];
            $stmt->close();

            // 2. Delete the session
            $delStmt = $conn->prepare("DELETE FROM br_reading_sessions WHERE session_id = ? AND user_id = ?");
            $delStmt->bind_param("ii", $session_id, $user_id);

            if ($delStmt->execute()) {
                $delStmt->close();

                // 3. Check if any OTHER sessions use this document
                $chkStmt = $conn->prepare("SELECT COUNT(*) as count FROM br_reading_sessions WHERE doc_id = ?");
                $chkStmt->bind_param("i", $doc_id);
                $chkStmt->execute();
                $chkResult = $chkStmt->get_result();
                $chkRow = $chkResult->fetch_assoc();
                $chkStmt->close();

                // 4. If no other sessions, delete document and file
                if ($chkRow['count'] == 0) {
                    $docDelStmt = $conn->prepare("DELETE FROM br_documents WHERE doc_id = ?");
                    $docDelStmt->bind_param("i", $doc_id);
                    $docDelStmt->execute();
                    $docDelStmt->close();

                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                $response = ['success' => true, 'message' => 'Session deleted successfully.'];
            } else {
                throw new Exception("Delete Failed: " . $delStmt->error);
            }
        } else {
            throw new Exception("Session not found or permission denied.");
        }
    }

    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    if (ob_get_length())
        ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Server Error: " . $e->getMessage()]);
}
?>