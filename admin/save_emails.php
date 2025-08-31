<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once '../api/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $emails = $input['emails'] ?? [];
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No emails provided']);
        exit;
    }
    
    // Validate emails
    $validEmails = [];
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validEmails[] = $email;
        }
    }
    
    if (empty($validEmails)) {
        echo json_encode(['success' => false, 'message' => 'No valid emails provided']);
        exit;
    }
    
    // Ensure data directory exists
    $dataDir = dirname(EMAIL_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    // Backup current file
    if (file_exists(EMAIL_FILE)) {
        copy(EMAIL_FILE, EMAIL_FILE . '.backup.' . time());
    }
    
    // Write emails atomically
    $tempFile = EMAIL_FILE . '.tmp';
    file_put_contents($tempFile, implode("\n", $validEmails));
    rename($tempFile, EMAIL_FILE);
    
    // Reload emails into database
    $manager = new EmailManager();
    $count = $manager->loadEmailsFromFile();
    
    echo json_encode([
        'success' => true,
        'count' => count($validEmails),
        'message' => 'Emails saved successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save emails',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("Save Emails Error: " . $e->getMessage());
}
