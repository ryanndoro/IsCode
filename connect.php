<?php
$connect = mysqli_connect("localhost", "root", "", "iscode");

// Check connection
if (!$connect) {
    die("Connection failed: " .mysqli_connect_error($connect));
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>