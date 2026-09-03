<?php

class ReviewModel {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function submitForPatientUser(
        int $appointmentId,
        int $patientUserId,
        int $rating,
        string $feedback = ''
    ): array {
        $feedback = trim($feedback);

        if ($appointmentId <= 0) {
            return ['success' => false, 'message' => 'Select a valid completed appointment.'];
        }
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Choose a rating from 1 to 5 stars.'];
        }
        if (mb_strlen($feedback) > 1000) {
            return ['success' => false, 'message' => 'Feedback must not exceed 1,000 characters.'];
        }

        try {
            $this->conn->beginTransaction();

            $appointment = $this->conn->prepare("
                SELECT a.appointment_id, a.status
                FROM appointments a
                JOIN patients p ON p.patient_id = a.patient_id
                WHERE a.appointment_id = :appointment_id
                    AND p.user_id = :patient_user_id
                FOR UPDATE
            ");
            $appointment->execute([
                ':appointment_id' => $appointmentId,
                ':patient_user_id' => $patientUserId,
            ]);
            $row = $appointment->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This appointment is not available for your account.'];
            }
            if ($row['status'] !== 'Completed') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only completed visits can be rated.'];
            }

            $existing = $this->conn->prepare('
                SELECT review_id
                FROM appointment_reviews
                WHERE appointment_id = :appointment_id
            ');
            $existing->execute([':appointment_id' => $appointmentId]);
            if ($existing->fetchColumn() !== false) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Feedback has already been submitted for this visit.'];
            }

            $insert = $this->conn->prepare("
                INSERT INTO appointment_reviews (appointment_id, rating, feedback)
                VALUES (:appointment_id, :rating, :feedback)
            ");
            $insert->execute([
                ':appointment_id' => $appointmentId,
                ':rating' => $rating,
                ':feedback' => $feedback !== '' ? $feedback : null,
            ]);

            $reviewId = (int) $this->conn->lastInsertId();
            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Thank you. Your visit feedback has been submitted.',
                'review' => [
                    'review_id' => $reviewId,
                    'appointment_id' => $appointmentId,
                    'rating' => $rating,
                    'feedback' => $feedback,
                ],
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Feedback has already been submitted for this visit.'];
            }
            error_log('submitForPatientUser review error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit your feedback right now. Please try again.'];
        }
    }

    public function getForAppointments(array $appointmentIds): array {
        $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));
        if (!$appointmentIds) return [];

        try {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $stmt = $this->conn->prepare("
                SELECT review_id, appointment_id, rating, feedback, created_at
                FROM appointment_reviews
                WHERE appointment_id IN ({$placeholders})
            ");
            $stmt->execute($appointmentIds);

            $reviews = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
                $reviews[(int) $review['appointment_id']] = $review;
            }
            return $reviews;
        } catch (PDOException $e) {
            error_log('getForAppointments review error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAdminSummary(): array {
        try {
            $summary = $this->conn->query("
                SELECT COUNT(*) AS total_reviews,
                    COALESCE(ROUND(AVG(rating), 1), 0) AS average_rating,
                    SUM(CASE WHEN feedback IS NOT NULL AND feedback <> '' THEN 1 ELSE 0 END) AS written_feedback_count
                FROM appointment_reviews
            ")->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_reviews' => (int) ($summary['total_reviews'] ?? 0),
                'average_rating' => (float) ($summary['average_rating'] ?? 0),
                'written_feedback_count' => (int) ($summary['written_feedback_count'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('getAdminSummary review error: ' . $e->getMessage());
            return ['total_reviews' => 0, 'average_rating' => 0.0, 'written_feedback_count' => 0];
        }
    }

    public function getAdminReviews(): array {
        try {
            $stmt = $this->conn->query("
                SELECT r.review_id, r.appointment_id, r.rating, r.feedback, r.created_at,
                    a.date AS appointment_date, a.completed_at,
                    p.firstname, p.lastname,
                    c.clinic_name,
                    GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ') AS service_names
                FROM appointment_reviews r
                JOIN appointments a ON a.appointment_id = r.appointment_id
                LEFT JOIN patients p ON p.patient_id = a.patient_id
                LEFT JOIN clinics c ON c.clinic_id = a.clinic_id
                LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id
                LEFT JOIN services s ON s.service_id = aps.service_id
                GROUP BY r.review_id, r.appointment_id, r.rating, r.feedback, r.created_at,
                    a.date, a.completed_at, p.firstname, p.lastname, c.clinic_name
                ORDER BY r.created_at DESC, r.review_id DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('getAdminReviews error: ' . $e->getMessage());
            return [];
        }
    }
}
