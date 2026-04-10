<?php
require_once "conexion.php";

$nueva = password_hash("admin123", PASSWORD_DEFAULT);

$sql = "UPDATE usuarios SET password = ? WHERE usuario = 'admin'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $nueva);
$stmt->execute();

echo "Contraseña del admin cambiada a: admin123";