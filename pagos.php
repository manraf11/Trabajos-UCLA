<?php
// Configuración de la base de datos
$host = "localhost";
$dbname = "CAPCEL";
$user = "postgres";
$password = "123";

// Establecer conexión
try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Iniciar sesión
session_start();
$user = $_SESSION['user'];
$nombre_usuario = $user['nombre']; // Obtenemos el nombre de la sesión

// Verificar autenticación y roles
if (!isset($_SESSION['cod_empleado'])) {
    // Simulación de autenticación - en producción usar sistema real
    $_SESSION['cod_empleado'] = '55090';
    $_SESSION['nombre_usuario'] = 'Administrador';
    $_SESSION['es_admin'] = true;
    $_SESSION['roles'] = ['admin']; // Puede ser admin, presidente, tesorero, secretario
}

// Verificar si el usuario tiene permisos para aprobar
function puedeAprobar() {
    return !empty(array_intersect($_SESSION['roles'] ?? [], ['admin', 'presidente', 'tesorero', 'secretario']));
}

// Obtener datos del empleado con validación
$empleado = [];
try {
    $stmt = $conn->prepare("SELECT * FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$_SESSION['cod_empleado']]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        die("Error: No se encontró el empleado con código " . $_SESSION['cod_empleado']);
    }
} catch (PDOException $e) {
    die("Error al obtener datos del empleado: " . $e->getMessage());
}



