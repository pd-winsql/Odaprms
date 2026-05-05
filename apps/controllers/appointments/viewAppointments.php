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


// TODO: create the logic for retrieving the data from the database and displaying it in the viewAppointments.html file here. You can use the seeAppointment() method in the Appointment model to retrieve the data from the database. Make sure to connect the clinic schedule with the date input field here as well.