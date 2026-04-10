<?php
// exportar_orden_excel.php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

$fecha_orden = $_GET["fecha_orden"] ?? "";
$id_usuario  = isset($_GET["id_usuario"]) ? (int)$_GET["id_usuario"] : 0;
$id_bodega   = isset($_GET["id_bodega"]) ? (int)$_GET["id_bodega"] : 0;
$tipo        = $_GET["tipo"] ?? "";
$observacion = $_GET["observacion"] ?? "";

if ($fecha_orden === "" || $id_usuario <= 0 || $id_bodega <= 0 || ($tipo !== "ENTRADA" && $tipo !== "SALIDA")) {
    die("Parámetros inválidos.");
}

$sql = "
SELECT
  p.codigo,
  p.nombre AS producto,
  m.cantidad,
  m.tipo_movimiento,
  b.nombre AS bodega,
  u.usuario AS usuario,
  DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s') AS fecha_orden,
  COALESCE(m.observacion,'') AS observacion
FROM movimientos_inventario m
JOIN productos p ON p.id_producto = m.id_producto
JOIN bodegas b ON b.id_bodega = m.id_bodega
JOIN usuarios u ON u.id_usuario = m.id_usuario
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

// Headers para Excel (CSV)
$filename = "orden_detalle_" . preg_replace('/[^0-9]/', '', $fecha_orden) . ".csv";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// BOM para Excel (UTF-8)
echo "\xEF\xBB\xBF";

// Encabezados CSV
$out = fopen("php://output", "w");
fputcsv($out, ["Fecha/Hora", "Usuario", "Bodega", "Movimiento", "Código", "Producto", "Cantidad", "Observación"]);

while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
        $row["fecha_orden"],
        $row["usuario"],
        $row["bodega"],
        $row["tipo_movimiento"],
        $row["codigo"],
        $row["producto"],
        (int)$row["cantidad"],
        $row["observacion"],
    ]);
}

fclose($out);

$stmt->close();
$conexion->close();
exit();