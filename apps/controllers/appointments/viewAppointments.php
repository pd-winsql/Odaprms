<?php

session_start();
require_once '../../models/appointments.php';
require_once '../../../config/conn.php';

$db = new Database();
$conn=$db->connect();
$appModel = new Appointment($conn);
