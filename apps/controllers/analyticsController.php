<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/analyticsModel.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Analytics are available to administrators only.']);
    exit;
}

try {
    $filters = AnalyticsModel::normalizeFilters($_GET);
    $conn = (new Database())->connect();
    if (!$conn) throw new RuntimeException('The analytics database is unavailable.');
    $model = new AnalyticsModel($conn);

    if (($_GET['action'] ?? '') === 'drilldown') {
        $drilldown = $model->getDrilldown(
            $filters,
            trim((string) ($_GET['dimension'] ?? '')),
            trim((string) ($_GET['value'] ?? '')),
            max(1, (int) ($_GET['page'] ?? 1)),
            10
        );
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode(['success' => true, 'data' => $drilldown], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $data = $model->getDashboardData($filters);

    if (($_GET['action'] ?? '') === 'export_csv') {
        $filename = 'admin-analytics-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        $safe = static function ($value): string {
            $value = (string) $value;
            return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
        };
        $row = static function ($output, array $values) use ($safe): void {
            fputcsv($output, array_map($safe, $values));
        };

        $row($output, ['Admin Analytics']);
        $row($output, ['Date range', $filters['date_from'] . ' to ' . $filters['date_to']]);
        $row($output, []);
        $row($output, ['KPI', 'Value']);
        foreach ($data['kpis'] as $label => $value) $row($output, [ucwords(str_replace('_', ' ', $label)), $value]);
        foreach ([
            'appointment_trend' => ['Appointment Trend', 'Period', 'Appointments'],
            'status_distribution' => ['Status Distribution', 'Status', 'Appointments'],
            'top_services' => ['Top Services', 'Service', 'Appointments'],
            'patient_growth' => ['Patient Growth', 'Period', 'New Patients'],
        ] as $key => [$title, $first, $second]) {
            $row($output, []); $row($output, [$title]); $row($output, [$first, $second]);
            foreach ($data[$key] as $item) $row($output, [$item['label'], $item['value']]);
        }
        $row($output, []); $row($output, ['Clinic Comparison']);
        $row($output, ['Clinic', 'Appointments', 'Completed', 'Utilization Rate']);
        foreach ($data['clinic_comparison'] as $item) {
            $row($output, [$item['label'], $item['appointments'], $item['completed'], $item['utilization_rate'] . '%']);
        }
        fclose($output);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Analytics error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Analytics could not be loaded.']);
}
