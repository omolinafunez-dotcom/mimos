<?php
require_once "conexion.php";

$sql = "SELECT id_usuario, password FROM usuarios";
$result = $conexion->query($sql);

while ($row = $result->fetch_assoc()) {

    $id = $row["id_usuario"];
    $passwordPlano = $row["password"];

    // Solo convertir si NO parece hash ya
    if (strpos($passwordPlano, '$2y$') !== 0) {

        $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

        $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
        $stmt->close();

        echo "Usuario ID $id convertido correctamente.<br>";
    }
}

echo "<br>Proceso terminado.";
$conexion->close();
?>