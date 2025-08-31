<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once '../api/config.php';

try {
    $manager = new EmailManager();
    $stats = $manager->getStats();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("Stats Error: " . $e->getMessage());
}
