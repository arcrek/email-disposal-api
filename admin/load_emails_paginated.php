<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once '../api/config.php';

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(1000, max(10, (int)($_GET['limit'] ?? 100))); // Max 1000 per page
    $search = trim($_GET['search'] ?? '');
    
    $manager = new EmailManager();
    $result = $manager->getEmailsPaginated($page, $limit, $search);
    
    echo json_encode([
        'success' => true,
        'data' => $result
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
    error_log("Paginated Load Error: " . $e->getMessage());
}
