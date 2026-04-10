<?php
// productos_api.php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["id_usuario"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado"]);
    exit();
}

require_once "conexion.php";

$id_bodega = isset($_GET["id_bodega"]) ? (int)$_GET["id_bodega"] : 0;
$id_categoria = isset($_GET["id_categoria"]) ? (int)$_GET["id_categoria"] : 0;
$tipo = isset($_GET["tipo"]) ? $_GET["tipo"] : "";

if ($id_bodega <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Falta id_bodega"]);
    exit();
}

$soloConStock = ($tipo === "SALIDA");

$params = [];
$types = "";

if ($soloConStock) {
    // SALIDA: solo productos con stock > 0 en esa bodega
    $sql = "
        SELECT
            p.id_producto,
            p.codigo,
            p.nombre,
            p.id_categoria,
            i.cantidad AS stock
        FROM inventario i
        JOIN productos p ON p.id_producto = i.id_producto
        WHERE i.id_bodega = ?
          AND i.cantidad > 0
          AND p.activo = 1
    ";
    $types .= "i";
    $params[] = $id_bodega;

    if ($id_categoria > 0) {
        $sql .= " AND p.id_categoria = ? ";
        $types .= "i";
        $params[] = $id_categoria;
    }

    $sql .= " ORDER BY p.nombre ";
} else {
    // ENTRADA: todos los productos activos, mostrando stock si ya existe fila en inventario para esa bodega
    $sql = "
        SELECT
            p.id_producto,
            p.codigo,
            p.nombre,
            p.id_categoria,
            COALESCE(i.cantidad, 0) AS stock
        FROM productos p
        LEFT JOIN inventario i
          ON i.id_producto = p.id_producto AND i.id_bodega = ?
        WHERE p.activo = 1
    ";
    $types .= "i";
    $params[] = $id_bodega;

    if ($id_categoria > 0) {
        $sql .= " AND p.id_categoria = ? ";
        $types .= "i";
        $params[] = $id_categoria;
    }

    $sql .= " ORDER BY p.nombre ";
}

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Error SQL: " . $conexion->error]);
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        "id_producto" => (int)$row["id_producto"],
        "codigo" => $row["codigo"],
        "nombre" => $row["nombre"],
        "id_categoria" => (int)$row["id_categoria"],
        "stock" => (int)$row["stock"],
    ];
}

echo json_encode(["ok" => true, "items" => $items]);

$stmt->close();
$conexion->close();