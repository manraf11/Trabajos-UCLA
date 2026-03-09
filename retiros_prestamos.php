<?php
session_start();

// Verificar autenticación (usando la estructura del segundo código)
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre'];

// Configuración de la base de datos
$host = "localhost";
$dbname = "CAPCEL";
$dbuser = "postgres";
$dbpassword = "123";

// Establecer conexión
try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $dbuser, $dbpassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos completos del usuario logueado desde la tabla usuarios
$usuario = [];
try {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        die("Error: No se encontró el usuario con ID " . $user['id']);
    }
} catch (PDOException $e) {
    die("Error al obtener datos del usuario: " . $e->getMessage());
}

// Obtener datos del empleado asociado (si existe)
$empleado = [];
try {
    if (!empty($usuario['codigo_empleado'])) {
        $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
        $stmt->execute([$usuario['codigo_empleado']]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Si hay error, continuar sin datos del empleado
    $empleado = [];
}

// Función para obtener el rol del usuario actual
function obtenerRolUsuario() {
    global $usuario;
    return $usuario['rol'] ?? 'usuario';
}

// Verificar si el usuario tiene permisos para aprobar
function puedeAprobar() {
    global $usuario;
    $roles_que_pueden_aprobar = ['administrador', 'presidente', 'tesorero', 'secretario'];
    return in_array($usuario['rol'] ?? '', $roles_que_pueden_aprobar);
}

// Obtener el rol del usuario actual para mostrar
$rol_usuario = obtenerRolUsuario();

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['solicitar_retiro'])) {
        procesarRetiroHaberes($conn, $empleado);
    } elseif (isset($_POST['solicitar_prestamo'])) {
        procesarSolicitudPrestamo($conn, $empleado);
    } elseif (isset($_POST['solicitar_liquidacion'])) {
        procesarLiquidacion($conn, $empleado);
    } elseif (isset($_POST['aprobar_retiro']) && puedeAprobar()) {
        aprobarRetiro($conn);
    } elseif (isset($_POST['rechazar_retiro']) && puedeAprobar()) {
        rechazarRetiro($conn);
    } elseif (isset($_POST['aprobar_prestamo']) && puedeAprobar()) {
        aprobarPrestamo($conn);
    } elseif (isset($_POST['rechazar_prestamo']) && puedeAprobar()) {
        rechazarPrestamo($conn);
    } elseif (isset($_POST['aprobar_liquidacion']) && puedeAprobar()) {
        aprobarLiquidacion($conn);
    } elseif (isset($_POST['rechazar_liquidacion']) && puedeAprobar()) {
        rechazarLiquidacion($conn);
    }
}

