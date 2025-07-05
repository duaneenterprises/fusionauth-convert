<?php
/**
 * Test Setup Script
 * 
 * Verifies that all components are properly configured and accessible
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

echo "=== FusionAuth User Import - Setup Test ===\n\n";

// Test 1: Check PHP version
echo "1. PHP Version Check:\n";
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    echo "   ✅ PHP " . PHP_VERSION . " (meets requirement)\n";
} else {
    echo "   ❌ PHP " . PHP_VERSION . " (requires 8.1.0 or higher)\n";
    exit(1);
}

// Test 2: Check required extensions
echo "\n2. PHP Extensions Check:\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext} extension loaded\n";
    } else {
        echo "   ❌ {$ext} extension not loaded\n";
        exit(1);
    }
}

// Test 3: Check configuration files
echo "\n3. Configuration Files Check:\n";
$requiredFiles = ['config.php', 'DatabaseClient.php', 'FusionAuthClient.php', 'Logger.php'];
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ {$file} exists\n";
    } else {
        echo "   ❌ {$file} missing\n";
        exit(1);
    }
}

// Test 4: Load configuration
echo "\n4. Configuration Load Test:\n";
try {
    $config = require 'config.php';
    echo "   ✅ Configuration loaded successfully\n";
    echo "   📊 Configuration summary:\n";
    echo "      - Database: {$config['database']['host']}:{$config['database']['port']}/{$config['database']['database']}\n";
    echo "      - FusionAuth: {$config['fusionauth']['base_url']}\n";
    echo "      - Tenant ID: {$config['fusionauth']['tenant_id']}\n";
    echo "      - App IDs: " . implode(', ', $config['league_joe_app_ids']) . "\n";
    echo "      - Batch Size: {$config['batch_size']}\n";
} catch (Exception $e) {
    echo "   ❌ Configuration load failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Database connection
echo "\n5. Database Connection Test:\n";
try {
    require_once 'DatabaseClient.php';
    $db = new DatabaseClient($config['database']);
    
    if ($db->testConnection()) {
        echo "   ✅ Database connection successful\n";
        
        // Get database stats
        $stats = $db->getStats();
        echo "   📊 Database statistics:\n";
        echo "      - Total users: {$stats['total_users']}\n";
        echo "      - Confirmed users: {$stats['confirmed_users']}\n";
        echo "      - Unconfirmed users: {$stats['unconfirmed_users']}\n";
        echo "      - Users by level:\n";
        foreach ($stats['users_by_level'] as $level => $count) {
            echo "        Level {$level}: {$count} users\n";
        }
    } else {
        echo "   ❌ Database connection failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: FusionAuth connection
echo "\n6. FusionAuth Connection Test:\n";
try {
    require_once 'FusionAuthClient.php';
    $fa = new FusionAuthClient($config['fusionauth']);
    
    // Test basic connection by trying to get tenant info
    $response = file_get_contents($config['fusionauth']['base_url'] . '/api/tenant', false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: ' . $config['fusionauth']['api_key'],
            'timeout' => 10
        ]
    ]));
    
    if ($response !== false) {
        echo "   ✅ FusionAuth connection successful\n";
        echo "   📊 FusionAuth info:\n";
        echo "      - Base URL: {$config['fusionauth']['base_url']}\n";
        echo "      - Tenant ID: {$config['fusionauth']['tenant_id']}\n";
        echo "      - API Key: " . substr($config['fusionauth']['api_key'], 0, 8) . "...\n";
    } else {
        echo "   ❌ FusionAuth connection failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ FusionAuth test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Logger test
echo "\n7. Logger Test:\n";
try {
    require_once 'Logger.php';
    $logger = new Logger('test.log', 'DEBUG');
    $logger->info('Test log message', ['test' => true]);
    
    if (file_exists('test.log')) {
        echo "   ✅ Logger working correctly\n";
        unlink('test.log'); // Clean up test log
    } else {
        echo "   ❌ Logger failed to create log file\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Logger test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 8: Environment check
echo "\n8. Environment Check:\n";
$requiredEnvVars = [
    'DB_HOST' => 'Database host',
    'DB_NAME' => 'Database name', 
    'DB_USER' => 'Database user',
    'FUSIONAUTH_BASE_URL' => 'FusionAuth URL',
    'FUSIONAUTH_API_KEY' => 'FusionAuth API key',
    'FUSIONAUTH_TENANT_ID' => 'FusionAuth tenant ID'
];

foreach ($requiredEnvVars as $var => $description) {
    if (!empty($_ENV[$var])) {
        echo "   ✅ {$description} configured\n";
    } else {
        echo "   ⚠️  {$description} not configured (using default)\n";
    }
}

echo "\n=== Setup Test Complete ===\n";
echo "✅ All tests passed! The system is ready to use.\n";
echo "\nNext steps:\n";
echo "1. Run 'make dry-run' to test the import process\n";
echo "2. Run 'make run' to perform the actual import\n";
echo "3. Check 'make help' for all available commands\n"; 