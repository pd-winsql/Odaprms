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
            $this->conn->beginTransaction();

            $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM services");
            $countStmt->execute();
            $serviceCount = (int)$countStmt->fetchColumn();

            $desiredOrder = (int)$order;
            if ($desiredOrder <= 0) {
                $desiredOrder = $serviceCount + 1;
            } elseif ($desiredOrder > $serviceCount + 1) {
                $desiredOrder = $serviceCount + 1;
            }

            $shiftStmt = $this->conn->prepare("
                UPDATE services
                SET display_order = display_order + 1
                WHERE display_order >= :desired_order
            ");
            $shiftStmt->execute([
                ':desired_order' => $desiredOrder
            ]);

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
                ':order' => $desiredOrder
            ]);

            $this->conn->commit();
            return $this->conn->lastInsertId();

        } catch(PDOException $e){
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateService($service_id, $name, $description, $icon, $category_id, $is_active, $display_order) {
        try {
            $this->conn->beginTransaction();

            $currentStmt = $this->conn->prepare("
                SELECT display_order
                FROM services
                WHERE service_id = :service_id
            ");
            $currentStmt->execute([':service_id' => $service_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $this->conn->rollBack();
                return false;
            }

            $currentOrder = (int)$current['display_order'];

            $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM services");
            $countStmt->execute();
            $serviceCount = (int)$countStmt->fetchColumn();

            $desiredOrder = (int)$display_order;
            if ($desiredOrder <= 0) {
                $desiredOrder = $serviceCount;
            } elseif ($desiredOrder > $serviceCount) {
                $desiredOrder = $serviceCount;
            }

            if ($desiredOrder > $currentOrder) {
                $shiftDownStmt = $this->conn->prepare("
                    UPDATE services
                    SET display_order = display_order - 1
                    WHERE service_id <> :service_id
                      AND display_order > :current_order
                      AND display_order <= :desired_order
                ");
                $shiftDownStmt->execute([
                    ':service_id' => $service_id,
                    ':current_order' => $currentOrder,
                    ':desired_order' => $desiredOrder
                ]);
            } elseif ($desiredOrder < $currentOrder) {
                $shiftUpStmt = $this->conn->prepare("
                    UPDATE services
                    SET display_order = display_order + 1
                    WHERE service_id <> :service_id
                      AND display_order >= :desired_order
                      AND display_order < :current_order
                ");
                $shiftUpStmt->execute([
                    ':service_id' => $service_id,
                    ':desired_order' => $desiredOrder,
                    ':current_order' => $currentOrder
                ]);
            }

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

            $updated = $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':category_id' => $category_id,
                ':active' => $is_active,
                ':display_order' => $desiredOrder,
                ':service_id' => $service_id
            ]);

            if (!$updated) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("updateService error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteService($id) {
        try {
            $this->conn->beginTransaction();

            $currentStmt = $this->conn->prepare("
                SELECT display_order
                FROM services
                WHERE service_id = :id
            ");
            $currentStmt->execute([':id' => $id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $this->conn->rollBack();
                return false;
            }

            $deletedOrder = (int)$current['display_order'];

            $deleteStmt = $this->conn->prepare("DELETE FROM services WHERE service_id = :id");
            $deleted = $deleteStmt->execute([':id' => $id]);

            if (!$deleted) {
                $this->conn->rollBack();
                return false;
            }

            $compactStmt = $this->conn->prepare("
                UPDATE services
                SET display_order = display_order - 1
                WHERE display_order > :deleted_order
            ");
            $compactStmt->execute([
                ':deleted_order' => $deletedOrder
            ]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
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