<?php
header('Content-Type: application/json');
require_once './/db_connection.php';

$data = $_POST;

// Validar datos requeridos
$requiredFields = ['cod_empleado', 'cedula', 'nombre', 'tipo_nomina', 'fecha_ingreso', 'statu', 'salinicial'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }
}

try {
    // SOLUCIÓN DEFINITIVA: Manejo explícito del estado
    $estado_valor = false;
    
    if (isset($data['estado_deuda'])) {
        // Usar el valor enviado desde el formulario
        $estado_valor = ($data['estado_deuda'] === 'true');
    } elseif (isset($data['estado_forzado'])) {
        // Para compatibilidad con versiones anteriores
        $estado_valor = ($data['estado_forzado'] === '1' || $data['estado_forzado'] === 'true');
    } else {
        // Si no se envía el campo, determinar basado en la deuda
        $deuda_inic = floatval($data['deuda_inic'] ?? 0);
        $estado_valor = ($deuda_inic > 0);
    }

    // SOLUCIÓN CRÍTICA: Convertir a 't' o 'f' para PostgreSQL
    $estado_postgres = $estado_valor ? 't' : 'f';

    // Consulta SQL usando el formato correcto para PostgreSQL
    $sql = "UPDATE asociados SET
                cedula = :cedula,
                nombre = :nombre,
                tipo_nomina = :tipo_nomina,
                nacionalidad = :nacionalidad,
                status = :status,
                fecha_ingreso = :fecha_ingreso,
                statu = :statu,
                salinicial = :salinicial,
                deuda_inic = :deuda_inic,  
                sueldo = :sueldo,
                observaciones = :observaciones,
                estado = :estado
            WHERE cod_empleado = :cod_empleado";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar con el valor convertido explícitamente
    $stmt->execute([
        ':cod_empleado' => $data['cod_empleado'],
        ':cedula' => $data['cedula'],
        ':nombre' => $data['nombre'],
        ':tipo_nomina' => $data['tipo_nomina'],
        ':nacionalidad' => $data['nacionalidad'] ?? null,
        ':status' => $data['status'] ?? null,
        ':fecha_ingreso' => $data['fecha_ingreso'],
        ':statu' => $data['statu'],
        ':salinicial' => $data['salinicial'],
        ':deuda_inic' => $data['deuda_inic'] ?? 0,
        ':sueldo' => $data['sueldo'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null,
        ':estado' => $estado_postgres  // 't' o 'f' para PostgreSQL
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Socio actualizado correctamente',
        'debug' => [
            'estado_php' => $estado_valor,
            'estado_postgres' => $estado_postgres,
            'estado_recibido' => $data['estado_deuda'] ?? 'no_enviado'
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar el socio: ' . $e->getMessage()
    ]);
}
?>