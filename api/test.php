<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Test API endpoints
echo json_encode([
    'success' => true,
    'message' => 'Lift Display API System is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'available_endpoints' => [
        'upload-image.php' => 'Image upload and settings management',
        'lift-display-data.php' => 'Get AQI and weather data',
        'get-notices.php' => 'Get active notices',
        'air-quality-multi.php' => 'Multi-source AQI API',
        'config.php' => 'Configuration file'
    ],
    'test_urls' => [
        'Data API' => './lift-display-data.php',
        'Notices API' => './get-notices.php',
        'AQI API' => './air-quality-multi.php'
    ]
], JSON_PRETTY_PRINT);
?>