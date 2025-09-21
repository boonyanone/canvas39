<?php
/**
 * Air Quality Monitor v2.0 - Installation Backend
 * Backend script for handling installation wizard requests
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

class InstallationWizard {
    
    private $baseDir;
    private $apiDir;
    
    public function __construct() {
        $this->baseDir = dirname(__DIR__); // Go up one level from install/ to root
        $this->apiDir = $this->baseDir . '/api';
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'check_requirements':
                return $this->checkSystemRequirements();
            case 'test_database':
                return $this->testDatabaseConnection();
            case 'test_apis':
                return $this->testAPIEndpoints();
            case 'install':
                return $this->performInstallation();
            default:
                return $this->errorResponse('Unknown action');
        }
    }
    
    private function checkSystemRequirements() {
        $requirements = [
            'php_version' => [
                'name' => 'PHP Version 7.4+',
                'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'value' => PHP_VERSION
            ],
            'curl_extension' => [
                'name' => 'cURL Extension',
                'status' => extension_loaded('curl'),
                'value' => extension_loaded('curl') ? 'Available' : 'Missing'
            ],
            'json_extension' => [
                'name' => 'JSON Extension',
                'status' => extension_loaded('json'),
                'value' => extension_loaded('json') ? 'Available' : 'Missing'
            ],
            'write_permissions' => [
                'name' => 'Write Permissions (api/)',
                'status' => is_writable($this->apiDir),
                'value' => is_writable($this->apiDir) ? 'Writable' : 'Not writable'
            ],
            'https_support' => [
                'name' => 'HTTPS Support',
                'status' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'value' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Enabled' : 'HTTP only'
            ]
        ];
        
        $allPassed = true;
        foreach ($requirements as $req) {
            if (!$req['status']) {
                $allPassed = false;
                break;
            }
        }
        
        return $this->successResponse([
            'requirements' => $requirements,
            'all_passed' => $allPassed
        ]);
    }
    
    private function testDatabaseConnection() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $host = $input['host'] ?? 'localhost';
        $dbname = $input['name'] ?? '';
        $username = $input['user'] ?? '';
        $password = $input['pass'] ?? '';
        
        if (empty($dbname) || empty($username)) {
            return $this->errorResponse('Database name and username are required');
        }
        
        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Test query
            $stmt = $pdo->query('SELECT 1');
            $result = $stmt->fetch();
            
            if ($result) {
                return $this->successResponse([
                    'message' => 'Database connection successful',
                    'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
                ]);
            } else {
                return $this->errorResponse('Database connection test failed');
            }
            
        } catch (PDOException $e) {
            return $this->errorResponse('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function testAPIEndpoints() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $apis = $input['apis'] ?? [];
        $results = [];
        
        foreach ($apis as $apiType => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            $url = $config['url'] ?? '';
            if (empty($url)) {
                $results[$apiType] = [
                    'status' => false,
                    'message' => 'URL is required'
                ];
                continue;
            }
            
            $result = $this->testSingleAPI($url, $apiType);
            $results[$apiType] = $result;
        }
        
        $allPassed = true;
        foreach ($results as $result) {
            if (!$result['status']) {
                $allPassed = false;
                break;
            }
        }
        
        return $this->successResponse([
            'results' => $results,
            'all_passed' => $allPassed
        ]);
    }
    
    private function testSingleAPI($url, $type) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'AirQualityMonitor/2.0 InstallationWizard',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/html',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => false,
                'message' => 'Connection error: ' . $error,
                'http_code' => 0
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'status' => false,
                'message' => 'HTTP error: ' . $httpCode,
                'http_code' => $httpCode
            ];
        }
        
        // Try to extract some data based on API type
        $dataFound = false;
        $message = 'Connected successfully';
        
        if ($type === 'device') {
            // Check for JSON response from device API
            $data = json_decode($response, true);
            if ($data && isset($data['historical'])) {
                $dataFound = true;
                $message = 'Device API response valid';
            }
        } elseif ($type === 'public') {
            // Check for IQAir page content
            if (strpos($response, 'iqair') !== false || strpos($response, 'AQI') !== false) {
                $dataFound = true;
                $message = 'Public station page accessible';
            }
        }
        
        return [
            'status' => true,
            'message' => $message,
            'http_code' => $httpCode,
            'data_found' => $dataFound,
            'response_size' => strlen($response)
        ];
    }
    
    private function performInstallation() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $steps = [];
        $errors = [];
        
        try {
            // Step 1: Create config.php
            $steps[] = 'Creating config.php file...';
            $configResult = $this->createConfigFile($input);
            if (!$configResult['success']) {
                $errors[] = $configResult['error'];
            }
            
            // Step 2: Create database tables (if database is enabled)
            if ($input['database']['enabled'] ?? false) {
                $steps[] = 'Creating database tables...';
                $dbResult = $this->createDatabaseTables($input['database']);
                if (!$dbResult['success']) {
                    $errors[] = $dbResult['error'];
                }
            } else {
                $steps[] = 'Skipping database setup (not enabled)';
            }
            
            // Step 3: Create API configuration
            $steps[] = 'Configuring API endpoints...';
            $apiResult = $this->configureAPIEndpoints($input['apis']);
            if (!$apiResult['success']) {
                $errors[] = $apiResult['error'];
            }
            
            // Step 4: Create settings file
            $steps[] = 'Creating default settings...';
            $settingsResult = $this->createDefaultSettings($input);
            if (!$settingsResult['success']) {
                $errors[] = $settingsResult['error'];
            }
            
            // Step 5: Test final configuration
            $steps[] = 'Testing final configuration...';
            $testResult = $this->testFinalConfiguration();
            if (!$testResult['success']) {
                $errors[] = $testResult['error'];
            }
            
            $steps[] = 'Installation completed successfully!';
            
        } catch (Exception $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
        
        return $this->successResponse([
            'steps' => $steps,
            'errors' => $errors,
            'success' => empty($errors)
        ]);
    }
    
    private function createConfigFile($config) {
        $dbConfig = $config['database'] ?? [];
        
        $configContent = "<?php\n";
        $configContent .= "/**\n";
        $configContent .= " * Air Quality Monitor v2.0 - Configuration\n";
        $configContent .= " * Generated by Installation Wizard\n";
        $configContent .= " * Date: " . date('Y-m-d H:i:s') . "\n";
        $configContent .= " */\n\n";
        
        if ($dbConfig['enabled'] ?? false) {
            $configContent .= "// Database Configuration\n";
            $configContent .= "define('DB_HOST', '" . addslashes($dbConfig['host'] ?? 'localhost') . "');\n";
            $configContent .= "define('DB_NAME', '" . addslashes($dbConfig['name'] ?? '') . "');\n";
            $configContent .= "define('DB_USER', '" . addslashes($dbConfig['user'] ?? '') . "');\n";
            $configContent .= "define('DB_PASS', '" . addslashes($dbConfig['pass'] ?? '') . "');\n";
            $configContent .= "define('DB_ENABLED', true);\n\n";
        } else {
            $configContent .= "// Database Configuration (Disabled)\n";
            $configContent .= "define('DB_ENABLED', false);\n\n";
        }
        
        $configContent .= "// API Configuration\n";
        $configContent .= "define('API_VERSION', '2.0');\n";
        $configContent .= "define('INSTALL_DATE', '" . date('Y-m-d H:i:s') . "');\n";
        $configContent .= "define('DEBUG_MODE', false);\n\n";
        
        $configContent .= "// Timezone\n";
        $configContent .= "date_default_timezone_set('Asia/Bangkok');\n\n";
        
        $configContent .= "?>";
        
        $configPath = $this->apiDir . '/config.php';
        
        if (file_put_contents($configPath, $configContent) !== false) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to create config.php'];
        }
    }
    
    private function createDatabaseTables($dbConfig) {
        try {
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create tables
            $sql = "
            CREATE TABLE IF NOT EXISTS air_monitor_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS air_monitor_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                log_type VARCHAR(50) NOT NULL,
                message TEXT,
                data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS air_monitor_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aqi INT,
                pm25 DECIMAL(5,2),
                pm10 DECIMAL(5,2),
                temperature INT,
                humidity INT,
                pressure INT,
                source VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            ";
            
            $pdo->exec($sql);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database setup failed: ' . $e->getMessage()];
        }
    }
    
    private function configureAPIEndpoints($apis) {
        $sources = [];
        $priority = 1;
        
        if ($apis['device']['enabled'] ?? false) {
            $sources[] = [
                'type' => 'device_api',
                'url' => $apis['device']['url'],
                'priority' => $priority++
            ];
        }
        
        if ($apis['public']['enabled'] ?? false) {
            $sources[] = [
                'type' => 'public_station',
                'url' => $apis['public']['url'],
                'priority' => $priority++
            ];
        }
        
        // Add default city API as fallback
        $sources[] = [
            'type' => 'city_api',
            'city' => 'Bangkok',
            'state' => 'Bangkok',
            'country' => 'Thailand',
            'priority' => $priority++
        ];
        
        $configPath = $this->apiDir . '/api-sources.json';
        
        if (file_put_contents($configPath, json_encode($sources, JSON_PRETTY_PRINT)) !== false) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to create API configuration'];
        }
    }
    
    private function createDefaultSettings($config) {
        $settings = [
            'app_name' => 'Air Quality Monitor v2.0',
            'install_version' => '2.0',
            'install_date' => date('Y-m-d H:i:s'),
            'update_interval' => 300, // 5 minutes
            'display_mode' => 'auto',
            'theme' => 'device',
            'language' => 'th'
        ];
        
        $settingsPath = $this->baseDir . '/settings.json';
        
        if (file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT)) !== false) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to create settings file'];
        }
    }
    
    private function testFinalConfiguration() {
        // Test if all required files exist
        $requiredFiles = [
            $this->apiDir . '/config.php',
            $this->apiDir . '/air-quality-multi.php',
            $this->baseDir . '/index.html',
            $this->baseDir . '/index2.html',
            $this->baseDir . '/admin.html'
        ];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                return ['success' => false, 'error' => 'Required file missing: ' . basename($file)];
            }
        }
        
        // Test API endpoint
        $installDir = dirname($_SERVER['REQUEST_URI']);
        $baseUrl = dirname($installDir); // Go up one level from /install to root
        $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . $baseUrl . '/api/air-quality-multi.php';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'API endpoint test failed (HTTP ' . $httpCode . ')'];
        }
    }
    
    private function successResponse($data) {
        return json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function errorResponse($message) {
        return json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Handle the request
try {
    $installer = new InstallationWizard();
    echo $installer->handleRequest();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Installation error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>