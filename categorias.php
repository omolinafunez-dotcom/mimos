<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "ADMIN") {
    header("Location: index.php");
    exit();
}

$mensaje = "";
$tipoMensaje = "success";

function e($texto) {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    die("Error: No se encontró una conexión válida en conexion.php");
}

/**
 * PROCESAR ACCIONES
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // =========================
    // CATEGORÍAS
    // =========================
    if ($accion === "agregar_categoria") {
        $nombre = trim($_POST["nombre"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($nombre === "") {
            $mensaje = "El nombre de la categoría es obligatorio.";
            $tipoMensaje = "danger";
        } else {
            $stmt = $conexion->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre, $descripcion);

            if ($stmt->execute()) {
                $mensaje = "Categoría agregada correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al agregar la categoría.";
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    if ($accion === "editar_categoria") {
        $id_categoria = (int)($_POST["id_categoria"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($id_categoria <= 0 || $nombre === "") {
            $mensaje = "Datos inválidos para actualizar la categoría.";
            $tipoMensaje = "danger";
        } else {
            $stmt = $conexion->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id_categoria = ?");
            $stmt->bind_param("ssi", $nombre, $descripcion, $id_categoria);

            if ($stmt->execute()) {
                $mensaje = "Categoría actualizada correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al actualizar la categoría.";
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    if ($accion === "eliminar_categoria") {
        $id_categoria = (int)($_POST["id_categoria"] ?? 0);

        if ($id_categoria <= 0) {
            $mensaje = "Categoría no válida.";
            $tipoMensaje = "danger";
        } else {
            // Validar si la categoría está en uso por productos
            $stmtCheck = $conexion->prepare("SELECT COUNT(*) AS total FROM productos WHERE id_categoria = ?");
            $stmtCheck->bind_param("i", $id_categoria);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $enUso = 0;
            if ($resCheck && $filaCheck = $resCheck->fetch_assoc()) {
                $enUso = (int)$filaCheck["total"];
            }
            $stmtCheck->close();

            if ($enUso > 0) {
                $mensaje = "No se puede eliminar la categoría porque está asociada a uno o más productos.";
                $tipoMensaje = "danger";
            } else {
                $stmt = $conexion->prepare("DELETE FROM categorias WHERE id_categoria = ?");
                $stmt->bind_param("i", $id_categoria);

                if ($stmt->execute()) {
                    $mensaje = "Categoría eliminada correctamente.";
                    $tipoMensaje = "success";
                } else {
                    $mensaje = "Error al eliminar la categoría.";
                    $tipoMensaje = "danger";
                }
                $stmt->close();
            }
        }
    }

    // =========================
    // BODEGAS
    // =========================
    if ($accion === "agregar_bodega") {
        $nombre = trim($_POST["nombre"] ?? "");
        $ubicacion = trim($_POST["ubicacion"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($nombre === "") {
            $mensaje = "El nombre de la bodega es obligatorio.";
            $tipoMensaje = "danger";
        } else {
            $stmt = $conexion->prepare("INSERT INTO bodegas (nombre, ubicacion, descripcion) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $ubicacion, $descripcion);

            if ($stmt->execute()) {
                $mensaje = "Bodega agregada correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al agregar la bodega.";
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    if ($accion === "editar_bodega") {
        $id_bodega = (int)($_POST["id_bodega"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? "");
        $ubicacion = trim($_POST["ubicacion"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($id_bodega <= 0 || $nombre === "") {
            $mensaje = "Datos inválidos para actualizar la bodega.";
            $tipoMensaje = "danger";
        } else {
            $stmt = $conexion->prepare("UPDATE bodegas SET nombre = ?, ubicacion = ?, descripcion = ? WHERE id_bodega = ?");
            $stmt->bind_param("sssi", $nombre, $ubicacion, $descripcion, $id_bodega);

            if ($stmt->execute()) {
                $mensaje = "Bodega actualizada correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al actualizar la bodega.";
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    if ($accion === "eliminar_bodega") {
        $id_bodega = (int)($_POST["id_bodega"] ?? 0);

        if ($id_bodega <= 0) {
            $mensaje = "Bodega no válida.";
            $tipoMensaje = "danger";
        } else {
            // Validar si la bodega está en uso por inventario
            $stmtCheck = $conexion->prepare("SELECT COUNT(*) AS total FROM inventario WHERE id_bodega = ?");
            $stmtCheck->bind_param("i", $id_bodega);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $enUso = 0;
            if ($resCheck && $filaCheck = $resCheck->fetch_assoc()) {
                $enUso = (int)$filaCheck["total"];
            }
            $stmtCheck->close();

            if ($enUso > 0) {
                $mensaje = "No se puede eliminar la bodega porque está asociada a registros de inventario.";
                $tipoMensaje = "danger";
            } else {
                $stmt = $conexion->prepare("DELETE FROM bodegas WHERE id_bodega = ?");
                $stmt->bind_param("i", $id_bodega);

                if ($stmt->execute()) {
                    $mensaje = "Bodega eliminada correctamente.";
                    $tipoMensaje = "success";
                } else {
                    $mensaje = "Error al eliminar la bodega.";
                    $tipoMensaje = "danger";
                }
                $stmt->close();
            }
        }
    }
}

/**
 * RESÚMENES
 */
$totalCategorias = 0;
$totalBodegas = 0;

$res = $conexion->query("SELECT COUNT(*) AS total FROM categorias");
if ($res && $row = $res->fetch_assoc()) {
    $totalCategorias = (int)$row["total"];
}

$res = $conexion->query("SELECT COUNT(*) AS total FROM bodegas");
if ($res && $row = $res->fetch_assoc()) {
    $totalBodegas = (int)$row["total"];
}

