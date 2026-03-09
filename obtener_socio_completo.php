<?php
header('Content-Type: application/json');
require_once './/db_connection.php';

if (!isset($_GET['cod_empleado'])) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionó código de empleado']);
    exit;
}

try {
    $sql = "SELECT * FROM asociados WHERE cod_empleado = :cod_empleado";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cod_empleado' => $_GET['cod_empleado']]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($socio) {
        // Convertir el estado a booleano para el frontend
        $socio['estado'] = (bool)$socio['estado'];
        echo json_encode(['success' => true, 'data' => $socio]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Socio no encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al obtener el socio: ' . $e->getMessage()]);
}
?>