<?php
// reportes.php
session_start();
if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php?err=2");
    exit();
}
require_once "conexion.php";

// --- Fechas (GET) ---
$fecha_inicio = $_GET["fecha_inicio"] ?? "";
$fecha_fin    = $_GET["fecha_fin"] ?? "";

// Defaults: mes actual
if ($fecha_inicio === "" || $fecha_fin === "") {
    $fecha_inicio = date("Y-m-01");
    $fecha_fin    = date("Y-m-t");
}

// Para incluir todo el día fin
$dtInicio = $fecha_inicio . " 00:00:00";
$dtFin    = $fecha_fin . " 23:59:59";



// ===============================
// TENDENCIA SEMANAL (últimos 7 días terminando en fecha_fin)
// ===============================
$weekEndDate = $fecha_fin; // YYYY-MM-DD
$weekStartDate = date("Y-m-d", strtotime($weekEndDate . " -6 days"));

$dtWeekStart = $weekStartDate . " 00:00:00";
$dtWeekEnd   = $weekEndDate . " 23:59:59";

// Traer movimientos por día (ENTRADA vs SALIDA)
$sqlTrend = "
SELECT
  DATE(m.fecha) AS dia,
  SUM(CASE WHEN m.tipo_movimiento='ENTRADA' THEN m.cantidad ELSE 0 END) AS entradas,
  SUM(CASE WHEN m.tipo_movimiento='SALIDA'  THEN m.cantidad ELSE 0 END) AS salidas
FROM movimientos_inventario m
WHERE m.fecha BETWEEN ? AND ?
GROUP BY DATE(m.fecha)
ORDER BY dia
";
$stmtT = $conexion->prepare($sqlTrend);
$stmtT->bind_param("ss", $dtWeekStart, $dtWeekEnd);
$stmtT->execute();
$resT = $stmtT->get_result();

$trendMap = []; // 'YYYY-MM-DD' => ['entradas'=>x,'salidas'=>y]
while ($r = $resT->fetch_assoc()) {
    $trendMap[$r["dia"]] = [
        "entradas" => (int)$r["entradas"],
        "salidas"  => (int)$r["salidas"],
    ];
}
$stmtT->close();

// Rellenar 7 días aunque no haya datos
$trendLabels = [];
$trendEntradas = [];
$trendSalidas = [];

for ($i = 0; $i < 7; $i++) {
    $d = date("Y-m-d", strtotime($weekStartDate . " +$i days"));
    $trendLabels[] = $d;

    $trendEntradas[] = $trendMap[$d]["entradas"] ?? 0;
    $trendSalidas[]  = $trendMap[$d]["salidas"] ?? 0;
}

// ===============================
// ÚLTIMAS 5 "ÓRDENES/LOTES" en el rango seleccionado
// Orden/Lote = mismos: usuario + bodega + tipo_movimiento + observacion + fecha (al segundo)
// ===============================
$sqlOrdenes = "
SELECT
    MIN(m.id_movimiento) AS id_orden,
    DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s') AS fecha_orden,
    u.id_usuario,
    u.usuario AS usuario,
    b.id_bodega,
    b.nombre AS bodega,
    m.tipo_movimiento AS tipo_movimiento,
    COALESCE(m.observacion, '') AS observacion,
    COUNT(*) AS items,
    SUM(m.cantidad) AS total_cantidad
FROM movimientos_inventario m
JOIN usuarios u ON u.id_usuario = m.id_usuario
JOIN bodegas b ON b.id_bodega = m.id_bodega
WHERE m.fecha BETWEEN ? AND ?
GROUP BY
    DATE_FORMAT(m.fecha, '%Y-%m-%d %H:%i:%s'),
    m.id_usuario,
    m.id_bodega,
    m.tipo_movimiento,
    COALESCE(m.observacion, '')
