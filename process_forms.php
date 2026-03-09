<?php
function procesarRetiro() {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    try {
        $cod_empleado = $_POST['cod_empleado'];
        $monto_retiro = convertNumberFormat($_POST['monto_retiro']);
        $usuario = $_SESSION['user']['nombre'];
        
        // Validar empleado
        $asociado = obtenerAsociado($cod_empleado);
        if (!$asociado) {
            throw new Exception("Empleado no encontrado");
        }
        
        // Validar antigüedad
        $meses_antiguedad = calcularMesesAntiguedad($asociado['fecha_ingreso']);
        if ($meses_antiguedad < MESES_MINIMOS_ANTIGUEDAD) {
            throw new Exception("El empleado debe tener al menos ".MESES_MINIMOS_ANTIGUEDAD." meses de antigüedad");
        }
        
        // Validar retiros este año
        if ($asociado['retiros_anio_actual'] >= MAX_RETIROS_ANIO) {
            throw new Exception("El empleado ya realizó el máximo de retiros permitidos este año");
        }
        
        // Validar monto
        $monto_maximo = $asociado['totalcaja'] * PORCENTAJE_RETIRO;
        if ($monto_retiro > $monto_maximo) {
            throw new Exception("El monto solicitado excede el ". (PORCENTAJE_RETIRO*100) ."% disponible (".formatCurrency($monto_maximo).")");
        }
        
        // Registrar retiro
        $sql = "INSERT INTO historico_retiros_haberes 
                (cod_empleado, cedula, nombre, monto_retirado, fecha_retiro, anio_retiro, aprobado_por, estado)
                VALUES (?, ?, ?, ?, CURRENT_DATE, EXTRACT(YEAR FROM CURRENT_DATE), ?, 'Aprobado')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cod_empleado,
            $asociado['cedula'],
            $asociado['nombre'],
            $monto_retiro,
            $usuario
        ]);
        
        // Actualizar saldos
        $sql = "UPDATE asociados SET 
                montorhabe = montorhabe + ?, 
                totalcaja = totalcaja - ? 
                WHERE cod_empleado = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$monto_retiro, $monto_retiro, $cod_empleado]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Retiro aprobado exitosamente por ".formatCurrency($monto_retiro);
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error al procesar retiro: " . $e->getMessage();
        return false;
    }
}

function procesarPrestamo() {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    try {
        $cod_empleado = $_POST['cod_empleado'];
        $monto_prestamo = convertNumberFormat($_POST['monto_prestamo']);
        $plazo_meses = $_POST['plazo_meses'];
        $cod_fiador = $_POST['cod_fiador'] ?? null;
        $usuario = $_SESSION['user']['nombre'];
        
        // Validar empleado
        $asociado = obtenerAsociado($cod_empleado);
        if (!$asociado) {
            throw new Exception("Empleado no encontrado");
        }
        
        // Validar préstamos este año
        if ($asociado['prestamos_anio_actual'] >= MAX_PRESTAMOS_ANIO) {
            throw new Exception("El empleado ya tiene el máximo de préstamos permitidos este año");
        }
        
        // Validar monto
        $monto_maximo = $asociado['totalcaja'] * PORCENTAJE_PRESTAMO;
        if ($monto_prestamo > $monto_maximo) {
            throw new Exception("El monto solicitado excede el ". (PORCENTAJE_PRESTAMO*100) ."% disponible (".formatCurrency($monto_maximo).")");
        }
        
        // Verificar necesidad de fiador
        $fiador = null;
        if ($monto_prestamo > $asociado['totalcaja']) {
            if (empty($cod_fiador)) {
                throw new Exception("Se requiere un fiador para montos mayores al saldo disponible");
            }
            
            $fiador = obtenerAsociado($cod_fiador);
            if (!$fiador || $fiador['totalcaja'] < $monto_prestamo) {
                throw new Exception("El fiador no tiene suficiente saldo disponible");
            }
        }
        
        // Calcular detalles del préstamo
        $total_interes = $monto_prestamo * TASA_INTERES_ANUAL * ($plazo_meses / 12);
        $total_a_pagar = $monto_prestamo + $total_interes;
        $cuota_mensual = $total_a_pagar / $plazo_meses;
        $fecha_vencimiento = date('Y-m-d', strtotime("+$plazo_meses months"));
        
        // Registrar préstamo
        $sql = "INSERT INTO historico_prestamos 
                (cod_empleado, cedula, nombre, monto_solicitado, monto_aprobado, plazo_meses, 
                 interes_anual, cuota_mensual, total_a_pagar, fecha_solicitud, fecha_aprobacion, 
                 fecha_vencimiento, estado, aprobado_por, cod_fiador, nombre_fiador, saldo_pendiente)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, CURRENT_DATE, ?, 'Aprobado', ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cod_empleado,
            $asociado['cedula'],
            $asociado['nombre'],
            $monto_prestamo,
            $monto_prestamo,
            $plazo_meses,
            TASA_INTERES_ANUAL * 100,
            $cuota_mensual,
            $total_a_pagar,
            $fecha_vencimiento,
            $usuario,
            $cod_fiador,
            $fiador ? $fiador['nombre'] : null,
            $total_a_pagar
        ]);
        
        // Actualizar saldos
        $sql = "UPDATE asociados SET 
                totalprest = totalprest + ?, 
                totalcaja = totalcaja - ? 
                WHERE cod_empleado = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$monto_prestamo, $monto_prestamo, $cod_empleado]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Préstamo aprobado exitosamente por ".formatCurrency($monto_prestamo)." a $plazo_meses meses";
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error al procesar préstamo: " . $e->getMessage();
        return false;
    }
}

function obtenerAsociado($cod_empleado) {
    $pdo = getDBConnection();
    
    $sql = "SELECT a.*, 
            (SELECT COUNT(*) FROM historico_retiros_haberes 
             WHERE cod_empleado = a.cod_empleado AND anio_retiro = EXTRACT(YEAR FROM CURRENT_DATE) 
             AND estado = 'Aprobado') as retiros_anio_actual,
            (SELECT COUNT(*) FROM historico_prestamos 
             WHERE cod_empleado = a.cod_empleado AND EXTRACT(YEAR FROM fecha_solicitud) = EXTRACT(YEAR FROM CURRENT_DATE) 
             AND estado = 'Aprobado') as prestamos_anio_actual
            FROM asociados a 
            WHERE a.cod_empleado = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cod_empleado]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function convertNumberFormat($numberStr) {
    if (is_numeric($numberStr)) return $numberStr;
    
    $numberStr = str_replace('.', '', $numberStr);
    $numberStr = str_replace(',', '.', $numberStr);
    
    return $numberStr;
}

// Procesar formulario según acción
if (isset($_POST['solicitar_retiro'])) {
    procesarRetiro();
} elseif (isset($_POST['solicitar_prestamo'])) {
    procesarPrestamo();
}

// Redirigir manteniendo parámetros de búsqueda
$redirect_url = "retiros_prestamos.php";
if (!empty($_GET['q'])) {
    $redirect_url .= "?q=" . urlencode($_GET['q']);
}
header("Location: " . $redirect_url);
exit;
?>
