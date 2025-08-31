<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once '../api/config.php';

try {
    // Minimal data needed for initial admin panel load
    $manager = new EmailManager();
    
    // Get basic stats
    $stats = $manager->getStats();
    
    // Get first page of emails with minimal data
    $emails = $manager->getEmailsPaginated(1, 50); // Smaller initial load
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'emails' => [
            'data' => $emails,
            'hasData' => $emails['total'] > 0
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to preload data',
        'debug' => $e->getMessage()
    ]);
    error_log("Preload Error: " . $e->getMessage());
}
?>
