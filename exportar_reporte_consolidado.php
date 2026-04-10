<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

// =========================
// VALIDAR FECHAS
// =========================
$fecha_inicio = $_GET["fecha_inicio"] ?? "";
$fecha_fin    = $_GET["fecha_fin"] ?? "";

if ($fecha_inicio === "" || $fecha_fin === "") {
    die("Debe seleccionar una fecha de inicio y una fecha final.");
}

if ($fecha_inicio > $fecha_fin) {
    die("La fecha de inicio no puede ser mayor que la fecha final.");
}

// Para incluir todo el día
$dtInicio = $fecha_inicio . " 00:00:00";
$dtFin    = $fecha_fin . " 23:59:59";

// =========================
// CONSULTA CONSOLIDADA
// Agrupa por fecha y producto
// =========================
$sql = "
SELECT
    DATE(m.fecha) AS fecha,
    p.codigo AS codigo_producto,
    p.nombre AS producto,
    p.precio_unitario,
    
    SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.cantidad ELSE 0 END) AS cantidad_entrada,
    SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' THEN m.cantidad ELSE 0 END) AS cantidad_salida,
    SUM(m.cantidad) AS cantidad_total_movida,

    SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN (m.cantidad * p.precio_unitario) ELSE 0 END) AS costo_entradas,
    SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' THEN (m.cantidad * p.precio_unitario) ELSE 0 END) AS valor_salidas

FROM movimientos_inventario m
INNER JOIN productos p ON p.id_producto = m.id_producto
WHERE m.fecha BETWEEN ? AND ?
GROUP BY DATE(m.fecha), p.id_producto, p.codigo, p.nombre, p.precio_unitario
ORDER BY DATE(m.fecha) ASC, p.nombre ASC
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $dtInicio, $dtFin);
$stmt->execute();
$resultado = $stmt->get_result();

// =========================
// NOMBRE DEL ARCHIVO
// =========================
$nombreArchivo = "reporte_consolidado_" . $fecha_inicio . "_a_" . $fecha_fin . ".xls";

// =========================
// HEADERS PARA EXCEL
// =========================
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header("Pragma: no-cache");
header("Expires: 0");

// UTF-8 para Excel
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Consolidado</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
        }
        th {
            background-color: #d9eaf7;
            font-weight: bold;
            text-align: center;
        }
        .titulo {
            font-size: 16px;
            font-weight: bold;
        }
        .subtitulo {
            font-size: 12px;
        }
        .numero {
            text-align: right;
        }
        .texto {
            text-align: left;
        }
        .centrado {
            text-align: center;
        }
        .resumen td {
            font-weight: bold;
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="9" class="titulo">Reporte Consolidado de Movimientos de Inventario</td>
    </tr>
    <tr>
        <td colspan="9" class="subtitulo">
            Período evaluado: <?= htmlspecialchars($fecha_inicio) ?> a <?= htmlspecialchars($fecha_fin) ?>
        </td>
    </tr>
    <tr>
        <td colspan="9" class="subtitulo">
            Fecha de generación: <?= date("Y-m-d H:i:s") ?>
        </td>
    </tr>
    <tr><td colspan="9"></td></tr>

    <tr>
        <th>Fecha</th>
        <th>Código</th>
        <th>Producto</th>
        <th>Precio Unitario</th>
        <th>Cantidad Entrada</th>
        <th>Cantidad Salida</th>
        <th>Cantidad Total Movida</th>
        <th>Costo de Entradas</th>
        <th>Valor de Salidas</th>
    </tr>

<?php
$totalEntradas = 0;
$totalSalidas = 0;
$totalMovido = 0;
$totalCostoEntradas = 0;
$totalValorSalidas = 0;
$hayDatos = false;

while ($row = $resultado->fetch_assoc()) {
    $hayDatos = true;

    $cantidadEntrada     = (int)$row["cantidad_entrada"];
    $cantidadSalida      = (int)$row["cantidad_salida"];
    $cantidadTotalMovida = (int)$row["cantidad_total_movida"];
    $precioUnitario      = (float)$row["precio_unitario"];
    $costoEntradas       = (float)$row["costo_entradas"];
    $valorSalidas        = (float)$row["valor_salidas"];

    $totalEntradas += $cantidadEntrada;
    $totalSalidas += $cantidadSalida;
    $totalMovido += $cantidadTotalMovida;
    $totalCostoEntradas += $costoEntradas;
    $totalValorSalidas += $valorSalidas;
?>
    <tr>
        <td class="centrado"><?= htmlspecialchars($row["fecha"]) ?></td>
        <td class="texto"><?= htmlspecialchars($row["codigo_producto"]) ?></td>
        <td class="texto"><?= htmlspecialchars($row["producto"]) ?></td>
        <td class="numero"><?= number_format($precioUnitario, 2) ?></td>
        <td class="numero"><?= number_format($cantidadEntrada, 0) ?></td>
        <td class="numero"><?= number_format($cantidadSalida, 0) ?></td>
        <td class="numero"><?= number_format($cantidadTotalMovida, 0) ?></td>
        <td class="numero"><?= number_format($costoEntradas, 2) ?></td>
        <td class="numero"><?= number_format($valorSalidas, 2) ?></td>
    </tr>
<?php
}

if (!$hayDatos) {
?>
    <tr>
        <td colspan="9" class="centrado">No se encontraron movimientos en el período seleccionado.</td>
    </tr>
<?php
}
?>

    <tr><td colspan="9"></td></tr>

    <tr class="resumen">
        <td colspan="4" class="texto">Totales generales</td>
        <td class="numero"><?= number_format($totalEntradas, 0) ?></td>
        <td class="numero"><?= number_format($totalSalidas, 0) ?></td>
        <td class="numero"><?= number_format($totalMovido, 0) ?></td>
        <td class="numero"><?= number_format($totalCostoEntradas, 2) ?></td>
        <td class="numero"><?= number_format($totalValorSalidas, 2) ?></td>
    </tr>
</table>

</body>
</html>
<?php
$stmt->close();
$conexion->close();
exit();
?>