<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: public, max-age=10'); // 10 second cache
require_once '../api/config.php';

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(500, max(10, (int)($_GET['limit'] ?? 50))); // Smaller default for speed
    $search = trim($_GET['search'] ?? '');
    
    $manager = new EmailManager();
    
    // Super fast query - minimal data, no expensive operations
    $offset = ($page - 1) * $limit;
    $searchCondition = '';
    $params = [];
    
    if (!empty($search)) {
        $searchCondition = "WHERE email LIKE ?";
        $params[] = "%{$search}%";
    }
    
    // Lightning fast query with minimal data
    $stmt = $manager->pdo->prepare("
        SELECT id, email, is_locked
        FROM emails 
        {$searchCondition}
        ORDER BY id DESC 
        LIMIT ? OFFSET ?
    ");
    
    $queryParams = $params;
    $queryParams[] = $limit + 1; // Get one extra to check pagination
    $queryParams[] = $offset;
    $stmt->execute($queryParams);
    $allEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasMore = count($allEmails) > $limit;
    $emails = $hasMore ? array_slice($allEmails, 0, $limit) : $allEmails;
    
    // Add display formatting
    foreach ($emails as &$email) {
        $email['status'] = $email['is_locked'] ? 'Locked' : 'Available';
        $email['statusClass'] = $email['is_locked'] ? 'status-locked' : 'status-available';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'emails' => $emails,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'nextPage' => $hasMore ? $page + 1 : null,
            'prevPage' => $page > 1 ? $page - 1 : null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load emails',
        'debug' => $e->getMessage()
    ]);
    error_log("Fast Load Error: " . $e->getMessage());
}
?>
