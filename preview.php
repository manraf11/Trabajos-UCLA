<?php
// preview.php
require_once './/db_connection.php';

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$carga_id = $_GET['carga_id'] ?? 0;

if (!$carga_id) {
    echo json_encode(['success' => false, 'message' => 'ID de carga no proporcionado']);
    exit;
}

try {
    // Obtener registros de la carga
    $stmt = $pdo->prepare("SELECT * FROM carga_detalle WHERE carga_id = ?");
    $stmt->execute([$carga_id]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de registros
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM carga_detalle WHERE carga_id = ?");
    $stmt->execute([$carga_id]);
    $total = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $registros,
        'total' => $total
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al obtener vista previa: ' . $e->getMessage()]);
}
?>