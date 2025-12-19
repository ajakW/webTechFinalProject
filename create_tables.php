<?php
/**
 * Quick Table Creation Script
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Create Database Tables</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #FAF8F3;
        }

        pre {
            background: #FFFFFF;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #E8C99B;
        }

        .success {
            color: #7A9B7A;
            font-weight: bold;
        }

        .error {
            color: #8B4A4A;
            font-weight: bold;
        }

        a {
            color: #5B8FA3;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <h2>Creating Missing Database Tables</h2>
    <pre>
<?php

try {
    echo "Connecting to database...\n";
    $conn = getDBConnection();
    echo "✅ Connected successfully!\n\n";

    // Check if users table exists (required for foreign keys)
    echo "Checking if 'users' table exists...\n";
    $result = $conn->query("SHOW TABLES LIKE 'br_users'");
    if ($result && $result->num_rows > 0) {
        echo "✅ 'br_users' table exists\n\n";
    } else {
        echo "⚠️  'br_users' table does NOT exist. Creating it first...\n";
        $sql = "CREATE TABLE br_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($sql)) {
            echo "✅ 'br_users' table created\n\n";
        } else {
            echo "❌ Error: " . $conn->error . "\n\n";
        }
    }

    // Create documents table
    echo "Creating 'br_documents' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS br_documents (
        doc_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        word_count INT NOT NULL DEFAULT 0,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES br_users(id) ON DELETE CASCADE,
        INDEX idx_user_doc (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✅ 'br_documents' table created successfully!\n\n";
    } else {
        echo "❌ Error creating br_documents table: " . $conn->error . "\n\n";
    }

    // Create reading_sessions table
    echo "Creating 'br_reading_sessions' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS br_reading_sessions (
        session_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        doc_id INT NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        current_word_index INT NOT NULL DEFAULT 0,
        paused_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES br_users(id) ON DELETE CASCADE,
        FOREIGN KEY (doc_id) REFERENCES br_documents(doc_id) ON DELETE CASCADE,
        INDEX idx_user_session (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✅ 'br_reading_sessions' table created successfully!\n\n";
    } else {
        echo "❌ Error creating br_reading_sessions table: " . $conn->error . "\n\n";
    }

    // Verify all tables
    echo "Verifying tables...\n";
    $tables = ['br_users', 'br_documents', 'br_reading_sessions'];
    $all_ok = true;
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  ✅ Table '$table' exists\n";
        } else {
            echo "  ❌ Table '$table' is MISSING\n";
            $all_ok = false;
        }
    }

    if ($all_ok) {
        echo "\n<span class='success'>✅ SUCCESS! All tables are ready.</span>\n";
        echo "\nYou can now upload documents and use the reading session features.\n";
    } else {
        echo "\n<span class='error'>⚠️  Some tables are still missing. Please check the errors above.</span>\n";
    }

    $conn->close();

} catch (Exception $e) {
    echo "<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure MySQL is running in XAMPP\n";
    echo "2. Check if database 'webtech_2025A_ajak_panchol' exists\n";
    echo "3. Verify your database credentials in config.php\n";
}

?>
    </pre>
    <p>
        <a href="index.php">← Back to Home</a> |
        <a href="test_connection.php">Test Connection</a> |
        <a href="setup_database.php">Full Setup</a>
    </p>
</body>

</html>