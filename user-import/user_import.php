<?php
/**
 * FusionAuth User Import Script
 * 
 * This script imports users from MySQL to FusionAuth and registers them for League Joe applications.
 * It supports dry-run mode and detailed logging for debugging.
 * 
 * Usage:
 *   php user_import.php [--dry-run] [--import-only] [--register-only]
 * 
 * @author League Joe Migration Team
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/FusionAuthClient.php';
require_once __DIR__ . '/DatabaseClient.php';
require_once __DIR__ . '/Logger.php';

class UserImport
{
    private Logger $logger;
    private DatabaseClient $db;
    private FusionAuthClient $fa;
    private array $config;
    
    // Tracking arrays
    private array $importResults = [];
    private array $registrationResults = [];
    private array $errors = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = new Logger($config['log_file'] ?? 'user_import.log');
        $this->db = new DatabaseClient($config['database']);
        $this->fa = new FusionAuthClient($config['fusionauth']);
        
        $this->logger->info('UserImport initialized', [
            'fusionauth_url' => $config['fusionauth']['base_url'],
            'tenant_id' => $config['fusionauth']['tenant_id'],
            'app_ids' => $config['league_joe_app_ids']
        ]);
    }
    
    /**
     * Main execution method
     */
    public function run(bool $dryRun = false, bool $importOnly = false, bool $registerOnly = false): void
    {
        $this->logger->info('Starting user import process', [
            'dry_run' => $dryRun,
            'import_only' => $importOnly,
            'register_only' => $registerOnly
        ]);
        
        try {
            // Step 1: Import users to FusionAuth
            if (!$registerOnly) {
                $this->importUsers($dryRun);
            }
            
            // Step 2: Register users for League Joe applications
            if (!$importOnly) {
                $this->registerUsersForApps($dryRun);
            }
            
            // Step 3: Print summary
            $this->printSummary();
            
        } catch (Exception $e) {
            $this->logger->error('Fatal error during import process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Import users from MySQL to FusionAuth
     */
    private function importUsers(bool $dryRun): void
    {
        $this->logger->info('Starting user import phase');
        
        $batchSize = $this->config['batch_size'] ?? 100;
        $offset = 0;
        $totalImported = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        
        while (true) {
            $users = $this->db->getUsersBatch($offset, $batchSize);
            
            if (empty($users)) {
                $this->logger->info('No more users to process');
                break;
            }
            
            $this->logger->info("Processing batch", [
                'offset' => $offset,
                'batch_size' => count($users),
                'first_user' => $users[0]['email'] ?? 'N/A'
            ]);
            
            foreach ($users as $user) {
                $result = $this->processUserImport($user, $dryRun);
                
                switch ($result['status']) {
                    case 'imported':
                        $totalImported++;
                        break;
                    case 'skipped':
                        $totalSkipped++;
                        break;
                    case 'failed':
                        $totalFailed++;
                        break;
                }
                
                $this->importResults[] = $result;
            }
            
            $offset += $batchSize;
            
            $this->logger->info("Batch completed", [
                'offset' => $offset,
                'total_imported' => $totalImported,
                'total_skipped' => $totalSkipped,
                'total_failed' => $totalFailed
            ]);
        }
        
        $this->logger->info('User import phase completed', [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
            'total_failed' => $totalFailed
        ]);
    }
    
    /**
     * Process a single user import
     */
    private function processUserImport(array $user, bool $dryRun): array
    {
        $result = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'status' => 'unknown',
            'message' => '',
            'fusionauth_user_id' => null,
            'role' => $this->fa->getRoleForLevel($user['level'] ?? null)
        ];
        
        try {
            // Check if user already exists in FusionAuth
            $existingUser = $this->fa->getUserByEmail($user['email']);
            
            if ($existingUser) {
                $result['status'] = 'skipped';
                $result['message'] = 'User already exists in FusionAuth';
                $result['fusionauth_user_id'] = $existingUser['id'];
                
                $this->logger->debug('User skipped (already exists)', [
                    'email' => $user['email'],
                    'fusionauth_id' => $existingUser['id']
                ]);
                
                return $result;
            }
            
            if ($dryRun) {
                $this->logger->info("DRY RUN: Would import user", ['email' => $user['email']]);
                // Show what would be sent
                $userData = $this->fa->mapUserData($user);
                echo "DRY RUN: Would send payload for email: " . $user['email'] . "\n";
                echo "DRY RUN: Payload: " . json_encode($userData, JSON_PRETTY_PRINT) . "\n";
                $result['status'] = 'skipped';
                $result['message'] = 'Dry run - would import user';
                $result['fusionauth_user_id'] = null;
                
                $this->logger->debug('User would be imported (dry run)', [
                    'email' => $user['email']
                ]);
                
                return $result;
            }
            
            // Import user to FusionAuth
            $faUser = $this->fa->importUser($user);
            
            $result['status'] = 'imported';
            $result['message'] = 'User successfully imported';
            $result['fusionauth_user_id'] = $faUser['id'];
            
            $this->logger->info('User imported successfully', [
                'email' => $user['email'],
                'fusionauth_id' => $faUser['id']
            ]);
            
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            
            $this->logger->error('User import failed', [
                'email' => $user['email'],
                'error' => $e->getMessage()
            ]);
            
            $this->errors[] = [
                'type' => 'import',
                'user_id' => $user['id'],
                'email' => $user['email'],
                'error' => $e->getMessage()
            ];
        }
        
        return $result;
    }
    
    /**
     * Register users for League Joe applications
     */
    private function registerUsersForApps(bool $dryRun): void
    {
        $this->logger->info('Starting user registration phase');
        
        $appIds = $this->config['league_joe_app_ids'];
        $totalRegistrations = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        
        foreach ($this->importResults as $importResult) {
            if ($importResult['status'] !== 'imported' && $importResult['status'] !== 'skipped') {
                continue; // Skip users that failed to import
            }
            
            $faUserId = $importResult['fusionauth_user_id'];
            if (!$faUserId) {
                continue;
            }
            
            foreach ($appIds as $appId) {
                $result = $this->processUserRegistration($faUserId, $appId, $importResult, $dryRun);
                
                switch ($result['status']) {
                    case 'registered':
                        $totalRegistrations++;
                        break;
                    case 'skipped':
                        $totalSkipped++;
                        break;
                    case 'failed':
                        $totalFailed++;
                        break;
                }
                
                $this->registrationResults[] = $result;
            }
        }
        
        $this->logger->info('User registration phase completed', [
            'total_registrations' => $totalRegistrations,
            'total_skipped' => $totalSkipped,
            'total_failed' => $totalFailed
        ]);
    }
    
    /**
     * Process a single user registration
     */
    private function processUserRegistration(string $faUserId, string $appId, array $importResult, bool $dryRun): array
    {
        $result = [
            'user_id' => $importResult['user_id'],
            'email' => $importResult['email'],
            'fusionauth_user_id' => $faUserId,
            'app_id' => $appId,
            'status' => 'unknown',
            'message' => ''
        ];
        
        try {
            // Check if user is already registered for this app
            $existingRegistration = $this->fa->getUserRegistration($faUserId, $appId);
            
            if ($existingRegistration) {
                $result['status'] = 'skipped';
                $result['message'] = 'User already registered for this application';
                
                $this->logger->debug('Registration skipped (already exists)', [
                    'email' => $importResult['email'],
                    'app_id' => $appId
                ]);
                
                return $result;
            }
            
            if ($dryRun) {
                $result['status'] = 'skipped';
                $result['message'] = 'Dry run - would register user';
                
                $this->logger->debug('User would be registered (dry run)', [
                    'email' => $importResult['email'],
                    'app_id' => $appId
                ]);
                
                return $result;
            }
            
            // Register user for the application
            $registration = $this->fa->registerUserForApp($faUserId, $appId, $importResult);
            
            $result['status'] = 'registered';
            $result['message'] = 'User successfully registered for application';
            
            $this->logger->info('User registered successfully', [
                'email' => $importResult['email'],
                'app_id' => $appId
            ]);
            
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            
            $this->logger->error('User registration failed', [
                'email' => $importResult['email'],
                'app_id' => $appId,
                'error' => $e->getMessage()
            ]);
            
            $this->errors[] = [
                'type' => 'registration',
                'user_id' => $importResult['user_id'],
                'email' => $importResult['email'],
                'app_id' => $appId,
                'error' => $e->getMessage()
            ];
        }
        
        return $result;
    }
    
    /**
     * Print summary of import and registration results
     */
    private function printSummary(): void
    {
        $this->logger->info('=== IMPORT SUMMARY ===');
        
        // Import summary
        $importStats = $this->getImportStats();
        $this->logger->info('User Import Statistics', $importStats);
        
        // Registration summary
        $registrationStats = $this->getRegistrationStats();
        $this->logger->info('User Registration Statistics', $registrationStats);
        
        // Error summary
        if (!empty($this->errors)) {
            $this->logger->error('Errors encountered', [
                'total_errors' => count($this->errors),
                'error_types' => array_count_values(array_column($this->errors, 'type'))
            ]);
            
            foreach ($this->errors as $error) {
                $this->logger->error('Error details', $error);
            }
        }
        
        $this->logger->info('=== END SUMMARY ===');
        
        // Also print to console
        echo "\n=== IMPORT SUMMARY ===\n";
        echo "User Imports:\n";
        echo "  - Total: " . count($this->importResults) . "\n";
        echo "  - Imported: " . $importStats['imported'] . "\n";
        echo "  - Skipped: " . $importStats['skipped'] . "\n";
        echo "  - Failed: " . $importStats['failed'] . "\n";
        
        echo "\nUser Registrations:\n";
        echo "  - Total: " . count($this->registrationResults) . "\n";
        echo "  - Registered: " . $registrationStats['registered'] . "\n";
        echo "  - Skipped: " . $registrationStats['skipped'] . "\n";
        echo "  - Failed: " . $registrationStats['failed'] . "\n";
        
        if (!empty($this->errors)) {
            echo "\nErrors: " . count($this->errors) . "\n";
        }
        
        echo "=== END SUMMARY ===\n";
    }
    
    /**
     * Get import statistics
     */
    private function getImportStats(): array
    {
        $stats = [
            'total' => count($this->importResults),
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        
        foreach ($this->importResults as $result) {
            $stats[$result['status']]++;
        }
        
        return $stats;
    }
    
    /**
     * Get registration statistics
     */
    private function getRegistrationStats(): array
    {
        $stats = [
            'total' => count($this->registrationResults),
            'registered' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        
        foreach ($this->registrationResults as $result) {
            $stats[$result['status']]++;
        }
        
        return $stats;
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['dry-run', 'import-only', 'register-only', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php user_import.php [--dry-run] [--import-only] [--register-only]\n";
        echo "\nOptions:\n";
        echo "  --dry-run      Run without making changes to FusionAuth\n";
        echo "  --import-only  Only import users, skip registrations\n";
        echo "  --register-only Only register users for apps, skip imports\n";
        echo "  --help         Show this help message\n";
        exit(0);
    }
    
    $config = require __DIR__ . '/config.php';
    $importer = new UserImport($config);
    
    $dryRun = isset($options['dry-run']);
    $importOnly = isset($options['import-only']);
    $registerOnly = isset($options['register-only']);
    
    try {
        $importer->run($dryRun, $importOnly, $registerOnly);
        exit(0);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} 