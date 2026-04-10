<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

// ===============================
// FECHAS
// ===============================
$fecha_inicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
$fecha_fin    = $_GET["fecha_fin"] ?? date("Y-m-d");

if ($fecha_inicio > $fecha_fin) {
    $tmp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $tmp;
}

$dtInicio = $fecha_inicio . " 00:00:00";
$dtFin    = $fecha_fin . " 23:59:59";

// ===============================
// CONSULTA: TODOS LOS MOVIMIENTOS
// ===============================
$sql = "
SELECT
    m.id_movimiento,
    m.fecha,
    p.codigo,
    p.nombre AS producto,
    c.nombre AS categoria,
    b.nombre AS bodega,
    u.usuario,
    m.tipo_movimiento,
    m.cantidad,
    p.precio_unitario,
    (m.cantidad * p.precio_unitario) AS total_valor,
    COALESCE(m.observacion, '') AS observacion
FROM movimientos_inventario m
INNER JOIN productos p ON p.id_producto = m.id_producto
LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
INNER JOIN bodegas b ON b.id_bodega = m.id_bodega
INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
WHERE m.fecha BETWEEN ? AND ?
ORDER BY m.fecha DESC, m.id_movimiento DESC
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $dtInicio, $dtFin);
$stmt->execute();
$resultado = $stmt->get_result();

$movimientos = [];
while ($row = $resultado->fetch_assoc()) {
    $movimientos[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(180deg, #9ecbff 0%, #ffffff 70%);
        }
        .card-header {
            background-color: #0d6efd;
            color: #fff;
        }
        .badge-entrada {
            background-color: #198754;
        }
        .badge-salida {
            background-color: #dc3545;
        }
        .table td, .table th {
            vertical-align: middle;
        }
    </style>
</head>
<body>

<?php include "menu.php"; ?>

<div class="container py-4">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Lista de todos los movimientos</span>

            <a href="reportes.php?fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>"
               class="btn btn-light btn-sm">
                Volver a reportes
            </a>
        </div>

        <div class="card-body">
            <form method="GET" action="lista_movimiento.php" class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control"
                           value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control"
                           value="<?= htmlspecialchars($fecha_fin) ?>" required>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <a href="lista_movimiento.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>

            <div class="mb-3 text-muted">
                Mostrando movimientos desde
                <strong><?= htmlspecialchars($fecha_inicio) ?></strong>
                hasta
                <strong><?= htmlspecialchars($fecha_fin) ?></strong>
            </div>

            <?php if (count($movimientos) === 0): ?>
                <div class="alert alert-warning mb-0">
                    No se encontraron movimientos en el período seleccionado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:90px;">ID</th>
                                <th style="width:170px;">Fecha / Hora</th>
                                <th style="width:120px;">Código</th>
                                <th>Producto</th>
                                <th style="width:140px;">Categoría</th>
                                <th style="width:140px;">Bodega</th>
                                <th style="width:130px;">Usuario</th>
                                <th style="width:120px;">Movimiento</th>
                                <th style="width:110px;">Cantidad</th>
                                <th style="width:130px;">Precio Unit.</th>
                                <th style="width:130px;">Total</th>
                                <th style="min-width:180px;">Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalEntradas = 0;
                            $totalSalidas = 0;
                            $valorEntradas = 0;
                            $valorSalidas = 0;

                            foreach ($movimientos as $m):
                                $esEntrada = ($m["tipo_movimiento"] === "ENTRADA");
                                $badgeClass = $esEntrada ? "badge-entrada" : "badge-salida";

                                if ($esEntrada) {
                                    $totalEntradas += (int)$m["cantidad"];
                                    $valorEntradas += (float)$m["total_valor"];
                                } else {
                                    $totalSalidas += (int)$m["cantidad"];
                                    $valorSalidas += (float)$m["total_valor"];
                                }
                            ?>
                                <tr>
                                    <td><?= (int)$m["id_movimiento"] ?></td>
                                    <td><?= htmlspecialchars($m["fecha"]) ?></td>
                                    <td><?= htmlspecialchars($m["codigo"]) ?></td>
                                    <td><?= htmlspecialchars($m["producto"]) ?></td>
                                    <td><?= htmlspecialchars($m["categoria"] ?? "") ?></td>
                                    <td><?= htmlspecialchars($m["bodega"]) ?></td>
                                    <td><?= htmlspecialchars($m["usuario"]) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($m["tipo_movimiento"]) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= number_format((int)$m["cantidad"], 0) ?></td>
                                    <td class="text-end"><?= number_format((float)$m["precio_unitario"], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$m["total_valor"], 2) ?></td>
                                    <td><?= htmlspecialchars($m["observacion"]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="8" class="text-end">Totales de entradas</th>
                                <th class="text-end"><?= number_format($totalEntradas, 0) ?></th>
                                <th></th>
                                <th class="text-end"><?= number_format($valorEntradas, 2) ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="8" class="text-end">Totales de salidas</th>
                                <th class="text-end"><?= number_format($totalSalidas, 0) ?></th>
                                <th></th>
                                <th class="text-end"><?= number_format($valorSalidas, 2) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
<?php
$conexion->close();
?>