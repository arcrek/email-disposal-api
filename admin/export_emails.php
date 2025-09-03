<?php
declare(strict_types=1);

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="emails_' . date('Y-m-d') . '.txt"');
require_once '../api/config.php';

try {
    $manager = new EmailManager();
    
    // Get all emails from database
    $stmt = $manager->pdo->query("SELECT email FROM emails ORDER BY id");
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Output emails one per line
    foreach ($emails as $email) {
        echo $email . "\n";
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Export failed: ' . $e->getMessage()
    ]);
    error_log("Export Error: " . $e->getMessage());
}
?>
