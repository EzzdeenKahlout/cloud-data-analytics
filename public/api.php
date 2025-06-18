<?php
require_once 'vendor/autoload.php';

use CloudAnalytics\CloudAnalyticsService;

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type
header('Content-Type: application/json');

try {
    $service = new CloudAnalyticsService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Remove 'api.php' from path parts if present
    if ($pathParts[0] === 'api.php') {
        array_shift($pathParts);
    }
    
    $endpoint = $pathParts[0] ?? '';
    
    switch ($endpoint) {
        case 'auth':
            if ($method === 'GET') {
                if (isset($_GET['code'])) {
                    // Handle auth code
                    $result = $service->handleAuthCode($_GET['code']);
                    echo json_encode(['success' => $result, 'message' => 'Authentication successful']);
                } else {
                    // Get auth URL
                    if ($service->isAuthenticated()) {
                        echo json_encode(['authenticated' => true]);
                    } else {
                        $authUrl = $service->getAuthUrl();
                        echo json_encode(['authenticated' => false, 'auth_url' => $authUrl]);
                    }
                }
            }
            break;
            
        case 'upload':
            if ($method === 'POST' && isset($_FILES['document'])) {
                $uploadedFile = $_FILES['document'];
                $tempPath = 'uploads/' . uniqid() . '_' . $uploadedFile['name'];
                
                if (move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
                    $result = $service->uploadDocument($tempPath, $uploadedFile['name']);
                    unlink($tempPath); // Clean up
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
                }
            }
            break;
            
        case 'sync':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $query = $input['query'] ?? null;
                $result = $service->syncFromDrive($query);
                echo json_encode($result);
            }
            break;
            
        case 'search':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $keywords = $input['keywords'] ?? '';
                $searchInContent = $input['search_in_content'] ?? true;
                $result = $service->searchDocuments($keywords, $searchInContent);
                echo json_encode($result);
            }
            break;
            
        case 'sort':
            if ($method === 'GET') {
                $sortBy = $_GET['sort_by'] ?? 'title';
                $order = $_GET['order'] ?? 'ASC';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $result = $service->sortDocuments($sortBy, $order, $limit, $offset);
                echo json_encode($result);
            }
            break;
            
        case 'classify':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $forceReclassify = $input['force_reclassify'] ?? false;
                $result = $service->classifyDocuments($forceReclassify);
                echo json_encode($result);
            }
            break;
            
        case 'statistics':
            if ($method === 'GET') {
                $result = $service->getStatistics();
                echo json_encode($result);
            }
            break;
            
        case 'document':
            if ($method === 'GET' && isset($pathParts[1])) {
                $documentId = (int)$pathParts[1];
                $result = $service->getDocument($documentId);
                echo json_encode($result);
            }
            break;
            
        case 'classification-tree':
            if ($method === 'GET') {
                $result = $service->getClassificationTree();
                echo json_encode($result);
            } elseif ($method === 'PUT') {
                $input = json_decode(file_get_contents('php://input'), true);
                $tree = $input['tree'] ?? [];
                $result = $service->updateClassificationTree($tree);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

