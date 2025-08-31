<?php
declare(strict_types=1);

// Database Initialization Script
require_once 'api/config.php';

// Set content type for web or CLI
if (isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: text/plain');
    echo "=== Email API Database Initialization ===\n\n";
} else {
    echo "Initializing Email API Database...\n";
}

try {
    // Create data directory if it doesn't exist
    $dataDir = dirname(EMAIL_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
        echo "Created data directory.\n";
    }
    
    // Test database connection
    echo "Testing database connection...\n";
    $manager = new EmailManager();
    echo "✓ Database connection successful.\n";
    echo "✓ Tables and indexes created.\n";
    
    // Load emails from file
    if (file_exists(EMAIL_FILE)) {
        $count = $manager->loadEmailsFromFile();
        echo "✓ Loaded {$count} emails from email.txt.\n";
    } else {
        echo "⚠ No email.txt file found. Please add emails via admin panel.\n";
    }
    
    // Show stats
    $stats = $manager->getStats();
    echo "\nDatabase Statistics:\n";
    echo "- Total emails: {$stats['total']}\n";
    echo "- Available: {$stats['available']}\n";
    echo "- Locked: {$stats['locked']}\n";
    
    echo "\n=== Initialization Complete! ===\n";
    
    if (isset($_SERVER['HTTP_HOST'])) {
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
        echo "\nQuick Links:\n";
        echo "• API endpoint: {$baseUrl}/api/email\n";
        echo "• Admin panel: {$baseUrl}/admin/\n";
        echo "\nTest API:\n";
        echo "curl {$baseUrl}/api/email\n";
    } else {
        echo "API endpoint: /api/email\n";
        echo "Admin panel: /admin/\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\nDatabase connection failed. Please check:\n";
        echo "1. Database credentials in api/config.php\n";
        echo "2. Database user has proper permissions\n";
        echo "3. Database exists and is accessible\n";
    }
    
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "\nTip: Use setup.php for guided database configuration.\n";
    }
    
    exit(1);
}