// Funciones de procesamiento
function procesarRetiroHaberes($conn, $empleado) {
    // Determinar el empleado para la operación
    $cod_empleado_operacion = $_POST['cod_empleado_operacion'] ?? ($empleado['cod_empleado'] ?? '');
    
    if (empty($cod_empleado_operacion)) {
        $_SESSION['mensaje'] = "Error: No se ha seleccionado un empleado válido";
        return;
    }
    
    // Obtener datos del asociado seleccionado
    $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$cod_empleado_operacion]);
    $empleado_operacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado_operacion) {
        $_SESSION['mensaje'] = "Error: No se encontró el empleado con código $cod_empleado_operacion";
        return;
    }
    $empleado = $empleado_operacion;

    if (empty($_POST['monto']) || !is_numeric($_POST['monto'])) {
        $_SESSION['mensaje'] = "Error: Monto inválido";
        return;
    }

    $monto = floatval($_POST['monto']);
    
    // Validar antigüedad (más de 6 meses)
    $fecha_ingreso = new DateTime($empleado['fecha_ingreso']);
    $hoy = new DateTime();
    $diferencia = $fecha_ingreso->diff($hoy);
    
    if ($diferencia->m < 6 && $diferencia->y == 0) {
        $_SESSION['mensaje'] = "Error: Debe tener más de 6 meses como asociado para realizar retiros.";
        return;
    }
    
    // Obtener año de retiro del formulario o usar fecha actual
    $anio_retiro = !empty($_POST['anio_retiro']) ? $_POST['anio_retiro'] : date('Y');
    
    // Validar retiro único por año
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_retiros_haberes 
                               WHERE cod_empleado = ? AND anio_retiro = ? AND tipo_retiro = 'parcial'");
        $stmt->execute([$empleado['cod_empleado'], $anio_retiro]);
        $retiros_anio = $stmt->fetchColumn();
        
        if ($retiros_anio > 0) {
            $_SESSION['mensaje'] = "Error: Solo puede realizar un retiro de haberes por año.";
            return;
        }
    } catch (PDOException $e) {
        die("Error al verificar retiros: " . $e->getMessage());
    }
    
    // Validar monto (75% del totalcaja)
    $totalcaja = floatval($empleado['totalcaja']);
    $maximo_permitido = $totalcaja * 0.75;
    
    if ($monto <= 0) {
        $_SESSION['mensaje'] = "Error: El monto debe ser mayor a Bs0.00";
        return;
    }
    
    if ($monto > $maximo_permitido) {
        $_SESSION['mensaje'] = "Error: El monto máximo permitido es Bs" . number_format($maximo_permitido, 2);
        return;
    }
    
    // Registrar retiro con fecha seleccionada
    try {
        $conn->beginTransaction();
        
        // Usar fecha seleccionada o fecha actual
        $fecha_retiro = !empty($_POST['fecha_retiro']) ? $_POST['fecha_retiro'] : date('Y-m-d');
        
        $stmt = $conn->prepare("INSERT INTO historico_retiros_haberes 
                              (cod_empleado, cedula, nombre, monto_retirado, fecha_retiro, anio_retiro, estado, tipo_retiro)
                              VALUES (?, ?, ?, ?, ?, ?, 'Pendiente', 'parcial')");
        $stmt->execute([
            $empleado['cod_empleado'],
            $empleado['cedula'],
            $empleado['nombre'],
            $monto,
            $fecha_retiro,
            $anio_retiro
        ]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Solicitud de retiro registrada correctamente. Esperando aprobación.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al registrar retiro: " . $e->getMessage();
    }
}

// NUEVA FUNCIÓN: Procesar liquidación completa
function procesarLiquidacion($conn, $empleado) {
    // Determinar el empleado para la operación
    $cod_empleado_operacion = $_POST['cod_empleado_operacion'] ?? ($empleado['cod_empleado'] ?? '');
    
    if (empty($cod_empleado_operacion)) {
        $_SESSION['mensaje'] = "Error: No se ha seleccionado un empleado válido";
        return;
    }
    
    // Obtener datos del asociado seleccionado
    $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$cod_empleado_operacion]);
    $empleado_operacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado_operacion) {
        $_SESSION['mensaje'] = "Error: No se encontró el empleado con código $cod_empleado_operacion";
        return;
    }
    $empleado = $empleado_operacion;

    // Verificar que el empleado no tenga préstamos pendientes
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_prestamos 
                               WHERE cod_empleado = ? AND estado = 'Aprobado' AND saldo_pendiente > 0");
        $stmt->execute([$empleado['cod_empleado']]);
        $prestamos_pendientes = $stmt->fetchColumn();
        
        if ($prestamos_pendientes > 0) {
            $_SESSION['mensaje'] = "Error: No puede solicitar liquidación mientras tenga préstamos pendientes de pago.";
            return;
        }
    } catch (PDOException $e) {
        die("Error al verificar préstamos pendientes: " . $e->getMessage());
    }

    $monto_total = floatval($empleado['totalcaja']);
    $motivo = !empty($_POST['motivo_liquidacion']) ? $_POST['motivo_liquidacion'] : 'Liquidación completa por retiro de la caja de ahorros';
    
    if ($monto_total <= 0) {
        $_SESSION['mensaje'] = "Error: No tiene saldo disponible para liquidar.";
        return;
    }
    
    // Registrar solicitud de liquidación con fecha seleccionada
    try {
        $conn->beginTransaction();
        
        $fecha_retiro = !empty($_POST['fecha_liquidacion']) ? $_POST['fecha_liquidacion'] : date('Y-m-d');
        $anio_retiro = !empty($_POST['anio_liquidacion']) ? $_POST['anio_liquidacion'] : date('Y');
        
        $stmt = $conn->prepare("INSERT INTO historico_retiros_haberes 
                              (cod_empleado, cedula, nombre, monto_retirado, fecha_retiro, anio_retiro, estado, tipo_retiro, motivo)
                              VALUES (?, ?, ?, ?, ?, ?, 'Pendiente', 'liquidacion', ?)");
        $stmt->execute([
            $empleado['cod_empleado'],
            $empleado['cedula'],
            $empleado['nombre'],
            $monto_total,
            $fecha_retiro,
            $anio_retiro,
            $motivo
        ]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Solicitud de liquidación completa registrada correctamente. Esperando aprobación.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al registrar liquidación: " . $e->getMessage();
    }
}

function procesarSolicitudPrestamo($conn, $empleado) {
    // Determinar el empleado para la operación
    $cod_empleado_operacion = $_POST['cod_empleado_operacion'] ?? ($empleado['cod_empleado'] ?? '');
    
    if (empty($cod_empleado_operacion)) {
        $_SESSION['mensaje'] = "Error: No se ha seleccionado un empleado válido";
        return;
    }
    
    // Obtener datos del asociado seleccionado
    $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$cod_empleado_operacion]);
    $empleado_operacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado_operacion) {
        $_SESSION['mensaje'] = "Error: No se encontró el empleado con código $cod_empleado_operacion";
        return;
    }
    $empleado = $empleado_operacion;

    // Validaciones básicas
    if (empty($_POST['monto']) || !is_numeric($_POST['monto'])) {
        $_SESSION['mensaje'] = "Error: Monto inválido";
        return;
    }
    
    if (empty($_POST['plazo']) || !in_array($_POST['plazo'], [6, 12, 18])) {
        $_SESSION['mensaje'] = "Error: Plazo inválido";
        return;
    }

    $monto_solicitado = floatval($_POST['monto']);
    $plazo_meses = intval($_POST['plazo']);
    $cod_fiador = !empty($_POST['cod_fiador']) ? $_POST['cod_fiador'] : null;
    $nombre_fiador = !empty($_POST['nombre_fiador']) ? $_POST['nombre_fiador'] : null;
    
    // Validar antigüedad (más de 6 meses)
    $fecha_ingreso = new DateTime($empleado['fecha_ingreso']);
    $hoy = new DateTime();
    $diferencia = $fecha_ingreso->diff($hoy);
    
    if ($diferencia->m < 6 && $diferencia->y == 0) {
        $_SESSION['mensaje'] = "Error: Debe tener más de 6 meses como asociado para solicitar préstamos.";
        return;
    }
    
    // Obtener año de solicitud del formulario
    $anio_solicitud = !empty($_POST['anio_prestamo']) ? $_POST['anio_prestamo'] : date('Y');
    
    // Validar máximo 3 préstamos por año
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_prestamos 
                               WHERE cod_empleado = ? AND EXTRACT(YEAR FROM fecha_solicitud) = ?");
        $stmt->execute([$empleado['cod_empleado'], $anio_solicitud]);
        $prestamos_anio = $stmt->fetchColumn();
        
        if ($prestamos_anio >= 3) {
            $_SESSION['mensaje'] = "Error: Ha alcanzado el límite de 3 préstamos por año.";
            return;
        }
    } catch (PDOException $e) {
        die("Error al verificar préstamos: " . $e->getMessage());
    }
    
    // Validar monto (80% del totalcaja)
    $totalcaja = floatval($empleado['totalcaja']);
    $maximo_permitido = $totalcaja * 0.80;
    
    if ($monto_solicitado <= 0) {
        $_SESSION['mensaje'] = "Error: El monto debe ser mayor a cero.";
        return;
    }
    
    if ($monto_solicitado > $maximo_permitido && empty($cod_fiador)) {
        $_SESSION['mensaje'] = "Error: El monto máximo a solicitar es " . number_format($maximo_permitido, 2) . 
                             ". Puede solicitar un fiador si necesita un monto mayor.";
        return;
    }
    
    // Validar fiador si es necesario
    if ($cod_fiador) {
        try {
            // Verificar existencia del fiador
            $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
            $stmt->execute([$cod_fiador]);
            $fiador = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$fiador) {
                $_SESSION['mensaje'] = "Error: Fiador no encontrado.";
                return;
            }
            
            // Validar antigüedad del fiador
            $fecha_ingreso_f = new DateTime($fiador['fecha_ingreso']);
            $diferencia_f = $fecha_ingreso_f->diff($hoy);
            
            if ($diferencia_f->m < 6 && $diferencia_f->y == 0) {
                $_SESSION['mensaje'] = "Error: El fiador debe tener más de 6 meses como asociado.";
                return;
            }
            
            // Validar préstamos del fiador
            $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_prestamos 
                                   WHERE cod_empleado = ? AND EXTRACT(YEAR FROM fecha_solicitud) = ?");
            $stmt->execute([$cod_fiador, $anio_solicitud]);
            $prestamos_fiador = $stmt->fetchColumn();
            
            if ($prestamos_fiador >= 3) {
                $_SESSION['mensaje'] = "Error: El fiador ha alcanzado el límite de 3 préstamos por año.";
                return;
            }
            
            // Verificar que el fiador tenga suficiente saldo
            if (floatval($fiador['totalcaja']) < $monto_solicitado) {
                $_SESSION['mensaje'] = "Error: El fiador no tiene suficiente saldo disponible.";
                return;
            }
            
            // Si no se proporcionó nombre, usar el de la base de datos
            if (empty($nombre_fiador)) {
                $nombre_fiador = $fiador['nombre'];
            }
        } catch (PDOException $e) {
            die("Error al verificar fiador: " . $e->getMessage());
        }
    }
    
    // Calcular intereses y cuotas (12% anual)
    $interes_anual = 12.00;
    $interes_mensual = $interes_anual / 12 / 100;
    $monto_aprobado = $monto_solicitado; // Por defecto, igual al solicitado
    
    $cuota_mensual = $monto_aprobado * ($interes_mensual * pow(1 + $interes_mensual, $plazo_meses)) / 
                     (pow(1 + $interes_mensual, $plazo_meses) - 1);
    $total_a_pagar = $cuota_mensual * $plazo_meses;
    
    // Registrar préstamo con fecha seleccionada
    try {
        $conn->beginTransaction();
        
        $fecha_solicitud = !empty($_POST['fecha_prestamo']) ? $_POST['fecha_prestamo'] : date('Y-m-d');
        
        $stmt = $conn->prepare("INSERT INTO historico_prestamos 
                              (cod_empleado, cedula, nombre, monto_solicitado, monto_aprobado, 
                               plazo_meses, interes_anual, cuota_mensual, total_a_pagar, 
                               fecha_solicitud, anio_solicitud, estado, cod_fiador, nombre_fiador, saldo_pendiente)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?, ?)");
        $stmt->execute([
            $empleado['cod_empleado'],
            $empleado['cedula'],
            $empleado['nombre'],
            $monto_solicitado,
            $monto_aprobado,
            $plazo_meses,
            $interes_anual,
            $cuota_mensual,
            $total_a_pagar,
            $fecha_solicitud,
            $anio_solicitud,
            $cod_fiador,
            $nombre_fiador,
            $total_a_pagar // Saldo pendiente inicial = total a pagar
        ]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Solicitud de préstamo registrada correctamente. Esperando aprobación.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al registrar préstamo: " . $e->getMessage();
    }
}

function aprobarRetiro($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de retiro inválido";
        return;
    }

    $id = intval($_POST['id']);
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    
    try {
        $conn->beginTransaction();
        
        // Obtener datos del retiro (ya incluye la fecha registrada)
        $stmt = $conn->prepare("SELECT cod_empleado, monto_retirado, tipo_retiro, fecha_retiro FROM historico_retiros_haberes WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $retiro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$retiro) {
            $_SESSION['mensaje'] = "Error: Retiro no encontrado.";
            $conn->rollBack();
            return;
        }
        
        // Actualizar estado del retiro usando la fecha ya registrada
        $stmt = $conn->prepare("UPDATE historico_retiros_haberes 
                               SET estado = 'Aprobado', aprobado_por = ?, rol_aprobador = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $id]);
        
        // Descontar del totalcaja del empleado
        $stmt = $conn->prepare("UPDATE asociados 
                               SET totalcaja = totalcaja - ? 
                               WHERE cod_empleado = ?");
        $stmt->execute([$retiro['monto_retirado'], $retiro['cod_empleado']]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Retiro aprobado correctamente.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al aprobar retiro: " . $e->getMessage();
    }
}

// NUEVA FUNCIÓN: Aprobar liquidación - ACTUALIZADA PARA PONER EN 'L' Y SALDOS EN 0
function aprobarLiquidacion($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de liquidación inválido";
        return;
    }

    $id = intval($_POST['id']);
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    
    try {
        $conn->beginTransaction();
        
        // Obtener datos de la liquidación
        $stmt = $conn->prepare("SELECT cod_empleado, monto_retirado FROM historico_retiros_haberes WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$liquidacion) {
            $_SESSION['mensaje'] = "Error: Liquidación no encontrada.";
            $conn->rollBack();
            return;
        }
        
        // Actualizar estado de la liquidación (sin cambiar la fecha)
        $stmt = $conn->prepare("UPDATE historico_retiros_haberes 
                               SET estado = 'Aprobado', aprobado_por = ?, rol_aprobador = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $id]);
        
        // ACTUALIZAR TABLA ASOCIADOS - PONER EN 'L' Y SALDOS EN 0
        $stmt = $conn->prepare("UPDATE asociados 
                               SET statu = 'L', 
                                   status = 'Inactivo',
                                   totalcaja = 0,
                                   salinicial = 0,
                                   deuda_inic = 0,
                                   totalaport = 0,
                                   totalprest = 0,
                                   totalrete = 0,
                                   montorhabe = 0,
                                   totalabon = 0,
                                   totalcolab = 0,
                                   totalliqui = 0,
                                   totalreint = 0,
                                   totalcance = 0,
                                   totalctasx = 0,
                                   retencion = 0,
                                   pag_prest = 0,
                                   fianza = 0,
                                   negativo = 0,
                                   dctoseguro = 0,
                                   reint_int = 0,
                                   aporte = 0,
                                   colab = 0,
                                   aporte2 = 0,
                                   credito = 0
                               WHERE cod_empleado = ?");
        $stmt->execute([$liquidacion['cod_empleado']]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Liquidación aprobada correctamente. El asociado ha sido dado de baja y todos sus saldos han sido puestos en 0.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al aprobar liquidación: " . $e->getMessage();
    }
}

