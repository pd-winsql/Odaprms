<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/serviceModel.php';

class serviceController {
    private $services;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->services = new ServiceModel($conn);
    }

    private function requireAdmin() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
    }

    // ---------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------

    public function addCategory() {
        $this->requireAdmin();

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order       = (int)($_POST['order'] ?? 0);

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        $newId = $this->services->addCategory($name, $description, $order);

        if ($newId) {
            echo json_encode(['success' => true, 'message' => 'Category added.', 'category_id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add category.']);
        }
        exit;
    }

    public function updateCategory() {
        $this->requireAdmin();

        $id          = $_POST['category_id'] ?? '';
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order       = (int)($_POST['order'] ?? 0);

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        $result = $this->services->updateCategory($id, $name, $description, $order);

        echo json_encode($result
            ? ['success' => true, 'message' => 'Category updated.']
            : ['success' => false, 'message' => 'Failed to update category.']);
        exit;
    }

    public function deleteCategory($id) {
        $this->requireAdmin();
        $result = $this->services->deleteCategory($id);
        echo json_encode($result
            ? ['success' => true, 'message' => 'Category deleted.']
            : ['success' => false, 'message' => 'Failed to delete category.']);
        exit;
    }

    // ---------------------------------------------------------------
    // Services
    // ---------------------------------------------------------------

    public function addService() {
        $this->requireAdmin();

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

        echo json_encode(['success' => true, 'message' => 'Service added.', 'service_id' => $newId]);
        exit;
    }

    public function updateService() {
        $this->requireAdmin();

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

        $result = $this->services->updateService(
            $id,
            $name,
            $description,
            $icon,
            $category_id,
            $isActive,
            $order
        );

        echo json_encode($result
            ? ['success' => true, 'message' => 'Service updated.']
            : ['success' => false, 'message' => 'Failed to update service.']);
        exit;
    }

    public function deleteService($id) {
        $this->requireAdmin();
        $result = $this->services->deleteService($id);
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
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action     = $_GET['action'] ?? '';
    $controller = new serviceController();

    if ($action === 'deleteCategory') {
        $id = $_GET['id'] ?? null;
        if ($id) $controller->deleteCategory($id);
    } elseif ($action === 'deleteService') {
        $id = $_GET['id'] ?? null;
        if ($id) $controller->deleteService($id);
    } elseif ($action === 'bookingServices') {
        $controller->bookingServices();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
}