<?php
// crearorden.php
session_start();
if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}

require_once "conexion.php";

$id_usuario = (int)$_SESSION["id_usuario"];
$nombre_usuario = $_SESSION["nombre_usuario"] ?? "Usuario";
$rol_usuario = $_SESSION["rol"] ?? "";

// Mensajes
$errores = [];
$exito = "";

// ===============================
// Cargar datos para selects
// ===============================
$categorias = [];
$bodegas    = [];
$tiposMovimiento = [];

// Tipos de movimiento desde el ENUM de la BD
$sqlEnum = "
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'movimientos_inventario'
  AND COLUMN_NAME = 'tipo_movimiento'
LIMIT 1
";
$resEnum = $conexion->query($sqlEnum);
if ($resEnum && $rowEnum = $resEnum->fetch_assoc()) {
    // Ej: enum('ENTRADA','SALIDA','AJUSTE')
    $columnType = $rowEnum["COLUMN_TYPE"];
    if (preg_match("/^enum\((.*)\)$/i", $columnType, $m)) {
        $vals = str_getcsv($m[1], ",", "'");
        foreach ($vals as $v) {
            $v = trim($v);
            if ($v !== "") $tiposMovimiento[] = $v;
        }
    }
}
if (!$tiposMovimiento) $tiposMovimiento = ["ENTRADA", "SALIDA", "AJUSTE"];

// Categorías
$resCat = $conexion->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
if ($resCat) while ($r = $resCat->fetch_assoc()) $categorias[] = $r;

// Bodegas
$resBod = $conexion->query("SELECT id_bodega, nombre FROM bodegas ORDER BY nombre");
if ($resBod) while ($r = $resBod->fetch_assoc()) $bodegas[] = $r;