function rechazarRetiro($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de retiro inválido";
        return;
    }

    $id = intval($_POST['id']);
    $motivo = !empty($_POST['motivo']) ? $_POST['motivo'] : 'Sin motivo especificado';
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    
    try {
        $stmt = $conn->prepare("UPDATE historico_retiros_haberes 
                               SET estado = 'Rechazado', aprobado_por = ?, rol_aprobador = ?, motivo_rechazo = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $motivo, $id]);
        
        $_SESSION['mensaje'] = "Retiro rechazado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al rechazar retiro: " . $e->getMessage();
    }
}

// NUEVA FUNCIÓN: Rechazar liquidación
function rechazarLiquidacion($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de liquidación inválido";
        return;
    }

    $id = intval($_POST['id']);
    $motivo = !empty($_POST['motivo']) ? $_POST['motivo'] : 'Sin motivo especificado';
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    
    try {
        $stmt = $conn->prepare("UPDATE historico_retiros_haberes 
                               SET estado = 'Rechazado', aprobado_por = ?, rol_aprobador = ?, motivo_rechazo = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $motivo, $id]);
        
        $_SESSION['mensaje'] = "Liquidación rechazada correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al rechazar liquidación: " . $e->getMessage();
    }
}

function aprobarPrestamo($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de préstamo inválido";
        return;
    }

    $id = intval($_POST['id']);
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    $plazo_meses = !empty($_POST['plazo']) ? intval($_POST['plazo']) : 12;
    $fecha_vencimiento = date('Y-m-d', strtotime("+$plazo_meses months"));
    
    try {
        $conn->beginTransaction();
        
        // Obtener datos del préstamo con bloqueo para evitar concurrencia
        $stmt = $conn->prepare("SELECT cod_empleado, monto_aprobado FROM historico_prestamos WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prestamo) {
            $_SESSION['mensaje'] = "Error: Préstamo no encontrado.";
            $conn->rollBack();
            return;
        }
        
        // Actualizar estado del préstamo - GUARDAR ROL
        $stmt = $conn->prepare("UPDATE historico_prestamos 
                               SET estado = 'Aprobado', aprobado_por = ?, rol_aprobador = ?, 
                                   fecha_aprobacion = CURRENT_DATE, fecha_vencimiento = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $fecha_vencimiento, $id]);
        
        // Sumar al totalprest del empleado
        $stmt = $conn->prepare("UPDATE asociados 
                               SET totalprest = totalprest + ? 
                               WHERE cod_empleado = ?");
        $stmt->execute([$prestamo['monto_aprobado'], $prestamo['cod_empleado']]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Préstamo aprobado correctamente.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al aprobar préstamo: " . $e->getMessage();
    }
}

function rechazarPrestamo($conn) {
    global $usuario;
    
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
        $_SESSION['mensaje'] = "Error: ID de préstamo inválido";
        return;
    }

    $id = intval($_POST['id']);
    $motivo = !empty($_POST['motivo']) ? $_POST['motivo'] : 'Sin motivo especificado';
    $aprobado_por = $usuario['nombre'];
    $rol_aprobador = $usuario['rol'];
    
    try {
        $stmt = $conn->prepare("UPDATE historico_prestamos 
                               SET estado = 'Rechazado', aprobado_por = ?, rol_aprobador = ?, motivo_rechazo = ?
                               WHERE id = ?");
        $stmt->execute([$aprobado_por, $rol_aprobador, $motivo, $id]);
        
        $_SESSION['mensaje'] = "Préstamo rechazado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al rechazar préstamo: " . $e->getMessage();
    }
}

// Obtener historiales con manejo de errores y paginación
$historial_retiros = [];
$historial_prestamos = [];
$solicitudes_retiros = [];
$solicitudes_prestamos = [];
$solicitudes_liquidaciones = [];

// Configuración de paginación
$registros_por_pagina = 20;
$pagina_retiros = isset($_GET['pagina_retiros']) ? max(1, intval($_GET['pagina_retiros'])) : 1;
$pagina_prestamos = isset($_GET['pagina_prestamos']) ? max(1, intval($_GET['pagina_prestamos'])) : 1;

// Variables para información de paginación
$total_retiros = 0;
$total_paginas_retiros = 0;
$total_prestamos = 0;
$total_paginas_prestamos = 0;

