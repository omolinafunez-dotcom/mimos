<?php
session_start();
if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

$errores = [];
$exito = "";

/* ================================
   CALCULAR PUNTO DE REORDEN
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "calcular_automatico") {
    $idProducto = (int)($_POST["id_producto"] ?? 0);

    if ($idProducto <= 0) {
        $errores[] = "Producto no válido para calcular el punto de reorden.";
    } else {
        $sqlInv = "SELECT id_inventario
                   FROM inventario
                   WHERE id_producto = ?
                   ORDER BY id_inventario ASC";

        $stmtInv = $conexion->prepare($sqlInv);
        if ($stmtInv) {
            $stmtInv->bind_param("i", $idProducto);
            $stmtInv->execute();
            $resInv = $stmtInv->get_result();

            $inventarios = [];
            while ($rowInv = $resInv->fetch_assoc()) {
                $inventarios[] = (int)$rowInv["id_inventario"];
            }
            $stmtInv->close();

            if (count($inventarios) > 0) {
                $sqlCalc = "
                    SELECT COALESCE(SUM(cantidad), 0) AS total_salidas
                    FROM movimientos_inventario
                    WHERE id_producto = ?
                      AND tipo_movimiento = 'SALIDA'
                      AND fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                ";

                $stmtCalc = $conexion->prepare($sqlCalc);
                if ($stmtCalc) {
                    $stmtCalc->bind_param("i", $idProducto);
                    $stmtCalc->execute();
                    $resCalc = $stmtCalc->get_result();
                    $dataCalc = $resCalc->fetch_assoc();
                    $stmtCalc->close();

                    $totalSalidas = (int)($dataCalc["total_salidas"] ?? 0);
                    $stockMinimoCalculado = (int)ceil($totalSalidas / 3);

                    $conexion->begin_transaction();

                    try {
                        $sqlReset = "UPDATE inventario SET stock_minimo = 0 WHERE id_producto = ?";
                        $stmtReset = $conexion->prepare($sqlReset);
                        $stmtReset->bind_param("i", $idProducto);
                        $stmtReset->execute();
                        $stmtReset->close();

                        $primerInventario = $inventarios[0];
                        $sqlSet = "UPDATE inventario SET stock_minimo = ? WHERE id_inventario = ?";
                        $stmtSet = $conexion->prepare($sqlSet);
                        $stmtSet->bind_param("ii", $stockMinimoCalculado, $primerInventario);
                        $stmtSet->execute();
                        $stmtSet->close();

                        $conexion->commit();
                        $exito = "PoR calculado correctamente. Total de salidas en 3 meses: $totalSalidas. Stock mínimo sugerido: $stockMinimoCalculado";
                    } catch (Exception $e) {
                        $conexion->rollback();
                        $errores[] = "Error al calcular el PoR.";
                    }
                } else {
                    $errores[] = "No se pudo preparar la consulta de cálculo.";
                }
            } else {
                $errores[] = "El producto no tiene registros en inventario.";
            }
        } else {
            $errores[] = "No se pudo consultar el inventario del producto.";
        }
    }
}

/* ================================
   STOCK MINIMO MANUAL
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "stock_manual") {
    $idProducto = (int)($_POST["id_producto"] ?? 0);
    $stockManual = trim($_POST["stock_minimo_manual"] ?? "");

    if ($idProducto <= 0) {
        $errores[] = "Producto no válido.";
    } elseif ($stockManual === "" || !is_numeric($stockManual) || (int)$stockManual < 0) {
        $errores[] = "Ingrese una cantidad válida para el stock mínimo manual.";
    } else {
        $stockManual = (int)$stockManual;

        $sqlInv = "SELECT id_inventario
                   FROM inventario
                   WHERE id_producto = ?
                   ORDER BY id_inventario ASC";

        $stmtInv = $conexion->prepare($sqlInv);
        if ($stmtInv) {
            $stmtInv->bind_param("i", $idProducto);
            $stmtInv->execute();
            $resInv = $stmtInv->get_result();

            $inventarios = [];
            while ($rowInv = $resInv->fetch_assoc()) {
                $inventarios[] = (int)$rowInv["id_inventario"];
            }
            $stmtInv->close();

            if (count($inventarios) === 0) {
                $errores[] = "El producto no tiene registros en inventario. No se puede guardar stock mínimo manual.";
            } else {
                $conexion->begin_transaction();

                try {
                    $sqlReset = "UPDATE inventario SET stock_minimo = 0 WHERE id_producto = ?";
                    $stmtReset = $conexion->prepare($sqlReset);
                    $stmtReset->bind_param("i", $idProducto);
                    $stmtReset->execute();
                    $stmtReset->close();

                    $primerInventario = $inventarios[0];
                    $sqlSet = "UPDATE inventario SET stock_minimo = ? WHERE id_inventario = ?";
                    $stmtSet = $conexion->prepare($sqlSet);
                    $stmtSet->bind_param("ii", $stockManual, $primerInventario);
                    $stmtSet->execute();
                    $stmtSet->close();

                    $conexion->commit();
                    $exito = "Stock mínimo actualizado correctamente.";
                } catch (Exception $e) {
                    $conexion->rollback();
                    $errores[] = "Error al guardar el stock mínimo.";
                }
            }
        } else {
            $errores[] = "No se pudo consultar el inventario del producto.";
        }
    }
}

/* ================================
   TABLA
================================ */
$sqlTabla = "
SELECT
    p.id_producto,
    p.codigo,
    p.nombre,
    c.nombre AS categoria,
    p.precio_unitario,
    p.activo,
    COALESCE((SELECT SUM(ix.cantidad) FROM inventario ix WHERE ix.id_producto = p.id_producto), 0) AS stock_actual,
    COALESCE((SELECT SUM(ix.stock_minimo) FROM inventario ix WHERE ix.id_producto = p.id_producto), 0) AS stock_minimo,
    COALESCE((
        SELECT SUM(mi.cantidad)
        FROM movimientos_inventario mi
        WHERE mi.id_producto = p.id_producto
          AND mi.tipo_movimiento = 'SALIDA'
          AND mi.fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ), 0) AS salidas_3_meses
