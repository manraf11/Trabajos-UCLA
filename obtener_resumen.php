<?php
header('Content-Type: application/json');
require_once './/db_connection.php';



try {
    // Consulta para obtener el resumen completo
    $sql = "SELECT 
                COUNT(*) as total_socios,
                SUM(CASE WHEN statu = 'A' THEN 1 ELSE 0 END) as total_activos,
                SUM(CASE WHEN statu = 'I' THEN 1 ELSE 0 END) as total_inactivos,
                SUM(CASE WHEN statu = 'S' THEN 1 ELSE 0 END) as total_suspendidos,
                SUM(CASE WHEN statu = 'J' THEN 1 ELSE 0 END) as total_jubilados,
                SUM(CASE WHEN statu = 'L' THEN 1 ELSE 0 END) as total_liquidados,
                SUM(CASE WHEN fecha_ingreso >= DATE_TRUNC('month', CURRENT_DATE) THEN 1 ELSE 0 END) as nuevos_este_mes,
                COALESCE(AVG(salinicial), 0) as saldo_promedio,
                COALESCE(AVG(totalaport), 0) as aporte_promedio,
                SUM(CASE WHEN totalprest > 0 THEN 1 ELSE 0 END) as prestamos_activos
            FROM asociados";
    
    $stmt = $pdo->query($sql);
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'totalSocios' => (int)$resumen['total_socios'],
        'totalActivos' => (int)$resumen['total_activos'],
        'totalInactivos' => (int)$resumen['total_inactivos'],
        'nuevosEsteMes' => (int)$resumen['nuevos_este_mes'],
        'saldoPromedio' => (float)$resumen['saldo_promedio'],
        'aportePromedio' => (float)$resumen['aporte_promedio'],
        'prestamosActivos' => (int)$resumen['prestamos_activos'],
        'totalSuspendidos' => (int)$resumen['total_suspendidos'],
        'totalJubilados' => (int)$resumen['total_jubilados'],
        'totalLiquidados' => (int)$resumen['total_liquidados']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al obtener el resumen: ' . $e->getMessage()
    ]);
}
?>