<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'flowshop_vipart');
define('DB_USER', 'flowshop_vipart');
define('DB_PASS', 'Vipart@2025');

class LiftDisplayManager {
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
            $this->createTables();
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        // Create lift_display_settings table
        $sql = "CREATE TABLE IF NOT EXISTS lift_display_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value LONGTEXT,
            setting_type ENUM('text', 'image', 'json') DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // Create lift_display_images table for storing image files
        $sql = "CREATE TABLE IF NOT EXISTS lift_display_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_type ENUM('logo', 'building') NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            image_size INT,
            mime_type VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // Create lift_display_notices table
        $sql = "CREATE TABLE IF NOT EXISTS lift_display_notices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
    }
    
    public function uploadImage($type, $imageData) {
        // Validate image type
        if (!in_array($type, ['logo', 'building'])) {
            throw new Exception("Invalid image type. Must be 'logo' or 'building'.");
        }
        
        // Decode base64 image
        if (strpos($imageData, 'data:') === 0) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
        }
        
        $imageData = base64_decode($imageData);
        if ($imageData === false) {
            throw new Exception("Invalid base64 image data");
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/lift-display/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        // Generate unique filename
        $filename = $type . '_' . time() . '_' . uniqid() . '.jpg';
        $filepath = $uploadDir . $filename;
        
        // Save image file
        if (file_put_contents($filepath, $imageData) === false) {
            throw new Exception("Failed to save image file");
        }
        
        // Get image info
        $imageInfo = getimagesize($filepath);
        $mimeType = $imageInfo['mime'] ?? 'image/jpeg';
        $fileSize = filesize($filepath);
        
        // Deactivate previous images of same type
        $sql = "UPDATE lift_display_images SET is_active = FALSE WHERE image_type = ? AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type]);
        
        // Insert new image record
        $sql = "INSERT INTO lift_display_images (image_type, image_name, image_path, image_size, mime_type) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type, $filename, $filepath, $fileSize, $mimeType]);
        
        // Update settings table
        $relativePath = 'uploads/lift-display/' . $filename;
        $sql = "INSERT INTO lift_display_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, 'image') 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type . '_image', $relativePath]);
        
        return [
            'success' => true,
            'message' => ucfirst($type) . ' image uploaded successfully',
            'image_path' => $relativePath,
            'image_id' => $this->pdo->lastInsertId()
        ];
    }
    
    public function getSetting($key) {
        $sql = "SELECT setting_value, setting_type FROM lift_display_settings WHERE setting_key = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        if ($result['setting_type'] === 'json') {
            return json_decode($result['setting_value'], true);
        }
        
        return $result['setting_value'];
    }
    
    public function setSetting($key, $value, $type = 'text') {
        if ($type === 'json') {
            $value = json_encode($value);
        }
        
        $sql = "INSERT INTO lift_display_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key, $value, $type]);
        
        return [
            'success' => true,
            'message' => 'Setting updated successfully'
        ];
    }
    
    public function getActiveImages() {
        $sql = "SELECT image_type, image_name, image_path FROM lift_display_images 
                WHERE is_active = TRUE ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        $images = [];
        while ($row = $stmt->fetch()) {
            $images[$row['image_type']] = [
                'name' => $row['image_name'],
                'path' => str_replace('../', '', $row['image_path'])
            ];
        }
        
        return $images;
    }
    
    public function getActiveNotices() {
        $sql = "SELECT title, message FROM lift_display_notices 
                WHERE is_active = TRUE 
                AND start_time <= NOW() 
                AND (end_time IS NULL OR end_time >= NOW())
                ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function saveNotice($title, $message, $startTime = null, $endTime = null) {
        $sql = "INSERT INTO lift_display_notices (title, message, start_time, end_time) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$title, $message, $startTime, $endTime]);
        
        return [
            'success' => true,
            'message' => 'Notice saved successfully',
            'notice_id' => $this->pdo->lastInsertId()
        ];
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method allowed");
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("No input data received");
    }
    
    $displayManager = new LiftDisplayManager();
    
    // Handle different actions
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'get_settings':
                $settings = [];
                $settings['logo_image'] = $displayManager->getSetting('logo_image');
                $settings['building_image'] = $displayManager->getSetting('building_image');
                $settings['display_config'] = $displayManager->getSetting('display_config');
                
                echo json_encode([
                    'success' => true,
                    'settings' => $settings
                ]);
                break;
                
            case 'save_setting':
                if (!isset($input['key']) || !isset($input['value'])) {
                    throw new Exception("Missing key or value for setting");
                }
                
                $type = $input['type'] ?? 'text';
                $result = $displayManager->setSetting($input['key'], $input['value'], $type);
                echo json_encode($result);
                break;
                
            case 'get_images':
                $images = $displayManager->getActiveImages();
                echo json_encode([
                    'success' => true,
                    'images' => $images
                ]);
                break;
                
            case 'get_notices':
                $notices = $displayManager->getActiveNotices();
                echo json_encode([
                    'success' => true,
                    'notices' => $notices
                ]);
                break;
                
            case 'save_notice':
                if (!isset($input['title']) || !isset($input['message'])) {
                    throw new Exception("Missing title or message for notice");
                }
                
                $result = $displayManager->saveNotice(
                    $input['title'],
                    $input['message'],
                    $input['start_time'] ?? null,
                    $input['end_time'] ?? null
                );
                echo json_encode($result);
                break;
                
            default:
                throw new Exception("Unknown action: " . $input['action']);
        }
    } 
    // Handle image upload
    else if (isset($input['type']) && isset($input['image'])) {
        $result = $displayManager->uploadImage($input['type'], $input['image']);
        echo json_encode($result);
    } 
    else {
        throw new Exception("Invalid request format");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>