/**
 * LISTADOS
 */
$categorias = [];
$resCategorias = $conexion->query("SELECT id_categoria, nombre, descripcion FROM categorias ORDER BY id_categoria DESC");
if ($resCategorias) {
    while ($fila = $resCategorias->fetch_assoc()) {
        $categorias[] = $fila;
    }
}

$bodegas = [];
$resBodegas = $conexion->query("SELECT id_bodega, nombre, ubicacion, descripcion FROM bodegas ORDER BY id_bodega DESC");
if ($resBodegas) {
    while ($fila = $resBodegas->fetch_assoc()) {
        $bodegas[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías y Bodegas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(180deg, #9ecbff 0%, #ffffff 70%);
            min-height: 100vh;
        }

        .card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 24px rgba(0,0,0,.08);
        }

        .hero-admin {
            background: rgba(255,255,255,.9);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            padding: 1.75rem;
        }

        .metric {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .table thead th {
            white-space: nowrap;
        }
    </style>
</head>
<body>

<?php include "menu.php"; ?>

<div class="container py-4">

    <div class="hero-admin mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="mb-1">Gestión de Categorías y Bodegas</h2>
                <p class="text-muted mb-0">
                    Administra los nombres, descripciones y registros de categorías y bodegas del sistema.
                </p>
            </div>
        </div>
    </div>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show" role="alert">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- RESUMEN -->
    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Total Categorías</div>
                <div class="metric"><?= $totalCategorias ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Total Bodegas</div>
                <div class="metric"><?= $totalBodegas ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- CATEGORÍAS -->
        <div class="col-lg-6">
            <div class="card p-3 h-100">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                    <h5 class="mb-0">Categorías</h5>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarCategoria">
                        Agregar categoría
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay categorías registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias as $c): ?>
                                <tr>
                                    <td><?= (int)$c["id_categoria"] ?></td>
                                    <td><?= e($c["nombre"]) ?></td>
                                    <td><?= e($c["descripcion"]) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarCategoriaModal<?= (int)$c["id_categoria"] ?>">
                                            Editar
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDITAR CATEGORÍA -->
                                <div class="modal fade" id="editarCategoriaModal<?= (int)$c["id_categoria"] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar categoría #<?= (int)$c["id_categoria"] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <form method="POST" autocomplete="off">
                                                    <input type="hidden" name="accion" value="editar_categoria">
                                                    <input type="hidden" name="id_categoria" value="<?= (int)$c["id_categoria"] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Nombre</label>
                                                        <input type="text" name="nombre" class="form-control" value="<?= e($c["nombre"]) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Descripción</label>
                                                        <textarea name="descripcion" class="form-control" rows="3"><?= e($c["descripcion"]) ?></textarea>
                                                    </div>

                                                    <div class="d-flex justify-content-between gap-2">
                                                        <button type="submit" class="btn btn-success">Guardar cambios</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    </div>
                                                </form>

                                                <hr>

                                                <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta categoría?');">
                                                    <input type="hidden" name="accion" value="eliminar_categoria">
                                                    <input type="hidden" name="id_categoria" value="<?= (int)$c["id_categoria"] ?>">
                                                    <button type="submit" class="btn btn-danger w-100">Eliminar categoría</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BODEGAS -->
        <div class="col-lg-6">
            <div class="card p-3 h-100">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                    <h5 class="mb-0">Bodegas</h5>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarBodega">
                        Agregar bodega
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Ubicación</th>
                                <th>Descripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($bodegas)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay bodegas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bodegas as $b): ?>
                                <tr>
                                    <td><?= (int)$b["id_bodega"] ?></td>
                                    <td><?= e($b["nombre"]) ?></td>
                                    <td><?= e($b["ubicacion"]) ?></td>
                                    <td><?= e($b["descripcion"]) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarBodegaModal<?= (int)$b["id_bodega"] ?>">
                                            Editar
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDITAR BODEGA -->
                                <div class="modal fade" id="editarBodegaModal<?= (int)$b["id_bodega"] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar bodega #<?= (int)$b["id_bodega"] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <form method="POST" autocomplete="off">
                                                    <input type="hidden" name="accion" value="editar_bodega">
                                                    <input type="hidden" name="id_bodega" value="<?= (int)$b["id_bodega"] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Nombre</label>
                                                        <input type="text" name="nombre" class="form-control" value="<?= e($b["nombre"]) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Ubicación</label>
                                                        <input type="text" name="ubicacion" class="form-control" value="<?= e($b["ubicacion"]) ?>">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Descripción</label>
                                                        <textarea name="descripcion" class="form-control" rows="3"><?= e($b["descripcion"]) ?></textarea>
                                                    </div>

                                                    <div class="d-flex justify-content-between gap-2">
                                                        <button type="submit" class="btn btn-success">Guardar cambios</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    </div>
                                                </form>

                                                <hr>

                                                <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta bodega?');">
                                                    <input type="hidden" name="accion" value="eliminar_bodega">
                                                    <input type="hidden" name="id_bodega" value="<?= (int)$b["id_bodega"] ?>">
                                                    <button type="submit" class="btn btn-danger w-100">Eliminar bodega</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL AGREGAR CATEGORÍA -->
<div class="modal fade" id="modalAgregarCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="agregar_categoria">

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>

                    <button class="btn btn-success w-100">Guardar categoría</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR BODEGA -->
<div class="modal fade" id="modalAgregarBodega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar bodega</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="agregar_bodega">

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input class="form-control" name="ubicacion">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>

                    <button class="btn btn-success w-100">Guardar bodega</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>