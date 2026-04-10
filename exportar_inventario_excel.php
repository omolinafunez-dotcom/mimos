<?php
// exportar_inventario_excel.php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

// Nombre del archivo
$filename = "inventario_actual_" . date("Ymd_His") . ".csv";

// Headers para descarga
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// BOM UTF-8 para que Excel abra bien tildes y ñ
echo "\xEF\xBB\xBF";

$out = fopen("php://output", "w");

// Encabezados
fputcsv($out, ["Producto", "Categoría", "Bodega", "Stock Actual", "Stock Mínimo", "Estado"]);

// Consulta del inventario actual
$sql = "
SELECT
    p.nombre AS producto,
    c.nombre AS categoria,
    b.nombre AS bodega,
    i.cantidad AS stock_actual,
    i.stock_minimo AS stock_minimo
FROM inventario i
JOIN productos p   ON p.id_producto = i.id_producto
JOIN categorias c  ON c.id_categoria = p.id_categoria
JOIN bodegas b     ON b.id_bodega = i.id_bodega
ORDER BY c.nombre, p.nombre, b.nombre
";

$resultado = $conexion->query($sql);

if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        $estado = ((int)$row["stock_actual"] <= (int)$row["stock_minimo"]) ? "Stock Bajo" : "OK";

        fputcsv($out, [
            $row["producto"],
            $row["categoria"],
            $row["bodega"],
            (int)$row["stock_actual"],
            (int)$row["stock_minimo"],
            $estado
        ]);
    }
}

fclose($out);
$conexion->close();
exit();
?>