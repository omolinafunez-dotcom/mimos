<!-- verificacion de sesion -->
<?php
session_start();
if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

/* Cargar categorías y bodegas desde BD */
$listaCategorias = [];
$listaBodegas = [];

$resCat = $conexion->query("SELECT nombre FROM categorias ORDER BY nombre");
if ($resCat) {
    while ($r = $resCat->fetch_assoc()) $listaCategorias[] = $r["nombre"];
}

$resBod = $conexion->query("SELECT nombre FROM bodegas ORDER BY nombre");
if ($resBod) {
    while ($r = $resBod->fetch_assoc()) $listaBodegas[] = $r["nombre"];
}
?>
<!-- verificacion de sesion -->

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema de Inventario</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.sidebar {
    min-height: 100vh;
}

.category {
    cursor: pointer;
}

.category.active {
    background-color: #0d6efd;
    color: white;
}

.product-img {
    height: 120px;
    object-fit: cover;
    cursor: pointer;
}
</style>
</head>

<style>
body {
    min-height: 100vh;
    background: linear-gradient(
        180deg,
        #9ecbff 0%,
        #d9ecff 35%,
        #ffffff 70%
    );
}

.card-header {
background-color: #0d6efd;
color: white;
}

.stock-ok {
    color: #198754;
    font-weight: bold;
}

.stock-low {
    color: #dc3545;
    font-weight: bold;
}
</style>
</head>

<!-- SCRIPTFILTRO DE TABLA -->
<script>
function filtrarTabla() {
  const keyword = (document.getElementById("filtroTabla")?.value || "").toLowerCase().trim();
  const categoria = (document.getElementById("filtroCategoria")?.value || "").toLowerCase().trim();
  const bodega = (document.getElementById("filtroBodega")?.value || "").toLowerCase().trim();

  const filas = document.querySelectorAll("#tablaInventario tbody tr");

  let visibles = 0;

  filas.forEach(fila => {
    // OJO: columna 0=Producto, 1=Categoría, 2=Bodega
    const productoTxt = fila.cells[0]?.textContent.toLowerCase().trim() || "";
    const categoriaTxt = fila.cells[1]?.textContent.toLowerCase().trim() || "";
    const bodegaTxt = fila.cells[2]?.textContent.toLowerCase().trim() || "";

    // keyword busca en toda la fila
    const filaTxt = fila.textContent.toLowerCase();

    let mostrar = true;

    // 1) filtro keyword
    if (keyword && !filaTxt.includes(keyword)) mostrar = false;

    // 2) filtro categoría
    if (categoria && categoriaTxt !== categoria) mostrar = false;

    // 3) filtro bodega
    if (bodega && bodegaTxt !== bodega) mostrar = false;

    fila.style.display = mostrar ? "" : "none";
    if (mostrar) visibles++;
  });

  // Mensaje sin resultados (no dentro de la tabla)
  const msg = document.getElementById("mensajeSinResultados");
  if (msg) msg.style.display = (visibles === 0) ? "" : "none";
}

function limpiarFiltro() {
  const input = document.getElementById("filtroTabla");
  if (input) input.value = "";

  const cat = document.getElementById("filtroCategoria");
  if (cat) cat.value = "";

  const bod = document.getElementById("filtroBodega");
  if (bod) bod.value = "";

  filtrarTabla();
}
</script>
<!-- SCRIPTFILTRO DE TABLA FIN -->


<body>

<!-- llamando al MENU -->
<?php include "menu.php"; ?>
<!-- llamando al MENU -->


<div class="container mt-4 mb-5">

    <div class="row">
        <div class="col-12">

            <div class="card shadow">
                <div class="card-header fw-bold">
                    Inventario Actual
                </div>

                <div class="card-body">

                    <!-- ID DE TABLA Y FILTRO -->
                     <div class="row mb-3">
                        <div class="col-md-4">
                           <input type="text"
                                    id="filtroTabla"
                                    class="form-control"
                                    placeholder="Buscar producto, categoría o bodega..."
                                    onkeyup="filtrarTabla()">
                        </div>
                    

                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" onclick="limpiarFiltro()">
                                Limpiar
                            </button>
                        </div>

                        <div class="col-md-3">
                            <a href="exportar_inventario_excel.php" class="btn btn-success w-100">
                                Descargar Excel
                            </a>
                        </div>
                                                
                    </div>
                    <!-- ID DE TABLA Y FILTRO FIN-->

                    <!-- FILTRO-->
                     <div class="row mb-3">

                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Categoría</label><!-- FILTRO CATEGORIAS-->
                            <select id="filtroCategoria" class="form-select" onchange="filtrarTabla()">
                                <option value="">Todas</option>
                                <?php foreach ($listaCategorias as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select> <!--FILTRO CATEGORIAS-->
                        </div>
                        <!-- filtroBODEGAS-->       
                        <div class="col-md-4">
                            <label class="form-label">Bodega</label>
                            <select id="filtroBodega" class="form-select" onchange="filtrarTabla()">
                                <option value="">Todas</option>
                                <?php foreach ($listaBodegas as $bod): ?>
                                    <option value="<?= htmlspecialchars($bod) ?>">
                                        <?= htmlspecialchars($bod) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- filtroBODEGAS
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" onclick="filtrarTabla()">
                                Filtrar
                            </button>
                        </div>
                    </div>
                    -->


                    <hr class="my-1">
                    <div class="table-responsive mt-1">
                        <table  id="tablaInventario" class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Bodega</th>
                                    <th>Stock Actual</th>
                                    <th>Stock Mínimo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            <!-- PHP LLENAR TABLA-->
                    <?php


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

if (!$resultado) {
    echo '<tr><td colspan="6" class="text-danger">Error en consulta: ' . htmlspecialchars($conexion->error) . '</td></tr>';
} else {
    while ($row = $resultado->fetch_assoc()) {
        if ((int)$row["stock_actual"] <= (int)$row["stock_minimo"]) {
            $estadoHtml = "<span class='badge bg-danger'>Stock Bajo</span>";
        } else {
            $estadoHtml = "<span class='badge bg-success'>OK</span>";
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["producto"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["categoria"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["bodega"]) . "</td>";
        echo "<td>" . (int)$row["stock_actual"] . "</td>";
        echo "<td>" . (int)$row["stock_minimo"] . "</td>";
        echo "<td>" . $estadoHtml . "</td>";
        echo "</tr>";
    }

    if ($resultado->num_rows === 0) {
        echo '<tr><td colspan="6" class="text-muted text-center">No hay datos en inventario.</td></tr>';
    }
}


?>
<!-- PHP LLENAR TABLA-->
  
                            </tbody>
                        </table>
                    </div>

                    <div id="mensajeSinResultados" class="text-center text-muted mt-2" style="display:none;">
                        No se encontraron resultados.
                    </div>

                    <!-- LEYENDA -->
                    <div class="mt-3">
                        <span class="badge bg-success">OK</span>
                        <span class="badge bg-danger ms-2">Stock Bajo</span>
                    </div>

                </div>
            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<?php $conexion->close(); ?>
</body>

</html>
