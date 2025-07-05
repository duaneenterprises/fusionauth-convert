<?php
/**
 * Configuration file for FusionAuth User Import
 */

// Load environment variables from .env file
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

return [
    // Database configuration
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_NAME'] ?? 'leaguejoe',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Force TCP/IP connection instead of socket
            PDO::ATTR_PERSISTENT => false,
        ]
    ],
    
    // FusionAuth configuration
    'fusionauth' => [
        'base_url' => $_ENV['FUSIONAUTH_BASE_URL'] ?? 'http://localhost:9011',
        'api_key' => $_ENV['FUSIONAUTH_API_KEY'] ?? '',
        'tenant_id' => $_ENV['FUSIONAUTH_TENANT_ID'] ?? '',
        'timeout' => 30,
        'verify_ssl' => false
    ],
    
    // League Joe application IDs to register users for
    'league_joe_app_ids' => [
        $_ENV['LEAGUE_JOE_APP_ID'] ?? '45562ca3-d36c-4d0a-af6f-c0c5abfffffd'
    ],
    
    // Processing configuration
    'batch_size' => (int)($_ENV['BATCH_SIZE'] ?? 100),
    'log_file' => $_ENV['LOG_FILE'] ?? 'user_import.log',
    'log_level' => $_ENV['LOG_LEVEL'] ?? 'INFO',
    
    // User mapping configuration
    'user_mapping' => [
        'email_field' => 'email',
        'username_field' => 'username',
        'password_field' => 'password',
        'first_name_field' => 'first_name',
        'last_name_field' => 'last_name',
        'confirmed_field' => 'confirmed',
        'active_field' => 'active',
        'salt_field' => 'salt'
    ],
    
    // Role mapping configuration
    'role_mapping' => [
        'default_role' => 'Rookie',
        'role_field' => 'level',
        'role_mapping' => [
            1 => 'Rookie',
            2 => 'Player',
            3 => 'Coach',
            4 => 'Global Admin'
        ]
    ]
]; 