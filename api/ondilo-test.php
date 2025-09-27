<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Ondilo API Configuration
const ONDILO_API_BASE = 'https://interop.ondilo.com';
const CLIENT_ID = 'customer_api';
const REDIRECT_URI = 'https://your.app.url/authorize'; // Dummy redirect URI
const SCOPE = 'api';

/**
 * Ondilo OAuth Test Functions
 */
class OndiloAPITest {
    
    /**
     * Step 1: Get Authorization URL
     * User needs to visit this URL to login and get authorization code
     */
    public static function getAuthorizationUrl() {
        $state = bin2hex(random_bytes(16)); // Generate random state for security
        
        $params = [
            'client_id' => CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => REDIRECT_URI,
            'scope' => SCOPE,
            'state' => $state
        ];
        
        $authUrl = ONDILO_API_BASE . '/oauth2/authorize?' . http_build_query($params);
        
        return [
            'authorization_url' => $authUrl,
            'state' => $state,
            'instructions' => [
                '1. Visit the authorization URL',
                '2. Login with your Ondilo account',
                '3. Copy the authorization code from the redirect URL',
                '4. Use the code to get access token'
            ]
        ];
    }
    
    /**
     * Step 2: Exchange authorization code for access token
     * Note: This requires client_secret which needs to be obtained from Ondilo
     */
    public static function getAccessToken($authorizationCode, $clientSecret = null) {
        if (!$clientSecret) {
            return [
                'error' => 'Client secret required',
                'message' => 'Need to get client_secret from Ondilo developer portal'
            ];
        }
        
        $tokenUrl = ONDILO_API_BASE . '/oauth2/token';
        
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => CLIENT_ID,
            'client_secret' => $clientSecret,
            'code' => $authorizationCode,
            'redirect_uri' => REDIRECT_URI
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    /**
     * Step 3: Test API call with access token
     */
    public static function testApiCall($accessToken, $endpoint = '/pools') {
        $apiUrl = ONDILO_API_BASE . '/api/customer/v1' . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'Accept-Charset: utf-8',
            'Accept-Encoding: gzip, deflate',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    /**
     * Alternative: Try to use username/password directly (if supported)
     * This is for testing purposes and may not work with Ondilo's OAuth flow
     */
    public static function attemptDirectAuth($username, $password) {
        // Try Resource Owner Password Credentials Grant (may not be supported)
        $tokenUrl = ONDILO_API_BASE . '/oauth2/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => CLIENT_ID,
            'username' => $username,
            'password' => $password,
            'scope' => SCOPE
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'method' => 'Resource Owner Password Credentials',
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'curl_error' => $error,
            'note' => 'This grant type may not be supported by Ondilo'
        ];
    }
}

// Main execution based on action parameter
$action = $_GET['action'] ?? 'auth_url';

try {
    switch ($action) {
        case 'auth_url':
            $result = OndiloAPITest::getAuthorizationUrl();
            break;
            
        case 'get_token':
            $code = $_GET['code'] ?? '';
            $clientSecret = $_GET['client_secret'] ?? null;
            $result = OndiloAPITest::getAccessToken($code, $clientSecret);
            break;
            
        case 'test_api':
            $accessToken = $_GET['access_token'] ?? '';
            $endpoint = $_GET['endpoint'] ?? '/pools';
            $result = OndiloAPITest::testApiCall($accessToken, $endpoint);
            break;
            
        case 'direct_auth':
            $username = $_GET['username'] ?? 'suvallop@gmail.com';
            $password = $_GET['password'] ?? 'kencen2007';
            $result = OndiloAPITest::attemptDirectAuth($username, $password);
            break;
            
        default:
            $result = [
                'error' => 'Invalid action',
                'available_actions' => [
                    'auth_url' => 'Get authorization URL',
                    'get_token' => 'Exchange code for access token (requires code and client_secret)',
                    'test_api' => 'Test API call (requires access_token)',
                    'direct_auth' => 'Try direct authentication (may not work)'
                ]
            ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>