<?php
$paginaActual = basename($_SERVER["PHP_SELF"]);
$nombre = $_SESSION["nombre_usuario"] ?? "Usuario";
$rol = $_SESSION["rol"] ?? "";
// menu.php
// Requiere sesión iniciada en la página que lo incluye.
?>

<!-- NAVBAR SUPERIOR -->
<nav class="navbar navbar-dark bg-dark px-3">
    <button class="btn btn-outline-light"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebarMenu">
        ☰ Menú
    </button>

    <div class="d-flex align-items-center gap-3">
        <span class="navbar-brand mb-0">Sistema de Inventarios</span>
        <span class="text-white-50 small">
            <?= htmlspecialchars($nombre) ?><?= $rol ? " · " . htmlspecialchars($rol) : "" ?>
        </span>
    </div>
</nav>

<!-- SIDEBAR IZQUIERDO -->
<div class="offcanvas offcanvas-start text-bg-dark"
     tabindex="-1"
     id="sidebarMenu">

    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Navegación</h5>
        <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <div class="d-grid gap-2">

            <a href="index.php"
            class="btn <?= ($paginaActual == 'index.php') ? 'btn-light text-dark' : 'btn-outline-light' ?>">
            Inventario
            </a>

            <a href="crud.php"
            class="btn <?= ($paginaActual == 'crud.php') ? 'btn-warning' : 'btn-outline-warning' ?>">
            Productos
            </a>

            <a href="crearorden.php"
            class="btn <?= ($paginaActual == 'crearorden.php') ? 'btn-success' : 'btn-outline-success' ?>">
            Crear Orden
            </a>

            <a href="reportes.php"
            class="btn <?= ($paginaActual == 'reportes.php') ? 'btn-info' : 'btn-outline-info' ?>">
            Reportes
            </a>

<!--
            <a href="index.php" class="btn btn-outline-primary">
                Stock Actual
            </a>-->
            <?php if ($rol === 'ADMIN'): ?>
                <a href="admin.php"
                class="btn <?= ($paginaActual == 'admin.php') ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                Administración
                </a>
            <?php endif; ?>

            


            <hr>


            <a href="logout.php" class="btn btn-danger">
                Cerrar Sesión
            </a>

        </div>

    </div>
</div>