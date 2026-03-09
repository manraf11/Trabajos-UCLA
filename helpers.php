<?php
function getDBConnection() {
    $dsn = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function buscarAsociados($term) {
    $pdo = getDBConnection();
    
    $sql = "SELECT a.*, 
            (SELECT COUNT(*) FROM historico_retiros_haberes 
             WHERE cod_empleado = a.cod_empleado AND anio_retiro = EXTRACT(YEAR FROM CURRENT_DATE) 
             AND estado = 'Aprobado') as retiros_anio_actual,
            (SELECT COUNT(*) FROM historico_prestamos 
             WHERE cod_empleado = a.cod_empleado AND EXTRACT(YEAR FROM fecha_solicitud) = EXTRACT(YEAR FROM CURRENT_DATE) 
             AND estado = 'Aprobado') as prestamos_anio_actual
            FROM asociados a 
            WHERE a.nombre ILIKE :term OR a.cedula ILIKE :term 
            ORDER BY a.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['term' => '%' . $term . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatCurrency($number) {
    if ($number === null || !is_numeric($number)) {
        return 'Bs. 0,00';
    }
    return 'Bs. ' . number_format($number, 2, ',', '.');
}

function formatDate($date) {
    if (!$date) return 'N/A';
    return date('d/m/Y', strtotime($date));
}

function formatStatus($statu) {
    switch (strtoupper($statu)) {
        case 'A': return '<span class="badge bg-success">Activo</span>';
        case 'J': return '<span class="badge bg-info">Jubilado</span>';
        case 'L': return '<span class="badge bg-danger">Liquidado</span>';
        case 'S': return '<span class="badge bg-warning text-dark">Suspendido</span>';
        default: return '<span class="badge bg-secondary">Desconocido</span>';
    }
}

function calcularMesesAntiguedad($fecha_ingreso) {
    if (!$fecha_ingreso) return 0;
    
    $fecha = new DateTime($fecha_ingreso);
    $hoy = new DateTime();
    $diferencia = $fecha->diff($hoy);
    return $diferencia->y * 12 + $diferencia->m;
}

function obtenerHistorialRetiros($cod_empleado) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM historico_retiros_haberes 
            WHERE cod_empleado = :cod_empleado 
            ORDER BY fecha_retiro DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cod_empleado' => $cod_empleado]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerHistorialPrestamos($cod_empleado) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM historico_prestamos 
            WHERE cod_empleado = :cod_empleado 
            ORDER BY fecha_solicitud DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cod_empleado' => $cod_empleado]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerFiadores($cod_empleado_excluir) {
    $pdo = getDBConnection();
    
    $sql = "SELECT cod_empleado, cedula, nombre, totalcaja 
            FROM asociados 
            WHERE statu = 'A' AND estado = true AND cod_empleado != :exclude
            ORDER BY nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['exclude' => $cod_empleado_excluir]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
