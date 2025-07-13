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
        
        // Normalize confirmed to a boolean
        $confirmed = (bool)($user[$mapping['confirmed_field']] ?? false);
        
        $userData = [
            'sendSetPasswordEmail' => false,
            'skipVerification' => true,
            'active' => (bool)($user[$mapping['active_field']] ?? true),
            'data' => [
                'displayName' => $user[$mapping['first_name_field']] ?? '',
                'jerseyName' => $user['jersey_name'] ?? null,
                'jerseyNumber' => $user['jersey_number'] ?? null,
                'title' => $user['title'] ?? null,
                'company' => $user['company'] ?? null,
                'phoneNumber' => $user['phone_number'] ?? null,
                'address' => [
                    'street' => $user['street_address'] ?? null,
                    'city' => $user['city_address'] ?? null,
                    'state' => $user['state_address'] ?? null,
                    'zipCode' => $user['zip_code'] ?? null,
                    'country' => $user['country'] ?? null
                ],
                'confirmed' => $confirmed,
                'approved' => (bool)($user['approved'] ?? false),
                'originalCreated' => $user['created'] ?? null,
                'originalUpdated' => $user['updated'] ?? null
            ],
            'email' => $user[$mapping['email_field']] ?? '',
            'encryptionScheme' => 'leaguejoe-password-encryptor',
            'factor' => 1,
            'firstName' => $user[$mapping['first_name_field']] ?? '',
            'fullName' => $this->buildFullName($user, $mapping),
            'imageUrl' => $user['avatar'] ? $config['web_url'] . $user['avatar'] : null,
            'lastName' => $user[$mapping['last_name_field']] ?? '',
            'middleName' => $user['middle_name'] ?? '',
            'password' => $user[$mapping['password_field']] ?? '',
            'passwordChangeRequired' => false,
            'preferredLanguages' => ['en'],
            'salt' => $user['salt'] ?? '',
            'twoFactorEnabled' => false,
            'usernameStatus' => 'ACTIVE',
            'username' => $user[$mapping['username_field']] ?? '',
            'verified' => $confirmed,
            'role' => '', // Empty role field to match ImportUsers
            'registrations' => [
                [
                    'applicationId' => $config['fusionauth']['app_ids'][0], // Use first app ID
                    'username' => $user[$mapping['username_field']] ?? '',
                    'usernameStatus' => 'ACTIVE',
                    'verified' => $confirmed,
                    'roles' => [$this->getRoleForLevel($user[$roleMapping['role_field']] ?? null)]
                ]
            ]
        ];
        
        // Add birthdate if available (use ISO format like ImportUsers)
        if (isset($user['birthdate']) && $user['birthdate']) {
            $userData['birthDate'] = date('Y-m-d', strtotime($user['birthdate']));
        }
        
        // Add gender if available
        if (isset($user['gender']) && $user['gender']) {
            $userData['data']['gender'] = $user['gender'];
        }
        
        return $userData;
    }
    
    /**
     * Build full name from available name components
     */
    private function buildFullName(array $user, array $mapping): string
    {
        $parts = [];
        if (!empty($user[$mapping['first_name_field']])) {
            $parts[] = $user[$mapping['first_name_field']];
        }
        if (!empty($user['middle_name'])) {
            $parts[] = $user['middle_name'];
        }
        if (!empty($user[$mapping['last_name_field']])) {
            $parts[] = $user[$mapping['last_name_field']];
        }
        return implode(' ', $parts);
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
                if ($errorData) {
                    if (isset($errorData['fieldErrors'])) {
                        $errorMessage .= " - Field errors: " . json_encode($errorData['fieldErrors']);
                    }
                    if (isset($errorData['generalErrors'])) {
                        $errorMessage .= " - General errors: " . json_encode($errorData['generalErrors']);
                    }
                    if (isset($errorData['message'])) {
                        $errorMessage .= " - Message: " . $errorData['message'];
                    }
                    // If no specific error details, include the full response
                    if (!isset($errorData['fieldErrors']) && !isset($errorData['generalErrors']) && !isset($errorData['message'])) {
                        $errorMessage .= " - Full response: " . $response;
                    }
                } else {
                    $errorMessage .= " - Raw response: " . $response;
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