<?php
// detalle_orden_api.php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["id_usuario"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado"]);
    exit();
}

require_once "conexion.php";

$fecha_orden = $_GET["fecha_orden"] ?? "";
$id_usuario  = isset($_GET["id_usuario"]) ? (int)$_GET["id_usuario"] : 0;
$id_bodega   = isset($_GET["id_bodega"]) ? (int)$_GET["id_bodega"] : 0;
$tipo        = $_GET["tipo"] ?? "";
$observacion = $_GET["observacion"] ?? "";

// Validación mínima
if ($fecha_orden === "" || $id_usuario <= 0 || $id_bodega <= 0 || ($tipo !== "ENTRADA" && $tipo !== "SALIDA")) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Parámetros inválidos"]);
    exit();
}

$sql = "
SELECT
  p.codigo,
  p.nombre AS producto,
  m.cantidad,
  m.tipo_movimiento,
  b.nombre AS bodega
FROM movimientos_inventario m
JOIN productos p ON p.id_producto = m.id_producto
JOIN bodegas b ON b.id_bodega = m.id_bodega
WHERE DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s') = ?
  AND m.id_usuario = ?
  AND m.id_bodega = ?
  AND m.tipo_movimiento = ?
  AND COALESCE(m.observacion,'') = ?
ORDER BY p.nombre
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("siiss", $fecha_orden, $id_usuario, $id_bodega, $tipo, $observacion);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        "codigo" => $row["codigo"],
        "producto" => $row["producto"],
        "cantidad" => (int)$row["cantidad"],
        "tipo_movimiento" => $row["tipo_movimiento"],
        "bodega" => $row["bodega"],
    ];
}

echo json_encode(["ok" => true, "items" => $items]);

$stmt->close();
$conexion->close();