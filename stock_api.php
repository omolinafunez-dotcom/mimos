<?php
// stock_api.php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["id_usuario"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado"]);
    exit();
}

require_once "conexion.php";

$id_producto = isset($_GET["id_producto"]) ? (int)$_GET["id_producto"] : 0;
$id_bodega   = isset($_GET["id_bodega"]) ? (int)$_GET["id_bodega"] : 0;

if ($id_producto <= 0 || $id_bodega <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Parámetros inválidos"]);
    exit();
}

$sql = "SELECT cantidad FROM inventario WHERE id_producto = ? AND id_bodega = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id_producto, $id_bodega);
$stmt->execute();
$res = $stmt->get_result();

$stock = 0;
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $stock = (int)$row["cantidad"];
}

echo json_encode(["ok" => true, "stock" => $stock]);

$stmt->close();
$conexion->close();