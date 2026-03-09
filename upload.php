<?php
session_start();
require_once './/db_connection.php'; // Conexión a la base de datos

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$tipo_carga = $_POST['tipo_carga'] ?? '';
$formato_archivo = $_POST['formato_archivo'] ?? '';

// Validar tipo de carga
$tipos_permitidos = ['aportes', 'descuentos', 'prestamos', 'pagos'];
if (!in_array($tipo_carga, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de carga no válido']);
    exit;
}

// Procesar archivo
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['archivo'];
    $nombre_archivo = basename($file['name']);
    $tmp_name = $file['tmp_name'];
    
    try {
        $pdo->beginTransaction();
        
        // Insertar registro de carga masiva
        $stmt = $pdo->prepare("
            INSERT INTO cargas_masivas 
            (usuario_id, tipo_carga, nombre_archivo, total_registros, fecha_carga, estado) 
            VALUES (?, ?, ?, 0, NOW(), 'pendiente')
        ");
        $stmt->execute([$user_id, $tipo_carga, $nombre_archivo]);
        $carga_id = $pdo->lastInsertId();
        
        // Procesar archivo según formato
        $registros = [];
        if ($formato_archivo === 'txt' || $formato_archivo === 'csv') {
            $registros = procesarArchivoTXT($tmp_name, $tipo_carga, $carga_id, $pdo);
        }
        
        
        // Actualizar conteo de registros
        $total_registros = count($registros);
        $exitosos = count(array_filter($registros, fn($r) => $r['estado'] === 'valido'));
        
        $stmt = $pdo->prepare("
            UPDATE cargas_masivas 
            SET total_registros = ?, registros_exitosos = ?, registros_fallidos = ?, estado = 'preprocesado'
            WHERE id = ?
        ");
        $stmt->execute([$total_registros, $exitosos, $total_registros - $exitosos, $carga_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'carga_id' => $carga_id,
            'total_registros' => $total_registros,
            'registros_exitosos' => $exitosos
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al procesar archivo: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
}

function procesarArchivoTXT($file_path, $tipo_carga, $carga_id, $pdo) {
    $registros = [];
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $fields = explode('|', $line);
        $codigoemp = trim($fields[0] ?? '');
        $nombre = trim($fields[1] ?? '');
        $monto = floatval($fields[2] ?? 0);
        $fecha_str = trim($fields[3] ?? '');
        
        // Inicializar registro con estado inválido por defecto
        $registro = [
            'carga_id' => $carga_id,
            'codigoemp' => $codigoemp,
            'nombre' => $nombre,
            'monto' => $monto,
            'fecha_transaccion' => null, // Inicializar como null
            'estado' => 'invalido', // Por defecto inválido hasta que se valide
            'mensaje_error' => 'Fecha no proporcionada'
        ];
        
        // Validación y formato de fecha
        if (!empty($fecha_str)) {
            try {
                // Eliminar posibles comillas o espacios adicionales
                $fecha_str = trim($fecha_str, "'\" \t\n\r\0\x0B");
                
                // Verificar formato YYYY-MM-DD con expresión regular
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_str)) {
                    $date_obj = new DateTime($fecha_str);
                    $registro['fecha_transaccion'] = $date_obj->format('Y-m-d');
                    $registro['estado'] = 'valido';
                    $registro['mensaje_error'] = '';
                } else {
                    $registro['mensaje_error'] = 'Formato de fecha inválido (debe ser YYYY-MM-DD)';
                }
            } catch (Exception $e) {
                $registro['mensaje_error'] = 'Fecha inválida: ' . $e->getMessage();
            }
        }
        
        // Validaciones adicionales solo si la fecha es válida
        if ($registro['estado'] === 'valido') {
            if (empty($codigoemp) || !preg_match('/^\d+$/', $codigoemp)) {
                $registro['estado'] = 'invalido';
                $registro['mensaje_error'] = 'ID de socio inválido';
            }
            
            if ($monto <= 0) {
                $registro['estado'] = 'invalido';
                $registro['mensaje_error'] = 'Monto debe ser positivo';
            }
            
            // Verificar si el socio existe
            if ($registro['estado'] === 'valido') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM asociados WHERE cod_empleado = ?");
                $stmt->execute([$codigoemp]);
                if ($stmt->fetchColumn() == 0) {
                    $registro['estado'] = 'invalido';
                    $registro['mensaje_error'] = 'Socio no existe';
                }
            }
        }
        
        // Insertar en carga_detalle solo si tenemos fecha (aunque sea inválida)
        if ($registro['fecha_transaccion'] !== null) {
            $stmt = $pdo->prepare("
                INSERT INTO carga_detalle 
                (carga_id, codigoemp, nombre, monto, fecha_transaccion, estado, mensaje_error)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $registro['carga_id'],
                $registro['codigoemp'],
                $registro['nombre'],
                $registro['monto'],
                $registro['fecha_transaccion'],
                $registro['estado'],
                $registro['mensaje_error']
            ]);
            
            $registros[] = $registro;
        } else {
            // Registrar error de fecha faltante
            error_log("Registro omitido - Fecha faltante o inválida: " . implode("|", $fields));
        }
    }
    
    return $registros;
}
?>