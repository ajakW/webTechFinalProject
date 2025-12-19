<?php

function getUserDetails($userId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, email, created_at, is_admin FROM br_users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getReadingSessions()
{
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];

    // Join sessions with documents to get document info
    $query = "SELECT s.*, d.title as original_title, d.word_count 
              FROM br_reading_sessions s 
              JOIN br_documents d ON s.doc_id = d.doc_id 
              WHERE s.user_id = ? 
              ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Database Query Error (getReadingSessions): " . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    return $sessions;
}

function getReadingData($sessionId)
{
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];

    // Verify session belongs to user and get file path
    $query = "SELECT s.*, d.file_path, d.title 
              FROM br_reading_sessions s 
              JOIN br_documents d ON s.doc_id = d.doc_id 
              WHERE s.session_id = ? AND s.user_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Database Query Error (getReadingData): " . $conn->error);
    }
    $stmt->bind_param("ii", $sessionId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($data = $result->fetch_assoc()) {
        if (file_exists($data['file_path'])) {
            $data['content'] = file_get_contents($data['file_path']);
            return $data;
        } else {
            // Handle missing file
            logError("Reading file missing", ['path' => $data['file_path']]);
            return null;
        }
    }

    return null;
}
