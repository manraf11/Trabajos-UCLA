<?php
header('Content-Type: application/json');
require_once './/db_connection.php';

try {
    // Parámetros de paginación
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $itemsPorPagina = isset($_GET['itemsPorPagina']) ? (int)$_GET['itemsPorPagina'] : 10;
    $offset = ($pagina - 1) * $itemsPorPagina;

    // CORRECCIÓN: Incluir la columna 'estado' en la consulta
    $sql = "SELECT 
                cod_empleado, cedula, nombre, tipo_nomina, nacionalidad, 
                status, fecha_ingreso, cta_empleado, tipo_cuenta, nombre_banco, 
                sueldo, salinicial, statu, totalaport, totalprest, pag_prest,
                estado
            FROM asociados 
            WHERE 1=1";
    
    $params = [];

    // Filtros
    if (!empty($_GET['busqueda'])) {
        $sql .= " AND (nombre ILIKE :busqueda OR cod_empleado ILIKE :busqueda OR cedula ILIKE :busqueda)";
        $params[':busqueda'] = '%'.$_GET['busqueda'].'%';
    }
    
    if (!empty($_GET['estado'])) {
        $sql .= " AND statu = :estado";
        $params[':estado'] = $_GET['estado'];
    }
    
    if (!empty($_GET['tipo_nomina'])) {
        $sql .= " AND tipo_nomina = :tipo_nomina";
        $params[':tipo_nomina'] = $_GET['tipo_nomina'];
    }

    // Consulta para contar total
    $countSql = "SELECT COUNT(*) FROM asociados WHERE 1=1";
    $countParams = [];
    
    if (!empty($_GET['busqueda'])) {
        $countSql .= " AND (nombre ILIKE :busqueda OR cod_empleado ILIKE :busqueda OR cedula ILIKE :busqueda)";
        $countParams[':busqueda'] = '%'.$_GET['busqueda'].'%';
    }
    if (!empty($_GET['estado'])) {
        $countSql .= " AND statu = :estado";
        $countParams[':estado'] = $_GET['estado'];
    }
    if (!empty($_GET['tipo_nomina'])) {
        $countSql .= " AND tipo_nomina = :tipo_nomina";
        $countParams[':tipo_nomina'] = $_GET['tipo_nomina'];
    }

    // Preparar y ejecutar count
    $countStmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    // Consulta principal con paginación
    $sql .= " ORDER BY nombre LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itemsPorPagina;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $asociados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG: Verificar que el campo estado está llegando
    error_log("DEBUG: Número de socios obtenidos: " . count($asociados));
    if (count($asociados) > 0) {
        error_log("DEBUG: Primer socio - Estado: " . $asociados[0]['estado'] . ", Tipo: " . gettype($asociados[0]['estado']));
    }

    echo json_encode([
        'success' => true,
        'data' => $asociados,
        'total' => $total,
        'pagina' => $pagina,
        'totalPaginas' => ceil($total / $itemsPorPagina),
        'debug' => count($asociados) > 0 ? [
            'primer_socio_estado' => $asociados[0]['estado'],
            'primer_socio_estado_tipo' => gettype($asociados[0]['estado'])
        ] : 'no_data'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al obtener los socios: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}
?>