<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/serviceModel.php';
require_once '../models/auditLogModel.php';
require_once '../helpers/csrf.php';
require_once '../helpers/authorization.php';

class serviceController {
    private $services;
    private $auditLog;
    private const MAX_SERVICE_IMAGE_BYTES = 5 * 1024 * 1024;
    private const SERVICE_IMAGE_WEB_PATH = 'public/uploads/services/';

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->services = new ServiceModel($conn);
        $this->auditLog = new AuditLog($conn);
    }

    private function requireStaff() {
        header('Content-Type: application/json');
        vdRequireDentalAssistantJson();
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
            exit;
        }
    }

    private function storeServiceImage(?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'path' => null];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'The service image could not be uploaded.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_SERVICE_IMAGE_BYTES) {
            return ['success' => false, 'message' => 'Service images must be 5 MB or smaller.'];
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
            return ['success' => false, 'message' => 'Upload a valid JPG, PNG, or WebP image.'];
        }

        $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'services';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            return ['success' => false, 'message' => 'The service image directory is unavailable.'];
        }

        $filename = 'service-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'The service image could not be saved.'];
        }

        return ['success' => true, 'path' => self::SERVICE_IMAGE_WEB_PATH . $filename];
    }

    private function deleteUploadedServiceImage(?string $path): void
    {
        $normalized = str_replace('\\', '/', trim((string) $path));
        $filename = basename($normalized);
        if (!str_starts_with($normalized, self::SERVICE_IMAGE_WEB_PATH) || !str_starts_with($filename, 'service-')) {
            return;
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    private function servicePricingInput(): array
    {
        $rawPrice = trim((string) ($_POST['default_price'] ?? ''));
        if ($rawPrice === '') {
            $price = null;
        } elseif (!is_numeric($rawPrice) || (float) $rawPrice < 0 || (float) $rawPrice > 99999999.99) {
            return ['success' => false, 'message' => 'Default price must be a valid non-negative amount.'];
        } else {
            $price = round((float) $rawPrice, 2);
        }

        $unit = trim((string) ($_POST['billing_unit'] ?? 'service'));
        if (!in_array($unit, ['service', 'tooth'], true)) {
            return ['success' => false, 'message' => 'Select a valid pricing basis.'];
        }

        return ['success' => true, 'price' => $price, 'unit' => $unit];
    }

    // ---------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------

    public function addCategory() {
        $this->requireStaff();

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order       = (int)($_POST['order'] ?? 0);

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        $newId = $this->services->addCategory($name, $description, $order);

        if ($newId) {
            $this->auditLog->recordForUser('service_category', (int) $newId, 'service_category_created', "Created service category {$name}.", null, ['name' => $name, 'description' => $description, 'order' => $order], (int) $_SESSION['user_id']);
            echo json_encode(['success' => true, 'message' => 'Category added.', 'category_id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add category.']);
        }
        exit;
    }

    public function updateCategory() {
        $this->requireStaff();

        $id          = $_POST['category_id'] ?? '';
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order       = (int)($_POST['order'] ?? 0);

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        $old = $this->services->getCategoryById($id);
        $result = $this->services->updateCategory($id, $name, $description, $order);
        if ($result) $this->auditLog->recordForUser('service_category', (int) $id, 'service_category_updated', "Updated service category {$name}.", $old ?: null, ['name' => $name, 'description' => $description, 'order' => $order], (int) $_SESSION['user_id']);

        echo json_encode($result
            ? ['success' => true, 'message' => 'Category updated.']
            : ['success' => false, 'message' => 'Failed to update category.']);
        exit;
    }

    public function deleteCategory($id) {
        $this->requireStaff();
        $old = $this->services->getCategoryById($id);
        $result = $this->services->deleteCategory($id);
        if ($result && $old) $this->auditLog->recordForUser('service_category', (int) $id, 'service_category_deleted', 'Deleted service category ' . $old['category_name'] . '.', $old, null, (int) $_SESSION['user_id']);
        echo json_encode($result
            ? ['success' => true, 'message' => 'Category deleted.']
            : ['success' => false, 'message' => 'Failed to delete category.']);
        exit;
    }

    // ---------------------------------------------------------------
    // Services
    // ---------------------------------------------------------------

    public function addService() {
        $this->requireStaff();

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $order       = (int)($_POST['order'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Service name is required.']);
            exit;
        }

        $pricing = $this->servicePricingInput();
        if (!$pricing['success']) {
            echo json_encode($pricing);
            exit;
        }

        $imageResult = $this->storeServiceImage($_FILES['service_image'] ?? null);
        if (!$imageResult['success']) {
            echo json_encode($imageResult);
            exit;
        }
        $image = $imageResult['path'];

        $newId = $this->services->addService(
            $name,
            $description,
            $image,
            $category_id,
            $isActive,
            $order,
            $pricing['price'],
            $pricing['unit']
        );

        if (!$newId) {
            $this->deleteUploadedServiceImage($image);
            echo json_encode(['success' => false, 'message' => 'Failed to add service.']);
            exit;
        }

        $this->auditLog->recordForUser('service', (int) $newId, 'service_created', "Created service {$name}.", null, ['name' => $name, 'description' => $description, 'image' => $image, 'category_id' => $category_id, 'is_active' => $isActive, 'order' => $order, 'default_price' => $pricing['price'], 'billing_unit' => $pricing['unit']], (int) $_SESSION['user_id']);

        echo json_encode(['success' => true, 'message' => 'Service added.', 'service_id' => $newId]);
        exit;
    }

    public function updateService() {
        $this->requireStaff();

        $id          = $_POST['service_id'] ?? '';
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $order       = (int)($_POST['order'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Service name is required.']);
            exit;
        }

        $old = $this->services->getServiceById($id);
        if (!$old) {
            echo json_encode(['success' => false, 'message' => 'Service not found.']);
            exit;
        }

        $pricing = $this->servicePricingInput();
        if (!$pricing['success']) {
            echo json_encode($pricing);
            exit;
        }

        $imageResult = $this->storeServiceImage($_FILES['service_image'] ?? null);
        if (!$imageResult['success']) {
            echo json_encode($imageResult);
            exit;
        }
        $uploadedImage = $imageResult['path'];
        $removeImage = ($_POST['remove_image'] ?? '') === '1';
        $image = $uploadedImage ?: ($removeImage ? null : ($old['service_image'] ?? null));

        $result = $this->services->updateService(
            $id,
            $name,
            $description,
            $image,
            $category_id,
            $isActive,
            $order,
            $pricing['price'],
            $pricing['unit']
        );
        if ($result) {
            if (($old['service_image'] ?? null) !== $image) {
                $this->deleteUploadedServiceImage($old['service_image'] ?? null);
            }
            $this->auditLog->recordForUser('service', (int) $id, 'service_updated', "Updated service {$name}.", $old, ['name' => $name, 'description' => $description, 'image' => $image, 'category_id' => $category_id, 'is_active' => $isActive, 'order' => $order, 'default_price' => $pricing['price'], 'billing_unit' => $pricing['unit']], (int) $_SESSION['user_id']);
        } elseif ($uploadedImage) {
            $this->deleteUploadedServiceImage($uploadedImage);
        }

        echo json_encode($result
            ? ['success' => true, 'message' => 'Service updated.']
            : ['success' => false, 'message' => 'Failed to update service.']);
        exit;
    }

    public function deleteService($id) {
        $this->requireStaff();
        $old = $this->services->getServiceById($id);
        $result = $this->services->deleteService($id);
        if ($result && $old) {
            $this->deleteUploadedServiceImage($old['service_image'] ?? null);
            $this->auditLog->recordForUser('service', (int) $id, 'service_deleted', 'Deleted service ' . $old['service_name'] . '.', $old, null, (int) $_SESSION['user_id']);
        }
        echo json_encode($result
            ? ['success' => true, 'message' => 'Service deleted.']
            : ['success' => false, 'message' => 'Failed to delete service.']);
        exit;
    }

    public function homepage()
    {
        $services = $this->services->getHomepageServices();

        require '../views/index.php';
    }

    // ---------------------------------------------------------------
    // Public: services for the booking form (no admin required)
    // ---------------------------------------------------------------

    public function bookingServices()
    {
        header('Content-Type: application/json');

        $rows = $this->services->getHomepageServices();

        $categories = [];

        foreach ($rows as $row) {
            $catId = $row['category_id'];

            if (!isset($categories[$catId])) {
                $categories[$catId] = [
                    'category_id'          => $catId,
                    'category_name'        => $row['category_name'],
                    'category_description' => $row['category_description'],
                    'services'             => [],
                ];
            }

            if ($row['service_id']) {
                $categories[$catId]['services'][] = [
                    'service_id'          => $row['service_id'],
                    'service_name'        => $row['service_name'],
                    'service_description' => $row['service_description'],
                    'service_image'       => $row['service_image'],
                ];
            }
        }

        echo json_encode(['success' => true, 'categories' => array_values($categories)]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $controller = new serviceController();

    if ($action === 'addCategory') {
        $controller->addCategory();
    } elseif ($action === 'updateCategory') {
        $controller->updateCategory();
    } elseif ($action === 'addService') {
        $controller->addService();
    } elseif ($action === 'updateService') {
        $controller->updateService();
    } elseif ($action === 'deleteCategory') {
        $controller->deleteCategory($_POST['id'] ?? null);
    } elseif ($action === 'deleteService') {
        $controller->deleteService($_POST['id'] ?? null);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action     = $_GET['action'] ?? '';
    $controller = new serviceController();

    if ($action === 'bookingServices') {
        $controller->bookingServices();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
}
