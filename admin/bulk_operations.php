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
    $operation = $input['operation'] ?? '';
    
    $manager = new EmailManager();
    
    switch ($operation) {
        case 'bulk_add':
            $emails = $input['emails'] ?? [];
            if (empty($emails)) {
                echo json_encode(['success' => false, 'message' => 'No emails provided']);
                exit;
            }
            
            $count = $manager->saveEmailsBulk($emails);
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Added {$count} emails successfully"
            ]);
            break;
            
        case 'bulk_delete':
            $emailIds = $input['email_ids'] ?? [];
            if (empty($emailIds)) {
                echo json_encode(['success' => false, 'message' => 'No email IDs provided']);
                exit;
            }
            
            $count = $manager->deleteEmailsBulk($emailIds);
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Deleted {$count} emails successfully"
            ]);
            break;
            
        case 'clear_locked':
            // Force unlock all locked emails
            $count = $manager->clearAllLocks();
            
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Unlocked {$count} emails"
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid operation']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("Bulk Operations Error: " . $e->getMessage());
}
