<?php
// Configuración de la base de datos
$host = "localhost";
$dbname = "CAPCEL";
$user = "postgres";
$password = "123";

try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión']));
}

$term = $_POST['term'] ?? '';

if (empty($term)) {
    echo json_encode(['success' => false, 'message' => 'Término de búsqueda vacío']);
    exit;
}

try {
    // Buscar por código de empleado o cédula
    $stmt = $conn->prepare("
        SELECT cod_empleado, cedula, nombre, totalcaja, fecha_ingreso 
        FROM asociados 
        WHERE cod_empleado::text LIKE :term OR cedula LIKE :term
        LIMIT 10
    ");
    
    $stmt->execute([':term' => '%' . $term . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar condiciones para cada resultado
    foreach ($results as &$associate) {
        $associate['puede_retirar'] = verificarCondicionesRetiro($conn, $associate);
        $associate['puede_prestamo'] = verificarCondicionesPrestamo($conn, $associate);
    }
    
    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la consulta']);
}

function verificarCondicionesRetiro($conn, $asociado) {
    $hoy = new DateTime();
    $fecha_ingreso = new DateTime($asociado['fecha_ingreso']);
    $diferencia = $fecha_ingreso->diff($hoy);
    
    // Verificar antigüedad (más de 6 meses)
    if ($diferencia->m < 6 && $diferencia->y == 0) {
        return [
            'aprobado' => false,
            'motivo' => 'El asociado debe tener más de 6 meses de antigüedad'
        ];
    }
    
    // Verificar retiro único por año
    $anio_actual = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_retiros_haberes 
                           WHERE cod_empleado = ? AND anio_retiro = ?");
    $stmt->execute([$asociado['cod_empleado'], $anio_actual]);
    $retiros_anio = $stmt->fetchColumn();
    
    if ($retiros_anio > 0) {
        return [
            'aprobado' => false,
            'motivo' => 'Solo se permite un retiro por año'
        ];
    }
    
    return [
        'aprobado' => true,
        'motivo' => 'Cumple con todas las condiciones para retiro'
    ];
}

function verificarCondicionesPrestamo($conn, $asociado) {
    $hoy = new DateTime();
    $fecha_ingreso = new DateTime($asociado['fecha_ingreso']);
    $diferencia = $fecha_ingreso->diff($hoy);
    
    // Verificar antigüedad (más de 6 meses)
    if ($diferencia->m < 6 && $diferencia->y == 0) {
        return [
            'aprobado' => false,
            'motivo' => 'El asociado debe tener más de 6 meses de antigüedad'
        ];
    }
    
    // Verificar máximo 3 préstamos por año
    $anio_actual = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_prestamos 
                           WHERE cod_empleado = ? AND EXTRACT(YEAR FROM fecha_solicitud) = ?");
    $stmt->execute([$asociado['cod_empleado'], $anio_actual]);
    $prestamos_anio = $stmt->fetchColumn();
    
    if ($prestamos_anio >= 3) {
        return [
            'aprobado' => false,
            'motivo' => 'Ha alcanzado el límite de 3 préstamos por año'
        ];
    }
    
    return [
        'aprobado' => true,
        'motivo' => 'Cumple con todas las condiciones para préstamo'
    ];
}
?>