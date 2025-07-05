<?php
/**
 * Web Interface for FusionAuth User Import
 * 
 * Provides a simple web interface to trigger the import process
 */

declare(strict_types=1);

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'user_import.php';
        
        $config = require 'config.php';
        $importer = new UserImport($config);
        
        $dryRun = isset($_POST['dry_run']);
        $importOnly = isset($_POST['import_only']);
        $registerOnly = isset($_POST['register_only']);
        
        // Capture output
        ob_start();
        $importer->run($dryRun, $importOnly, $registerOnly);
        $output = ob_get_clean();
        
        $message = "Process completed successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get log file info
$logFile = $_ENV['LOG_FILE'] ?? 'user_import.log';
$logExists = file_exists($logFile);
$logSize = $logExists ? filesize($logFile) : 0;
$logLines = $logExists ? count(file($logFile)) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FusionAuth User Import</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
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
        .checkbox-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
        }
        input[type="checkbox"] {
            margin: 0;
        }
        .btn {
            background-color: #007cba;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn:hover {
            background-color: #005a87;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            margin-top: 0;
            color: #0066cc;
        }
        .log-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .log-info h4 {
            margin-top: 0;
            color: #495057;
        }
        .log-info p {
            margin: 5px 0;
            color: #6c757d;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.exists {
            background-color: #28a745;
            color: white;
        }
        .status.missing {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>FusionAuth User Import</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>About This Tool</h3>
            <p>This tool imports users from your MySQL database to FusionAuth and registers them for League Joe applications.</p>
            <p><strong>Use dry-run mode first</strong> to test the process without making any changes.</p>
        </div>
        
        <div class="log-info">
            <h4>Log File Information</h4>
            <p><strong>Log File:</strong> <?= htmlspecialchars($logFile) ?></p>
            <p><strong>Status:</strong> 
                <span class="status <?= $logExists ? 'exists' : 'missing' ?>">
                    <?= $logExists ? 'Exists' : 'Missing' ?>
                </span>
            </p>
            <?php if ($logExists): ?>
                <p><strong>Size:</strong> <?= number_format($logSize) ?> bytes</p>
                <p><strong>Lines:</strong> <?= number_format($logLines) ?></p>
                <p><strong>Last Modified:</strong> <?= date('Y-m-d H:i:s', filemtime($logFile)) ?></p>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Import Options:</label>
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry Run (no changes made)
                    </label>
                    <label>
                        <input type="checkbox" name="import_only" value="1">
                        Import Only (skip registrations)
                    </label>
                    <label>
                        <input type="checkbox" name="register_only" value="1">
                        Register Only (skip imports)
                    </label>
                </div>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-warning">
                    🧪 Run Dry Test
                </button>
                <button type="submit" class="btn" onclick="return confirm('Are you sure you want to run the actual import? This will make changes to FusionAuth.')">
                    🚀 Run Import
                </button>
                <button type="button" class="btn" onclick="window.open('<?= htmlspecialchars($logFile) ?>', '_blank')">
                    📋 View Logs
                </button>
            </div>
        </form>
        
        <script>
            // Auto-uncheck dry run when user clicks "Run Import"
            document.querySelectorAll('button').forEach(btn => {
                if (btn.textContent.includes('Run Import')) {
                    btn.addEventListener('click', function() {
                        document.querySelector('input[name="dry_run"]').checked = false;
                    });
                }
            });
        </script>
    </div>
</body>
</html> 