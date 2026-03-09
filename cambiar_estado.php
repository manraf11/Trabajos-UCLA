<?php
header('Content-Type: application/json');
require_once './/db_connection.php';

// Validar datos de entrada
if (empty($_POST['cod_empleado']) || empty($_POST['nuevo_estado'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Actualizar el estado del socio
    $sql = "UPDATE asociados SET statu = :nuevo_estado WHERE cod_empleado = :cod_empleado";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nuevo_estado' => $_POST['nuevo_estado'],
        ':cod_empleado' => $_POST['cod_empleado']
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado: ' . $e->getMessage()]);
}
?>