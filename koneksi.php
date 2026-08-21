<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_parkir_bandara";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Pastikan karakter khusus (aksen, tanda kutip, emoji, dll) tersimpan
// dan tertampil dengan benar, bukan hanya mengandalkan default server.
mysqli_set_charset($conn, "utf8mb4");
?>