FROM productos p
JOIN categorias c ON c.id_categoria = p.id_categoria
ORDER BY p.nombre
";

$resTabla = $conexion->query($sqlTabla);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Punto de Reorden</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    min-height: 100vh;
    background: linear-gradient(180deg,#9ecbff 0%,#d9ecff 35%,#ffffff 70%);
}
.card-header {
    background-color: #0d6efd;
    color: white;
}
</style>
</head>

<body>

<?php include "menu.php"; ?>

<div class="container mt-4 mb-5">

    <?php if ($exito): ?>
        <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header">Punto de Reorden</div>
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Salidas 3 Meses</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if ($resTabla && $resTabla->num_rows > 0): ?>
                            <?php while ($row = $resTabla->fetch_assoc()): ?>
                                <?php
                                $stock = (int)$row["stock_actual"];
                                $min = (int)$row["stock_minimo"];

                                if ($stock <= 0) {
                                    $estado = "<span class='badge bg-danger'>Agotado</span>";
                                } elseif ($stock <= $min && $min > 0) {
                                    $estado = "<span class='badge bg-warning text-dark'>En reorden</span>";
                                } else {
                                    $estado = "<span class='badge bg-success'>Normal</span>";
                                }
                                ?>

                                <tr>
                                    <td><?= htmlspecialchars($row["codigo"]) ?></td>
                                    <td><?= htmlspecialchars($row["nombre"]) ?></td>
                                    <td><?= htmlspecialchars($row["categoria"]) ?></td>
                                    <td>$<?= number_format((float)$row["precio_unitario"], 2) ?></td>
                                    <td><?= $stock ?></td>
                                    <td><?= $min ?></td>
                                    <td><?= (int)$row["salidas_3_meses"] ?></td>
                                    <td><?= $estado ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#calc<?= $row["id_producto"] ?>">
                                            Calcular
                                        </button>

                                        <button class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#m<?= $row["id_producto"] ?>">
                                            Manual
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL CALCULO AUTOMATICO -->
                                <div class="modal fade" id="calc<?= $row["id_producto"] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">

                                            <form method="POST">
                                                <input type="hidden" name="accion" value="calcular_automatico">
                                                <input type="hidden" name="id_producto" value="<?= $row["id_producto"] ?>">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Calcular punto de reorden</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <p class="mb-2">
                                                        <strong>Producto:</strong> <?= htmlspecialchars($row["nombre"]) ?>
                                                    </p>

                                                    <p class="text-muted small mb-3">
                                                        El sistema calculará automáticamente el stock mínimo de este producto tomando como base las
                                                        <strong>salidas registradas durante los últimos 3 meses</strong>.
                                                    </p>

                                                    <p class="text-muted small mb-3">
                                                        La fórmula utilizada es:
                                                        <br>
                                                        <strong>Total de salidas de los últimos 3 meses ÷ 3</strong>
                                                    </p>

                                                    <p class="text-muted small mb-0">
                                                        Este proceso reemplazará el stock mínimo actual del producto por el nuevo valor calculado.
                                                        ¿Desea continuar?
                                                    </p>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-warning">Sí, calcular</button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL STOCK MANUAL -->
                                <div class="modal fade" id="m<?= $row["id_producto"] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">

                                            <form method="POST">
                                                <input type="hidden" name="accion" value="stock_manual">
                                                <input type="hidden" name="id_producto" value="<?= $row["id_producto"] ?>">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Definir stock mínimo</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <p class="mb-2">
                                                        <strong>Producto:</strong> <?= htmlspecialchars($row["nombre"]) ?>
                                                    </p>

                                                    <p class="text-muted small">
                                                        Ingrese la cantidad mínima de unidades que desea mantener disponibles en inventario para este producto.
                                                        Cuando el stock actual sea igual o menor a este valor, el sistema lo marcará como <strong>En reorden</strong>.
                                                    </p>

                                                    <label class="form-label">Cantidad de stock mínimo</label>
                                                    <input type="number"
                                                           name="stock_minimo_manual"
                                                           class="form-control"
                                                           min="0"
                                                           value="<?= $row["stock_minimo"] ?>"
                                                           required>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>

                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No hay productos registrados.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <small class="text-muted">
                    El cálculo automático del PoR usa las salidas de los últimos 3 meses y guarda como stock mínimo el promedio mensual.
                </small>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conexion->close();
?>