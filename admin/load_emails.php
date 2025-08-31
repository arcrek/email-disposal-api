<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once '../api/config.php';

try {
    if (!file_exists(EMAIL_FILE)) {
        echo json_encode([
            'success' => true,
            'emails' => []
        ]);
        exit;
    }
    
    $emails = array_filter(array_map('trim', file(EMAIL_FILE)));
    
    echo json_encode([
        'success' => true,
        'emails' => array_values($emails)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load emails',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("Load Emails Error: " . $e->getMessage());
}
