<?php
header('Content-Type: application/json');
require_once './/db_connection.php';

// Obtener datos del POST
$data = $_POST;

// Validar datos requeridos
$requiredFields = ['cod_empleado', 'cedula', 'nombre', 'tipo_nomina', 'fecha_ingreso', 'salinicial'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }
}

try {
    // FORZADO: Siempre enviar 0 para estado (deuda inactiva)
    $estado_pgsql = 0; // 0 = false, deuda inactiva
    
    // La deuda inicial se guarda pero no afecta el estado
    $deuda_inic = floatval($data['deuda_inic'] ?? 0);

    // Preparar consulta SQL
    $sql = "INSERT INTO asociados (
        cod_empleado, cedula, nombre, tipo_nomina, nacionalidad, status, fecha_ingreso,
        cta_empleado, tipo_cuenta, nombre_banco, sueldo, salinicial, deuda_inic,
        totalaport, totalprest, totalrete, montorhabe, totalcaja, totalabon, totalcolab,
        totalliqui, totalreint, totalcance, totalctasx, retencion, statu, pag_prest,
        estado, fianza, negativo, dctoseguro, reint_int, aporte, colab, aporte2, credito,
        observaciones
    ) VALUES (
        :cod_empleado, :cedula, :nombre, :tipo_nomina, :nacionalidad, :status, :fecha_ingreso,
        :cta_empleado, :tipo_cuenta, :nombre_banco, :sueldo, :salinicial, :deuda_inic,
        :totalaport, :totalprest, :totalrete, :montorhabe, :totalcaja, :totalabon, :totalcolab,
        :totalliqui, :totalreint, :totalcance, :totalctasx, :retencion, :statu, :pag_prest,
        :estado, :fianza, :negativo, :dctoseguro, :reint_int, :aporte, :colab, :aporte2, :credito,
        :observaciones
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar consulta - estado siempre 0
    $result = $stmt->execute([
        ':cod_empleado' => $data['cod_empleado'],
        ':cedula' => $data['cedula'],
        ':nombre' => $data['nombre'],
        ':tipo_nomina' => $data['tipo_nomina'],
        ':nacionalidad' => isset($data['nacionalidad']) && $data['nacionalidad'] !== '' ? $data['nacionalidad'] : null,
        ':status' => isset($data['status']) && $data['status'] !== '' ? $data['status'] : 'Activo',
        ':fecha_ingreso' => $data['fecha_ingreso'],
        ':cta_empleado' => isset($data['cta_empleado']) && $data['cta_empleado'] !== '' ? $data['cta_empleado'] : null,
        ':tipo_cuenta' => isset($data['tipo_cuenta']) && $data['tipo_cuenta'] !== '' ? $data['tipo_cuenta'] : null,
        ':nombre_banco' => isset($data['nombre_banco']) && $data['nombre_banco'] !== '' ? $data['nombre_banco'] : null,
        ':sueldo' => isset($data['sueldo']) && $data['sueldo'] !== '' ? floatval($data['sueldo']) : 0,
        ':salinicial' => floatval($data['salinicial']),
        ':deuda_inic' => $deuda_inic,
        ':totalaport' => 0,
        ':totalprest' => 0,
        ':totalrete' => 0,
        ':montorhabe' => 0,
        ':totalcaja' => 0,
        ':totalabon' => 0,
        ':totalcolab' => 0,
        ':totalliqui' => 0,
        ':totalreint' => 0,
        ':totalcance' => 0,
        ':totalctasx' => 0,
        ':retencion' => 0,
        ':statu' => 'A',
        ':pag_prest' => 0,
        ':estado' => $estado_pgsql, // SIEMPRE 0 (deuda inactiva)
        ':fianza' => 0,
        ':negativo' => 0,
        ':dctoseguro' => 0,
        ':reint_int' => 0,
        ':aporte' => 0,
        ':colab' => 0,
        ':aporte2' => 0,
        ':credito' => 0,
        ':observaciones' => isset($data['observaciones']) && $data['observaciones'] !== '' ? $data['observaciones'] : null
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Verificar si es un error de duplicado
    if ($e->getCode() == 23505) {
        echo json_encode(['success' => false, 'message' => 'El código de empleado o cédula ya existe']);
    } else {
        error_log("Error PDO en guardar_asociado: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar el socio: ' . $e->getMessage()]);
    }
}
?>