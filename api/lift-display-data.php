<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Include existing AQI system if available
if (file_exists('air-quality-multi.php')) {
    require_once 'air-quality-multi.php';
}

class LiftDisplayData {
    private $aqiSystem;
    
    public function __construct() {
        if (class_exists('AirQualityMultiSource')) {
            $this->aqiSystem = new AirQualityMultiSource();
        } else {
            $this->aqiSystem = null;
        }
    }
    
    public function getAllData() {
        try {
            // Get AQI data (both indoor and outdoor)
            $aqiData = $this->getAQIData();
            
            // Get weather data
            $weatherData = $this->getWeatherData();
            
            // Get notices
            $notices = $this->getNotices();
            
            return [
                'success' => true,
                'data' => [
                    'aqi' => $aqiData,
                    'weather' => $weatherData,
                    'notices' => $notices,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => $this->getDemoData(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    private function getAQIData() {
        $outdoorAQI = null;
        
        // Try to get outdoor AQI from existing system
        if ($this->aqiSystem) {
            $outdoorSources = [
                [
                    'type' => 'station_api',
                    'url' => 'https://device.iqair.com/v2/6790850e7307e18fb3e0c815/validated-data',
                    'priority' => 1
                ],
                [
                    'type' => 'public_station',
                    'url' => 'https://www.iqair.com/thailand/bangkok/bangkok/canvas-39',
                    'priority' => 2
                ]
            ];
            
            $outdoorResult = $this->aqiSystem->getData($outdoorSources);
            
            // Format outdoor AQI
            if ($outdoorResult['success'] && $outdoorResult['data']) {
                $pollution = $outdoorResult['data']['current']['pollution'];
                $outdoorAQI = [
                    'value' => $pollution['aqius'] ?? 0,
                    'status' => $this->getAQIStatus($pollution['aqius'] ?? 0),
                    'pollutant' => 'PM2.5',
                    'concentration' => ($pollution['pm25']['v'] ?? 0) . ' μg/m³'
                ];
            }
        }
        
        // Get indoor AQI (simulated)
        $indoorAQI = $this->getIndoorAQI();
        
        return [
            'indoor' => $indoorAQI,
            'outdoor' => $outdoorAQI ?: $this->getDemoOutdoorAQI()
        ];
    }
    
    private function getIndoorAQI() {
        // Simulated indoor data (typically better than outdoor)
        $simulatedAQI = rand(30, 60);
        $simulatedPM = rand(15, 35);
        
        return [
            'value' => $simulatedAQI,
            'status' => $this->getAQIStatus($simulatedAQI),
            'pollutant' => 'PM10',
            'concentration' => $simulatedPM . ' μg/m³'
        ];
    }
    
    private function getWeatherData() {
        try {
            // Try OpenWeatherMap API (free alternative)
            $apiKey = 'your-openweather-api-key'; // Replace with actual API key
            
            if ($apiKey === 'your-openweather-api-key') {
                return $this->getDemoWeather();
            }
            
            $lat = '13.7563'; // Bangkok coordinates
            $lon = '100.5018';
            
            $currentUrl = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$apiKey}&units=metric";
            $forecastUrl = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&appid={$apiKey}&units=metric";
            
            $currentData = $this->fetchWeatherApi($currentUrl);
            $forecastData = $this->fetchWeatherApi($forecastUrl);
            
            if ($currentData && $forecastData) {
                return $this->formatOpenWeatherData($currentData, $forecastData);
            }
            
            return $this->getDemoWeather();
            
        } catch (Exception $e) {
            error_log("Weather API error: " . $e->getMessage());
            return $this->getDemoWeather();
        }
    }
    
    private function fetchWeatherApi($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result && $httpCode === 200) {
            return json_decode($result, true);
        }
        
        return null;
    }
    
    private function formatOpenWeatherData($current, $forecast) {
        // Map OpenWeather icons to emojis
        $iconMap = [
            '01d' => '☀️', '01n' => '🌙',
            '02d' => '⛅', '02n' => '☁️',
            '03d' => '☁️', '03n' => '☁️',
            '04d' => '☁️', '04n' => '☁️',
            '09d' => '🌧️', '09n' => '🌧️',
            '10d' => '🌦️', '10n' => '🌧️',
            '11d' => '⛈️', '11n' => '⛈️',
            '13d' => '❄️', '13n' => '❄️',
            '50d' => '🌫️', '50n' => '🌫️'
        ];
        
        $currentIcon = $current['weather'][0]['icon'] ?? '01d';
        
        // Get next 4 forecasts
        $forecastItems = [];
        for ($i = 0; $i < 4 && $i < count($forecast['list']); $i++) {
            $item = $forecast['list'][$i];
            $time = date('H:i', $item['dt']);
            $icon = $iconMap[$item['weather'][0]['icon']] ?? '☀️';
            
            $forecastItems[] = [
                'time' => $time,
                'icon' => $icon
            ];
        }
        
        return [
            'current' => [
                'icon' => $iconMap[$currentIcon] ?? '☀️',
                'low' => round($current['main']['temp_min']),
                'high' => round($current['main']['temp_max']),
                'humidity' => $current['main']['humidity'],
                'pressure' => $current['main']['pressure']
            ],
            'forecast' => $forecastItems,
            'source' => 'openweathermap'
        ];
    }
    
    private function getDemoWeather() {
        return [
            'current' => [
                'icon' => '🌧️',
                'low' => 26,
                'high' => 34,
                'humidity' => 75,
                'pressure' => 1012
            ],
            'forecast' => [
                ['time' => '11:00', 'icon' => '☀️'],
                ['time' => '12:00', 'icon' => '⛅'],
                ['time' => '13:00', 'icon' => '☁️'],
                ['time' => '14:00', 'icon' => '⛈️']
            ],
            'source' => 'demo'
        ];
    }
    
    private function getNotices() {
        try {
            // Use upload-image.php API to get notices
            $postData = json_encode(['action' => 'get_notices']);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => './upload-image.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($result && $httpCode === 200) {
                $data = json_decode($result, true);
                if ($data['success']) {
                    return $data['notices'];
                }
            }
            
        } catch (Exception $e) {
            error_log("Notices API error: " . $e->getMessage());
        }
        
        return []; // No notices by default
    }
    
    private function getAQIStatus($aqi) {
        if ($aqi <= 50) return 'Good';
        if ($aqi <= 100) return 'Moderate';
        if ($aqi <= 150) return 'Unhealthy for Sensitive Groups';
        if ($aqi <= 200) return 'Unhealthy';
        if ($aqi <= 300) return 'Very Unhealthy';
        return 'Hazardous';
    }
    
    private function getDemoOutdoorAQI() {
        return [
            'value' => 84,
            'status' => 'Moderate',
            'pollutant' => 'PM2.5',
            'concentration' => '27 μg/m³'
        ];
    }
    
    private function getDemoData() {
        return [
            'aqi' => [
                'indoor' => [
                    'value' => 40,
                    'status' => 'Good',
                    'pollutant' => 'PM10',
                    'concentration' => '43.5 μg/m³'
                ],
                'outdoor' => [
                    'value' => 84,
                    'status' => 'Moderate',
                    'pollutant' => 'PM2.5',
                    'concentration' => '27 μg/m³'
                ]
            ],
            'weather' => $this->getDemoWeather(),
            'notices' => []
        ];
    }
}

// Main execution
try {
    $liftDisplay = new LiftDisplayData();
    $result = $liftDisplay->getAllData();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>