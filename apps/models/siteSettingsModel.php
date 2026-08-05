<?php

class SiteSettingsModel {
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Fields an admin is allowed to update, grouped by the dashboard card
    // that edits them — used to whitelist incoming POST data per save action.
    const FIELD_GROUPS = [
        'brand'   => ['brand_name_top', 'brand_name_sub'],
        'hero'    => ['hero_system_tag', 'hero_eyebrow', 'hero_title', 'hero_subtext'],
        'about'   => ['about_intro', 'pillar1_title', 'pillar1_desc', 'pillar2_title', 'pillar2_desc', 'pillar3_title', 'pillar3_desc'],
        'contact' => ['contact_address', 'contact_phone', 'contact_email'],
        'payment' => ['deposit_amount', 'payment_deadline_minutes', 'gcash_account_name', 'gcash_account_number'],
    ];

    // Fetch the single settings row. Falls back to the table's own DEFAULT
    // values (via an empty INSERT) if the row is ever missing.
    public function getSettings() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM site_settings WHERE id = 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->conn->prepare("INSERT INTO site_settings (id) VALUES (1)")->execute();
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $row ?: [];
        } catch (PDOException $e) {
            error_log("getSettings error: " . $e->getMessage());
            return [];
        }
    }

    // Updates only the fields belonging to $group (see FIELD_GROUPS),
    // using values from $data. Unknown keys in $data are ignored.
    public function updateGroup($group, array $data, $updatedBy = 'admin') {
        if (!isset(self::FIELD_GROUPS[$group])) {
            return false;
        }

        $fields = self::FIELD_GROUPS[$group];
        $setSql = [];
        $params = [];

        foreach ($fields as $field) {
            $setSql[] = "`$field` = :$field";
            $params[":$field"] = $data[$field] ?? null;
        }

        $setSql[] = "last_updated_by = :updated_by";
        $setSql[] = "last_updated_at = NOW()";
        $params[':updated_by'] = $updatedBy;

        try {
            $sql = "UPDATE site_settings SET " . implode(', ', $setSql) . " WHERE id = 1";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("updateGroup ($group) error: " . $e->getMessage());
            return false;
        }
    }

    public function updateLogo($filename, $updatedBy = 'admin') {
        try {
            $stmt = $this->conn->prepare("
                UPDATE site_settings
                SET site_logo = :logo, last_updated_by = :updated_by, last_updated_at = NOW()
                WHERE id = 1
            ");
            return $stmt->execute([':logo' => $filename, ':updated_by' => $updatedBy]);
        } catch (PDOException $e) {
            error_log("updateLogo error: " . $e->getMessage());
            return false;
        }
    }

    public function updateGcashQr($filename, $updatedBy = 'admin') {
        try {
            $stmt = $this->conn->prepare("
                UPDATE site_settings
                SET gcash_qr_path = :qr, last_updated_by = :updated_by, last_updated_at = NOW()
                WHERE id = 1
            ");
            return $stmt->execute([':qr' => $filename, ':updated_by' => $updatedBy]);
        } catch (PDOException $e) {
            error_log('updateGcashQr error: ' . $e->getMessage());
            return false;
        }
    }
}
