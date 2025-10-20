<?php

$servername = "localhost";   // usually 'localhost'
$username   = "root";        // default username for XAMPP/WAMP
$password   = "";            // leave blank if no password
$database   = "faculty_eval"; // name of your database

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

session_start();
