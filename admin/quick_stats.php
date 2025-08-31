<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: public, max-age=30'); // Cache for 30 seconds
require_once '../api/config.php';

try {
    // Ultra-fast stats query using approximate counts for large tables
    $manager = new EmailManager();
    
    // For tables with millions of records, use approximate counts
    $stmt = $manager->pdo->query("SELECT COUNT(*) FROM emails");
    $total = $stmt->fetchColumn();
    
    if ($total > 100000) {
        // Use faster approximate query for large datasets
        $stmt = $manager->pdo->query("
            SELECT 
                table_rows as total,
                0 as locked,
                table_rows as available
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'emails'
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get actual lock count if needed
        $stmt = $manager->pdo->query("SELECT COUNT(*) FROM emails WHERE is_locked = 1");
        $locked = $stmt->fetchColumn();
        
        $stats['locked'] = (int)$locked;
        $stats['available'] = (int)$stats['total'] - (int)$locked;
        $stats['total'] = (int)$stats['total'];
        $stats['approximate'] = true;
    } else {
        // Use exact counts for smaller datasets
        $stats = $manager->getStats();
        $stats['approximate'] = false;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics',
        'debug' => $e->getMessage()
    ]);
    error_log("Quick Stats Error: " . $e->getMessage());
}
?>
