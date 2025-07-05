<?php
/**
 * FusionAuth API Client
 * 
 * Handles all interactions with the FusionAuth API
 */

declare(strict_types=1);

class FusionAuthClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $tenantId;
    private int $timeout;
    private bool $verifySsl;
    
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->apiKey = $config['api_key'];
        $this->tenantId = $config['tenant_id'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->verifySsl = $config['verify_ssl'] ?? true;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        try {
            $response = $this->makeRequest('POST', "/api/user/search", [
                'search' => [
                    'queryString' => $email,
                    'queryStringFields' => ['email']
                ]
            ]);
            
            if (isset($response['users']) && !empty($response['users'])) {
                return $response['users'][0];
            }
            
            return null;
        } catch (Exception $e) {
            // If user not found, return null instead of throwing
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }
    
    /**
     * Import user to FusionAuth
     */
    public function importUser(array $user): array
    {
        $userData = $this->mapUserData($user);
        
        // Debug: Log the payload being sent
        if (defined('LOG_LEVEL') && LOG_LEVEL === 'DEBUG') {
            error_log("DEBUG: Sending user import payload for email: " . $userData['email']);
            error_log("DEBUG: Payload: " . json_encode($userData, JSON_PRETTY_PRINT));
        }
        
        // Send as 'users' array
        $response = $this->makeRequest('POST', "/api/user/import", [
            'users' => [ $userData ]
        ]);
        
        // FusionAuth returns the imported user data in the response
        if (isset($response['users']) && !empty($response['users'])) {
            return $response['users'][0];
        }
        
        // If no user data returned, try to get the user by email
        return $this->getUserByEmail($userData['email']) ?? [];
    }
    
    /**
     * Get user registration for specific application
     */
    public function getUserRegistration(string $userId, string $appId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/api/user/registration/{$userId}/{$appId}");
            return $response['registration'] ?? null;
        } catch (Exception $e) {
            // If registration not found, return null instead of throwing
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }
    
    /**
     * Register user for application
     */
    public function registerUserForApp(string $userId, string $appId, array $importResult): array
    {
        $registrationData = $this->mapRegistrationData($importResult);
        
        $response = $this->makeRequest('POST', "/api/user/registration", [
            'registration' => array_merge($registrationData, [
                'userId' => $userId,
                'applicationId' => $appId
            ])
        ]);
        
        return $response['registration'];
    }
    
    /**
     * Map user data from MySQL to FusionAuth format
     */
    public function mapUserData(array $user): array
    {
        global $config;
        $mapping = $config['user_mapping'];
        $roleMapping = $config['role_mapping'];
        
        $userData = [
            'email' => $user[$mapping['email_field']] ?? '',
            'username' => $user[$mapping['username_field']] ?? '',
            'password' => $user[$mapping['password_field']] ?? '',
            'verified' => (bool)($user[$mapping['confirmed_field']] ?? false),
            'active' => (bool)($user[$mapping['active_field']] ?? true),
            'data' => [],
            // Password import fields from ImportUsers logic
            'encryptionScheme' => 'leaguejoe-password-encryptor',
            'factor' => 1,
            'salt' => $user['salt'] ?? '',
            'passwordChangeRequired' => false,
            'twoFactorEnabled' => false,
            'usernameStatus' => 'ACTIVE'
        ];
        
        // Add first and last name if available
        if (isset($user[$mapping['first_name_field']])) {
            $userData['firstName'] = $user[$mapping['first_name_field']];
        }
        
        if (isset($user[$mapping['last_name_field']])) {
            $userData['lastName'] = $user[$mapping['last_name_field']];
        }
        
        // Map user level to role
        if (isset($user[$roleMapping['role_field']])) {
            $userLevel = $user[$roleMapping['role_field']];
            $role = $this->getRoleForLevel($userLevel);
            $userData['data']['user_level'] = $userLevel;
            $userData['data']['mapped_role'] = $role;
        }
        
        // Add any additional user data
        foreach ($user as $key => $value) {
            if (!in_array($key, array_values($mapping)) && $key !== 'salt') {
                $userData['data'][$key] = $value;
            }
        }
        
        return $userData;
    }
    
    /**
     * Map registration data
     */
    private function mapRegistrationData(array $importResult): array
    {
        $registrationData = [
            'verified' => true, // Assume verified since they're being imported
            'roles' => []
        ];
        
        // Add role if available
        if (isset($importResult['role'])) {
            $registrationData['roles'] = [$importResult['role']];
        } else {
            $registrationData['roles'] = [$this->getRoleForLevel(null)]; // Use default role
        }
        
        return $registrationData;
    }
    
    /**
     * Make HTTP request to FusionAuth API
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . $this->apiKey,
            'X-FusionAuth-TenantId: ' . $this->tenantId
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_VERBOSE => true
        ]);
        
        if ($data !== null) {
            $jsonData = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
            
            // Debug: Log the exact request being sent
            if (defined('LOG_LEVEL') && LOG_LEVEL === 'DEBUG') {
                echo "DEBUG: Making request to: $url\n";
                echo "DEBUG: Method: $method\n";
                echo "DEBUG: Headers:\n";
                foreach ($headers as $header) {
                    echo "DEBUG:   $header\n";
                }
                echo "DEBUG: Request Body:\n";
                echo "DEBUG: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        // Debug: Log the response
        if (defined('LOG_LEVEL') && LOG_LEVEL === 'DEBUG') {
            echo "DEBUG: HTTP Status Code: $httpCode\n";
            echo "DEBUG: cURL Error: " . ($error ?: 'None') . "\n";
            echo "DEBUG: Response Body: " . $response . "\n";
        }
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode >= 400) {
            $errorMessage = "FusionAuth API error ($httpCode)";
            if ($response) {
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['fieldErrors'])) {
                    $errorMessage .= " - Field errors: " . json_encode($errorData['fieldErrors']);
                } else {
                    $errorMessage .= " - " . $response;
                }
            }
            throw new Exception($errorMessage);
        }
        
        return $response ? json_decode($response, true) : [];
    }
    
    /**
     * Get role name for user level (same logic as ImportUsers)
     */
    public function getRoleForLevel(?int $level): string
    {
        if ($level === 1) {
            return "Global Admin";
        } elseif ($level === 2) {
            return "Rookie";
        } elseif ($level === 5) {
            return "Player";
        } elseif ($level === 6) {
            return "Coach";
        } else {
            return "Rookie"; // Default role
        }
    }
} 