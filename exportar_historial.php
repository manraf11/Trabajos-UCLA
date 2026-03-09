<?php
require_once './/db_connection.php';

// Obtener parámetros del formulario
$fechaInicio = $_POST['fechaInicio'] ?? date('Y-m-01');
$fechaFin = $_POST['fechaFin'] ?? date('Y-m-d');
$tipoTransaccion = $_POST['tipoTransaccion'] ?? '';
$idSocio = $_POST['idSocio'] ?? '';
$exportFormat = $_POST['exportFormat'] ?? 'excel';
$exportRange = $_POST['exportRange'] ?? 'current';
$columns = $_POST['columns'] ?? ['fecha', 'id', 'descripcion', 'tipo', 'socio', 'monto', 'estado'];

// Aquí iría el código para generar el archivo de exportación según el formato seleccionado
// Este es solo un ejemplo básico para CSV

if ($exportFormat == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="historial_transacciones.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Escribir encabezados
    $headers = [];
    if (in_array('fecha', $columns)) $headers[] = 'Fecha';
    if (in_array('id', $columns)) $headers[] = 'ID Transacción';
    if (in_array('descripcion', $columns)) $headers[] = 'Descripción';
    if (in_array('tipo', $columns)) $headers[] = 'Tipo';
    if (in_array('socio', $columns)) $headers[] = 'ID Socio';
    if (in_array('monto', $columns)) $headers[] = 'Monto';
    if (in_array('estado', $columns)) $headers[] = 'Estado';
    
    fputcsv($output, $headers);
    
    // Consulta para obtener datos
    $query = "SELECT 
        fecha,
        id_movimiento as id,
        descripcion,
        tipo_movimiento as tipo,
        id_socio as socio,
        monto,
        'Completado' as estado
    FROM movimientos_contables
    WHERE fecha BETWEEN :fechaInicio AND :fechaFin";
    
    $params = [':fechaInicio' => $fechaInicio, ':fechaFin' => $fechaFin];
    
    if (!empty($tipoTransaccion)) {
        $query .= " AND tipo_movimiento = :tipoMovimiento";
        $params[':tipoMovimiento'] = $tipoTransaccion;
    }
    
    if (!empty($idSocio)) {
        $query .= " AND id_socio = :idSocio";
        $params[':idSocio'] = $idSocio;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Escribir datos
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data = [];
        if (in_array('fecha', $columns)) $data[] = $row['fecha'];
        if (in_array('id', $columns)) $data[] = 'TRX-' . $row['id'];
        if (in_array('descripcion', $columns)) $data[] = $row['descripcion'];
        if (in_array('tipo', $columns)) $data[] = $row['tipo'];
        if (in_array('socio', $columns)) $data[] = $row['socio'];
        if (in_array('monto', $columns)) $data[] = '$' . number_format($row['monto'], 2);
        if (in_array('estado', $columns)) $data[] = $row['estado'];
        
        fputcsv($output, $data);
    }
    
    fclose($output);
    exit;
}

// Similar para otros formatos (Excel, PDF)
// En producción, usarías bibliotecas como PhpSpreadsheet para Excel o TCPDF para PDF