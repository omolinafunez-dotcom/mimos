<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "ADMIN") {
    header("Location: index.php");
    exit();
}

$paginaActual = basename($_SERVER["PHP_SELF"]);
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

    // AGREGAR USUARIO
    if ($accion === "agregar_usuario") {
        $nombre = trim($_POST["nombre"] ?? "");
        $usuario = trim($_POST["usuario"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $rol = $_POST["rol"] ?? "OPERARIO";

        if ($nombre === "" || $usuario === "" || $password === "") {
            $mensaje = "Todos los campos son obligatorios.";
            $tipoMensaje = "danger";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, usuario, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $nombre, $usuario, $hash, $rol);

            if ($stmt->execute()) {
                $mensaje = "Usuario creado correctamente.";
                $tipoMensaje = "success";
            } else {
                if ($conexion->errno == 1062) {
                    $mensaje = "El nombre de usuario ya existe.";
                } else {
                    $mensaje = "Error al crear usuario.";
                }
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    // EDITAR USUARIO
    if ($accion === "editar_usuario") {
        $id = (int)($_POST["id_usuario"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? "");
        $usuario = trim($_POST["usuario"] ?? "");
        $rol = $_POST["rol"] ?? "OPERARIO";
        $activo = (int)($_POST["activo"] ?? 1);
        $nuevaPassword = trim($_POST["nueva_password"] ?? "");

        if ($id <= 0 || $nombre === "" || $usuario === "") {
            $mensaje = "Datos inválidos para actualizar el usuario.";
            $tipoMensaje = "danger";
        } elseif (!in_array($rol, ["ADMIN", "OPERARIO"], true)) {
            $mensaje = "Rol no válido.";
            $tipoMensaje = "danger";
        } elseif (!in_array($activo, [0, 1], true)) {
            $mensaje = "Estado no válido.";
            $tipoMensaje = "danger";
        } else {
            if ($nuevaPassword !== "") {
                $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ?, password = ? WHERE id_usuario = ?");
                $stmt->bind_param("sssisi", $nombre, $usuario, $rol, $activo, $hash, $id);
            } else {
                $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ? WHERE id_usuario = ?");
                $stmt->bind_param("sssii", $nombre, $usuario, $rol, $activo, $id);
            }

            if ($stmt->execute()) {
                $mensaje = "Usuario actualizado correctamente.";
                $tipoMensaje = "success";
            } else {
                if ($conexion->errno == 1062) {
                    $mensaje = "El nombre de usuario ya existe.";
                } else {
                    $mensaje = "Error al actualizar el usuario.";
                }
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }

    // ELIMINAR USUARIO
    if ($accion === "eliminar_usuario") {
        $id = (int)($_POST["id_usuario"] ?? 0);

        if ($id <= 0) {
            $mensaje = "Usuario no válido.";
            $tipoMensaje = "danger";
        } elseif (isset($_SESSION["id_usuario"]) && $id == $_SESSION["id_usuario"]) {
            $mensaje = "No puedes eliminar tu propio usuario mientras tienes la sesión activa.";
            $tipoMensaje = "danger";
        } else {
            $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Usuario eliminado correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al eliminar el usuario.";
                $tipoMensaje = "danger";
            }
            $stmt->close();
        }
    }
}

/**
 * DASHBOARD
 */
$totalUsuarios = 0;
$totalAdmins = 0;
$totalOperarios = 0;
$totalActivos = 0;

$res = $conexion->query("SELECT COUNT(*) AS total FROM usuarios");
if ($res && $row = $res->fetch_assoc()) $totalUsuarios = (int)$row["total"];

$res = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'ADMIN'");
if ($res && $row = $res->fetch_assoc()) $totalAdmins = (int)$row["total"];

$res = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'OPERARIO'");
if ($res && $row = $res->fetch_assoc()) $totalOperarios = (int)$row["total"];

$res = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1");
if ($res && $row = $res->fetch_assoc()) $totalActivos = (int)$row["total"];

/**
 * LISTADO
 */
$usuarios = [];
$resUsuarios = $conexion->query("SELECT id_usuario, nombre, usuario, rol, activo, fecha_creacion FROM usuarios ORDER BY id_usuario DESC");
if ($resUsuarios) {
    while ($fila = $resUsuarios->fetch_assoc()) {
        $usuarios[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración</title>
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

        .metric {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .hero-admin {
            background: rgba(255,255,255,.9);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            padding: 1.75rem;
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
                <h2 class="mb-1">Panel de Administración</h2>
                <p class="text-muted mb-0">
                    Gestión de usuarios, privilegios del sistema y acceso a configuración administrativa.
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

    <!-- DASHBOARD -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Total Usuarios</div>
                <div class="metric"><?= $totalUsuarios ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Admins</div>
                <div class="metric"><?= $totalAdmins ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Operarios</div>
                <div class="metric"><?= $totalOperarios ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center h-100">
                <div class="text-muted">Activos</div>
                <div class="metric"><?= $totalActivos ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- PANEL ACCESO RAPIDO -->
        <div class="col-lg-4">
            <div class="card p-3">
                <h5 class="mb-3">Acceso rápido</h5>
                <div class="d-grid gap-2">
                    <a href="PoR.php" class="btn btn-outline-primary">Configurar puntos de reorden</a>
                    <a href="index.php" class="btn btn-outline-secondary">Volver al inventario</a>
                    <a href="categorias.php" class="btn btn-outline-warning">Categorias</a>
                </div>
            </div>
        </div>

        <!-- TABLA USUARIOS -->
        <div class="col-lg-8">
            <div class="card p-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                    <h5 class="mb-0">Usuarios</h5>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarUsuario">
                        Agregar nuevo usuario
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= (int)$u["id_usuario"] ?></td>
                                    <td><?= e($u["nombre"]) ?></td>
                                    <td><?= e($u["usuario"]) ?></td>
                                    <td>
                                        <?php if ($u["rol"] === "ADMIN"): ?>
                                            <span class="badge text-bg-primary">ADMIN</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">OPERARIO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$u["activo"] === 1): ?>
                                            <span class="badge text-bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($u["fecha_creacion"]) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarUsuarioModal<?= (int)$u["id_usuario"] ?>">
                                            Editar
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDITAR -->
                                <div class="modal fade" id="editarUsuarioModal<?= (int)$u["id_usuario"] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar usuario #<?= (int)$u["id_usuario"] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <form method="POST" autocomplete="off">
                                                    <input type="hidden" name="accion" value="editar_usuario">
                                                    <input type="hidden" name="id_usuario" value="<?= (int)$u["id_usuario"] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Nombre</label>
                                                        <input type="text" name="nombre" class="form-control" value="<?= e($u["nombre"]) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Usuario</label>
                                                        <input type="text" name="usuario" class="form-control" value="<?= e($u["usuario"]) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Rol</label>
                                                        <select name="rol" class="form-select" required>
                                                            <option value="ADMIN" <?= $u["rol"] === "ADMIN" ? "selected" : "" ?>>ADMIN</option>
                                                            <option value="OPERARIO" <?= $u["rol"] === "OPERARIO" ? "selected" : "" ?>>OPERARIO</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Nueva contraseña</label>
                                                        <input type="password" name="nueva_password" class="form-control" placeholder="Déjalo vacío si no cambiará">
                                                        <div class="form-text">Solo escribe una contraseña si deseas reemplazar la actual.</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Estado</label>
                                                        <select name="activo" class="form-select" required>
                                                            <option value="1" <?= (int)$u["activo"] === 1 ? "selected" : "" ?>>Activo</option>
                                                            <option value="0" <?= (int)$u["activo"] === 0 ? "selected" : "" ?>>Inactivo</option>
                                                        </select>
                                                    </div>

                                                    <div class="d-flex justify-content-between gap-2">
                                                        <button type="submit" class="btn btn-success">Guardar cambios</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    </div>
                                                </form>

                                                <hr>

                                                <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                                    <input type="hidden" name="accion" value="eliminar_usuario">
                                                    <input type="hidden" name="id_usuario" value="<?= (int)$u["id_usuario"] ?>">
                                                    <button type="submit" class="btn btn-danger w-100">Eliminar usuario</button>
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

                <hr>

                <small class="text-muted">
                    Usa el botón Editar para modificar datos del usuario, cambiar contraseña, activar, desactivar o eliminar.
                </small>
            </div>
        </div>

    </div>
</div>

<!-- MODAL AGREGAR USUARIO -->
<div class="modal fade" id="modalAgregarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="agregar_usuario">

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input class="form-control" name="usuario" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input class="form-control" name="password" type="password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol">
                            <option value="OPERARIO">OPERARIO</option>
                            <option value="ADMIN">ADMIN</option>
                        </select>
                    </div>

                    <button class="btn btn-success w-100">Guardar usuario</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>