// ===============================
// Procesar orden (POST)
// ===============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "procesar") {
    $tipo = $_POST["tipo_movimiento"] ?? "";
    $id_bodega = (int)($_POST["id_bodega"] ?? 0);
    $observacion = trim($_POST["observacion"] ?? "");

    $items_producto = $_POST["item_id_producto"] ?? [];
    $items_cantidad = $_POST["item_cantidad"] ?? [];

    // Validaciones básicas
    if (!in_array($tipo, ["ENTRADA", "SALIDA"], true)) {
        $errores[] = "Seleccione un tipo de movimiento válido (ENTRADA o SALIDA).";
    }
    if ($id_bodega <= 0) {
        $errores[] = "Seleccione una bodega.";
    }
    if (!is_array($items_producto) || !is_array($items_cantidad) || count($items_producto) === 0) {
        $errores[] = "Agregue al menos un producto a la orden.";
    }
    if (count($items_producto) !== count($items_cantidad)) {
        $errores[] = "La lista de productos está incompleta (error de formulario).";
    }

    // Normalizar items (agrupa si repiten producto)
    $lineas = [];
    if (!$errores) {
        for ($i = 0; $i < count($items_producto); $i++) {
            $pid = (int)$items_producto[$i];
            $cant = (int)$items_cantidad[$i];

            if ($pid <= 0 || $cant <= 0) continue;

            if (!isset($lineas[$pid])) $lineas[$pid] = 0;
            $lineas[$pid] += $cant;
        }
        if (count($lineas) === 0) {
            $errores[] = "Las cantidades deben ser mayores a 0.";
        }
    }

    if (!$errores) {
        $conexion->begin_transaction();

        try {
            $stmtSelectInv = $conexion->prepare(
                "SELECT id_inventario, cantidad
                 FROM inventario
                 WHERE id_producto = ? AND id_bodega = ?
                 FOR UPDATE"
            );

            $stmtInsertInv = $conexion->prepare(
                "INSERT INTO inventario (id_producto, id_bodega, cantidad, stock_minimo)
                 VALUES (?, ?, ?, 0)"
            );

            $stmtUpdateInv = $conexion->prepare(
                "UPDATE inventario
                 SET cantidad = ?
                 WHERE id_inventario = ?"
            );

            $stmtInsertMov = $conexion->prepare(
                "INSERT INTO movimientos_inventario
                 (id_producto, id_bodega, tipo_movimiento, cantidad, id_usuario, observacion)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            if (!$stmtSelectInv || !$stmtInsertInv || !$stmtUpdateInv || !$stmtInsertMov) {
                throw new Exception("Error preparando consultas: " . $conexion->error);
            }

            foreach ($lineas as $id_producto => $cantidad) {
                // Lock inventario
                $stmtSelectInv->bind_param("ii", $id_producto, $id_bodega);
                $stmtSelectInv->execute();
                $res = $stmtSelectInv->get_result();

                if ($res->num_rows === 0) {
                    // No existe inventario para ese producto/bodega
                    if ($tipo === "SALIDA") {
                        throw new Exception("No hay inventario para este producto en la bodega seleccionada. No se puede registrar SALIDA.");
                    }

                    // ENTRADA: crear inventario
                    $stmtInsertInv->bind_param("iii", $id_producto, $id_bodega, $cantidad);
                    if (!$stmtInsertInv->execute()) {
                        throw new Exception("Error insertando inventario: " . $stmtInsertInv->error);
                    }

                } else {
                    $inv = $res->fetch_assoc();
                    $id_inventario = (int)$inv["id_inventario"];
                    $stock_actual  = (int)$inv["cantidad"];

                    if ($tipo === "ENTRADA") {
                        $nuevo_stock = $stock_actual + $cantidad;
                    } else {
                        $nuevo_stock = $stock_actual - $cantidad;
                        if ($nuevo_stock < 0) {
                            throw new Exception("Stock insuficiente para SALIDA. Producto ID {$id_producto}: disponible {$stock_actual}, solicitado {$cantidad}.");
                        }
                    }

                    $stmtUpdateInv->bind_param("ii", $nuevo_stock, $id_inventario);
                    if (!$stmtUpdateInv->execute()) {
                        throw new Exception("Error actualizando inventario: " . $stmtUpdateInv->error);
                    }
                }

                // Registrar movimiento (fecha/hora la pone MySQL automáticamente)
                $stmtInsertMov->bind_param("iisiis", $id_producto, $id_bodega, $tipo, $cantidad, $id_usuario, $observacion);
                if (!$stmtInsertMov->execute()) {
                    throw new Exception("Error insertando movimiento: " . $stmtInsertMov->error);
                }
            }

            $conexion->commit();
            $exito = "Orden procesada correctamente. Inventario actualizado y movimientos registrados.";
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear Orden</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
  min-height:100vh;
  background: linear-gradient(180deg,#9ecbff 0%,#d9ecff 35%,#ffffff 70%);
}
.card-header{
  background-color:#0d6efd;
  color:#fff;
}
</style>
</head>

<body>

<!-- Menú común -->
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
    <div class="card-header fw-bold">Orden de Inventario (Entrada / Salida)</div>
    <div class="card-body">

      <form method="POST" action="crearorden.php" id="formOrden">
        <input type="hidden" name="accion" value="procesar">

        <!-- Usuario y fecha/hora -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Usuario que registra</label>
            <input type="text"
                   class="form-control"
                   value="<?= htmlspecialchars($nombre_usuario . ($rol_usuario ? " · $rol_usuario" : "")) ?>"
                   readonly>
          </div>

          <div class="col-md-6">
            <label class="form-label">Fecha / Hora</label>
            <input type="text"
                   class="form-control"
                   value="<?= date('d/m/Y H:i') ?>"
                   readonly>
            <div class="form-text">La fecha/hora oficial se guarda en MySQL al registrar el movimiento.</div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Tipo de movimiento *</label>
            <select name="tipo_movimiento" class="form-select" required>
              <option value="">Seleccione...</option>
              <?php foreach ($tiposMovimiento as $tm): ?>
                <?php if ($tm === "AJUSTE") continue; ?>
                <option value="<?= htmlspecialchars($tm) ?>">
                  <?= htmlspecialchars($tm) ?> <?= ($tm === "ENTRADA") ? "(Ingreso)" : "(Egreso)" ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Bodega *</label>
            <select name="id_bodega" class="form-select" required>
              <option value="">Seleccione...</option>
              <?php foreach ($bodegas as $b): ?>
                <option value="<?= (int)$b["id_bodega"] ?>"><?= htmlspecialchars($b["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Observación</label>
            <input type="text" name="observacion" class="form-control" placeholder="Ej: Recepción, Producción, etc.">
          </div>
        </div>

        <!-- Panel stock -->
        <div class="alert alert-info py-2 d-flex align-items-center justify-content-between" id="panelStock" style="display:none;">
          <div>
            <strong>Stock disponible en bodega:</strong>
            <span id="stockValor" class="badge bg-dark ms-2">0</span>
            <span id="stockTexto" class="ms-2 text-muted"></span>
          </div>
          <div id="stockWarning" class="text-danger fw-bold" style="display:none;">
            Stock insuficiente
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Agregar productos a la orden</h5>

        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label">Categoría (filtro)</label>
            <select id="categoriaSelect" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= (int)$c["id_categoria"] ?>"><?= htmlspecialchars($c["nombre"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Producto</label>
            <!-- AHORA SE LLENA DESDE LA BD SEGÚN: tipo + bodega + categoría -->
            <select id="productoSelect" class="form-select" disabled>
              <option value="">Seleccione bodega y tipo</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <input type="number" id="cantidadInput" class="form-control" min="1" value="1">
          </div>

          <div class="col-md-2">
            <button type="button" id="btnAgregarLinea" class="btn btn-success w-100" onclick="agregarLinea()">
              Agregar
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">ID</th>
                <th>Producto</th>
                <th style="width:160px;">Cantidad</th>
                <th style="width:120px;">Acción</th>
              </tr>
            </thead>
            <tbody id="tablaOrden"></tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="reset" class="btn btn-outline-secondary" onclick="resetTabla()">Limpiar</button>
          <button type="submit" class="btn btn-primary">Guardar Orden</button>
        </div>

      </form>

    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ===============================
// 1) Cargar productos por BD según filtros
// ===============================
async function cargarProductos() {
  const idBodega = document.querySelector('select[name="id_bodega"]')?.value || "";
  const idCategoria = document.getElementById("categoriaSelect")?.value || "";
  const tipo = document.querySelector('select[name="tipo_movimiento"]')?.value || "";

  const productoSelect = document.getElementById("productoSelect");
  productoSelect.disabled = true;

  if (!idBodega || !tipo) {
    productoSelect.innerHTML = `<option value="">Seleccione bodega y tipo</option>`;
    actualizarPanelStock();
    return;
  }

  productoSelect.innerHTML = `<option value="">Cargando...</option>`;

  try {
    const url = `productos_api.php?id_bodega=${encodeURIComponent(idBodega)}&id_categoria=${encodeURIComponent(idCategoria)}&tipo=${encodeURIComponent(tipo)}`;
    const resp = await fetch(url, { credentials: "same-origin" });
    const data = await resp.json();

    if (!data.ok) {
      productoSelect.innerHTML = `<option value="">Error cargando productos</option>`;
      actualizarPanelStock();
      return;
    }

    if (!data.items || data.items.length === 0) {
      productoSelect.innerHTML = `<option value="">No hay productos para este filtro</option>`;
      actualizarPanelStock();
      return;
    }

    const opts = data.items.map(p => {
      const extra = (tipo === "SALIDA") ? ` (Stock: ${p.stock})` : "";
      // cacheamos stock para el panel (evita una llamada extra si ya lo tenemos)
      stockCache.set(`${idBodega}|${p.id_producto}`, p.stock);
      return `<option value="${p.id_producto}">${escapeHtml(p.codigo)} - ${escapeHtml(p.nombre)}${extra}</option>`;
    }).join("");

    productoSelect.innerHTML = `<option value="">Seleccione un producto</option>` + opts;
    productoSelect.disabled = false;

    actualizarPanelStock();
  } catch (e) {
    productoSelect.innerHTML = `<option value="">Error de red</option>`;
    actualizarPanelStock();
  }
}

// Eventos de recarga
document.querySelector('select[name="id_bodega"]')?.addEventListener("change", () => {
  cargarProductos();
});
document.querySelector('select[name="tipo_movimiento"]')?.addEventListener("change", () => {
  cargarProductos();
});
document.getElementById("categoriaSelect")?.addEventListener("change", () => {
  cargarProductos();
});
document.getElementById("productoSelect")?.addEventListener("change", () => {
  actualizarPanelStock();
});
document.getElementById("cantidadInput")?.addEventListener("input", () => {
  actualizarPanelStock();
});

// ===============================
// 2) Panel de stock y bloqueo UI
// ===============================
const stockCache = new Map(); // key: "bodegaId|productoId" -> stock number

function getTipoMovimiento() {
  return document.querySelector('select[name="tipo_movimiento"]')?.value || "";
}
function getBodega() {
  return document.querySelector('select[name="id_bodega"]')?.value || "";
}

function keyStock(idBodega, idProducto) {
  return `${idBodega}|${idProducto}`;
}

function cantidadYaSolicitada(idProducto) {
  let total = 0;
  const ids = document.querySelectorAll('#tablaOrden input[name="item_id_producto[]"]');
  const qtys = document.querySelectorAll('#tablaOrden input[name="item_cantidad[]"]');

  ids.forEach((inp, idx) => {
    if (parseInt(inp.value, 10) === parseInt(idProducto, 10)) {
      total += parseInt(qtys[idx]?.value || "0", 10) || 0;
    }
  });
  return total;
}

async function obtenerStock(idProducto, idBodega) {
  const k = keyStock(idBodega, idProducto);
  if (stockCache.has(k)) return stockCache.get(k);

  const resp = await fetch(`stock_api.php?id_producto=${encodeURIComponent(idProducto)}&id_bodega=${encodeURIComponent(idBodega)}`, {
    credentials: "same-origin"
  });
  const data = await resp.json();

  if (!data.ok) throw new Error(data.error || "No se pudo obtener stock");
  stockCache.set(k, data.stock);
  return data.stock;
}

async function actualizarPanelStock() {
  const tipo = getTipoMovimiento();
  const idBodega = getBodega();
  const idProducto = document.getElementById("productoSelect")?.value || "";
  const cantidad = parseInt(document.getElementById("cantidadInput")?.value || "0", 10);

  const panel = document.getElementById("panelStock");
  const stockValor = document.getElementById("stockValor");
  const stockTexto = document.getElementById("stockTexto");
  const stockWarning = document.getElementById("stockWarning");
  const btnAgregar = document.getElementById("btnAgregarLinea");

  panel.style.display = "none";
  stockWarning.style.display = "none";
  btnAgregar.disabled = false;
  stockTexto.textContent = "";
  stockValor.className = "badge bg-dark ms-2";
  stockValor.textContent = "0";

  if (tipo !== "SALIDA") return;
  if (!idBodega || !idProducto) return;

  panel.style.display = "";

  try {
    const stock = await obtenerStock(idProducto, idBodega);
    const ya = cantidadYaSolicitada(idProducto);
    const disponible = stock - ya;

    stockValor.textContent = `${disponible}`;
    stockTexto.textContent = ya > 0 ? `(Ya agregado en esta orden: ${ya})` : "";

    if (cantidad > 0 && cantidad > disponible) {
      stockWarning.style.display = "";
      btnAgregar.disabled = true;
      stockValor.className = "badge bg-danger ms-2";
    } else {
      stockWarning.style.display = "none";
      btnAgregar.disabled = false;
      stockValor.className = "badge bg-dark ms-2";
    }
  } catch (e) {
    stockValor.textContent = "0";
    stockValor.className = "badge bg-danger ms-2";
    stockTexto.textContent = "No se pudo consultar stock";
    btnAgregar.disabled = true;
  }
}

// ===============================
// 3) Agregar/Quitar líneas
// ===============================
function agregarLinea() {
  const prodSel = document.getElementById("productoSelect");
  const idProducto = prodSel.value;
  const nombreProducto = prodSel.options[prodSel.selectedIndex]?.text || "";
  const cantidad = parseInt(document.getElementById("cantidadInput").value, 10);

  if (!idProducto) {
    alert("Seleccione un producto.");
    return;
  }
  if (!cantidad || cantidad <= 0) {
    alert("Ingrese una cantidad válida.");
    return;
  }

  // Si es SALIDA, el botón ya puede estar bloqueado, pero verificamos igual
  if (getTipoMovimiento() === "SALIDA" && document.getElementById("btnAgregarLinea").disabled) {
    alert("Stock insuficiente para esa cantidad.");
    return;
  }

  const tbody = document.getElementById("tablaOrden");
  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${idProducto}</td>
    <td>${escapeHtml(nombreProducto)}</td>
    <td>${cantidad}</td>
    <td>
      <button type="button" class="btn btn-danger btn-sm" onclick="quitarLinea(this)">
        Quitar
      </button>
    </td>
    <input type="hidden" name="item_id_producto[]" value="${idProducto}">
    <input type="hidden" name="item_cantidad[]" value="${cantidad}">
  `;

  tbody.appendChild(tr);

  // reset selección rápida
  prodSel.value = "";
  document.getElementById("cantidadInput").value = 1;

  actualizarPanelStock();
}

function quitarLinea(btn) {
  btn.closest("tr").remove();
  actualizarPanelStock();
}

function resetTabla() {
  document.getElementById("tablaOrden").innerHTML = "";
  actualizarPanelStock();
}

// util
function escapeHtml(str) {
  return (str || "").replace(/[&<>"']/g, function(m) {
    return ({
      "&":"&amp;",
      "<":"&lt;",
      ">":"&gt;",
      '"':"&quot;",
      "'":"&#039;"
    })[m];
  });
}

// Inicial
cargarProductos();
actualizarPanelStock();
</script>

</body>
</html>
<?php
$conexion->close();
?>