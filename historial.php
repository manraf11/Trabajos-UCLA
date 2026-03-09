<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre'];

// Conexión a la base de datos
$host = 'localhost';
$dbname = 'CAPCEL';
$username = 'postgres';
$password = '123';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener parámetros de filtrado
$fechaInicio = isset($_GET['fechaInicio']) ? $_GET['fechaInicio'] : date('Y-m-01');
$fechaFin = isset($_GET['fechaFin']) ? $_GET['fechaFin'] : date('Y-m-d');
$tipoTransaccion = isset($_GET['tipoTransaccion']) ? $_GET['tipoTransaccion'] : '';
$idSocio = isset($_GET['idSocio']) ? $_GET['idSocio'] : '';

// Consulta para transacciones generales
$queryTransacciones = "SELECT 
    m.id_movimiento,
    m.fecha,
    m.tipo_movimiento,
    m.monto,
    m.descripcion,
    m.referencia,
    m.id_socio,
    a.nombre as nombre_socio,
    m.fecha_registro
FROM movimientos_contables m
LEFT JOIN asociados a ON m.id_socio = a.cod_empleado
WHERE m.fecha BETWEEN :fechaInicio AND :fechaFin";

$params = [
    ':fechaInicio' => $fechaInicio,
    ':fechaFin' => $fechaFin
];

if (!empty($tipoTransaccion)) {
    $queryTransacciones .= " AND m.tipo_movimiento = :tipoMovimiento";
    $params[':tipoMovimiento'] = $tipoTransaccion;
}

if (!empty($idSocio)) {
    $queryTransacciones .= " AND m.id_socio = :idSocio";
    $params[':idSocio'] = $idSocio;
}

$queryTransacciones .= " ORDER BY m.fecha DESC, m.id_movimiento DESC LIMIT 100";

$stmtTransacciones = $pdo->prepare($queryTransacciones);
$stmtTransacciones->execute($params);
$transacciones = $stmtTransacciones->fetchAll(PDO::FETCH_ASSOC);

// Consulta para aportes
$queryAportes = "SELECT 
    h.id_aporte,
    h.fecha_aporte as fecha,
    h.cod_empleado as id_socio,
    a.nombre as nombre_socio,
    h.monto_aporte as monto,
    h.tipo_aporte,
    h.forma_pago,
    h.referencia_pago as referencia,
    h.observaciones,
    h.estado_aporte as estado
FROM historial_aportes h
JOIN asociados a ON h.cod_empleado = a.cod_empleado
WHERE h.fecha_aporte BETWEEN :fechaInicio AND :fechaFin";

$paramsAportes = [
    ':fechaInicio' => $fechaInicio,
    ':fechaFin' => $fechaFin
];

if (!empty($idSocio)) {
    $queryAportes .= " AND h.cod_empleado = :idSocio";
    $paramsAportes[':idSocio'] = $idSocio;
}

$queryAportes .= " ORDER BY h.fecha_aporte DESC, h.id_aporte DESC LIMIT 100";

$stmtAportes = $pdo->prepare($queryAportes);
$stmtAportes->execute($paramsAportes);
$aportes = $stmtAportes->fetchAll(PDO::FETCH_ASSOC);

// Consulta para resumen
$queryResumen = "SELECT 
    SUM(CASE WHEN tipo_movimiento = 'aporte' THEN monto ELSE 0 END) as total_aportes,
    SUM(CASE WHEN tipo_movimiento = 'prestamo' THEN monto ELSE 0 END) as total_prestamos,
    SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
    SUM(CASE WHEN tipo_movimiento = 'egreso' THEN monto ELSE 0 END) as total_egresos
FROM movimientos_contables
WHERE fecha BETWEEN :fechaInicio AND :fechaFin";

$paramsResumen = [
    ':fechaInicio' => $fechaInicio,
    ':fechaFin' => $fechaFin
];

if (!empty($idSocio)) {
    $queryResumen .= " AND id_socio = :idSocio";
    $paramsResumen[':idSocio'] = $idSocio;
}

$stmtResumen = $pdo->prepare($queryResumen);
$stmtResumen->execute($paramsResumen);
$resumen = $stmtResumen->fetch(PDO::FETCH_ASSOC);

