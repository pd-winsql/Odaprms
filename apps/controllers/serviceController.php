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
        $icon        = trim($_POST['icon'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $order       = (int)($_POST['order'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Service name is required.']);
            exit;
        }

        $newId = $this->services->addService(
            $name,
            $description,
            $icon,
            $category_id,
            $isActive,
            $order
        );

        if (!$newId) {
            echo json_encode(['success' => false, 'message' => 'Failed to add service.']);
            exit;
        }

        $this->auditLog->recordForUser('service', (int) $newId, 'service_created', "Created service {$name}.", null, ['name' => $name, 'description' => $description, 'icon' => $icon, 'category_id' => $category_id, 'is_active' => $isActive, 'order' => $order], (int) $_SESSION['user_id']);

        echo json_encode(['success' => true, 'message' => 'Service added.', 'service_id' => $newId]);
        exit;
    }

    public function updateService() {
        $this->requireStaff();

        $id          = $_POST['service_id'] ?? '';
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon        = trim($_POST['icon'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $order       = (int)($_POST['order'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Service name is required.']);
            exit;
        }

        $old = $this->services->getServiceById($id);
        $result = $this->services->updateService(
            $id,
            $name,
            $description,
            $icon,
            $category_id,
            $isActive,
            $order
        );
        if ($result) $this->auditLog->recordForUser('service', (int) $id, 'service_updated', "Updated service {$name}.", $old ?: null, ['name' => $name, 'description' => $description, 'icon' => $icon, 'category_id' => $category_id, 'is_active' => $isActive, 'order' => $order], (int) $_SESSION['user_id']);

        echo json_encode($result
            ? ['success' => true, 'message' => 'Service updated.']
            : ['success' => false, 'message' => 'Failed to update service.']);
        exit;
    }

    public function deleteService($id) {
        $this->requireStaff();
        $old = $this->services->getServiceById($id);
        $result = $this->services->deleteService($id);
        if ($result && $old) $this->auditLog->recordForUser('service', (int) $id, 'service_deleted', 'Deleted service ' . $old['service_name'] . '.', $old, null, (int) $_SESSION['user_id']);
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
                    'service_icon'        => $row['service_icon'],
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
