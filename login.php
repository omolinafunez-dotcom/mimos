<?php
session_start();
$err = isset($_GET["err"]) ? $_GET["err"] : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login - Sistema de Inventario</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(180deg,#9ecbff 0%,#d9ecff 35%,#ffffff 70%);
    min-height: 100vh;
}
.login-card {
    max-width: 400px;
    width: 100%;
}
</style>
</head>

<body class="d-flex align-items-center justify-content-center">

<div class="card login-card shadow-lg">
    <div class="card-body p-4">

        <h4 class="text-center mb-4">Sistema de Inventario</h4>

        <?php if ($err === "1"): ?>
            <div class="alert alert-danger">
                Usuario o contraseña incorrectos.
            </div>
        <?php elseif ($err === "2"): ?>
            <div class="alert alert-warning">
                Debe iniciar sesión para continuar.
            </div>
        <?php endif; ?>

        <form action="validar_login.php" method="POST">

            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text"
                       class="form-control"
                       name="usuario"
                       placeholder="Ingrese su usuario"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password"
                       class="form-control"
                       name="password"
                       placeholder="Ingrese su contraseña"
                       required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    Iniciar Sesión
                </button>
            </div>

        </form>

        <div class="text-center mt-3">
            <small class="text-muted">© Sistema de Inventarios</small>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>