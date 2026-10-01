<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Validator.php';
require_once __DIR__ . '/../../src/AssetService.php';

use App\AssetService;

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$service = new AssetService();
$method = $_SERVER['REQUEST_METHOD'];

// Parse input
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?: [];

// Get ID if present in query string
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $asset = $service->getAssetById($id);
                if (!$asset) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Asset not found']);
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $asset]);
            } else {
                $category = $_GET['category'] ?? null;
                $status = $_GET['status'] ?? null;
                $search = $_GET['search'] ?? null;
                $assets = $service->getAllAssets($category, $status, $search);
                echo json_encode(['success' => true, 'count' => count($assets), 'data' => $assets]);
            }
            break;

        case 'POST':
            $input = !empty($body) ? $body : $_POST;
            $newAsset = $service->createAsset($input);
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Asset created successfully', 'data' => $newAsset]);
            break;

        case 'PUT':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Asset ID is required for update']);
                exit;
            }
            $updated = $service->updateAsset($id, $body);
            echo json_encode(['success' => true, 'message' => 'Asset updated successfully', 'data' => $updated]);
            break;

        case 'PATCH':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Asset ID is required for stock adjustment']);
                exit;
            }
            if ($action === 'adjust' || isset($body['adjustment'])) {
                $adjusted = $service->adjustStock($id, $body);
                echo json_encode(['success' => true, 'message' => 'Stock adjusted successfully', 'data' => $adjusted]);
            } else {
                $updated = $service->updateAsset($id, $body);
                echo json_encode(['success' => true, 'message' => 'Asset patched successfully', 'data' => $updated]);
            }
            break;

        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Asset ID is required for deletion']);
                exit;
            }
            $service->deleteAsset($id);
            echo json_encode(['success' => true, 'message' => "Asset #{$id} deleted successfully"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => "Method {$method} not allowed"]);
            break;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $decoded = json_decode($e->getMessage(), true);
    echo json_encode(['success' => false, 'error' => $decoded ?: $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}