// Procesar formulario de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    try {
        // Validar datos
        $id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
        $monto_pago = filter_input(INPUT_POST, 'monto_pago', FILTER_VALIDATE_FLOAT);
        $metodo_pago = htmlspecialchars($_POST['metodo_pago']);
        $referencia = htmlspecialchars($_POST['referencia_pago'] ?? '');
        $observaciones = htmlspecialchars($_POST['observaciones'] ?? '');
        
        if (!$id_prestamo || !$monto_pago || !$metodo_pago) {
            throw new Exception("Datos de pago inválidos");
        }
        
        // Obtener información del préstamo
        $stmt = $conn->prepare("SELECT cod_empleado, saldo_pendiente FROM historico_prestamos WHERE id = ?");
        $stmt->execute([$id_prestamo]);
        $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prestamo) {
            throw new Exception("Préstamo no encontrado");
        }
        
        if ($monto_pago > $prestamo['saldo_pendiente']) {
            throw new Exception("El monto del pago excede el saldo pendiente");
        }
        
        // Registrar el pago
        $stmt = $conn->prepare("INSERT INTO registro_pagos (
            id_prestamo, cod_empleado, monto_pago, fecha_pago, metodo_pago, 
            referencia_pago, observaciones, usuario_registro
        ) VALUES (?, ?, ?, CURRENT_DATE, ?, ?, ?, ?)");
        
        $stmt->execute([
            $id_prestamo,
            $prestamo['cod_empleado'],
            $monto_pago,
            $metodo_pago,
            $referencia,
            $observaciones,
            $_SESSION['nombre_usuario']
        ]);
        
        $_SESSION['mensaje'] = "Pago registrado exitosamente";
        header("Location: pagos_prestamos.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Obtener préstamos activos del empleado (o todos si es admin)
if ($_SESSION['es_admin']) {
    $stmt = $conn->prepare("SELECT p.id, p.cod_empleado, a.nombre, p.monto_aprobado, 
                           p.saldo_pendiente, p.cuota_mensual, p.fecha_vencimiento
                           FROM historico_prestamos p
                           JOIN asociados a ON p.cod_empleado = a.cod_empleado
                           WHERE p.estado = 'Aprobado' AND p.saldo_pendiente > 0
                           ORDER BY p.fecha_vencimiento ASC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT id, monto_aprobado, saldo_pendiente, cuota_mensual, fecha_vencimiento
                           FROM historico_prestamos
                           WHERE cod_empleado = ? AND estado = 'Aprobado' AND saldo_pendiente > 0
                           ORDER BY fecha_vencimiento ASC");
    $stmt->execute([$_SESSION['cod_empleado']]);
}
$prestamos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial de pagos recientes
if ($_SESSION['es_admin']) {
    $stmt = $conn->prepare("SELECT r.*, a.nombre, p.monto_aprobado
                           FROM registro_pagos r
                           JOIN asociados a ON r.cod_empleado = a.cod_empleado
                           JOIN historico_prestamos p ON r.id_prestamo = p.id
                           ORDER BY r.fecha_pago DESC, r.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT r.*, p.monto_aprobado
                           FROM registro_pagos r
                           JOIN historico_prestamos p ON r.id_prestamo = p.id
                           WHERE r.cod_empleado = ?
                           ORDER BY r.fecha_pago DESC, r.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute([$_SESSION['cod_empleado']]);
}
$historial_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Pagos de Préstamos - Caja de Ahorro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" href=".//logo/capcel.png">
    <style>
        /* Estilos consistentes con el módulo anterior */
        :root {
        --primary-color: #0056b3;
        --secondary-color: #007bff;
        --light-gray: #f8f9fa;
        --dark-gray: #343a40;
        --background-color: #f0f2f5;
        --sidebar-width: 260px; /* Ajustado para coincidir con contabilidad */
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

    #page-content-wrapper {
        flex: 1;
        padding-left: 0;
        transition: padding-left 0.3s ease;
    }
    
    @media (min-width: 768px) {
        #wrapper:not(.sidebar-collapsed) #page-content-wrapper {
            padding-left: var(--sidebar-width);
        }
    }

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

    .main-content {
        padding: 2rem;
        max-width: 100%;
        margin: 0 auto;
        width: 100%;
    }
    
    .members-card {
        width: 100%;
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 1.5rem;
    }
    
    .members-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .members-table {
        width: 100%;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    
    .members-table th {
        background-color: var(--primary-color);
        color: white;
    }
    
    .members-table td, .members-table th {
        vertical-align: middle;
    }
    
    .member-status {
        font-size: 0.8rem;
        padding: 0.35rem 0.75rem;
        border-radius: 50rem;
    }
    
    .filter-section {
        width: 100%;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .member-summary {
        width: 100%;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .member-summary .row {
        margin-left: 0;
        margin-right: 0;
    }
    
    .member-summary .col-md-3 {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .summary-item:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 1rem;
    }
    
    .member-actions .btn {
        margin-right: 5px;
    }
    
    .quick-actions {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    /* Modal más ancho para coincidir con contabilidad */
    .modal-lg {
        max-width: 900px;
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
                 <a href="retiros_prestamos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Retiros y Préstamos">
                    <i class="fas fa-hand-holding-usd"></i>Retiros y Préstamos
                </a>
                <a href="pagos.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-calculator"></i>Pagos
                    </a>
                    <a href="colaboraciones_creditos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Colaboraciones y Créditos">
                    <i class="fas fa-handshake"></i>Colaboraciones y Créditos
                </a>
                <a href="consulta.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Consulta">
                    <i class="fas fa-search-dollar"></i>Módulo de Consulta
                </a>
                <a href="carga.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Carga de Datos">
                    <i class="fas fa-upload"></i>Carga Masiva e Individual
                </a>
                <a href="socios.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Gestión de Socios">
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
                <h1 class="mb-4">Registro de Pagos de Préstamos</h1>
                
                <?php if (!empty($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['mensaje']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <?php unset($_SESSION['mensaje']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Registrar Nuevo Pago</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" id="formPago">
                                    <div class="mb-3">
                                        <label for="id_prestamo" class="form-label">Préstamo</label>
                                        <select class="form-control" id="id_prestamo" name="id_prestamo" required>
                                            <option value="">Seleccione un préstamo</option>
                                            <?php foreach ($prestamos_activos as $prestamo): ?>
                                                <option value="<?= $prestamo['id'] ?>" 
                                                    data-saldo="<?= $prestamo['saldo_pendiente'] ?>"
                                                    data-cuota="<?= $prestamo['cuota_mensual'] ?>">
                                                    <?= htmlspecialchars($_SESSION['es_admin'] ? $prestamo['cod_empleado'].' - '.$prestamo['nombre'] : 'Préstamo #'.$prestamo['id']) ?>
                                                    - Saldo: Bs<?= number_format($prestamo['saldo_pendiente'], 2) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 currency-input">
                                        <label for="monto_pago" class="form-label">Monto del Pago</label>
                                        <input type="number" step="0.01" class="form-control" id="monto_pago" 
                                               name="monto_pago" min="0.01" required>
                                        <small class="form-text text-muted">
                                            Saldo disponible: <span id="saldo-disponible">Bs0.00</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCuota">
                                                Usar cuota mensual
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSaldoTotal">
                                                Pagar saldo total
                                            </button>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="metodo_pago" class="form-label">Método de Pago</label>
                                        <select class="form-control" id="metodo_pago" name="metodo_pago" required>
                                            <option value="">Seleccione método de pago</option>
                                            <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                            <option value="Descuento de Nómina">Descuento de Nómina</option>
                                            <option value="Domiciliación Bancaria">Domiciliación Bancaria</option>
                                            <option value="Descuento Bono Vacacional">Descuento Bono Vacacional</option>
                                            <option value="Descuento Bono Fin de Año">Descuento Bono Fin de Año</option>
                                            <option value="Efectivo">Efectivo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="referenciaContainer" style="display: none;">
                                        <label for="referencia_pago" class="form-label">Número de Referencia/Comprobante</label>
                                        <input type="text" class="form-control" id="referencia_pago" name="referencia_pago">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="observaciones" class="form-label">Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="registrar_pago" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Registrar Pago
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Información del Préstamo</h5>
                            </div>
                            <div class="card-body" id="prestamoInfo">
                                <div class="alert alert-info">
                                    Seleccione un préstamo para ver los detalles
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de Pagos Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial_pagos)): ?>
                            <div class="alert alert-info">No hay registros de pagos</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <?php if ($_SESSION['es_admin']): ?>
                                                <th>Empleado</th>
                                            <?php endif; ?>
                                            <th>Préstamo</th>
                                            <th>Monto</th>
                                            <th>Método</th>
                                            <th>Referencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial_pagos as $pago): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($pago['fecha_pago']) ?></td>
                                                <?php if ($_SESSION['es_admin']): ?>
                                                    <td><?= htmlspecialchars($pago['nombre'] ?? $pago['cod_empleado']) ?></td>
                                                <?php endif; ?>
                                                <td>#<?= $pago['id_prestamo'] ?> (Bs<?= number_format($pago['monto_aprobado'], 2) ?>)</td>
                                                <td>Bs<?= number_format($pago['monto_pago'], 2) ?></td>
                                                <td>
                                                    <span class="badge badge-pago 
                                                        metodo-<?= strtolower(str_replace(' ', '-', $pago['metodo_pago'])) ?>">
                                                        <?= htmlspecialchars($pago['metodo_pago']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($pago['referencia_pago'] ?? 'N/A') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mostrar/ocultar campo de referencia según método de pago
            $('#metodo_pago').change(function() {
                const metodo = $(this).val();
                const requiereReferencia = ['Transferencia Bancaria', 'Domiciliación Bancaria'].includes(metodo);
                $('#referenciaContainer').toggle(requiereReferencia);
                if (requiereReferencia) {
                    $('#referencia_pago').prop('required', true);
                } else {
                    $('#referencia_pago').prop('required', false).val('');
                }
            });
            
            // Actualizar información del préstamo seleccionado
            $('#id_prestamo').change(function() {
                const option = $(this).find('option:selected');
                const saldo = option.data('saldo') || 0;
                const cuota = option.data('cuota') || 0;
                
                $('#saldo-disponible').text('Bs' + saldo.toFixed(2));
                $('#monto_pago').attr('max', saldo);
                
                // Mostrar información del préstamo
                if (option.val()) {
                    $.ajax({
                        url: 'obtener_info_prestamo.php',
                        type: 'POST',
                        data: { id_prestamo: option.val() },
                        dataType: 'html',
                        success: function(response) {
                            $('#prestamoInfo').html(response);
                        },
                        error: function() {
                            $('#prestamoInfo').html('<div class="alert alert-danger">Error al cargar información</div>');
                        }
                    });
                } else {
                    $('#prestamoInfo').html('<div class="alert alert-info">Seleccione un préstamo para ver los detalles</div>');
                }
            });
            
            // Botón para usar la cuota mensual como monto
            $('#btnCuota').click(function() {
                const option = $('#id_prestamo').find('option:selected');
                const cuota = option.data('cuota') || 0;
                const saldo = option.data('saldo') || 0;
                
                if (!option.val()) {
                    Swal.fire('Error', 'Seleccione un préstamo primero', 'error');
                    return;
                }
                
                const monto = Math.min(cuota, saldo);
                $('#monto_pago').val(monto.toFixed(2));
            });
            
            // Botón para usar el saldo total como monto
            $('#btnSaldoTotal').click(function() {
                const option = $('#id_prestamo').find('option:selected');
                const saldo = option.data('saldo') || 0;
                
                if (!option.val()) {
                    Swal.fire('Error', 'Seleccione un préstamo primero', 'error');
                    return;
                }
                
                $('#monto_pago').val(saldo.toFixed(2));
            });
            
            // Validación del formulario
            $('#formPago').submit(function(e) {
                const monto = parseFloat($('#monto_pago').val()) || 0;
                const saldo = parseFloat($('#saldo-disponible').text().replace('Bs', '')) || 0;
                
                if (monto <= 0) {
                    Swal.fire('Error', 'El monto debe ser mayor a cero', 'error');
                    return false;
                }
                
                if (monto > saldo) {
                    Swal.fire('Error', 'El monto no puede ser mayor al saldo pendiente', 'error');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>