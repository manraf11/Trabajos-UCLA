<?php
require_once 'config.php'; // Incluir configuración de la base de datos

if (!isset($_POST['id_prestamo'])) {
    die('<div class="alert alert-danger">No se especificó el préstamo</div>');
}

$id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
if (!$id_prestamo) {
    die('<div class="alert alert-danger">ID de préstamo inválido</div>');
}

try {
    $stmt = $conn->prepare("SELECT 
        p.id, p.cod_empleado, a.nombre, a.cedula,
        p.monto_aprobado, p.saldo_pendiente, p.cuota_mensual,
        p.fecha_solicitud, p.fecha_aprobacion, p.fecha_vencimiento,
        p.plazo_meses, p.interes_anual, p.total_a_pagar,
        COUNT(r.id_pago) as pagos_realizados,
        COALESCE(SUM(r.monto_pago), 0) as total_pagado
        FROM historico_prestamos p
        JOIN asociados a ON p.cod_empleado = a.cod_empleado
        LEFT JOIN registro_pagos r ON p.id = r.id_prestamo
        WHERE p.id = ?
        GROUP BY p.id, a.nombre, a.cedula");
    $stmt->execute([$id_prestamo]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prestamo) {
        die('<div class="alert alert-danger">Préstamo no encontrado</div>');
    }
    
    // Calcular información adicional
    $porcentaje_pagado = ($prestamo['total_pagado'] / $prestamo['total_a_pagar']) * 100;
    $dias_restantes = (new DateTime($prestamo['fecha_vencimiento']))->diff(new DateTime())->days;
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<p><strong>Empleado:</strong> ' . htmlspecialchars($prestamo['nombre']) . '</p>';
    echo '<p><strong>Cédula:</strong> ' . htmlspecialchars($prestamo['cedula']) . '</p>';
    echo '<p><strong>Código:</strong> ' . htmlspecialchars($prestamo['cod_empleado']) . '</p>';
    echo '<p><strong>Fecha Aprobación:</strong> ' . htmlspecialchars($prestamo['fecha_aprobacion']) . '</p>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<p><strong>Monto Aprobado:</strong> Bs' . number_format($prestamo['monto_aprobado'], 2) . '</p>';
    echo '<p><strong>Saldo Pendiente:</strong> Bs' . number_format($prestamo['saldo_pendiente'], 2) . '</p>';
    echo '<p><strong>Cuota Mensual:</strong> Bs' . number_format($prestamo['cuota_mensual'], 2) . '</p>';
    echo '<p><strong>Fecha Vencimiento:</strong> ' . htmlspecialchars($prestamo['fecha_vencimiento']) . ' (' . $dias_restantes . ' días)</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="progress mt-3 mb-3">';
    echo '<div class="progress-bar" role="progressbar" style="width: ' . $porcentaje_pagado . '%;" ';
    echo 'aria-valuenow="' . $porcentaje_pagado . '" aria-valuemin="0" aria-valuemax="100">';
    echo round($porcentaje_pagado, 1) . '% pagado</div>';
    echo '</div>';
    
    echo '<p><strong>Pagos realizados:</strong> ' . $prestamo['pagos_realizados'] . '</p>';
    echo '<p><strong>Total pagado:</strong> Bs' . number_format($prestamo['total_pagado'], 2) . '</p>';
    echo '<p><strong>Interés anual:</strong> ' . $prestamo['interes_anual'] . '%</p>';
    echo '<p><strong>Plazo:</strong> ' . $prestamo['plazo_meses'] . ' meses</p>';
    
} catch (PDOException $e) {
    die('<div class="alert alert-danger">Error al obtener información: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>