try {
    // Obtener total de retiros para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_retiros_haberes");
    $stmt->execute();
    $total_retiros = $stmt->fetchColumn();
    $total_paginas_retiros = ceil($total_retiros / $registros_por_pagina);
    
    // Obtener retiros con paginación
    $offset_retiros = ($pagina_retiros - 1) * $registros_por_pagina;
    $stmt = $conn->prepare("SELECT * FROM historico_retiros_haberes 
                           ORDER BY fecha_retiro DESC, id DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset_retiros, PDO::PARAM_INT);
    $stmt->execute();
    $historial_retiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de préstamos para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) FROM historico_prestamos");
    $stmt->execute();
    $total_prestamos = $stmt->fetchColumn();
    $total_paginas_prestamos = ceil($total_prestamos / $registros_por_pagina);
    
    // Obtener préstamos con paginación
    $offset_prestamos = ($pagina_prestamos - 1) * $registros_por_pagina;
    $stmt = $conn->prepare("SELECT *, EXTRACT(YEAR FROM fecha_solicitud) as anio_solicitud FROM historico_prestamos 
                           ORDER BY fecha_solicitud DESC, id DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset_prestamos, PDO::PARAM_INT);
    $stmt->execute();
    $historial_prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Solicitudes pendientes (para usuarios con permisos) - SOLO PENDIENTES
    if (puedeAprobar()) {
        // Retiros parciales pendientes
        $stmt = $conn->prepare("SELECT * FROM historico_retiros_haberes 
                               WHERE estado = 'Pendiente' AND tipo_retiro = 'parcial'
                               ORDER BY fecha_retiro DESC, id DESC");
        $stmt->execute();
        $solicitudes_retiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Liquidaciones pendientes
        $stmt = $conn->prepare("SELECT * FROM historico_retiros_haberes 
                               WHERE estado = 'Pendiente' AND tipo_retiro = 'liquidacion'
                               ORDER BY fecha_retiro DESC, id DESC");
        $stmt->execute();
        $solicitudes_liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("SELECT * FROM historico_prestamos 
                               WHERE estado = 'Pendiente' 
                               ORDER BY fecha_solicitud DESC, id DESC");
        $stmt->execute();
        $solicitudes_prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error al obtener historiales: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Retiros y Préstamos - Caja de Ahorro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" href="./logo/capcel.png">
    <style>
         :root {
            --primary-color: #0056b3;
            --secondary-color: #007bff;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --background-color: #f0f2f5;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 0.75rem;
        }

        body {
            background-color: var(--background-color);
            color: var(--dark-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        #wrapper {
            display: flex;
            transition: all 0.3s ease;
        }
        
        /* Sidebar Styles */
        #sidebar-wrapper {
            width: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--primary-color);
            color: #fff;
            transition: margin-left 0.3s ease;
        }

        .sidebar-heading {
            padding: 1.5rem;
            text-align: center;
            font-weight: 700;
            font-size: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-heading .logo {
            max-width: 150px;
            margin-right: 10px;
        }

        .list-group-item {
            background-color: var(--primary-color);
            color: #ccc;
            border: none;
            padding: 1rem 1.5rem;
            transition: background-color 0.2s, color 0.2s;
        }

        .list-group-item:hover, .list-group-item.active {
            background-color: var(--secondary-color);
            color: #fff;
            text-decoration: none;
        }
        
        .list-group-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Content Wrapper */
        #page-content-wrapper {
            flex: 1;
            padding-left: 0;
            transition: padding-left 0.3s ease;
        }
        
        /* Sidebar Toggled State */
        #wrapper.sidebar-collapsed #sidebar-wrapper {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        #wrapper.sidebar-collapsed #page-content-wrapper {
            padding-left: 0;
        }
        
        @media (min-width: 768px) {
            #wrapper:not(.sidebar-collapsed) #page-content-wrapper {
                padding-left: var(--sidebar-width);
            }
        }

        /* Top Navbar */
        .navbar {
            padding: 1rem 1.5rem;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        #menu-toggle {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        /* Main Content & Cards */
        .main-content {
            padding: 2rem;
        }
        
        .search-card, .results-card {
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: none;
        }
        
        .results-card .card-header {
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
        }
        
        .table th {
            background-color: var(--light-gray);
        }
        
        .bg-white {
            background-color: #fff !important;
        }
        
        .badge {
            font-size: 0.85em;
            font-weight: 500;
        }

        /* Botón de impresión PDF */
        #print-pdf-btn {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }

        #print-pdf-btn:hover {
            background-color: #b71c1c;
            border-color: #b71c1c;
        }

        /* Estilos específicos para el módulo de retiros y préstamos */
        .saldo-disponible {
            font-size: 1.2rem;
            color: #28a745;
            font-weight: bold;
        }
        
        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
            background-color: white;
        }
        
        .nav-tabs .nav-link {
            color: var(--dark-gray);
        }
        
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .info-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .card-header {
            background-color: var(--light-gray);
            font-weight: bold;
        }
        
        /* Nuevos estilos agregados */
        .search-box {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .search-results {
            display: none;
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .search-result-item:hover {
            background-color: #e9ecef;
        }
        
        .selected-associate {
            background-color: #e7f5ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .currency-input {
            position: relative;
        }
        
        .currency-input input {
            padding-left: 25px;
        }
        
        .currency-input::before {
            content: "Bs";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .max-amount-btn {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: underline;
            font-size: 0.8rem;
        }
        
        .max-amount-btn:hover {
            color: #0a58ca;
        }
        
        .eligibility-badge {
            font-size: 0.8rem;
            padding: 3px 6px;
            margin-left: 5px;
        }
        
        .eligibility-message {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .eligibility-ok {
            background-color: #e6ffed;
            color: #22863a;
        }
        
        .eligibility-error {
            background-color: #ffeef0;
            color: #cb2431;
        }
        
        .operation-selector {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .operation-buttons {
            margin-top: 10px;
            display: none;
        }
        
        .associate-info-box {
            background-color: #e7f5ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        /* Estilos para paginación */
        .pagination {
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .page-link {
            color: var(--primary-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        /* Nuevos estilos para liquidación */
        .liquidacion-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .btn-liquidacion {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-liquidacion:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        .badge-liquidacion {
            background-color: #dc3545;
        }
        
        .asociado-liquidado {
            background-color: #f8d7da !important;
            color: #721c24;
        }
    </style>
</head>
<body>
   <div id="wrapper">
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <img src="logo/capcel.png" alt="Logo" class="logo">
                <span>Caja de Ahorro</span>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
                
                <a href="historial.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-history"></i>Módulo Historial
                </a>
                <a href="retiros_prestamos.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Retiros y Préstamos">
                    <i class="fas fa-hand-holding-usd"></i>Retiros y Préstamos
                </a>
                <a href="pagos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-calculator"></i>Pagos
                    </a>
                    <a href="colaboraciones_creditos.php" class="list-group-item list-group-item-action  " data-bs-toggle="tooltip" data-bs-placement="right" title="Colaboraciones y Créditos">
                    <i class="fas fa-handshake"></i>Colaboraciones y Créditos
                </a>
                <a href="consulta.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Consulta">
                    <i class="fas fa-search-dollar"></i>Módulo de Consulta
                </a>
                <a href="carga.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Carga de Datos">
                    <i class="fas fa-upload"></i>Carga Masiva e Individual
                </a>
                <a href="socios.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Gestión de Socios">
                    <i class="fas fa-users-cog"></i>Agregar y Gestionar Socios
                </a>
            </div>
        </div>
        
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light">
                <i class="fas fa-bars" id="menu-toggle"></i>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($nombre_usuario); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="#">Mi Perfil</a>
                                <a class="dropdown-item" href="#">Configuración</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Cerrar Sesión</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

        <main class="main-content">
            <h1 class="mb-4">Sistema de Retiros y Préstamos</h1>
            
            <?php if (!empty($_SESSION['mensaje'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['mensaje']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    <?php unset($_SESSION['mensaje']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Información del Usuario Conectado -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Usuario Conectado</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?></p>
                            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['usuario']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                            <p><strong>Cédula:</strong> <?php echo htmlspecialchars($usuario['cedula'] ?? 'No disponible'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Rol:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($rol_usuario); ?></span></p>
                            <p><strong>Código Empleado:</strong> <?php echo htmlspecialchars($usuario['codigo_empleado'] ?? 'No asignado'); ?></p>
                            <?php if (!empty($empleado)): ?>
                                <?php if ($empleado['statu'] == 'L'): ?>
                                    <div class="alert alert-danger">
                                        <strong><i class="fas fa-exclamation-triangle"></i> ESTADO: LIQUIDADO</strong>
                                        <p>Este asociado ha sido liquidado y no puede realizar operaciones.</p>
                                    </div>
                                <?php else: ?>
                                    <p><strong>Saldo Disponible:</strong> <span class="saldo-disponible">Bs<?php echo number_format(floatval($empleado['totalcaja']), 2); ?></span></p>
                                    <p><strong>Máximo Retiro (75%):</strong> Bs<?php echo number_format(floatval($empleado['totalcaja']) * 0.75, 2); ?></p>
                                    <p><strong>Máximo Préstamo (80%):</strong> Bs<?php echo number_format(floatval($empleado['totalcaja']) * 0.80, 2); ?></p>
                                    <p><strong>Estado:</strong> 
                                        <span class="badge bg-<?= $empleado['statu'] == 'A' ? 'success' : 'warning' ?>">
                                            <?= $empleado['statu'] == 'A' ? 'Activo' : ($empleado['statu'] == 'L' ? 'Liquidado' : $empleado['statu']) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p><strong>Estado:</strong> <span class="badge bg-warning">No asociado a empleado</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Buscador de Asociados (para operaciones) -->
            <div class="search-box">
                <h5>Buscar Asociado para Operación</h5>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="searchAssociate" placeholder="Ingrese cédula o código de empleado">
                    <button class="btn btn-outline-secondary" type="button" id="searchAssociateBtn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                <div class="search-results" id="searchResults"></div>
                
                <div class="associate-info-box" id="selectedAssociate">
                    <h5>Asociado Seleccionado</h5>
                    <div id="associateInfo"></div>
                    <input type="hidden" id="selectedCodEmpleado" name="cod_empleado_operacion">
                    
                    <div class="operation-selector">
                        <h6>Seleccione operación a realizar:</h6>
                        <div class="operation-buttons" id="operationButtons">
                            <button type="button" class="btn btn-primary me-2" id="btnRetiro">
                                <i class="fas fa-money-bill-wave"></i> Solicitar Retiro
                            </button>
                            <button type="button" class="btn btn-success me-2" id="btnPrestamo">
                                <i class="fas fa-hand-holding-usd"></i> Solicitar Préstamo
                            </button>
                            <!-- NUEVO BOTÓN: Liquidación -->
                            <button type="button" class="btn btn-liquidacion" id="btnLiquidacion">
                                <i class="fas fa-sign-out-alt"></i> Liquidación Completa
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formularios de Retiro, Préstamo y Liquidación (ocultos inicialmente) -->
            <div class="row">
                <div class="col-md-12">
                    <!-- Formulario de Retiro -->
                    <div class="card mb-4" id="retiroForm" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">Solicitud de Retiro de Haberes</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" onsubmit="return validarRetiro()">
                                <input type="hidden" name="cod_empleado_operacion" id="formCodEmpleadoRetiro">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_retiro" class="form-label">Fecha de Retiro</label>
                                        <input type="date" class="form-control" id="fecha_retiro" name="fecha_retiro" 
                                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="anio_retiro" class="form-label">Año de Retiro</label>
                                        <select class="form-control" id="anio_retiro" name="anio_retiro" required>
                                            <?php 
                                            $current_year = date('Y');
                                            for($i = $current_year; $i >= $current_year - 5; $i--): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3 currency-input">
                                    <label for="monto_retiro" class="form-label">Monto a Retirar</label>
                                    <input type="number" step="0.01" class="form-control" id="monto_retiro" name="monto" 
                                           min="0.01" required>
                                    <small class="form-text text-muted">
                                        Máximo permitido: <span id="maxRetiro">Bs0.00</span>
                                        <span class="max-amount-btn" onclick="setMaxRetiro()">(Usar máximo)</span>
                                    </small>
                                </div>
                                <button type="submit" name="solicitar_retiro" class="btn btn-primary">
                                    Enviar Solicitud de Retiro
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelarOperacion()">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Formulario de Préstamo -->
                    <div class="card mb-4" id="prestamoForm" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">Solicitud de Préstamo</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" onsubmit="return validarPrestamo()">
                                <input type="hidden" name="cod_empleado_operacion" id="formCodEmpleadoPrestamo">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_prestamo" class="form-label">Fecha de Solicitud</label>
                                        <input type="date" class="form-control" id="fecha_prestamo" name="fecha_prestamo" 
                                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="anio_prestamo" class="form-label">Año de Solicitud</label>
                                        <select class="form-control" id="anio_prestamo" name="anio_prestamo" required>
                                            <?php 
                                            $current_year = date('Y');
                                            for($i = $current_year; $i >= $current_year - 5; $i--): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3 currency-input">
                                    <label for="monto_prestamo" class="form-label">Monto a Solicitar</label>
                                    <input type="number" step="0.01" class="form-control" id="monto_prestamo" name="monto" 
                                           min="0.01" required>
                                    <small class="form-text text-muted">
                                        Máximo permitido sin fiador: <span id="maxPrestamo">Bs0.00</span>
                                        <span class="max-amount-btn" onclick="setMaxPrestamo()">(Usar máximo)</span>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="plazo" class="form-label">Plazo (meses)</label>
                                    <select class="form-control" id="plazo" name="plazo" required>
                                        <option value="6">6 meses</option>
                                        <option value="12" selected>12 meses</option>
                                        <option value="18">18 meses</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="requiere_fiador">
                                        <label class="form-check-label" for="requiere_fiador">
                                            Necesito un fiador (para montos mayores al 80% de mi saldo)
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="fiador_fields" style="display: none;">
                                    <div class="mb-3">
                                        <label for="cod_fiador" class="form-label">Código del Fiador</label>
                                        <input type="text" class="form-control" id="cod_fiador" name="cod_fiador" placeholder="Ingrese el código de empleado">
                                    </div>
                                    <div class="mb-3">
                                        <label for="nombre_fiador" class="form-label">Nombre del Fiador</label>
                                        <input type="text" class="form-control" id="nombre_fiador" name="nombre_fiador" placeholder="Nombre completo del fiador">
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-3" id="info_cuota" style="display: none;">
                                    <strong>Información del préstamo:</strong>
                                    <div id="detalle_cuota"></div>
                                </div>
                                
                                <button type="submit" name="solicitar_prestamo" class="btn btn-success">
                                    Enviar Solicitud de Préstamo
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelarOperacion()">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- NUEVO FORMULARIO: Liquidación Completa -->
                    <div class="card mb-4" id="liquidacionForm" style="display: none;">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Solicitud de Liquidación Completa</h5>
                        </div>
                        <div class="card-body">
                            <div class="liquidacion-warning">
                                <h6><i class="fas fa-exclamation-circle"></i> ADVERTENCIA IMPORTANTE</h6>
                                <p>Esta operación retirará <strong>TODO el saldo disponible</strong> de la caja de ahorros y dará de baja al asociado.</p>
                                <p><strong>Esta acción es irreversible</strong> y solo debe realizarse en casos de retiro definitivo de la institución.</p>
                                <p>El asociado será marcado como <strong>LIQUIDADO (L)</strong> y todos sus saldos serán puestos en <strong>CERO</strong>.</p>
                            </div>
                            
                            <form method="post" onsubmit="return validarLiquidacion()">
                                <input type="hidden" name="cod_empleado_operacion" id="formCodEmpleadoLiquidacion">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_liquidacion" class="form-label">Fecha de Liquidación</label>
                                        <input type="date" class="form-control" id="fecha_liquidacion" name="fecha_liquidacion" 
                                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="anio_liquidacion" class="form-label">Año de Liquidación</label>
                                        <select class="form-control" id="anio_liquidacion" name="anio_liquidacion" required>
                                            <?php 
                                            $current_year = date('Y');
                                            for($i = $current_year; $i >= $current_year - 5; $i--): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="motivo_liquidacion" class="form-label">Motivo de la Liquidación</label>
                                    <textarea class="form-control" id="motivo_liquidacion" name="motivo_liquidacion" 
                                              rows="3" placeholder="Especifique el motivo de la liquidación completa..." required></textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6>Resumen de la Liquidación</h6>
                                    <p><strong>Saldo total a retirar:</strong> <span id="totalLiquidacion" class="saldo-disponible">Bs0.00</span></p>
                                    <p><strong>Estado después de la liquidación:</strong> El asociado será marcado como LIQUIDADO (L) y todos sus saldos serán puestos en 0</p>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirmar_liquidacion" required>
                                    <label class="form-check-label" for="confirmar_liquidacion">
                                        Confirmo que entiendo que esta acción es irreversible y dará de baja al asociado permanentemente.
                                    </label>
                                </div>
                                
                                <button type="submit" name="solicitar_liquidacion" class="btn btn-liquidacion">
                                    <i class="fas fa-sign-out-alt"></i> Solicitar Liquidación Completa
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelarOperacion()">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sección de Aprobaciones (solo para usuarios con permisos) -->
            <?php if (puedeAprobar()): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Solicitudes Pendientes de Aprobación</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="approvalTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="retiros-tab" data-bs-toggle="tab" data-bs-target="#retiros-pendientes" type="button" role="tab">
                                        Retiros de Haberes
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="prestamos-tab" data-bs-toggle="tab" data-bs-target="#prestamos-pendientes" type="button" role="tab">
                                        Préstamos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="liquidaciones-tab" data-bs-toggle="tab" data-bs-target="#liquidaciones-pendientes" type="button" role="tab">
                                        Liquidaciones
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="approvalTabsContent">
                                <!-- Pestaña de Retiros Pendientes -->
                                <div class="tab-pane fade show active" id="retiros-pendientes" role="tabpanel">
                                    <?php if (empty($solicitudes_retiros)): ?>
                                        <div class="alert alert-info mt-3">No hay solicitudes de retiro pendientes</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Empleado</th>
                                                        <th>Cédula</th>
                                                        <th>Monto</th>
                                                        <th>Fecha</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($solicitudes_retiros as $solicitud): ?>
                                                    <tr>
                                                        <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                                        <td><?= htmlspecialchars($solicitud['nombre']) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['cedula']) ?></td>
                                                        <td>Bs<?= number_format($solicitud['monto_retirado'], 2) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['fecha_retiro']) ?></td>
                                                        <td>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="id" value="<?= $solicitud['id'] ?>">
                                                                <button type="submit" name="aprobar_retiro" class="btn btn-sm btn-success">Aprobar</button>
                                                            </form>
                                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                                    data-bs-target="#rechazarRetiroModal" 
                                                                    data-id="<?= $solicitud['id'] ?>">Rechazar</button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Pestaña de Préstamos Pendientes -->
                                <div class="tab-pane fade" id="prestamos-pendientes" role="tabpanel">
                                    <?php if (empty($solicitudes_prestamos)): ?>
                                        <div class="alert alert-info mt-3">No hay solicitudes de préstamo pendientes</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Empleado</th>
                                                        <th>Monto</th>
                                                        <th>Plazo</th>
                                                        <th>Cuota</th>
                                                        <th>Fiador</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($solicitudes_prestamos as $solicitud): ?>
                                                    <tr>
                                                        <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                                        <td><?= htmlspecialchars($solicitud['nombre']) ?></td>
                                                        <td>Bs<?= number_format($solicitud['monto_solicitado'], 2) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['plazo_meses']) ?> meses</td>
                                                        <td>Bs<?= number_format($solicitud['cuota_mensual'], 2) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['nombre_fiador'] ?? 'Ninguno') ?></td>
                                                        <td>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="id" value="<?= $solicitud['id'] ?>">
                                                                <input type="hidden" name="plazo" value="<?= $solicitud['plazo_meses'] ?>">
                                                                <button type="submit" name="aprobar_prestamo" class="btn btn-sm btn-success">Aprobar</button>
                                                            </form>
                                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                                    data-bs-target="#rechazarPrestamoModal" 
                                                                    data-id="<?= $solicitud['id'] ?>">Rechazar</button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- NUEVA PESTAÑA: Liquidaciones Pendientes -->
                                <div class="tab-pane fade" id="liquidaciones-pendientes" role="tabpanel">
                                    <?php if (empty($solicitudes_liquidaciones)): ?>
                                        <div class="alert alert-info mt-3">No hay solicitudes de liquidación pendientes</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Empleado</th>
                                                        <th>Cédula</th>
                                                        <th>Monto Total</th>
                                                        <th>Motivo</th>
                                                        <th>Fecha</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($solicitudes_liquidaciones as $solicitud): ?>
                                                    <tr>
                                                        <td><strong>#<?= $solicitud['id'] ?></strong></td>
                                                        <td><?= htmlspecialchars($solicitud['nombre']) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['cedula']) ?></td>
                                                        <td class="saldo-disponible">Bs<?= number_format($solicitud['monto_retirado'], 2) ?></td>
                                                        <td><?= htmlspecialchars($solicitud['motivo'] ?? 'Liquidación completa') ?></td>
                                                        <td><?= htmlspecialchars($solicitud['fecha_retiro']) ?></td>
                                                        <td>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="id" value="<?= $solicitud['id'] ?>">
                                                                <button type="submit" name="aprobar_liquidacion" class="btn btn-sm btn-success">Aprobar</button>
                                                            </form>
                                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                                    data-bs-target="#rechazarLiquidacionModal" 
                                                                    data-id="<?= $solicitud['id'] ?>">Rechazar</button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Sección de Historial -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Historial General de Operaciones</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="historyTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="historial-retiros-tab" data-bs-toggle="tab" data-bs-target="#historial-retiros" type="button" role="tab">
                                        Retiros de Haberes (<?= $total_retiros ?>)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="historial-prestamos-tab" data-bs-toggle="tab" data-bs-target="#historial-prestamos" type="button" role="tab">
                                        Préstamos (<?= $total_prestamos ?>)
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="historyTabsContent">
                                <!-- Pestaña de Historial de Retiros -->
                                <div class="tab-pane fade show active" id="historial-retiros" role="tabpanel">
                                    <?php if (empty($historial_retiros)): ?>
                                        <div class="alert alert-info mt-3">No hay registros de retiros</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Empleado</th>
                                                        <th>Cédula</th>
                                                        <th>Monto</th>
                                                        <th>Fecha</th>
                                                        <th>Año</th>
                                                        <th>Tipo</th>
                                                        <th>Estado</th>
                                                        <th>Aprobado Por</th>
                                                        <th>Rol</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historial_retiros as $retiro): ?>
                                                    <tr class="<?= $retiro['tipo_retiro'] == 'liquidacion' ? 'asociado-liquidado' : '' ?>">
                                                        <td><strong>#<?= htmlspecialchars($retiro['id']) ?></strong></td>
                                                        <td><?= htmlspecialchars($retiro['nombre']) ?></td>
                                                        <td><?= htmlspecialchars($retiro['cedula']) ?></td>
                                                        <td>Bs<?= number_format($retiro['monto_retirado'], 2) ?></td>
                                                        <td><?= htmlspecialchars($retiro['fecha_retiro']) ?></td>
                                                        <td><?= htmlspecialchars($retiro['anio_retiro']) ?></td>
                                                        <td>
                                                            <?php if ($retiro['tipo_retiro'] == 'liquidacion'): ?>
                                                                <span class="badge badge-liquidacion">Liquidación</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary">Parcial</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $retiro['estado'] == 'Pendiente' ? 'warning' : 
                                                                ($retiro['estado'] == 'Rechazado' ? 'danger' : 'success') ?>">
                                                                <?= htmlspecialchars($retiro['estado']) ?>
                                                            </span>
                                                            <?php if ($retiro['estado'] == 'Rechazado' && !empty($retiro['motivo_rechazo'])): ?>
                                                                <br><small><?= htmlspecialchars($retiro['motivo_rechazo']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($retiro['aprobado_por'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($retiro['rol_aprobador'] ?? 'N/A') ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Paginación para Retiros -->
                                        <?php if ($total_paginas_retiros > 1): ?>
                                        <nav aria-label="Paginación de retiros">
                                            <ul class="pagination justify-content-center">
                                                <?php if ($pagina_retiros > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?pagina_retiros=<?= $pagina_retiros - 1 ?>&pagina_prestamos=<?= $pagina_prestamos ?>#historial-retiros" aria-label="Anterior">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_paginas_retiros; $i++): ?>
                                                    <li class="page-item <?= $i == $pagina_retiros ? 'active' : '' ?>">
                                                        <a class="page-link" href="?pagina_retiros=<?= $i ?>&pagina_prestamos=<?= $pagina_prestamos ?>#historial-retiros">
                                                            <?= $i ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($pagina_retiros < $total_paginas_retiros): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?pagina_retiros=<?= $pagina_retiros + 1 ?>&pagina_prestamos=<?= $pagina_prestamos ?>#historial-retiros" aria-label="Siguiente">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                            <div class="text-center text-muted">
                                                Página <?= $pagina_retiros ?> de <?= $total_paginas_retiros ?> 
                                                (Mostrando <?= count($historial_retiros) ?> de <?= $total_retiros ?> registros)
                                            </div>
                                        </nav>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Pestaña de Historial de Préstamos -->
                                <div class="tab-pane fade" id="historial-prestamos" role="tabpanel">
                                    <?php if (empty($historial_prestamos)): ?>
                                        <div class="alert alert-info mt-3">No hay registros de préstamos</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Empleado</th>
                                                        <th>Monto</th>
                                                        <th>Plazo</th>
                                                        <th>Cuota</th>
                                                        <th>Estado</th>
                                                        <th>Fiador</th>
                                                        <th>Aprobado Por</th>
                                                        <th>Rol</th>
                                                        <th>Saldo</th>
                                                        <th>Año</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historial_prestamos as $prestamo): ?>
                                                    <tr>
                                                        <td><strong>#<?= htmlspecialchars($prestamo['id']) ?></strong></td>
                                                        <td><?= htmlspecialchars($prestamo['nombre']) ?></td>
                                                        <td>Bs<?= number_format($prestamo['monto_aprobado'], 2) ?></td>
                                                        <td><?= htmlspecialchars($prestamo['plazo_meses']) ?> meses</td>
                                                        <td>Bs<?= number_format($prestamo['cuota_mensual'], 2) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $prestamo['estado'] == 'Pendiente' ? 'warning' : 
                                                                ($prestamo['estado'] == 'Rechazado' ? 'danger' : 'success') ?>">
                                                                <?= htmlspecialchars($prestamo['estado']) ?>
                                                            </span>
                                                            <?php if ($prestamo['estado'] == 'Rechazado' && !empty($prestamo['motivo_rechazo'])): ?>
                                                                <br><small><?= htmlspecialchars($prestamo['motivo_rechazo']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($prestamo['nombre_fiador'] ?? 'Ninguno') ?></td>
                                                        <td><?= htmlspecialchars($prestamo['aprobado_por'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($prestamo['rol_aprobador'] ?? 'N/A') ?></td>
                                                        <td>Bs<?= number_format($prestamo['saldo_pendiente'], 2) ?></td>
                                                        <td><?= htmlspecialchars($prestamo['anio_solicitud'] ?? date('Y', strtotime($prestamo['fecha_solicitud']))) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Paginación para Préstamos -->
                                        <?php if ($total_paginas_prestamos > 1): ?>
                                        <nav aria-label="Paginación de préstamos">
                                            <ul class="pagination justify-content-center">
                                                <?php if ($pagina_prestamos > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?pagina_retiros=<?= $pagina_retiros ?>&pagina_prestamos=<?= $pagina_prestamos - 1 ?>#historial-prestamos" aria-label="Anterior">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_paginas_prestamos; $i++): ?>
                                                    <li class="page-item <?= $i == $pagina_prestamos ? 'active' : '' ?>">
                                                        <a class="page-link" href="?pagina_retiros=<?= $pagina_retiros ?>&pagina_prestamos=<?= $i ?>#historial-prestamos">
                                                            <?= $i ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($pagina_prestamos < $total_paginas_prestamos): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?pagina_retiros=<?= $pagina_retiros ?>&pagina_prestamos=<?= $pagina_prestamos + 1 ?>#historial-prestamos" aria-label="Siguiente">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                </ul>
                                                <div class="text-center text-muted">
                                                    Página <?= $pagina_prestamos ?> de <?= $total_paginas_prestamos ?> 
                                                    (Mostrando <?= count($historial_prestamos) ?> de <?= $total_prestamos ?> registros)
                                                </div>
                                            </nav>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para rechazar retiro -->
    <div class="modal fade" id="rechazarRetiroModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Retiro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="retiro_id">
                        <div class="mb-3">
                            <label for="motivo_rechazo" class="form-label">Motivo del Rechazo</label>
                            <textarea class="form-control" id="motivo_rechazo" name="motivo" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="rechazar_retiro" class="btn btn-danger">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para rechazar préstamo -->
    <div class="modal fade" id="rechazarPrestamoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Préstamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="prestamo_id">
                        <div class="mb-3">
                            <label for="motivo_rechazo_prestamo" class="form-label">Motivo del Rechazo</label>
                            <textarea class="form-control" id="motivo_rechazo_prestamo" name="motivo" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="rechazar_prestamo" class="btn btn-danger">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- NUEVO MODAL: Rechazar liquidación -->
    <div class="modal fade" id="rechazarLiquidacionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Liquidación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="liquidacion_id">
                        <div class="mb-3">
                            <label for="motivo_rechazo_liquidacion" class="form-label">Motivo del Rechazo</label>
                            <textarea class="form-control" id="motivo_rechazo_liquidacion" name="motivo" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="rechazar_liquidacion" class="btn btn-danger">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        // Variable para almacenar el asociado seleccionado
        let currentAssociate = null;
        
        // Mostrar/ocultar campos de fiador
        document.getElementById('requiere_fiador').addEventListener('change', function() {
            document.getElementById('fiador_fields').style.display = this.checked ? 'block' : 'none';
        });
        
        // Configurar modales
        var rechazarRetiroModal = document.getElementById('rechazarRetiroModal');
        rechazarRetiroModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var modalInput = rechazarRetiroModal.querySelector('#retiro_id');
            modalInput.value = id;
        });
        
        var rechazarPrestamoModal = document.getElementById('rechazarPrestamoModal');
        rechazarPrestamoModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var modalInput = rechazarPrestamoModal.querySelector('#prestamo_id');
            modalInput.value = id;
        });
        
        // NUEVO MODAL: Configurar modal de rechazo de liquidación
        var rechazarLiquidacionModal = document.getElementById('rechazarLiquidacionModal');
        rechazarLiquidacionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var modalInput = rechazarLiquidacionModal.querySelector('#liquidacion_id');
            modalInput.value = id;
        });
        
        // Validación de retiro actualizada
        function validarRetiro() {
            const monto = parseFloat(document.getElementById('monto_retiro').value);
            const maximo = parseFloat(document.getElementById('monto_retiro').getAttribute('max'));
            const fecha = document.getElementById('fecha_retiro').value;
            const anio = document.getElementById('anio_retiro').value;
            
            if (!fecha) {
                Swal.fire('Error', 'Debe seleccionar una fecha de retiro', 'error');
                return false;
            }
            
            if (isNaN(monto) || monto <= 0) {
                Swal.fire('Error', 'El monto debe ser mayor a Bs0.00', 'error');
                return false;
            }
            
            if (monto > maximo) {
                Swal.fire('Error', 'El monto máximo permitido es Bs' + maximo.toFixed(2), 'error');
                return false;
            }
            
            // Verificar que el año seleccionado coincida con el año de la fecha
            const fechaAnio = new Date(fecha).getFullYear();
            if (parseInt(anio) !== fechaAnio) {
                Swal.fire('Advertencia', 'El año seleccionado no coincide con el año de la fecha. Se usará el año de la fecha.', 'warning');
                document.getElementById('anio_retiro').value = fechaAnio;
            }
            
            return true;
        }
        
        // Validación de préstamo actualizada
        function validarPrestamo() {
            const monto = parseFloat(document.getElementById('monto_prestamo').value);
            const plazo = parseInt(document.getElementById('plazo').value);
            const requiereFiador = document.getElementById('requiere_fiador').checked;
            const codFiador = document.getElementById('cod_fiador').value;
            const fecha = document.getElementById('fecha_prestamo').value;
            const anio = document.getElementById('anio_prestamo').value;
            
            if (!fecha) {
                Swal.fire('Error', 'Debe seleccionar una fecha de solicitud', 'error');
                return false;
            }
            
            if (isNaN(monto) || monto <= 0) {
                Swal.fire('Error', 'El monto debe ser mayor a cero', 'error');
                return false;
            }
            
            if (requiereFiador && !codFiador) {
                Swal.fire('Error', 'Debe especificar el código del fiador', 'error');
                return false;
            }
            
            // Verificar que el año seleccionado coincida con el año de la fecha
            const fechaAnio = new Date(fecha).getFullYear();
            if (parseInt(anio) !== fechaAnio) {
                Swal.fire('Advertencia', 'El año seleccionado no coincide con el año de la fecha. Se usará el año de la fecha.', 'warning');
                document.getElementById('anio_prestamo').value = fechaAnio;
            }
            
            return true;
        }
        
        // NUEVA FUNCIÓN: Validación de liquidación actualizada
        function validarLiquidacion() {
            const confirmacion = document.getElementById('confirmar_liquidacion').checked;
            const motivo = document.getElementById('motivo_liquidacion').value.trim();
            const fecha = document.getElementById('fecha_liquidacion').value;
            const anio = document.getElementById('anio_liquidacion').value;
            
            if (!fecha) {
                Swal.fire('Error', 'Debe seleccionar una fecha de liquidación', 'error');
                return false;
            }
            
            if (!confirmacion) {
                Swal.fire('Error', 'Debe confirmar que entiende las consecuencias de la liquidación', 'error');
                return false;
            }
            
            if (motivo.length < 10) {
                Swal.fire('Error', 'Debe especificar un motivo detallado para la liquidación (mínimo 10 caracteres)', 'error');
                return false;
            }
            
            // Verificar que el año seleccionado coincida con el año de la fecha
            const fechaAnio = new Date(fecha).getFullYear();
            if (parseInt(anio) !== fechaAnio) {
                Swal.fire('Advertencia', 'El año seleccionado no coincide con el año de la fecha. Se usará el año de la fecha.', 'warning');
                document.getElementById('anio_liquidacion').value = fechaAnio;
            }
            
            // Confirmación final
            return Swal.fire({
                title: '¿Está seguro?',
                text: "Esta acción retirará TODO el saldo, pondrá todos los saldos en 0 y marcará al asociado como LIQUIDADO (L) permanentemente.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, solicitar liquidación',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                return result.isConfirmed;
            });
        }
        
        // Calcular y mostrar información del préstamo
        document.getElementById('monto_prestamo').addEventListener('input', calcularCuota);
        document.getElementById('plazo').addEventListener('change', calcularCuota);
        
        function calcularCuota() {
            const monto = parseFloat(document.getElementById('monto_prestamo').value) || 0;
            const plazo = parseInt(document.getElementById('plazo').value);
            
            if (monto > 0 && plazo > 0) {
                const interesMensual = 12 / 12 / 100; // 12% anual a mensual
                const cuota = monto * (interesMensual * Math.pow(1 + interesMensual, plazo)) / 
                              (Math.pow(1 + interesMensual, plazo) - 1);
                const total = cuota * plazo;
                
                document.getElementById('info_cuota').style.display = 'block';
                document.getElementById('detalle_cuota').innerHTML = `
                    <p>Cuota mensual: Bs${cuota.toFixed(2)}</p>
                    <p>Total a pagar: Bs${total.toFixed(2)}</p>
                    <p>Tasa de interés: 12% anual</p>
                `;
            } else {
                document.getElementById('info_cuota').style.display = 'none';
            }
        }

        // Script para colapsar el menú lateral
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("sidebar-collapsed");
        });

        // Activar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Buscar asociados al hacer clic en el botón
        $('#searchAssociateBtn').click(function() {
            searchAssociates();
        });
        
        // También buscar al presionar Enter
        $('#searchAssociate').keypress(function(e) {
            if (e.which == 13) {
                searchAssociates();
                return false;
            }
        });
        
        function searchAssociates() {
            const searchTerm = $('#searchAssociate').val().trim();
            
            if (searchTerm.length < 2) {
                Swal.fire('Error', 'Ingrese al menos 2 caracteres para buscar', 'warning');
                return;
            }
            
            $.ajax({
                url: 'buscar_asociado.php',
                type: 'POST',
                data: { term: searchTerm },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayResults(response.data);
                    } else {
                        $('#searchResults').html('<div class="alert alert-warning">' + response.message + '</div>');
                        $('#searchResults').show();
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al buscar asociados', 'error');
                }
            });
        }
        
        function displayResults(results) {
            const $resultsContainer = $('#searchResults');
            $resultsContainer.empty();
            
            if (results.length === 0) {
                $resultsContainer.html('<div class="alert alert-info">No se encontraron resultados</div>');
                $resultsContainer.show();
                return;
            }
            
            results.forEach(function(associate) {
                const $item = $('<div class="search-result-item"></div>');
                
                const retiroIcon = associate.puede_retirar.aprobado ? 
                    '<i class="fas fa-check-circle text-success"></i>' : 
                    '<i class="fas fa-times-circle text-danger"></i>';
                    
                const prestamoIcon = associate.puede_prestamo.aprobado ? 
                    '<i class="fas fa-check-circle text-success"></i>' : 
                    '<i class="fas fa-times-circle text-danger"></i>';
                
                // Verificar si puede solicitar liquidación (no tener préstamos pendientes y no estar ya liquidado)
                const puedeLiquidacion = !associate.tiene_prestamos_pendientes && associate.statu !== 'L';
                const liquidacionIcon = puedeLiquidacion ? 
                    '<i class="fas fa-check-circle text-success"></i>' : 
                    '<i class="fas fa-times-circle text-danger"></i>';
                
                // Si ya está liquidado, mostrar estado especial
                if (associate.statu === 'L') {
                    $item.addClass('asociado-liquidado');
                }
                
                $item.html(`
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${associate.nombre}</strong>
                            ${associate.statu === 'L' ? '<span class="badge badge-liquidacion ms-2">LIQUIDADO</span>' : ''}
                            <br>
                            <small>Código: ${associate.cod_empleado} | Cédula: ${associate.cedula}</small>
                        </div>
                        <div class="text-end">
                            <small>Retiro ${retiroIcon} | Préstamo ${prestamoIcon} | Liquidación ${liquidacionIcon}</small>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small>Saldo: Bs${parseFloat(associate.totalcaja).toFixed(2)}</small>
                        ${associate.statu === 'L' ? '<br><small class="text-danger"><strong>ESTADO: LIQUIDADO</strong></small>' : ''}
                    </div>
                `);
                
                $item.attr('title', 
                    `Retiro: ${associate.puede_retirar.motivo}\nPréstamo: ${associate.puede_prestamo.motivo}\nLiquidación: ${associate.statu === 'L' ? 'YA LIQUIDADO' : (puedeLiquidacion ? 'Disponible' : 'No disponible - Tiene préstamos pendientes')}`
                );
                $item.tooltip({placement: 'right'});
                
                $item.click(function() {
                    selectAssociate(associate);
                });
                
                $resultsContainer.append($item);
            });
            
            $resultsContainer.show();
        }
        
        function selectAssociate(associate) {
            console.log('Asociado seleccionado:', associate);
            
            // Verificar que los datos necesarios estén presentes
            if (!associate.totalcaja || associate.totalcaja <= 0) {
                Swal.fire('Error', 'El asociado no tiene saldo disponible', 'error');
                return;
            }
            
            $('#selectedAssociate').show();
            
            const retiroClass = associate.puede_retirar.aprobado ? 'eligibility-ok' : 'eligibility-error';
            const prestamoClass = associate.puede_prestamo.aprobado ? 'eligibility-ok' : 'eligibility-error';
            const puedeLiquidacion = !associate.tiene_prestamos_pendientes && associate.statu !== 'L';
            const liquidacionClass = puedeLiquidacion ? 'eligibility-ok' : 'eligibility-error';
            const motivoLiquidacion = associate.statu === 'L' ? 
                'YA LIQUIDADO - No puede solicitar liquidación' : 
                (puedeLiquidacion ? 'Puede solicitar liquidación completa' : 'No puede solicitar liquidación mientras tenga préstamos pendientes');
            
            $('#associateInfo').html(`
                <p><strong>Nombre:</strong> ${associate.nombre}</p>
                <p><strong>Código:</strong> ${associate.cod_empleado}</p>
                <p><strong>Cédula:</strong> ${associate.cedula}</p>
                <p><strong>Saldo disponible:</strong> Bs${parseFloat(associate.totalcaja).toFixed(2)}</p>
                <p><strong>Estado:</strong> 
                    ${associate.statu === 'L' ? 
                        '<span class="badge badge-liquidacion">LIQUIDADO</span>' : 
                        '<span class="badge bg-success">ACTIVO</span>'
                    }
                </p>
                
                <div class="eligibility-message ${retiroClass}">
                    <h6>Estado para Retiro:</h6>
                    <p>${associate.puede_retirar.motivo}</p>
                    <p><strong>Máximo retiro (75%):</strong> Bs${parseFloat(associate.totalcaja * 0.75).toFixed(2)}</p>
                </div>
                
                <div class="eligibility-message ${prestamoClass}">
                    <h6>Estado para Préstamo:</h6>
                    <p>${associate.puede_prestamo.motivo}</p>
                    <p><strong>Máximo préstamo (80%):</strong> Bs${parseFloat(associate.totalcaja * 0.80).toFixed(2)}</p>
                </div>
                
                <div class="eligibility-message ${liquidacionClass}">
                    <h6>Estado para Liquidación:</h6>
                    <p>${motivoLiquidacion}</p>
                    <p><strong>Total a liquidar:</strong> Bs${parseFloat(associate.totalcaja).toFixed(2)}</p>
                </div>
            `);
            
            $('#selectedCodEmpleado').val(associate.cod_empleado);
            $('#searchResults').hide();
            
            // Mostrar botones de operación si tiene al menos una opción disponible y NO está liquidado
            const puedeRetirar = (associate.puede_retirar.aprobado && associate.statu !== 'L') || false;
            const puedePrestamo = (associate.puede_prestamo.aprobado && associate.statu !== 'L') || false;
            
            if ((puedeRetirar || puedePrestamo || puedeLiquidacion) && associate.statu !== 'L') {
                $('#operationButtons').show();
                
                // Configurar botones según disponibilidad
                $('#btnRetiro').toggle(puedeRetirar)
                    .prop('disabled', !puedeRetirar);
                
                $('#btnPrestamo').toggle(puedePrestamo)
                    .prop('disabled', !puedePrestamo);
                
                $('#btnLiquidacion').toggle(puedeLiquidacion)
                    .prop('disabled', !puedeLiquidacion);
            } else {
                $('#operationButtons').hide();
                if (associate.statu === 'L') {
                    Swal.fire('Información', 'Este asociado ya ha sido LIQUIDADO y no puede realizar operaciones.', 'info');
                } else {
                    Swal.fire('Información', 'Este asociado no cumple con los requisitos para ninguna operación', 'info');
                }
            }
            
            // Guardar datos del asociado para usar en los formularios
            currentAssociate = associate;
        }
        
        // Configurar botones de operación
        $('#btnRetiro').click(function() {
            if (!currentAssociate) return;
            
            $('#retiroForm').show();
            $('#prestamoForm').hide();
            $('#liquidacionForm').hide();
            
            // Configurar formulario de retiro
            $('#formCodEmpleadoRetiro').val(currentAssociate.cod_empleado);
            updateMaxAmountsForAssociate(currentAssociate.totalcaja);
            
            // Scroll al formulario
            $('html, body').animate({
                scrollTop: $('#retiroForm').offset().top - 20
            }, 500);
        });
        
        $('#btnPrestamo').click(function() {
            if (!currentAssociate) return;
            
            $('#prestamoForm').show();
            $('#retiroForm').hide();
            $('#liquidacionForm').hide();
            
            // Configurar formulario de préstamo
            $('#formCodEmpleadoPrestamo').val(currentAssociate.cod_empleado);
            updateMaxAmountsForAssociate(currentAssociate.totalcaja);
            
            // Scroll al formulario
            $('html, body').animate({
                scrollTop: $('#prestamoForm').offset().top - 20
            }, 500);
        });
        
        // NUEVO BOTÓN: Liquidación
        $('#btnLiquidacion').click(function() {
            if (!currentAssociate) return;
            
            $('#liquidacionForm').show();
            $('#retiroForm').hide();
            $('#prestamoForm').hide();
            
            // Configurar formulario de liquidación
            $('#formCodEmpleadoLiquidacion').val(currentAssociate.cod_empleado);
            $('#totalLiquidacion').text('Bs' + parseFloat(currentAssociate.totalcaja).toFixed(2));
            
            // Scroll al formulario
            $('html, body').animate({
                scrollTop: $('#liquidacionForm').offset().top - 20
            }, 500);
        });
        
        function cancelarOperacion() {
            $('#retiroForm').hide();
            $('#prestamoForm').hide();
            $('#liquidacionForm').hide();
        }
        
        // Función para actualizar montos máximos basados en el asociado seleccionado
        function updateMaxAmountsForAssociate(totalCaja) {
            const maxRetiro = parseFloat(totalCaja) * 0.75;
            const maxPrestamo = parseFloat(totalCaja) * 0.80;
            
            console.log("Total caja del asociado:", totalCaja);
            console.log("Máximo retiro:", maxRetiro);
            
            // Actualizar campo de retiro
            $('#monto_retiro').attr('max', maxRetiro);
            $('#maxRetiro').text('Bs' + maxRetiro.toFixed(2));
            
            // Actualizar campo de préstamo
            $('#monto_prestamo').attr('max', maxPrestamo);
            $('#maxPrestamo').text('Bs' + maxPrestamo.toFixed(2));
        }
        
        function setMaxRetiro() {
            const max = parseFloat($('#monto_retiro').attr('max'));
            $('#monto_retiro').val(max.toFixed(2));
        }
        
        function setMaxPrestamo() {
            const max = parseFloat($('#monto_prestamo').attr('max'));
            $('#monto_prestamo').val(max.toFixed(2));
            calcularCuota(); // Actualizar cálculo de cuota si está visible
        }
        
        // Formatear montos al salir del campo
        $('input[type="number"]').blur(function() {
            if ($(this).val() !== '') {
                $(this).val(parseFloat($(this).val()).toFixed(2));
            }
        });

        // Cargar datos del usuario actual al iniciar - SOLO INFORMACIÓN
        $(document).ready(function() {
            // Solo mostrar información del usuario logueado, pero no seleccionarlo automáticamente
            console.log('Usuario logueado:', {
                nombre: '<?php echo $usuario["nombre"]; ?>',
                rol: '<?php echo $rol_usuario; ?>'
            });
        });
        
        // Sincronizar año cuando cambia la fecha
        document.getElementById('fecha_retiro').addEventListener('change', function() {
            const fecha = new Date(this.value);
            document.getElementById('anio_retiro').value = fecha.getFullYear();
        });
        
        document.getElementById('fecha_prestamo').addEventListener('change', function() {
            const fecha = new Date(this.value);
            document.getElementById('anio_prestamo').value = fecha.getFullYear();
        });
        
        document.getElementById('fecha_liquidacion').addEventListener('change', function() {
            const fecha = new Date(this.value);
            document.getElementById('anio_liquidacion').value = fecha.getFullYear();
        });
        
        // Sincronizar fecha cuando cambia el año (aproximado)
        document.getElementById('anio_retiro').addEventListener('change', function() {
            const fechaInput = document.getElementById('fecha_retiro');
            const fecha = new Date(fechaInput.value);
            fecha.setFullYear(this.value);
            fechaInput.value = fecha.toISOString().split('T')[0];
        });
        
        document.getElementById('anio_prestamo').addEventListener('change', function() {
            const fechaInput = document.getElementById('fecha_prestamo');
            const fecha = new Date(fechaInput.value);
            fecha.setFullYear(this.value);
            fechaInput.value = fecha.toISOString().split('T')[0];
        });
        
        document.getElementById('anio_liquidacion').addEventListener('change', function() {
            const fechaInput = document.getElementById('fecha_liquidacion');
            const fecha = new Date(fechaInput.value);
            fecha.setFullYear(this.value);
            fechaInput.value = fecha.toISOString().split('T')[0];
        });
    </script>
</body>
</html>