ORDER BY fecha_orden DESC
LIMIT 5
";
$stmt = $conexion->prepare($sqlOrdenes);
$stmt->bind_param("ss", $dtInicio, $dtFin);
$stmt->execute();
$resOrdenes = $stmt->get_result();

$ordenes = [];
while ($row = $resOrdenes->fetch_assoc()) $ordenes[] = $row;
$stmt->close();

// ===============================
// PIE: TOP 5 MÁS MOVIDOS (rango seleccionado) -> %
// ===============================
$sqlTop5 = "
SELECT p.nombre AS producto, SUM(m.cantidad) AS total_movido
FROM movimientos_inventario m
JOIN productos p ON p.id_producto = m.id_producto
WHERE m.fecha BETWEEN ? AND ?
GROUP BY p.id_producto, p.nombre
ORDER BY total_movido DESC
LIMIT 5
";
$stmtTop5 = $conexion->prepare($sqlTop5);
$stmtTop5->bind_param("ss", $dtInicio, $dtFin);
$stmtTop5->execute();
$resTop5 = $stmtTop5->get_result();

$labelsTop5 = [];
$valsTop5   = [];
$sumTop5    = 0;

while ($row = $resTop5->fetch_assoc()) {
    $labelsTop5[] = $row["producto"];
    $v = (int)$row["total_movido"];
    $valsTop5[] = $v;
    $sumTop5 += $v;
}
$stmtTop5->close();

// Convertir a %
$dataTop5Pct = [];
if ($sumTop5 > 0) {
    foreach ($valsTop5 as $v) {
        $dataTop5Pct[] = round(($v / $sumTop5) * 100, 2);
    }
}



// ===============================
// PIE: BOTTOM 3 MENOS MOVIDOS (rango seleccionado) -> %
// Nota: excluye productos con 0 movimientos (no aparecen en SUM)
// ===============================
$sqlBottom3 = "
SELECT p.nombre AS producto, SUM(m.cantidad) AS total_movido
FROM movimientos_inventario m
JOIN productos p ON p.id_producto = m.id_producto
WHERE m.fecha BETWEEN ? AND ?
GROUP BY p.id_producto, p.nombre
ORDER BY total_movido ASC
LIMIT 3
";
$stmtB3 = $conexion->prepare($sqlBottom3);
$stmtB3->bind_param("ss", $dtInicio, $dtFin);
$stmtB3->execute();
$resB3 = $stmtB3->get_result();

$labelsBottom3 = [];
$valsBottom3   = [];
$sumBottom3    = 0;

while ($row = $resB3->fetch_assoc()) {
    $labelsBottom3[] = $row["producto"];
    $v = (int)$row["total_movido"];
    $valsBottom3[] = $v;
    $sumBottom3 += $v;
}
$stmtB3->close();

