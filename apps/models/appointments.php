<?php
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();

class Appointment {
    private $conn;
    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function addAppointment($lastname, $firstname, $middlename, $age, $gender, 
    $phone_number, $email, $clinic, $service, $date, $time ) {
        $stmt = $this->conn->prepare("INSERT INTO appointments (lastname, firstName, middlename, age, gender, 
    phone_number, email, clinic, service, date, time) 
    VALUES (:lastname, :firstName, :middlename, :age, :gender, 
    :phone_number, :email, :clinic, :service, :date, :time)");

    $stmt->execute([
        ':lastname' => $lastname,
        ':firstName' => $firstname,
        ':middlename' => $middlename,
        ':age' => $age,
        ':gender' => $gender,
        ':phone_number' => $phone_number,
        ':email' => $email,
        ':clinic' => $clinic,
        ':service' => $service,
        ':date' => $date,
        'time' => $time
    ]);
    }

    public function seeAppointment($lastname, $firstname, $middlename, $age, $gender, 
    $phone_number, $email, $clinic, $service, $date, $time)
}