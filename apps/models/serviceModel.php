<?php

class ServiceModel {
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // ---------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------

    public function getAllCategories() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM service_categories ORDER BY display_order ASC, category_name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllCategories error: " . $e->getMessage());
            return [];
        }
    }

    public function getCategoryById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM service_categories WHERE category_id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getCategoryById error: " . $e->getMessage());
            return null;
        }
    }

    public function addCategory($name, $description, $order) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO service_categories (category_name, category_description, display_order)
                 VALUES (:name, :description, :order)"
            );
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':order' => $order,
            ]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("addCategory error: " . $e->getMessage());
            return false;
        }
    }

    public function updateCategory($id, $name, $description, $order) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE service_categories
                 SET category_name = :name, category_description = :description, display_order = :order
                 WHERE category_id = :id"
            );
            return $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':description' => $description,
                ':order' => $order,
            ]);
        } catch (PDOException $e) {
            error_log("updateCategory error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteCategory($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM service_categories WHERE category_id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("deleteCategory error: " . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // Services
    // ---------------------------------------------------------------

    public function getAllServices() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM services ORDER BY display_order ASC, service_name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllServices error: " . $e->getMessage());
            return [];
        }
    }

    public function getServiceById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM services WHERE service_id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getServiceById error: " . $e->getMessage());
            return null;
        }
    }

    public function addService($name, $description, $icon, $isActive, $order) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO services (service_name, service_description, service_icon, is_active, display_order)
                 VALUES (:name, :description, :icon, :active, :order)"
            );
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':active' => $isActive,
                ':order' => $order,
            ]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("addService error: " . $e->getMessage());
            return false;
        }
    }

    public function updateService($id, $name, $description, $icon, $isActive, $order) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE services
                 SET service_name = :name, service_description = :description, service_icon = :icon,
                     is_active = :active, display_order = :order
                 WHERE service_id = :id"
            );
            return $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':active' => $isActive,
                ':order' => $order,
            ]);
        } catch (PDOException $e) {
            error_log("updateService error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteService($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM services WHERE service_id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("deleteService error: " . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // Service <-> Category mapping (many-to-many)
    // ---------------------------------------------------------------

    public function getAllServiceCategoryMap() {
        try {
            $stmt = $this->conn->prepare("SELECT service_id, category_id FROM service_category_map");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllServiceCategoryMap error: " . $e->getMessage());
            return [];
        }
    }

    // Replaces all category assignments for a service in one go —
    // simplest approach for an inline chip-toggle UI (delete then re-insert).
    public function setServiceCategories($serviceId, array $categoryIds) {
        try {
            $this->conn->beginTransaction();

            $del = $this->conn->prepare("DELETE FROM service_category_map WHERE service_id = :id");
            $del->execute([':id' => $serviceId]);

            if (!empty($categoryIds)) {
                $ins = $this->conn->prepare(
                    "INSERT INTO service_category_map (service_id, category_id) VALUES (:service_id, :category_id)"
                );
                foreach ($categoryIds as $categoryId) {
                    $ins->execute([':service_id' => $serviceId, ':category_id' => $categoryId]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("setServiceCategories error: " . $e->getMessage());
            return false;
        }
    }
}