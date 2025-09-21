<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'flowshop_vipart');
define('DB_USER', 'flowshop_vipart');
define('DB_PASS', 'Vipart@2025');

class AirQualityMultiSource {
    private $pdo;
    
    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Continue without database connection - use fallback names
            $this->pdo = null;
        }
    }
    
    private function scrapePublicStation($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$html || $httpCode !== 200) {
            throw new Exception("Failed to fetch public page: HTTP $httpCode");
        }
        
        // Extract JSON data from script tags
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.+?});/', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if ($jsonData && isset($jsonData['current'])) {
                return $this->parsePublicJsonData($jsonData['current']);
            }
        }
        
        // Try to extract basic AQI from HTML
        if (preg_match('/"aqius":(\d+)/', $html, $matches)) {
            $aqi = intval($matches[1]);
            $pm25 = 0;
            if (preg_match('/"pm25"[^}]*"v":(\d+\.?\d*)/', $html, $pm25Matches)) {
                $pm25 = floatval($pm25Matches[1]);
            }
            
            return [
                'aqi' => $aqi,
                'pm25' => $pm25,
                'pm10' => null,
                'pm1' => null,
                'temp' => null,
                'humidity' => null,
                'source' => 'public_scrape_basic'
            ];
        }
        
        throw new Exception("Cannot extract AQI from public page");
    }
    
    private function parsePublicJsonData($data) {
        return [
            'aqi' => $data['pollution']['aqius'] ?? null,
            'pm25' => $data['pollution']['pm25']['v'] ?? null,
            'pm10' => $data['pollution']['pm10']['v'] ?? null,
            'pm1' => $data['pollution']['pm1']['v'] ?? null,
            'temp' => $data['weather']['tp'] ?? null,
            'humidity' => $data['weather']['hu'] ?? null,
            'pressure' => $data['weather']['pr'] ?? null,
            'wind' => $data['weather']['ws'] ?? null,
            'source' => 'public_json_data'
        ];
    }
    
    private function fetchDeviceAPI($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AirQualityMonitor/2.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$result || $httpCode !== 200) {
            throw new Exception("Device API failed: HTTP $httpCode");
        }
        
        $data = json_decode($result, true);
        return $this->parseDeviceData($data);
    }
    
    private function parseDeviceData($data) {
        // Parse Device API data - support multiple formats
        
        // Format 1: historical/daily (v1 API)
        if (isset($data['historical']['daily'][0])) {
            $latest = $data['historical']['daily'][0];
            return [
                'aqi' => $latest['pm25']['aqius'] ?? null,
                'pm25' => $latest['pm25']['conc'] ?? null,
                'pm10' => $latest['pm10']['conc'] ?? null,
                'pm1' => $latest['pm1'] ?? null,
                'temp' => $latest['tp'] ?? null,
                'humidity' => $latest['hm'] ?? null,
                'pressure' => isset($latest['pr']) ? round($latest['pr'] / 100) : null,
                'wind' => $latest['ws'] ?? null,
                'source' => 'device_api_historical'
            ];
        }
        
        // Format 2: current/validated (v2 API)
        if (isset($data['current'])) {
            $current = $data['current'];
            return [
                'aqi' => $current['aqius'] ?? $current['pm25']['aqius'] ?? null,
                'pm25' => $current['pm25']['v'] ?? $current['pm25']['conc'] ?? $current['pm25'] ?? null,
                'pm10' => $current['pm10']['v'] ?? $current['pm10']['conc'] ?? $current['pm10'] ?? null,
                'pm1' => $current['pm1']['v'] ?? $current['pm1'] ?? null,
                'temp' => $current['tp'] ?? $current['temperature'] ?? null,
                'humidity' => $current['hu'] ?? $current['hm'] ?? $current['humidity'] ?? null,
                'pressure' => $current['pr'] ?? $current['pressure'] ?? null,
                'wind' => $current['ws'] ?? $current['wind_speed'] ?? null,
                'source' => 'device_api_current'
            ];
        }
        
        // Format 3: direct format (validated-data endpoint)
        if (isset($data['aqius']) || isset($data['pm25'])) {
            return [
                'aqi' => $data['aqius'] ?? $data['aqi'] ?? null,
                'pm25' => $data['pm25']['v'] ?? $data['pm25']['conc'] ?? $data['pm25'] ?? null,
                'pm10' => $data['pm10']['v'] ?? $data['pm10']['conc'] ?? $data['pm10'] ?? null,
                'pm1' => $data['pm1']['v'] ?? $data['pm1'] ?? null,
                'temp' => $data['tp'] ?? $data['temperature'] ?? null,
                'humidity' => $data['hu'] ?? $data['humidity'] ?? null,
                'pressure' => $data['pr'] ?? $data['pressure'] ?? null,
                'wind' => $data['ws'] ?? $data['wind_speed'] ?? null,
                'source' => 'device_api_validated'
            ];
        }
        
        return null;
    }
    
    private function fetchCityAPI($city, $state, $country) {
        $endpoints = [
            "https://api.iqair.com/v2/city?city={$city}&state={$state}&country={$country}&key=demo",
            "https://api.iqair.com/v2/nearest_city?lat=13.7563&lon=100.5018&key=demo",
            "https://api.iqair.com/v2/city?city=Bangkok&country=Thailand&key=demo"
        ];
        
        foreach ($endpoints as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AirQualityMonitor/2.0)',
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($result && $httpCode === 200) {
                $data = json_decode($result, true);
                
                if (isset($data['data']['current'])) {
                    $current = $data['data']['current'];
                    return [
                        'aqi' => $current['pollution']['aqius'] ?? null,
                        'pm25' => $current['pollution']['pm25']['v'] ?? null,
                        'pm10' => $current['pollution']['pm10']['v'] ?? null,
                        'temp' => $current['weather']['tp'] ?? null,
                        'humidity' => $current['weather']['hu'] ?? null,
                        'pressure' => $current['weather']['pr'] ?? null,
                        'wind' => $current['weather']['ws'] ?? null,
                        'source' => 'city_api_data'
                    ];
                }
            }
        }
        
        throw new Exception("All City API endpoints failed");
    }
    
    private function formatStandardOutput($data) {
        return [
            'current' => [
                'pollution' => [
                    'aqius' => $data['aqi'] ? intval($data['aqi']) : null,
                    'pm25' => $data['pm25'] ? ['v' => floatval($data['pm25'])] : null,
                    'pm10' => $data['pm10'] ? ['v' => floatval($data['pm10'])] : null,
                    'pm1' => $data['pm1'] ? ['v' => floatval($data['pm1'])] : null
                ],
                'weather' => [
                    'tp' => $data['temp'] ? intval($data['temp']) : null,
                    'hu' => $data['humidity'] ? intval($data['humidity']) : null,
                    'pr' => $data['pressure'] ? intval($data['pressure']) : null,
                    'ws' => $data['wind'] ? intval($data['wind']) : null
                ]
            ]
        ];
    }
    
    public function getData($sources = []) {
        $defaultSources = [
            [
                'type' => 'device_api',
                'url' => 'https://device.iqair.com/v2/6790850e7307e18fb3e0c815/validated-data',
                'priority' => 1
            ],
            [
                'type' => 'public_station',
                'url' => 'https://www.iqair.com/thailand/bangkok/bangkok/canvas-39',
                'priority' => 2
            ]
        ];
        
        $sources = $sources ?: $defaultSources;
        usort($sources, fn($a, $b) => $a['priority'] - $b['priority']);
        
        $errors = [];
        
        foreach ($sources as $source) {
            try {
                $data = null;
                
                switch ($source['type']) {
                    case 'station_api':
                        $data = $this->fetchDeviceAPI($source['url']);
                        break;
                    case 'public_station':
                        $data = $this->scrapePublicStation($source['url']);
                        break;
                    case 'device_api':
                        $data = $this->fetchDeviceAPI($source['url']);
                        break;
                    case 'city_api':
                        $data = $this->fetchCityAPI(
                            $source['city'] ?? 'Bangkok',
                            $source['state'] ?? 'Bangkok', 
                            $source['country'] ?? 'Thailand'
                        );
                        break;
                    default:
                        continue 2;
                }
                
                // Check if we have valid AQI data
                if ($data && isset($data['aqi']) && $data['aqi'] > 0) {
                    return [
                        'success' => true,
                        'data' => $this->formatStandardOutput($data),
                        'source' => $source['type'],
                        'source_url' => $source['url'] ?? 'N/A',
                        'data_source_detail' => $data['source'],
                        'timestamp' => date('Y-m-d H:i:s'),
                        'raw_data' => $data
                    ];
                }
                
            } catch (Exception $e) {
                $errors[] = [
                    'source' => $source['type'],
                    'error' => $e->getMessage(),
                    'url' => $source['url'] ?? 'N/A'
                ];
                continue;
            }
        }
        
        // If all sources failed
        return [
            'success' => false,
            'data' => null,
            'source' => 'none',
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'All data sources failed. No real data available.'
        ];
    }
}

// Main execution
try {
    $multiSource = new AirQualityMultiSource();
    
    // Get config from POST request
    $inputData = json_decode(file_get_contents('php://input'), true);
    $sources = $inputData['sources'] ?? null;
    
    $result = $multiSource->getData($sources);
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>