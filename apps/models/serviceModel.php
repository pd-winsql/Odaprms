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

    public function addService($name, $description, $icon, $category_id, $isActive, $order)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO services
                (
                    service_name,
                    service_description,
                    service_icon,
                    category_id,
                    is_active,
                    display_order
                )
                VALUES
                (
                    :name,
                    :description,
                    :icon,
                    :category_id,
                    :active,
                    :order
                )
            ");

            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':category_id' => $category_id,
                ':active' => $isActive,
                ':order' => $order
            ]);

            return $this->conn->lastInsertId();

        } catch(PDOException $e){
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateService($service_id, $name, $description, $icon, $category_id, $is_active, $display_order) {

        $stmt = $this->conn->prepare("
            UPDATE services
            SET
                service_name = :name,
                service_description = :description,
                service_icon = :icon,
                category_id = :category_id,
                is_active = :active,
                display_order = :display_order
            WHERE service_id = :service_id
        ");

        return $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':icon' => $icon,
            ':category_id' => $category_id,
            ':active' => $is_active,
            ':display_order' => $display_order,
            ':service_id' => $service_id
        ]);
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

    public function getServicesByCategory($category_id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM services
            WHERE category_id = :category_id
            ORDER BY display_order
        ");

        $stmt->execute([
            ':category_id' => $category_id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHomepageServices()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    c.category_id,
                    c.category_name,
                    c.category_description,
                    s.service_id,
                    s.service_name,
                    s.service_description,
                    s.service_icon
                FROM service_categories c
                LEFT JOIN services s
                    ON s.category_id = c.category_id
                    AND s.is_active = 1
                ORDER BY
                    c.display_order,
                    s.display_order
            ");

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch(PDOException $e){
            error_log($e->getMessage());
            return [];
        }
    }

}