<?php
session_start();
require_once "conexion.php";

$usuario = trim($_POST["usuario"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($usuario === "" || $password === "") {
    header("Location: login.php?err=1");
    exit();
}

$sql = "SELECT id_usuario, nombre, usuario, password, rol, activo
        FROM usuarios
        WHERE usuario = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: login.php?err=1");
    exit();
}

$row = $res->fetch_assoc();

if ((int)$row["activo"] !== 1) {
    header("Location: login.php?err=1");
    exit();
}

// 🔐 Validación correcta con hash
if (!password_verify($password, $row["password"])) {
    header("Location: login.php?err=1");
    exit();
}

// Crear sesión
$_SESSION["id_usuario"] = (int)$row["id_usuario"];
$_SESSION["nombre_usuario"] = $row["nombre"];
$_SESSION["usuario"] = $row["usuario"];
$_SESSION["rol"] = $row["rol"];

header("Location: index.php");
exit();