<?php
// Ensure this script can only be accessed via POST and by a logged-in user
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit("Method Not Allowed");
}

require_once 'config.php';
requireLogin();

// IMPORTANT: Requires composer libraries: phpword, pdfparser, vanderlee/syllable
require 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use Smalot\PdfParser\Parser;

// --- JSON RESPONSE HEADER ---
header('Content-Type: application/json');

// --- TEXT EXTRACTION FUNCTIONS (kept from original) ---

// Recursive function to extract text from all element types
function extractTextFromElement($element)
{
    $text = "";

    // Handle TextRun elements (they contain other elements)
    if ($element instanceof TextRun) {
        foreach ($element->getElements() as $subElement) {
            $text .= extractTextFromElement($subElement);
        }
    }
    // Handle Text elements directly
    elseif ($element instanceof Text) {
        $text .= $element->getText();
    }
    // Handle elements with getText() method
    elseif (method_exists($element, 'getText')) {
        $textValue = $element->getText();
        if (is_string($textValue)) {
            $text .= $textValue;
        }
    }
    // Handle elements with getElements() method (like paragraphs, tables, etc.)
    elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $subElement) {
            $text .= extractTextFromElement($subElement);
        }
    }
    // Handle Table elements
    elseif (method_exists($element, 'getRows')) {
        foreach ($element->getRows() as $row) {
            if (method_exists($row, 'getCells')) {
                foreach ($row->getCells() as $cell) {
                    if (method_exists($cell, 'getElements')) {
                        foreach ($cell->getElements() as $subElement) {
                            $text .= extractTextFromElement($subElement);
                        }
                    }
                }
            }
        }
    }

    return $text;
}

// --- UPLOAD & SESSION LOGIC ---

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (isset($_FILES['docfile'])) {
    $user_id = getCurrentUserId();
    $file = $_FILES['docfile'];
    $material_name = trim($_POST['material_name'] ?? 'Untitled Document');

    // 1. Validate Upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'File upload error: ' . $file['error'];
        logError('File upload error', ['user_id' => $user_id, 'error_code' => $file['error']]);
        echo json_encode($response);
        exit;
    }

    // 2. Validate file size (10MB max)
    $maxFileSize = 10 * 1024 * 1024; // 10MB in bytes
    if ($file['size'] > $maxFileSize) {
        $response['message'] = 'File too large. Maximum size is 10MB.';
        logError('File size exceeded', ['user_id' => $user_id, 'file_size' => $file['size']]);
        echo json_encode($response);
        exit;
    }

    $fileTmp = $file['tmp_name'];
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['docx', 'pdf'];
    if (!in_array($fileExt, $allowedExtensions)) {
        $response['message'] = 'Error: Invalid file extension. Please upload a .docx or .pdf file.';
        logError('Invalid file extension', ['user_id' => $user_id, 'extension' => $fileExt]);
        echo json_encode($response);
        exit;
    }

    try {
        $extracted_text = "";

        // 2. Extract Text based on file type
        if ($fileExt === 'docx') {
            $doc = IOFactory::load($fileTmp);
            foreach ($doc->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $extracted_text .= extractTextFromElement($element) . "\n\n";
                }
            }
        } elseif ($fileExt === 'pdf') {
            $parser = new Parser();
            $pdf = $parser->parseFile($fileTmp);
            // Iterate pages to separate them clearly
            $pages = $pdf->getPages();
            foreach ($pages as $page) {
                $extracted_text .= $page->getText() . "\n\n";
            }
        }

        // Clean up text but PRESERVE PARAGRAPHS (double newlines)
        // 1. Normalize line endings
        $extracted_text = str_replace(["\r\n", "\r"], "\n", $extracted_text);
        // 2. Collapse multiple spaces within lines but keep newlines
        $extracted_text = preg_replace('/[ \t]+/', ' ', $extracted_text);
        // 3. Ensure paragraphs are separated by exactly two newlines
        $extracted_text = preg_replace('/\n\s*\n/', "\n\n", $extracted_text);

        $extracted_text = trim($extracted_text);
        // Use whitespace splitting to match JavaScript word counting
        $word_count = count(preg_split('/\s+/', $extracted_text, -1, PREG_SPLIT_NO_EMPTY));

        if ($word_count < 10) {
            $response['message'] = 'The extracted text is too short. Please upload a longer document.';
            logError('Document text too short', ['user_id' => $user_id, 'word_count' => $word_count]);
            echo json_encode($response);
            exit;
        }

        // 3. Save File to Server
        $upload_dir = 'user_uploads/' . $user_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $unique_filename = bin2hex(random_bytes(16)) . '.txt';
        $target_path = $upload_dir . $unique_filename;

        // Save the *extracted text* as a simple TXT file for easy reading later
        if (file_put_contents($target_path, $extracted_text) === false) {
            throw new Exception("Could not save extracted content to storage.");
        }

        // 4. Save Metadata and Session to Database
        $conn = getDBConnection();
        $conn->begin_transaction();

        // Insert into documents table
        $stmt_doc = $conn->prepare("INSERT INTO br_documents (user_id, title, file_path, word_count) VALUES (?, ?, ?, ?)");
        $stmt_doc->bind_param("issi", $user_id, $fileName, $target_path, $word_count);
        $stmt_doc->execute();
        $doc_id = $conn->insert_id;
        $stmt_doc->close();

        // Create initial reading session
        $stmt_session = $conn->prepare("INSERT INTO br_reading_sessions (user_id, doc_id, material_name, current_word_index) VALUES (?, ?, ?, 0)");
        $stmt_session->bind_param("iis", $user_id, $doc_id, $material_name);
        $stmt_session->execute();
        $session_id = $conn->insert_id;
        $stmt_session->close();

        $conn->commit();
        $conn->close();

        $response = [
            'success' => true,
            'message' => 'Document processed and session started!',
            'redirect' => 'index.php?view=read&session_id=' . $session_id
        ];

    } catch (Exception $e) {
        // Rollback transaction and delete file on error
        if (isset($conn))
            $conn->rollback();
        if (isset($target_path) && file_exists($target_path))
            unlink($target_path);

        logError('Document processing error', ['user_id' => $user_id, 'error' => $e->getMessage()]);
        $response['message'] = 'Processing Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'No file uploaded.';
}

echo json_encode($response);
?>