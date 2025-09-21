<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $config = json_decode($input, true);
    
    if ($config && json_last_error() === JSON_ERROR_NONE) {
        // Save config to JSON file
        $result = file_put_contents('config.json', json_encode($config, JSON_PRETTY_PRINT));
        
        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Config saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save config file']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>