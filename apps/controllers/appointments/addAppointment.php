<?php

session_start();
require_once '../../models/appointments.php';
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();
$appModel = new Appointment($conn);

function allKeysSet(array $keys, array $array): bool {
    return !array_diff($keys, array_keys($array));
}

if(allKeysSet([
    'lastname', 'firstname', 'middlename', 'age', 'gender', 'phone_number', 'email', 'clinic', 'service', 'date', 'time'
], $_POST)) {
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $clinic = $_POST['clinic'];
    $service = $_POST['service'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    // TODO: make sure to connect the clinic schedule with the date input field here.
}