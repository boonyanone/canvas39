<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/**
 * Ondilo Automated OAuth Authentication
 * This script automates the OAuth flow to get access tokens
 */
class OndiloAuth {
    
    private const ONDILO_BASE = 'https://interop.ondilo.com';
    private const CLIENT_ID = 'customer_api';
    private const REDIRECT_URI = 'https://canvas39.vercel.app/auth/callback';
    
    private $cookieJar;
    
    public function __construct() {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'ondilo_cookies');
    }
    
    /**
     * Complete OAuth flow automatically
     */
    public function authenticateUser($email, $password) {
        try {
            // Step 1: Get authorization page and extract form data
            $authData = $this->getAuthorizationPage();
            if (!$authData['success']) {
                return $authData;
            }
            
            // Step 2: Submit login form
            $loginResult = $this->submitLoginForm($authData['token'], $email, $password);
            if (!$loginResult['success']) {
                return $loginResult;
            }
            
            // Step 3: Extract authorization code from redirect
            $code = $this->extractAuthorizationCode($loginResult['redirect_url']);
            if (!$code) {
                return ['success' => false, 'error' => 'Failed to extract authorization code'];
            }
            
            // Step 4: Exchange code for access token
            $tokenResult = $this->exchangeCodeForToken($code);
            
            return $tokenResult;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            // Clean up cookie file
            if (file_exists($this->cookieJar)) {
                unlink($this->cookieJar);
            }
        }
    }
    
    /**
     * Step 1: Get authorization page and extract CSRF token
     */
    private function getAuthorizationPage() {
        $state = bin2hex(random_bytes(16));
        $authUrl = self::ONDILO_BASE . '/oauth2/authorize?' . http_build_query([
            'client_id' => self::CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'api',
            'state' => $state
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Failed to get auth page. HTTP: $httpCode"];
        }
        
        // Extract CSRF token
        if (preg_match('/name="_token" value="([^"]+)"/', $html, $matches)) {
            return [
                'success' => true,
                'token' => $matches[1],
                'state' => $state,
                'html' => $html
            ];
        }
        
        return ['success' => false, 'error' => 'Could not extract CSRF token from auth page'];
    }
    
    /**
     * Step 2: Submit login form with credentials
     */
    private function submitLoginForm($csrfToken, $email, $password) {
        $loginUrl = self::ONDILO_BASE . '/oauth2/authorize?' . http_build_query([
            'client_id' => self::CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'api'
        ]);
        
        $postData = [
            '_token' => $csrfToken,
            'email' => $email,
            'password' => $password,
            'locale' => 'en',
            'proceed' => 'Authorize'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Referer: ' . $loginUrl,
            'Upgrade-Insecure-Requests: 1'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        
        if ($httpCode === 302 && $redirectUrl) {
            return [
                'success' => true,
                'redirect_url' => $redirectUrl,
                'http_code' => $httpCode
            ];
        }
        
        // Check for error messages in response
        if (strpos($response, 'error') !== false || strpos($response, 'invalid') !== false) {
            return [
                'success' => false,
                'error' => 'Login failed - check credentials',
                'http_code' => $httpCode,
                'response_snippet' => substr($response, 0, 500)
            ];
        }
        
        return [
            'success' => false,
            'error' => "Unexpected response. HTTP: $httpCode",
            'redirect_url' => $redirectUrl,
            'response_snippet' => substr($response, 0, 500)
        ];
    }
    
    /**
     * Step 3: Extract authorization code from redirect URL
     */
    private function extractAuthorizationCode($redirectUrl) {
        if (!$redirectUrl) {
            return null;
        }
        
        $parsedUrl = parse_url($redirectUrl);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            return $queryParams['code'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Step 4: Exchange authorization code for access token
     * Note: This requires client_secret which we might need to discover
     */
    private function exchangeCodeForToken($authCode) {
        // Try common client secrets or use the same as client_id
        $possibleSecrets = [
            'customer_api',
            'customer_secret',
            'api_secret',
            '', // empty secret
            null // no secret
        ];
        
        foreach ($possibleSecrets as $secret) {
            $result = $this->attemptTokenExchange($authCode, $secret);
            if ($result['success']) {
                return $result;
            }
        }
        
        return [
            'success' => false,
            'error' => 'Could not exchange code for token with any known client secret',
            'authorization_code' => $authCode
        ];
    }
    
    /**
     * Attempt token exchange with specific client secret
     */
    private function attemptTokenExchange($authCode, $clientSecret) {
        $tokenUrl = self::ONDILO_BASE . '/oauth2/token';
        
        $postData = [
            'grant_type' => 'authorization_code',
            'client_id' => self::CLIENT_ID,
            'code' => $authCode,
            'redirect_uri' => self::REDIRECT_URI
        ];
        
        if ($clientSecret !== null) {
            $postData['client_secret'] = $clientSecret;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode === 200 && isset($decodedResponse['access_token'])) {
            return [
                'success' => true,
                'access_token' => $decodedResponse['access_token'],
                'token_type' => $decodedResponse['token_type'] ?? 'Bearer',
                'expires_in' => $decodedResponse['expires_in'] ?? null,
                'refresh_token' => $decodedResponse['refresh_token'] ?? null,
                'client_secret_used' => $clientSecret
            ];
        }
        
        return [
            'success' => false,
            'http_code' => $httpCode,
            'response' => $decodedResponse,
            'client_secret_tried' => $clientSecret
        ];
    }
    
    /**
     * Test API call with access token
     */
    public function testApiCall($accessToken, $endpoint = '/pools') {
        $apiUrl = self::ONDILO_BASE . '/api/customer/v1' . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'Accept-Charset: utf-8',
            'Accept-Encoding: gzip, deflate',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'raw_response' => $response,
            'curl_error' => $error
        ];
    }
}

// Main execution
$action = $_GET['action'] ?? 'auth';
$auth = new OndiloAuth();

try {
    switch ($action) {
        case 'auth':
            $email = $_GET['email'] ?? 'suvallop@gmail.com';
            $password = $_GET['password'] ?? 'kencen2007';
            $result = $auth->authenticateUser($email, $password);
            break;
            
        case 'test':
            $accessToken = $_GET['access_token'] ?? '';
            $endpoint = $_GET['endpoint'] ?? '/pools';
            if (empty($accessToken)) {
                $result = ['success' => false, 'error' => 'Access token required'];
            } else {
                $result = $auth->testApiCall($accessToken, $endpoint);
            }
            break;
            
        default:
            $result = [
                'error' => 'Invalid action',
                'available_actions' => [
                    'auth' => 'Authenticate user and get access token',
                    'test' => 'Test API call with access token'
                ],
                'usage' => [
                    'auth' => '?action=auth&email=user@example.com&password=pass123',
                    'test' => '?action=test&access_token=TOKEN&endpoint=/pools'
                ]
            ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>