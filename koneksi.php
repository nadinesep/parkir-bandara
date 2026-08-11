<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_parkir_bandara";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>