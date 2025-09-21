<?php
// Database Configuration for Lift Display System
define('DB_HOST', 'localhost');
define('DB_NAME', 'flowshop_vipart');
define('DB_USER', 'flowshop_vipart');
define('DB_PASS', 'Vipart@2025');

// API Configuration
define('OPENWEATHER_API_KEY', 'your-openweather-api-key'); // Replace with actual API key
define('GOOGLE_WEATHER_API_KEY', 'your-google-weather-api-key'); // Replace with actual API key

// Default coordinates (Bangkok)
define('DEFAULT_LAT', '13.7563');
define('DEFAULT_LON', '100.5018');

// AQI API URLs
define('DEVICE_API_URL', 'https://device.iqair.com/v2/6790850e7307e18fb3e0c815/validated-data');
define('PUBLIC_STATION_URL', 'https://www.iqair.com/thailand/bangkok/bangkok/canvas-39');

// Upload settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('UPLOAD_PATH', '../uploads/lift-display/');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Common functions
function createResponse($success, $data = null, $error = null) {
    return [
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function validateImageType($mimeType) {
    return in_array($mimeType, UPLOAD_ALLOWED_TYPES);
}

function generateUniqueFilename($prefix, $extension) {
    return $prefix . '_' . time() . '_' . uniqid() . '.' . $extension;
}
?>