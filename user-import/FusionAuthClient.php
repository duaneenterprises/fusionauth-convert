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
            $response = $this->makeRequest('GET', "/api/user/search", [
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
        
        // FusionAuth returns an empty body on success
        return $response;
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
            'X-FusionAuth-TenantId: ' . $this->tenantId,
            'Authorization: ' . $this->apiKey
        ];
        
        $context = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ];
        
        if ($data !== null) {
            $context['http']['content'] = json_encode($data);
        }
        
        if (!$this->verifySsl) {
            $context['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false
            ];
        }
        
        $context = stream_context_create($context);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to make request to FusionAuth API: " . error_get_last()['message'] ?? 'Unknown error');
        }
        
        $httpCode = $this->getHttpResponseCode($http_response_header);
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $responseData['message'] ?? 'Unknown error';
            if (isset($responseData['fieldErrors'])) {
                $errorMessage .= ' - Field errors: ' . json_encode($responseData['fieldErrors']);
            }
            throw new Exception("FusionAuth API error ({$httpCode}): {$errorMessage}");
        }
        
        return $responseData;
    }
    
    /**
     * Extract HTTP response code from headers
     */
    private function getHttpResponseCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                return (int)$matches[1];
            }
        }
        return 200; // Default to 200 if we can't parse the code
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