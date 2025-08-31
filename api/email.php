<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';

try {
    // Skip file change check for now to avoid issues
    // checkEmailFileChange();
    
    $manager = new EmailManager();
    $email = $manager->getRandomEmail();
    
    if ($email === null) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'no_emails_available',
            'message' => 'No available emails at this time',
            'timestamp' => time()
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'email' => $email,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    
    // Show detailed error for debugging (remove in production)
    $errorDetails = [
        'success' => false,
        'error' => 'system_error',
        'message' => 'Internal server error',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => time()
    ];
    
    echo json_encode($errorDetails);
    
    // Log error
    error_log("Email API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
