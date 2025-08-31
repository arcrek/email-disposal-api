<?php
declare(strict_types=1);

// Email API Configuration
const EMAIL_FILE = __DIR__ . '/../data/email.txt';
const MAX_LOCK_TIME = 15; // seconds
const CLEANUP_INTERVAL = 5; // seconds

// Database Configuration - Update these values
const DB_HOST = 'localhost';
const DB_NAME = 'your_database_name';  // Change this to your database name
const DB_USER = 'your_username';       // Change this to your username
const DB_PASS = 'your_password';       // Change this to your password
const DB_CHARSET = 'utf8mb4';

class EmailManager {
    private PDO $pdo;
    
    public function __construct() {
        $this->initDatabase();
    }
    
    private function initDatabase(): void {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Create table if not exists - basic structure for compatibility
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS emails (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                is_locked TINYINT(1) DEFAULT 0,
                locked_at INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Try to add optimization column if it doesn't exist
        $this->addOptimizationColumn();
        
        // Create essential indexes for performance
        $this->createIndexes();
    }
    
    private function addOptimizationColumn(): void {
        try {
            // Check if email_hash column exists
            $stmt = $this->pdo->query("SHOW COLUMNS FROM emails LIKE 'email_hash'");
            if ($stmt->rowCount() == 0) {
                // Try to add the optimization column
                $this->pdo->exec("ALTER TABLE emails ADD COLUMN email_hash CHAR(32) AS (MD5(email)) STORED");
            }
        } catch (Exception $e) {
            // This is optional optimization, continue without it
            error_log("Optimization column not added: " . $e->getMessage());
        }
    }
    
    private function createIndexes(): void {
        try {
            // Essential indexes that should always work
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_locked_available ON emails(is_locked, id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_locked_at ON emails(locked_at)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_created ON emails(created_at)");
            
            // Check if email_hash column exists before creating its index
            $stmt = $this->pdo->query("SHOW COLUMNS FROM emails LIKE 'email_hash'");
            if ($stmt->rowCount() > 0) {
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_hash ON emails(email_hash)");
            }
        } catch (Exception $e) {
            // Log index creation issues but don't stop the application
            error_log("Index creation warning: " . $e->getMessage());
        }
    }
    
    public function loadEmailsFromFile(): int {
        if (!file_exists(EMAIL_FILE)) return 0;
        
        $count = 0;
        $batchSize = 10000; // Process in batches for memory efficiency
        $emails = [];
        
        // Stream file reading for large files
        $handle = fopen(EMAIL_FILE, 'r');
        if (!$handle) return 0;
        
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO emails (email) VALUES (?)");
        
        while (($line = fgets($handle)) !== false) {
            $email = trim($line);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
                
                // Process in batches to avoid memory issues
                if (count($emails) >= $batchSize) {
                    $count += $this->insertEmailBatch($emails, $stmt);
                    $emails = [];
                }
            }
        }
        
        // Process remaining emails
        if (!empty($emails)) {
            $count += $this->insertEmailBatch($emails, $stmt);
        }
        
        fclose($handle);
        return $count;
    }
    
    private function insertEmailBatch(array $emails, PDOStatement $stmt): int {
        $this->pdo->beginTransaction();
        try {
            $count = 0;
            foreach ($emails as $email) {
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) $count++;
            }
            $this->pdo->commit();
            return $count;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getRandomEmail(): ?string {
        $this->cleanupExpiredLocks();
        
        // Optimized random selection for large datasets
        // Uses efficient sampling method instead of ORDER BY RANDOM()
        $this->pdo->beginTransaction();
        try {
            // Get count of available emails
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM emails WHERE is_locked = 0");
            $stmt->execute();
            $totalAvailable = $stmt->fetchColumn();
            
            if ($totalAvailable == 0) {
                $this->pdo->rollBack();
                return null;
            }
            
            // Generate random offset for efficient selection
            $randomOffset = rand(0, $totalAvailable - 1);
            
            // Get email using LIMIT with OFFSET (much faster than ORDER BY RANDOM())
            $stmt = $this->pdo->prepare("
                SELECT id, email FROM emails 
                WHERE is_locked = 0 
                LIMIT 1 OFFSET ?
            ");
            $stmt->execute([$randomOffset]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }
            
            // Lock the email atomically
            $stmt = $this->pdo->prepare("
                UPDATE emails 
                SET is_locked = 1, locked_at = ? 
                WHERE id = ? AND is_locked = 0
            ");
            $stmt->execute([time(), $row['id']]);
            
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return null; // Already locked by another request
            }
            
            $this->pdo->commit();
            return $row['email'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function cleanupExpiredLocks(): void {
        $expiredTime = time() - MAX_LOCK_TIME;
        $stmt = $this->pdo->prepare("
            UPDATE emails 
            SET is_locked = 0, locked_at = 0 
            WHERE is_locked = 1 AND locked_at < ?
        ");
        $stmt->execute([$expiredTime]);
    }
    
    public function getStats(): array {
        // Use optimized query with cached results
        static $cache = null;
        static $cacheTime = 0;
        
        // Cache for 30 seconds to reduce database load
        if ($cache && (time() - $cacheTime) < 30) {
            return $cache;
        }
        
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(is_locked) as locked,
                COUNT(*) - SUM(is_locked) as available
            FROM emails
        ");
        
        $cache = $stmt->fetch(PDO::FETCH_ASSOC);
        $cacheTime = time();
        
        return $cache;
    }
    
    public function getEmailsPaginated(int $page = 1, int $limit = 100, string $search = ''): array {
        $offset = ($page - 1) * $limit;
        $searchCondition = '';
        $params = [];
        
        if (!empty($search)) {
            $searchCondition = "WHERE email LIKE ?";
            $params[] = "%{$search}%";
        }
        
        // Ultra-fast pagination - get data first, estimate total
        $stmt = $this->pdo->prepare("
            SELECT id, email, is_locked, locked_at, 
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_display
            FROM emails 
            {$searchCondition}
            ORDER BY id DESC 
            LIMIT ? OFFSET ?
        ");
        
        $queryParams = $params;
        $queryParams[] = $limit + 1; // Get one extra to check if there are more
        $queryParams[] = $offset;
        $stmt->execute($queryParams);
        $allEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasMore = count($allEmails) > $limit;
        $emails = $hasMore ? array_slice($allEmails, 0, $limit) : $allEmails;
        
        // Fast total estimation for large datasets
        if (empty($search) && $page <= 5) {
            // Use cached stats for first few pages
            $stats = $this->getStats();
            $total = $stats['total'];
        } else {
            // Estimate total based on current page
            if ($hasMore) {
                $total = ($page * $limit) + 1; // At least one more page
            } else {
                $total = $offset + count($emails);
            }
        }
        
        return [
            'emails' => $emails,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $hasMore ? $page + 1 : $page,
            'hasMore' => $hasMore,
            'estimated' => !empty($search) || $page > 5
        ];
    }
    
    public function saveEmailsBulk(array $emails): int {
        $count = 0;
        $batchSize = 10000;
        $currentBatch = [];
        
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO emails (email) VALUES (?)");
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $currentBatch[] = $email;
                
                if (count($currentBatch) >= $batchSize) {
                    $count += $this->insertEmailBatch($currentBatch, $stmt);
                    $currentBatch = [];
                }
            }
        }
        
        // Process remaining emails
        if (!empty($currentBatch)) {
            $count += $this->insertEmailBatch($currentBatch, $stmt);
        }
        
        return $count;
    }
    
    public function deleteEmailsBulk(array $emailIds): int {
        if (empty($emailIds)) return 0;
        
        $placeholders = str_repeat('?,', count($emailIds) - 1) . '?';
        $stmt = $this->pdo->prepare("DELETE FROM emails WHERE id IN ({$placeholders})");
        $stmt->execute($emailIds);
        
        return $stmt->rowCount();
    }
    
    public function clearAllLocks(): int {
        $stmt = $this->pdo->prepare("UPDATE emails SET is_locked = 0, locked_at = 0 WHERE is_locked = 1");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    // Make PDO accessible for benchmark script but protected
    public function __get($property) {
        if ($property === 'pdo') {
            return $this->pdo;
        }
        throw new Exception("Property {$property} not accessible");
    }
}

// Auto-reload emails on file change
function checkEmailFileChange(): void {
    static $lastModified = 0;
    
    if (!file_exists(EMAIL_FILE)) {
        return; // Skip if file doesn't exist
    }
    
    $currentModified = filemtime(EMAIL_FILE);
    
    if ($currentModified !== $lastModified) {
        try {
            $manager = new EmailManager();
            $manager->loadEmailsFromFile();
            $lastModified = $currentModified;
        } catch (Exception $e) {
            // Log error but don't break the API
            error_log("File change check failed: " . $e->getMessage());
        }
    }
}
