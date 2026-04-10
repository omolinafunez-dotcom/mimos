<?php
// conexion.php
$host = "localhost";
$user = "oscar";
$pass = "root";           // en XAMPP
$db   = "sistema_inventarios_am";

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");


?>