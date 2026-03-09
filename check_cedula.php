<?php
header('Content-Type: application/json');
require 'db_connection.php';

$cedula = strtoupper(trim($_POST['cedula'] ?? ''));

if (empty($cedula)) {
    echo json_encode(['success' => false, 'message' => 'Cédula no proporcionada']);
    exit;
}

try {
    // Buscar en socios por cedulaemp incluyendo el nombreemp
    $stmt = $pdo->prepare("SELECT nombreemp FROM socios WHERE cedulaemp = ?");
    $stmt->execute([$cedula]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($socio && !empty($socio['nombreemp'])) {
        echo json_encode([
            'exists' => true,
            'nombre' => $socio['nombreemp'],
            'cedula' => $cedula
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Cédula no encontrada o nombre no disponible'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>