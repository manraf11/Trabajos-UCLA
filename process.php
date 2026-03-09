<?php
// process.php
session_start();
require_once './/db_connection.php';



header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$carga_id = $_POST['carga_id'] ?? 0;
$confirmacion = $_POST['confirmacion'] ?? false;

if (!$carga_id || !$confirmacion) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros inválidos para procesar la carga'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 1. Obtener información de la carga
    $stmt = $pdo->prepare("SELECT * FROM cargas_masivas WHERE id = ?");
    $stmt->execute([$carga_id]);
    $carga = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carga) {
        throw new Exception("No se encontró la carga especificada");
    }
    
    // 2. Procesar registros válidos
    $stmt = $pdo->prepare("SELECT * FROM carga_detalle WHERE carga_id = ? AND estado = 'valido'");
    $stmt->execute([$carga_id]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $exitosos = 0;
    $fallidos = 0;
    $errores = [];
    
    foreach ($registros as $registro) {
        try {
            // Procesar según el tipo de carga
            if ($carga['tipo_carga'] === 'aportes') {
                procesarAporte($registro, $pdo);
                $exitosos++;
            } else {
                // Otros tipos de carga (descuentos, préstamos, etc.)
                // Implementar según sea necesario
                throw new Exception("Tipo de carga no implementado");
            }
            
            // Marcar como procesado
            $stmt = $pdo->prepare("UPDATE carga_detalle SET estado = 'procesado' WHERE id = ?");
            $stmt->execute([$registro['id']]);
            
        } catch (Exception $e) {
            $fallidos++;
            $errores[] = "ID Socio {$registro['codigoemp']}: " . $e->getMessage();
            
            // Actualizar registro con error
            $stmt = $pdo->prepare("
                UPDATE carga_detalle 
                SET estado = 'invalido', mensaje_error = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $registro['id']]);
        }
    }
    
    // 3. Actualizar estado de la carga
    $stmt = $pdo->prepare("
        UPDATE cargas_masivas 
        SET estado = ?, 
            registros_exitosos = ?, 
            registros_fallidos = ?,
            observaciones = ?
        WHERE id = ?
    ");
    
    $estado_final = ($fallidos > 0) ? 'parcial' : 'completado';
    $observaciones = ($fallidos > 0) ? implode("\n", $errores) : 'Todos los registros se procesaron correctamente';
    
    $stmt->execute([
        $estado_final,
        $exitosos,
        $fallidos,
        $observaciones,
        $carga_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'exitosos' => $exitosos,
        'fallidos' => $fallidos,
        'errores' => $errores,
        'message' => ($fallidos > 0) 
            ? "Carga completada con $fallidos errores" 
            : "Carga completada exitosamente"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    error_log("Error al procesar carga: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la carga: ' . $e->getMessage(),
        'errores' => isset($errores) ? $errores : []
    ]);
}

function procesarAporte($registro, $pdo) {
    // Verificar si ya existe un aporte para este socio en el mismo mes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aportes 
        WHERE cod_empleado = ? 
        AND DATE_TRUNC('month', fecha_aporte) = DATE_TRUNC('month', ?::date)
    ");
    $stmt->execute([$registro['codigoemp'], $registro['fecha_transaccion']]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Ya existe un aporte registrado para este socio en el mes especificado");
    }
    
    // Insertar aporte
    $stmt = $pdo->prepare("
        INSERT INTO aportes 
        (cod_empleado, monto_aporte, usuario_registro, fecha_aporte) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $registro['codigoemp'],
        $registro['monto'],
        $_SESSION['user']['nombre'],
        $registro['fecha_transaccion']
    ]);
    
    // Verificar que se insertó correctamente
    if ($stmt->rowCount() === 0) {
        throw new Exception("No se pudo registrar el aporte en la base de datos");
    }
}
// En tu archivo process.php
foreach ($registros as $registro) {
    if ($registro['estado'] === 'duplicado') {
        continue; // Saltar registros duplicados
    }
    
    // Insertar o actualizar el registro en la base de datos
    if ($registro['estado'] === 'procesado') {
        // Este es un registro que ya existía y solo se actualizó
        continue;
    }
    
    // Insertar nuevo registro
    $query = "INSERT INTO carga_detalle (...) VALUES (...)";
    // Ejecutar la consulta...
}
?>