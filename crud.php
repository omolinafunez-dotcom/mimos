<?php
session_start();
if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}
?>

<?php
// crud.php
require_once "conexion.php";


// ----------------------------
// INSERTAR NUEVO PRODUCTO
// ----------------------------
$errores = [];
$exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"]) && $_POST["accion"] === "crear") {
    $codigo = trim($_POST["codigo"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $precio = trim($_POST["precio_unitario"] ?? "");
    $url_imagen = trim($_POST["url_imagen"] ?? "");
    $id_categoria = (int)($_POST["id_categoria"] ?? 0);
    $id_proveedor = (int)($_POST["id_proveedor"] ?? 0);

    if ($codigo === "" || $nombre === "" || $precio === "" || $id_categoria <= 0 || $id_proveedor <= 0) {
        $errores[] = "Complete los campos obligatorios (código, nombre, precio, categoría y proveedor).";
    } elseif (!is_numeric($precio) || (float)$precio < 0) {
        $errores[] = "El precio debe ser un número válido mayor o igual a 0.";
    }

    if (!$errores) {
        $sqlInsert = "INSERT INTO productos (codigo, nombre, descripcion, precio_unitario, url_imagen, activo, id_proveedor, id_categoria)
                      VALUES (?, ?, ?, ?, ?, 1, ?, ?)";

        $stmt = $conexion->prepare($sqlInsert);
        if (!$stmt) {
            $errores[] = "Error preparando inserción: " . $conexion->error;
        } else {
            $precioFloat = (float)$precio;
            $stmt->bind_param("sssdsii", $codigo, $nombre, $descripcion, $precioFloat, $url_imagen, $id_proveedor, $id_categoria);

            if ($stmt->execute()) {
                $exito = "Producto creado correctamente.";
            } else {
                // Código duplicado (UNIQUE)
                if ($conexion->errno === 1062) {
                    $errores[] = "El código ya existe. Use un código diferente.";
                } else {
                    $errores[] = "Error al crear producto: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}

// ----------------------------
// ACTIVAR / DESACTIVAR
// ----------------------------
if (isset($_GET["toggle"]) && ctype_digit($_GET["toggle"])) {
    $id = (int)$_GET["toggle"];

    // Cambia activo = NOT activo
    $sqlToggle = "UPDATE productos SET activo = NOT activo WHERE id_producto = ?";
    $stmt = $conexion->prepare($sqlToggle);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: crud.php");
    exit;
}

// ----------------------------
// CARGAR LISTAS PARA SELECTS
// ----------------------------
$categorias = [];
$proveedores = [];

$resCat = $conexion->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
if ($resCat) {
    while ($r = $resCat->fetch_assoc()) $categorias[] = $r;
}

$resProv = $conexion->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre");
if ($resProv) {
    while ($r = $resProv->fetch_assoc()) $proveedores[] = $r;
}

// ----------------------------
// CARGAR TABLA DE PRODUCTOS
// ----------------------------
$sqlTabla = "
SELECT
    p.id_producto,
    p.codigo,
    p.nombre,
    c.nombre AS categoria,
    pr.nombre AS proveedor,
    p.precio_unitario,
    p.activo
FROM productos p
JOIN categorias c  ON c.id_categoria = p.id_categoria
JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
ORDER BY p.nombre
";
$resTabla = $conexion->query($sqlTabla);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Productos</title>

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
            <ul class="mb-0">
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Gestión de Productos</span>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto">
                + Nuevo Producto
            </button>
        </div>

        <div class="card-body">

            <!-- TABLA DE PRODUCTOS (MySQL) -->
            <div class="table-responsive">
                <table id="tablaProductos" class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Proveedor</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th style="width: 180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resTabla && $resTabla->num_rows > 0): ?>
                            <?php while ($row = $resTabla->fetch_assoc()): ?>
                                <?php
                                    $activo = (int)$row["activo"] === 1;
                                    $badge = $activo
                                        ? "<span class='badge bg-success'>Activo</span>"
                                        : "<span class='badge bg-secondary'>Inactivo</span>";
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row["codigo"]) ?></td>
                                    <td><?= htmlspecialchars($row["nombre"]) ?></td>
                                    <td><?= htmlspecialchars($row["categoria"]) ?></td>
                                    <td><?= htmlspecialchars($row["proveedor"]) ?></td>
                                    <td>$<?= number_format((float)$row["precio_unitario"], 2) ?></td>
                                    <td><?= $badge ?></td>
                                    <td>
                                        <!-- EDITAR lo dejamos preparado para siguiente paso -->
                                        <button class="btn btn-primary btn-sm" disabled title="Próximo paso: editar">
                                            Editar
                                        </button>

                                        <a class="btn btn-sm <?= $activo ? "btn-danger" : "btn-success" ?>"
                                           href="crud.php?toggle=<?= (int)$row["id_producto"] ?>"
                                           onclick="return confirm('¿Desea cambiar el estado de este producto?');">
                                            <?= $activo ? "Desactivar" : "Activar" ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay productos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- MODAL PRODUCTO -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form method="POST" action="crud.php">
                <input type="hidden" name="accion" value="crear">

                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Código *</label>
                            <input type="text" name="codigo" class="form-control" placeholder="BEB-001" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Categoría *</label>
                            <select name="id_categoria" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c["id_categoria"] ?>">
                                        <?= htmlspecialchars($c["nombre"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Proveedor *</label>
                            <select name="id_proveedor" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $p): ?>
                                    <option value="<?= (int)$p["id_proveedor"] ?>">
                                        <?= htmlspecialchars($p["nombre"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Precio Unitario *</label>
                            <input type="number" name="precio_unitario" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Imagen (URL)</label>
                        <input type="text" name="url_imagen" class="form-control" placeholder="imagenes/agua500.webp">
                    </div>

                    <small class="text-muted">Los campos marcados con * son obligatorios.</small>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
$conexion->close();
?>