// Convertir a %
$dataBottom3Pct = [];
if ($sumBottom3 > 0) {
    foreach ($valsBottom3 as $v) {
        $dataBottom3Pct[] = round(($v / $sumBottom3) * 100, 2);
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reportes</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { min-height: 100vh; background: linear-gradient(180deg, #9ecbff 0%, #ffffff 70%); }
.card-header{ background-color:#0d6efd; color:#fff; }

/*PIECHARTS*/
.pie-wrap{
  width: 260px;
  height: 260px;
  margin: 0 auto;
  position: relative;
}

/*TABLA DE TENDENCIA SEMANAL*/
.chart-wrap-line{
  width: 100%;
  max-width: 100%;
  height: 260px;
  position: relative;
}

</style>
</head>

<body>

<?php include "menu.php"; ?>

<div class="container mt-4 mb-5">

<!-- FILTRO POR PERIODO -->
<div class="card shadow mb-4">
    <div class="card-header fw-bold">Filtro por Periodo</div>
    <div class="card-body">

        <form method="GET" action="reportes.php">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio"
                           value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin"
                           value="<?= htmlspecialchars($fecha_fin) ?>" required>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Generar</button>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <a class="btn btn-success w-100"
                       href="exportar_reporte_consolidado.php?fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>">
                        Descargar Excel
                    </a>
                </div>
            </div>
        </form>

        <div class="text-muted mt-2">
            Mostrando datos entre:
            <strong><?= htmlspecialchars($fecha_inicio) ?></strong> y
            <strong><?= htmlspecialchars($fecha_fin) ?></strong>
        </div>

        <div class="text-muted small mt-1">
            El reporte consolidado incluirá todos los movimientos entre las fechas seleccionadas.
        </div>
    </div>
</div>

<!-- GRAFICAS DE PIE -->
<div class="card shadow mb-4">
  <div class="card-header fw-bold">
    Distribución porcentual de movimientos por producto
  </div>

  <div class="card-body">
    <div class="row g-4">

      <!-- PIE TOP 5 -->
      <div class="col-12 col-lg-6">
        <div class="border rounded p-3 bg-light h-100 text-center d-flex flex-column justify-content-between">
          <div class="fw-bold mb-2">Top 5 productos con más movimiento (100% = Top 5)</div>

          <div class="pie-wrap mx-auto">
            <canvas id="pieTop5"></canvas>
          </div>

          <div id="msgTop5" class="text-muted mt-3" style="display:none;">
            No hay movimientos en el rango seleccionado.
          </div>
        </div>
      </div>

      <!-- PIE BOTTOM 3 -->
      <div class="col-12 col-lg-6">
        <div class="border rounded p-3 bg-light h-100 text-center d-flex flex-column justify-content-between">
          <div class="fw-bold mb-2">Top 3 productos con menos movimiento (100% = Bottom 3)</div>

          <div class="pie-wrap mx-auto">
            <canvas id="pieBottom3"></canvas>
          </div>

          <div id="msgBottom3" class="text-muted mt-3" style="display:none;">
            No hay suficientes movimientos para calcular el Bottom 3.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<!-- GRAFICAS DE PIE -->


    <!-- GRÁFICA: TENDENCIA SEMANAL (ENTRADAS VS SALIDAS) -->
    <div class="card shadow mb-4">
        <div class="card-header fw-bold">
            Tendencia semanal de movimientos (últimos 7 días)
        </div>
        <div class="card-body">
            <div class="text-muted mb-2">
                Semana mostrada: <strong><?= htmlspecialchars($weekStartDate) ?></strong> a <strong><?= htmlspecialchars($weekEndDate) ?></strong>
            </div>

            <div class="chart-wrap-line">
                <canvas id="graficaSemana"></canvas>
            </div>

            <div class="mt-3">
                <span class="badge bg-primary">ENTRADAS</span>
                <span class="badge bg-danger ms-2">SALIDAS</span>
            </div>
        </div>
    </div>
    <!-- GRÁFICA: TENDENCIA SEMANAL (ENTRADAS VS SALIDAS) -->





    <!-- TABLA: ÚLTIMAS 5 ÓRDENES -->
    <div class="card shadow">
        

        <div class="card-header d-flex justify-content-between align-items-center fw-bold">
            <span>Últimas 5 Órdenes (según rango de fechas)</span>

            <a href="lista_movimiento.php?fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>"
              class="btn btn-light btn-sm">
                Ver todos los movimientos
            </a>
        </div>

        <div class="card-body">
            <?php if (count($ordenes) === 0): ?>
                <div class="alert alert-warning mb-0">
                    No se encontraron órdenes/movimientos en el periodo seleccionado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:120px;">No. Orden</th>
                                <th style="width:180px;">Fecha / Hora</th>
                                <th style="width:140px;">Usuario</th>
                                <th>Bodega</th>
                                <th style="width:120px;">Movimiento</th>
                                <th style="width:120px;">Items</th>
                                <th style="width:160px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordenes as $o):
                                $idOrden = (int)$o["id_orden"];
                                $badge = ($o["tipo_movimiento"] === "ENTRADA") ? "bg-success" : "bg-danger";

                                $data = [
                                    "id_orden"      => $idOrden,
                                    "fecha_orden"   => $o["fecha_orden"],
                                    "id_usuario"    => (int)$o["id_usuario"],
                                    "usuario"       => $o["usuario"],
                                    "id_bodega"     => (int)$o["id_bodega"],
                                    "bodega"        => $o["bodega"],
                                    "tipo"          => $o["tipo_movimiento"],
                                    "observacion"   => $o["observacion"],
                                ];
                            ?>
                            <tr>
                                <td><?= "OP-" . str_pad((string)$idOrden, 4, "0", STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars($o["fecha_orden"]) ?></td>
                                <td><?= htmlspecialchars($o["usuario"]) ?></td>
                                <td><?= htmlspecialchars($o["bodega"]) ?></td>
                                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($o["tipo_movimiento"]) ?></span></td>
                                <td><?= (int)$o["items"] ?></td>
                                <td>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm btnDetalle"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetalleOrden"
                                            data-orden='<?= htmlspecialchars(json_encode($data), ENT_QUOTES, "UTF-8") ?>'>
                                        Ver Detalle
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- TABLA: ÚLTIMAS 5 ÓRDENES -->                         
</div>



<!-- MODAL DETALLE ORDEN -->
<div class="modal fade" id="modalDetalleOrden" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Detalle de Orden</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="border rounded p-2 bg-light">
              <div class="text-muted small">Orden</div>
              <div class="fw-bold" id="mOrden">—</div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="border rounded p-2 bg-light">
              <div class="text-muted small">Fecha / Hora</div>
              <div class="fw-bold" id="mFecha">—</div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="border rounded p-2 bg-light">
              <div class="text-muted small">Usuario</div>
              <div class="fw-bold" id="mUsuario">—</div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="border rounded p-2 bg-light">
              <div class="text-muted small">Bodega</div>
              <div class="fw-bold" id="mBodega">—</div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3">
          <div><strong>Movimiento:</strong> <span id="mTipo">—</span></div>
          <div><strong>Observación:</strong> <span id="mObs" class="text-muted">—</span></div>
        </div>

        <div id="mCargando" class="alert alert-info py-2" style="display:none;">
          Cargando detalle...
        </div>

        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:140px;">Código</th>
                <th>Producto</th>
                <th style="width:160px;">Cantidad</th>
                <th style="width:160px;">Movimiento</th>
                <th style="width:220px;">Bodega</th>
              </tr>
            </thead>
            <tbody id="mTablaDetalle"></tbody>
          </table>
        </div>

        <div id="mError" class="alert alert-danger py-2" style="display:none;"></div>

      </div>

      <div class="modal-footer justify-content-between">
        <div class="text-muted small">
          * El “Excel” se descarga como CSV compatible con Microsoft Excel.
        </div>
        <div class="d-flex gap-2">
          <a id="btnExcelOrden" class="btn btn-success" href="#" target="_blank" rel="noopener">
            Descargar Excel
          </a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cerrar
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>// ---- graficas de pie -----
const top5Labels = <?= json_encode($labelsTop5) ?>;
const top5Pct    = <?= json_encode($dataTop5Pct) ?>;

const bottom3Labels = <?= json_encode($labelsBottom3) ?>;
const bottom3Pct    = <?= json_encode($dataBottom3Pct) ?>;

function makePie(canvasId, labels, dataPct, msgId) {
  const el = document.getElementById(canvasId);
  if (!el) return;

  if (!labels || labels.length === 0 || !dataPct || dataPct.length === 0) {
    const msg = document.getElementById(msgId);
    if (msg) msg.style.display = "";
    return;
  }

  new Chart(el, {
    type: "pie",
    data: {
      labels: labels,
      datasets: [{
        data: dataPct
      }]
    },
        options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
            position: "bottom",
            labels: {
                boxWidth: 12,
                font: {
                size: 11
                }
            }
            }
        }
        }
  });
}

makePie("pieTop5", top5Labels, top5Pct, "msgTop5");
makePie("pieBottom3", bottom3Labels, bottom3Pct, "msgBottom3");
// ---- graficas de pie -----
</script>

<script>
function esc(s){
  return (s ?? "").toString().replace(/[&<>"']/g, m => ({
    "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
  })[m]);
}

// ===== GRAFICA SEMANAL 0000
const labels = <?= json_encode($trendLabels) ?>;
const entradas = <?= json_encode($trendEntradas) ?>;
const salidas  = <?= json_encode($trendSalidas) ?>;

const ctx = document.getElementById("graficaSemana");
if (ctx) {
  new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [
        { label: "Entradas", data: entradas, tension: 0.25 },
        { label: "Salidas", data: salidas, tension: 0.25 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: true }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
}

// ===== Modal detalle orden =====
async function cargarDetalleEnModal(data){
  const tbody = document.getElementById("mTablaDetalle");
  const cargando = document.getElementById("mCargando");
  const errBox = document.getElementById("mError");

  document.getElementById("mOrden").textContent = "OP-" + String(data.id_orden).padStart(4,"0");
  document.getElementById("mFecha").textContent = data.fecha_orden || "—";
  document.getElementById("mUsuario").textContent = data.usuario || "—";
  document.getElementById("mBodega").textContent = data.bodega || "—";
  document.getElementById("mTipo").textContent = data.tipo || "—";
  document.getElementById("mObs").textContent = (data.observacion && data.observacion.trim()) ? data.observacion : "—";

  // Query params para API + Excel
  const q = new URLSearchParams({
    fecha_orden: data.fecha_orden,
    id_usuario: data.id_usuario,
    id_bodega: data.id_bodega,
    tipo: data.tipo,
    observacion: data.observacion ?? ""
  });

  document.getElementById("btnExcelOrden").href = "exportar_orden_excel.php?" + q.toString();

  errBox.style.display = "none";
  errBox.textContent = "";
  tbody.innerHTML = "";
  cargando.style.display = "";

  try{
    const resp = await fetch("detalle_orden_api.php?" + q.toString(), {credentials:"same-origin"});
    const json = await resp.json();

    if(!json.ok){
      throw new Error(json.error || "No se pudo cargar el detalle.");
    }

    const items = json.items || [];
    if(items.length === 0){
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No hay productos en esta orden.</td></tr>`;
    } else {
      tbody.innerHTML = items.map(it => {
        const esEntrada = (String(it.tipo_movimiento).toUpperCase() === "ENTRADA");
        return `
          <tr>
            <td>${esc(it.codigo)}</td>
            <td>${esc(it.producto)}</td>
            <td class="${esEntrada ? "text-success fw-bold" : "text-danger fw-bold"}">${esc(it.cantidad)}</td>
            <td>${esc(it.tipo_movimiento)}</td>
            <td>${esc(it.bodega)}</td>
          </tr>
        `;
      }).join("");
    }
  } catch(e){
    errBox.textContent = e.message || "Error cargando detalle";
    errBox.style.display = "";
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">—</td></tr>`;
  } finally {
    cargando.style.display = "none";
  }
}

document.querySelectorAll(".btnDetalle").forEach(btn => {
  btn.addEventListener("click", () => {
    const data = JSON.parse(btn.getAttribute("data-orden") || "{}");
    cargarDetalleEnModal(data);
  });
});


</script>


<script>// GRAFICO DE 5 TOP PRODUCTOS
const ctxTop5 = document.getElementById('graficaTop5');

new Chart(ctxTop5, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsTop5) ?>,
        datasets: [{
            label: 'Total Movido',
            data: <?= json_encode($dataTop5) ?>,
            backgroundColor: '#0d6efd'
        }]
    },
    options: {
        indexAxis: 'y', // HORIZONTAL
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});
</script>
</body>
</html>