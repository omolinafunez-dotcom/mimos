<?php
include "conexion.php";

$fecha_inicio = $_GET['inicio'];
$fecha_fin = $_GET['fin'];

$sql = "
SELECT
    p.codigo,
    p.nombre,
    SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.cantidad ELSE 0 END) AS total_entradas,
    SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' THEN m.cantidad ELSE 0 END) AS total_salidas
FROM movimientos_inventario m
JOIN productos p ON m.id_producto = p.id_producto
WHERE m.fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY p.id_producto, p.codigo, p.nombre
ORDER BY p.nombre
";

$resultado = $conexion->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_movimientos.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Código', 'Producto', 'Entradas', 'Salidas']);

while ($row = $resultado->fetch_assoc()) {
    fputcsv($output, [
        $row['codigo'],
        $row['nombre'],
        $row['total_entradas'],
        $row['total_salidas']
    ]);
}

fclose($output);
exit;
?>
