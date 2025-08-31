<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email API Database Setup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn {
            background: #2196f3;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background: #1976d2;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d2e;
            border: 1px solid #4caf50;
        }
        .error {
            background: #ffeaea;
            color: #c62828;
            border: 1px solid #f44336;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #2196f3;
            margin-bottom: 20px;
        }
        .code {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email API Database Setup</h1>
        
        <div class="info">
            <strong>Instructions:</strong><br>
            1. Create a MySQL database in your cPanel phpMyAdmin<br>
            2. Enter the database credentials below<br>
            3. Click "Setup Database" to initialize the system
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $_POST['host'] ?? 'localhost';
            $dbname = $_POST['dbname'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($dbname) || empty($username)) {
                echo '<div class="result error">Database name and username are required!</div>';
            } else {
                try {
                    // Test connection
                    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ];
                    
                    $pdo = new PDO($dsn, $username, $password, $options);
                    
                    // Create table
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS emails (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            email VARCHAR(255) UNIQUE NOT NULL,
                            is_locked TINYINT(1) DEFAULT 0,
                            locked_at INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    
                    // Create indexes
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_locked ON emails(is_locked)");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_locked_at ON emails(locked_at)");
                    
                    // Update config file
                    $configPath = __DIR__ . '/api/config.php';
                    $configContent = file_get_contents($configPath);
                    
                    $configContent = str_replace("const DB_HOST = 'localhost';", "const DB_HOST = '{$host}';", $configContent);
                    $configContent = str_replace("const DB_NAME = 'your_database_name';", "const DB_NAME = '{$dbname}';", $configContent);
                    $configContent = str_replace("const DB_USER = 'your_username';", "const DB_USER = '{$username}';", $configContent);
                    $configContent = str_replace("const DB_PASS = 'your_password';", "const DB_PASS = '{$password}';", $configContent);
                    
                    file_put_contents($configPath, $configContent);
                    
                    // Load sample emails
                    $emailFile = __DIR__ . '/data/email.txt';
                    if (file_exists($emailFile)) {
                        $emails = array_filter(array_map('trim', file($emailFile)));
                        $stmt = $pdo->prepare("INSERT IGNORE INTO emails (email) VALUES (?)");
                        $count = 0;
                        foreach ($emails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $stmt->execute([$email]);
                                $count++;
                            }
                        }
                    }
                    
                    echo '<div class="result success">';
                    echo '<strong>✅ Database setup successful!</strong><br>';
                    echo "Database table created with indexes<br>";
                    echo "Configuration file updated<br>";
                    if (isset($count)) {
                        echo "Loaded {$count} sample emails<br>";
                    }
                    echo '<br><strong>Next steps:</strong><br>';
                    echo '• Test API: <a href="api/email.php" target="_blank">api/email.php</a><br>';
                    echo '• Admin panel: <a href="admin/" target="_blank">admin/</a><br>';
                    echo '• Delete this setup.php file for security';
                    echo '</div>';
                    
                } catch (PDOException $e) {
                    echo '<div class="result error">';
                    echo '<strong>❌ Database connection failed!</strong><br>';
                    echo 'Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="result error">';
                    echo '<strong>❌ Setup failed!</strong><br>';
                    echo 'Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            }
        }
        ?>

        <form method="POST">
            <div class="form-group">
                <label for="host">Database Host:</label>
                <input type="text" id="host" name="host" value="localhost" required>
            </div>

            <div class="form-group">
                <label for="dbname">Database Name:</label>
                <input type="text" id="dbname" name="dbname" placeholder="e.g., username_emailapi" required>
            </div>

            <div class="form-group">
                <label for="username">Database Username:</label>
                <input type="text" id="username" name="username" placeholder="e.g., username_dbuser" required>
            </div>

            <div class="form-group">
                <label for="password">Database Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter database password" required>
            </div>

            <button type="submit" class="btn">🚀 Setup Database</button>
        </form>

        <div class="info" style="margin-top: 30px;">
            <strong>📋 cPanel Database Setup Guide:</strong><br>
            1. Go to <strong>cPanel → MySQL Databases</strong><br>
            2. Create new database (e.g., "emailapi")<br>
            3. Create new user with password<br>
            4. Add user to database with <strong>ALL PRIVILEGES</strong><br>
            5. Use the full names (usually prefixed with your username)
        </div>
    </div>
</body>
</html>
