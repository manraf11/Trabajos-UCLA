<?php
// history.php
session_start();
require_once './/db_connection.php';


header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$query = "SELECT 
            cm.id, 
            cm.tipo_carga, 
            cm.nombre_archivo, 
            cm.total_registros,
            cm.registros_exitosos,
            cm.registros_fallidos,
            cm.fecha_carga,
            cm.estado,
            u.nombre as usuario_nombre
          FROM cargas_masivas cm
          JOIN usuarios u ON cm.usuario_id = u.id
          WHERE cm.usuario_id = :user_id";

$params = [':user_id' => $_SESSION['user']['id']];

// Aplicar filtros
if (isset($_GET['days'])) {
    $query .= " AND cm.fecha_carga >= NOW() - INTERVAL :days DAY";
    $params[':days'] = intval($_GET['days']);
} elseif (isset($_GET['month'])) {
    if ($_GET['month'] === 'current') {
        $query .= " AND DATE_FORMAT(cm.fecha_carga, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
    } elseif ($_GET['month'] === 'previous') {
        $query .= " AND DATE_FORMAT(cm.fecha_carga, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m')";
    }
}

// Orden y paginación
$query .= " ORDER BY cm.fecha_carga DESC LIMIT :limit OFFSET :offset";

try {
    // Obtener datos paginados
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $cargas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener conteo total
    $countQuery = preg_replace('/LIMIT :limit OFFSET :offset$/', '', $query);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ($countQuery) AS total");
    
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $cargas,
        'paginacion' => [
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => ceil($total / $por_pagina)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error en history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener el historial de cargas'
    ]);
}