// Calcular saldo neto
$saldoNeto = ($resumen['total_aportes'] + $resumen['total_ingresos']) - ($resumen['total_prestamos'] + $resumen['total_egresos']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - Caja de Ahorro</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="shortcut icon" href=".//logo/capcel.png">

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
        
        /* --- Sidebar Styles --- */
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

        /* --- Content Wrapper --- */
        #page-content-wrapper {
            flex: 1;
            padding-left: 0;
            transition: padding-left 0.3s ease;
        }
        
        /* --- Sidebar Toggled State --- */
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

        /* --- Top Navbar --- */
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

        /* --- Main Content & Cards --- */
        .main-content {
            padding: 2rem;
        }
        
        .history-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1.5rem;
        }
        
        .history-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .history-table {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .history-table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .history-table td, .history-table th {
            vertical-align: middle;
        }
        
        .positive-amount {
            color: #28a745;
            font-weight: 600;
        }
        
        .negative-amount {
            color: #dc3545;
            font-weight: 600;
        }
        
        .history-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50rem;
        }
        
        .filter-section {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .history-summary {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
        
        .transaction-type {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
        }
        
        .transaction-details {
            flex-grow: 1;
        }
        
        .transaction-amount {
            text-align: right;
            min-width: 100px;
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
                
                <a href="historial.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-history"></i>Módulo Historial
                </a>
                 <a href="retiros_prestamos.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Retiros y Préstamos">
                    <i class="fas fa-hand-holding-usd"></i>Retiros y Préstamos
                </a>
                <a href="pagos.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-history me-2"></i> Historial de Transacciones</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-file-export me-2"></i>Exportar
                    </button>
                </div>
                
                <!-- Filtros -->
                <div class="filter-section">
                    <form method="get" action="historial.php">
                        <div class="row">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="fechaInicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="fechaFin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fechaFin" name="fechaFin" value="<?php echo htmlspecialchars($fechaFin); ?>">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="tipoTransaccion" class="form-label">Tipo de Transacción</label>
                                <select class="form-select" id="tipoTransaccion" name="tipoTransaccion">
                                    <option value="">Todos los tipos</option>
                                    <option value="aporte" <?php echo $tipoTransaccion == 'aporte' ? 'selected' : ''; ?>>Aporte</option>
                                    <option value="prestamo" <?php echo $tipoTransaccion == 'prestamo' ? 'selected' : ''; ?>>Préstamo</option>
                                    <option value="ingreso" <?php echo $tipoTransaccion == 'ingreso' ? 'selected' : ''; ?>>Ingreso</option>
                                    <option value="egreso" <?php echo $tipoTransaccion == 'egreso' ? 'selected' : ''; ?>>Egreso</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="idSocio" class="form-label">ID Socio (opcional)</label>
                                <input type="text" class="form-control" id="idSocio" name="idSocio" placeholder="Ej: 10025" value="<?php echo htmlspecialchars($idSocio); ?>">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2" id="aplicarFiltros">
                                    <i class="fas fa-filter me-2"></i>Aplicar Filtros
                                </button>
                                <a href="historial.php" class="btn btn-outline-secondary" id="limpiarFiltros">
                                    <i class="fas fa-broom me-2"></i>Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Resumen -->
                <div class="history-summary">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="summary-item">
                                <span>Total Aportes:</span>
                                <span class="positive-amount">Bs<?php echo number_format($resumen['total_aportes'], 2); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Total Egresos:</span>
                                <span class="negative-amount">Bs<?php echo number_format($resumen['total_egresos'], 2); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-item">
                                <span>Préstamos Otorgados:</span>
                                <span class="negative-amount">Bs<?php echo number_format($resumen['total_prestamos'], 2); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Otros Ingresos:</span>
                                <span class="positive-amount">Bs<?php echo number_format($resumen['total_ingresos'], 2); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-item">
                                <span>Transacciones:</span>
                                <span><?php echo count($transacciones); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Saldo Neto:</span>
                                <span class="<?php echo $saldoNeto >= 0 ? 'positive-amount' : 'negative-amount'; ?>">
                                    Bs<?php echo number_format(abs($saldoNeto), 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestañas de navegación -->
                <ul class="nav nav-tabs mb-3" id="historyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="transacciones-tab" data-bs-toggle="tab" data-bs-target="#transacciones" type="button" role="tab" aria-controls="transacciones" aria-selected="true">
                            <i class="fas fa-exchange-alt me-2"></i>Transacciones
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="aportes-tab" data-bs-toggle="tab" data-bs-target="#aportes" type="button" role="tab" aria-controls="aportes" aria-selected="false">
                            <i class="fas fa-hand-holding-usd me-2"></i>Aportes
                        </button>
                    </li>
                </ul>
                
                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="historyTabsContent">
                    <!-- Pestaña Transacciones -->
                    <div class="tab-pane fade show active" id="transacciones" role="tabpanel" aria-labelledby="transacciones-tab">
                        <div class="card history-card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h3 class="h5 mb-0">Listado de Transacciones</h3>
                                <span class="badge bg-primary"><?php echo count($transacciones); ?> transacciones</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover history-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>ID</th>
                                                <th>Descripción</th>
                                                <th>Tipo</th>
                                                <th>ID Socio</th>
                                                <th>Nombre</th>
                                                <th>Monto</th>
                                                <th>Referencia</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transacciones as $transaccion): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($transaccion['fecha'])); ?></td>
                                                <td>TRX-<?php echo $transaccion['id_movimiento']; ?></td>
                                                <td><?php echo htmlspecialchars($transaccion['descripcion']); ?></td>
                                                <td>
                                                    <?php 
                                                    $badgeClass = '';
                                                    switch($transaccion['tipo_movimiento']) {
                                                        case 'aporte': $badgeClass = 'bg-success'; break;
                                                        case 'prestamo': $badgeClass = 'bg-danger'; break;
                                                        case 'ingreso': $badgeClass = 'bg-primary'; break;
                                                        case 'egreso': $badgeClass = 'bg-warning text-dark'; break;
                                                        default: $badgeClass = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="history-badge <?php echo $badgeClass; ?>">
                                                        <?php echo ucfirst($transaccion['tipo_movimiento']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $transaccion['id_socio'] ?? '-'; ?></td>
                                                <td><?php echo $transaccion['nombre_socio'] ?? '-'; ?></td>
                                                <td class="<?php echo in_array($transaccion['tipo_movimiento'], ['aporte', 'ingreso']) ? 'positive-amount' : 'negative-amount'; ?>">
                                                    $<?php echo number_format($transaccion['monto'], 2); ?>
                                                </td>
                                                <td><?php echo $transaccion['referencia'] ?? '-'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" title="Ver Detalle" data-bs-toggle="modal" data-bs-target="#detalleModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Anterior</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pestaña Aportes -->
                    <div class="tab-pane fade" id="aportes" role="tabpanel" aria-labelledby="aportes-tab">
                        <div class="card history-card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h3 class="h5 mb-0">Historial de Aportes</h3>
                                <span class="badge bg-primary"><?php echo count($aportes); ?> aportes</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover history-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>ID Aporte</th>
                                                <th>ID Socio</th>
                                                <th>Nombre</th>
                                                <th>Monto</th>
                                                <th>Tipo</th>
                                                <th>Forma Pago</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aportes as $aporte): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($aporte['fecha'])); ?></td>
                                                <td>AP-<?php echo $aporte['id_aporte']; ?></td>
                                                <td><?php echo $aporte['id_socio']; ?></td>
                                                <td><?php echo htmlspecialchars($aporte['nombre_socio']); ?></td>
                                                <td class="positive-amount">Bs<?php echo number_format($aporte['monto'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($aporte['tipo_aporte']); ?></td>
                                                <td><?php echo htmlspecialchars($aporte['forma_pago']); ?></td>
                                                <td>
                                                    <?php 
                                                    $badgeClass = '';
                                                    switch($aporte['estado']) {
                                                        case 'Aplicado': $badgeClass = 'bg-success'; break;
                                                        case 'Pendiente': $badgeClass = 'bg-warning text-dark'; break;
                                                        case 'Anulado': $badgeClass = 'bg-danger'; break;
                                                        default: $badgeClass = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="history-badge <?php echo $badgeClass; ?>">
                                                        <?php echo $aporte['estado']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" title="Ver Detalle" data-bs-toggle="modal" data-bs-target="#detalleModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Anterior</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Últimas transacciones (vista compacta) -->
                <div class="card history-card">
                    <div class="card-header bg-white">
                        <h3 class="h5 mb-0">Últimas Transacciones</h3>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $contador = 0;
                            foreach ($transacciones as $transaccion): 
                                if ($contador >= 3) break;
                                $contador++;
                                
                                $iconClass = '';
                                $bgClass = '';
                                switch($transaccion['tipo_movimiento']) {
                                    case 'aporte': 
                                        $iconClass = 'fas fa-hand-holding-usd';
                                        $bgClass = 'bg-success';
                                        break;
                                    case 'prestamo': 
                                        $iconClass = 'fas fa-file-invoice-dollar';
                                        $bgClass = 'bg-danger';
                                        break;
                                    case 'ingreso': 
                                        $iconClass = 'fas fa-money-bill-wave';
                                        $bgClass = 'bg-primary';
                                        break;
                                    case 'egreso': 
                                        $iconClass = 'fas fa-share-square';
                                        $bgClass = 'bg-warning';
                                        break;
                                    default: 
                                        $iconClass = 'fas fa-exchange-alt';
                                        $bgClass = 'bg-secondary';
                                }
                            ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="transaction-type <?php echo $bgClass; ?>">
                                        <i class="<?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="transaction-details">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($transaccion['descripcion']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($transaccion['fecha'])); ?> - 
                                            ID: TRX-<?php echo $transaccion['id_movimiento']; ?>
                                        </small>
                                    </div>
                                    <div class="transaction-amount <?php echo in_array($transaccion['tipo_movimiento'], ['aporte', 'ingreso']) ? 'positive-amount' : 'negative-amount'; ?>">
                                        Bs<?php echo number_format($transaccion['monto'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Exportar -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel"><i class="fas fa-file-export me-2"></i>Exportar Historial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm" method="post" action="exportar_historial.php">
                        <input type="hidden" name="fechaInicio" value="<?php echo $fechaInicio; ?>">
                        <input type="hidden" name="fechaFin" value="<?php echo $fechaFin; ?>">
                        <input type="hidden" name="tipoTransaccion" value="<?php echo $tipoTransaccion; ?>">
                        <input type="hidden" name="idSocio" value="<?php echo $idSocio; ?>">
                        
                        <div class="mb-3">
                            <label for="exportFormat" class="form-label">Formato de Exportación</label>
                            <select class="form-select" id="exportFormat" name="exportFormat">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                                <option value="pdf">PDF (.pdf)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exportRange" class="form-label">Rango de Datos</label>
                            <select class="form-select" id="exportRange" name="exportRange">
                                <option value="current">Resultados actuales (filtrados)</option>
                                <option value="all">Todos los registros</option>
                                <option value="custom">Personalizado</option>
                            </select>
                        </div>
                        <div class="row mb-3" id="customRangeFields" style="display: none;">
                            <div class="col-md-6">
                                <label for="exportStartDate" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="exportStartDate" name="exportStartDate">
                            </div>
                            <div class="col-md-6">
                                <label for="exportEndDate" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="exportEndDate" name="exportEndDate">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Columnas a Incluir</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colFecha" name="columns[]" value="fecha" checked>
                                <label class="form-check-label" for="colFecha">Fecha</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colId" name="columns[]" value="id" checked>
                                <label class="form-check-label" for="colId">ID Transacción</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colDesc" name="columns[]" value="descripcion" checked>
                                <label class="form-check-label" for="colDesc">Descripción</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colTipo" name="columns[]" value="tipo" checked>
                                <label class="form-check-label" for="colTipo">Tipo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colSocio" name="columns[]" value="socio" checked>
                                <label class="form-check-label" for="colSocio">ID Socio</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colMonto" name="columns[]" value="monto" checked>
                                <label class="form-check-label" for="colMonto">Monto</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colEstado" name="columns[]" value="estado" checked>
                                <label class="form-check-label" for="colEstado">Estado</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="exportForm" class="btn btn-primary" id="confirmExport">
                        <i class="fas fa-download me-2"></i>Exportar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalleModalLabel"><i class="fas fa-info-circle me-2"></i>Detalle de Transacción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Información de la Transacción</h6>
                            <div class="mb-3">
                                <label class="form-label">ID Transacción:</label>
                                <p class="fw-bold" id="detalleId">TRX-12345</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fecha:</label>
                                <p class="fw-bold" id="detalleFecha">15/07/2023</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo:</label>
                                <p class="fw-bold" id="detalleTipo"><span class="history-badge bg-success">Aporte</span></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Información Financiera</h6>
                            <div class="mb-3">
                                <label class="form-label">Monto:</label>
                                <p class="fw-bold positive-amount" id="detalleMonto">Bs1,250.00</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Referencia:</label>
                                <p class="fw-bold" id="detalleReferencia">Nómina Julio 2023</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estado:</label>
                                <p class="fw-bold" id="detalleEstado"><span class="history-badge bg-success">Completado</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Información del Socio</h6>
                            <div class="mb-3">
                                <label class="form-label">ID Socio:</label>
                                <p class="fw-bold" id="detalleSocioId">10025</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre:</label>
                                <p class="fw-bold" id="detalleSocioNombre">Juan Pérez</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Saldo Actual:</label>
                                <p class="fw-bold positive-amount" id="detalleSocioSaldo">Bs15,780.00</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Documentos Adjuntos</h6>
                            <div class="mb-3">
                                <label class="form-label">Comprobante:</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-pdf me-2 text-danger" style="font-size: 1.5rem;"></i>
                                    <a href="#" class="me-2" id="detalleComprobante">comprobante_10025.pdf</a>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Descripción</h6>
                        <p class="fw-light" id="detalleDescripcion">Aporte correspondiente al mes de julio 2023, procesado automáticamente desde nómina.</p>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="text-muted">Notas Adicionales</h6>
                        <div class="alert alert-light">
                            <p class="mb-0" id="detalleNotas">Ninguna nota adicional registrada.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // --- Script para colapsar el menú lateral ---
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("sidebar-collapsed");
            });

            // --- Activar tooltips de Bootstrap ---
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // --- Script para marcar como activo el link del menú ---
            $('.list-group-item').on('click', function() {
                $('.list-group-item').removeClass('active');
                $(this).addClass('active');
            });
            
            // Mostrar/ocultar campos de rango personalizado
            $('#exportRange').change(function() {
                if ($(this).val() === 'custom') {
                    $('#customRangeFields').show();
                } else {
                    $('#customRangeFields').hide();
                }
            });
            
            // Manejar clic en botones de detalle
            $('[data-bs-target="#detalleModal"]').click(function() {
                // Obtener datos de la fila
                var row = $(this).closest('tr');
                var tipo = row.find('td:nth-child(4)').text().trim();
                var monto = row.find('td:nth-child(7)').text().trim();
                var montoClass = row.find('td:nth-child(7)').attr('class');
                
                // Actualizar modal con datos de ejemplo (en producción, obtendrías estos datos de la BD)
                $('#detalleId').text('TRX-' + row.find('td:nth-child(2)').text().replace('TRX-', ''));
                $('#detalleFecha').text(row.find('td:nth-child(1)').text());
                $('#detalleTipo').html(row.find('td:nth-child(4)').html());
                $('#detalleMonto').text(monto).attr('class', 'fw-bold ' + montoClass);
                $('#detalleReferencia').text(row.find('td:nth-child(8)').text());
                $('#detalleEstado').html('<span class="history-badge bg-success">Completado</span>');
                $('#detalleSocioId').text(row.find('td:nth-child(5)').text());
                $('#detalleSocioNombre').text(row.find('td:nth-child(6)').text());
                $('#detalleDescripcion').text(row.find('td:nth-child(3)').text());
                
                // Simular saldo actual (en producción, esto vendría de una consulta a la BD)
                var saldoInicial = 15000;
                var saldoActual = tipo.includes('Aporte') || tipo.includes('Ingreso') ? 
                    saldoInicial + parseFloat(monto.replace('$', '').replace(',', '')) : 
                    saldoInicial - parseFloat(monto.replace('$', '').replace(',', ''));
                
                $('#detalleSocioSaldo').text('$' + saldoActual.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","))
                    .toggleClass('positive-amount', saldoActual >= 0)
                    .toggleClass('negative-amount', saldoActual < 0);
            });
            
            // Manejar exportación
            $('#exportForm').submit(function(e) {
                e.preventDefault();
                $('#exportModal').modal('hide');
                
                // Mostrar notificación de éxito
                Swal.fire({
                    title: 'Exportando datos',
                    html: 'Preparando archivo para descarga...',
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                        // Simular tiempo de exportación
                        setTimeout(() => {
                            // En producción, esto sería una solicitud AJAX o el formulario se enviaría normalmente
                            Swal.fire({
                                icon: 'success',
                                title: 'Exportación completada',
                                text: 'Los datos se han exportado correctamente',
                                confirmButtonText: 'Aceptar'
                            });
                            
                            // Forzar la descarga (en producción, esto lo manejaría el servidor)
                            window.location.href = 'exportar_historial.php?' + $(this).serialize();
                        }, 2000);
                    }
                });
            });
            
            // Inicializar tooltips para botones de acción
            $('[title]').tooltip();
            
            // Manejar clic en paginación
            $('.page-link').click(function(e) {
                e.preventDefault();
                if (!$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                    // Mostrar spinner de carga
                    var cardBody = $(this).closest('.card-footer').siblings('.card-body');
                    cardBody.prepend(
                        '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>'
                    );
                    
                    // Simular carga de nueva página
                    setTimeout(() => {
                        cardBody.find('.spinner-border').remove();
                    }, 800);
                }
            });
        });
    </script>
</body>
</html>