<?php
// get_load_details.php
require_once './/db_connection.php';
require_once './/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $carga_id = $_GET['carga_id'] ?? 0;
    
    // Obtener información de la carga
    $stmt = $pdo->prepare("SELECT * FROM cargas_masivas WHERE carga_id = ? AND usuario_id = ?");
    $stmt->execute([$carga_id, $user_id]);
    $carga = $stmt->fetch();
    
    if (!$carga) {
        throw new Exception('Carga no encontrada');
    }
    
    // Obtener detalles de la carga
    $stmt = $pdo->prepare("SELECT * FROM detalle_cargas WHERE carga_id = ?");
    $stmt->execute([$carga_id]);
    $detalles = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => $carga,
        'detalles' => $detalles
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);