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
require_once 'db_connection.php';

// Inicializar variable $rango con valores por defecto
$rango = [
    'fecha_inicio' => null,
    'fecha_fin' => null
];

// Obtener el período contable actual
$periodo_actual = null;
try {
    $stmt = $pdo->query("SELECT * FROM periodos_contables WHERE cerrado = false ORDER BY fecha_inicio DESC LIMIT 1");
    $periodo_actual = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al obtener el período contable: ' . $e->getMessage()];
}

// Procesar filtros
$filtros = [
    'periodo' => $periodo_actual ? $periodo_actual['id_periodo'] : null,
    'tipo_movimiento' => 'todos'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['filtros'])) {
        $filtros['periodo'] = $_POST['periodo'];
        $filtros['tipo_movimiento'] = $_POST['tipo_movimiento'];
    }
    
    // Procesar nuevo movimiento
    if (isset($_POST['nuevo_movimiento'])) {
        try {
            $pdo->beginTransaction();
            
            // Insertar en movimientos_contables
            $stmt = $pdo->prepare("INSERT INTO movimientos_contables 
                (fecha, tipo_movimiento, id_cuenta_debito, id_cuenta_credito, monto, descripcion, referencia, id_usuario, id_socio) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['fecha'],
                $_POST['tipo_movimiento'],
                $_POST['tipo_movimiento'] === 'ingreso' ? $_POST['cuenta_destino'] : $_POST['cuenta_origen'],
                $_POST['tipo_movimiento'] === 'ingreso' ? null : $_POST['cuenta_destino'],
                $_POST['monto'],
                $_POST['descripcion'],
                $_POST['referencia'],
                $user['id'],
                $_POST['id_socio'] ?? null
            ]);
            
            // Si es un aporte, registrar también en historial_aportes
            if ($_POST['tipo_movimiento'] === 'aporte' && !empty($_POST['id_socio'])) {
                $stmtAporte = $pdo->prepare("INSERT INTO historial_aportes 
                    (cod_empleado, fecha_aporte, periodo_aporte, monto_aporte, tipo_aporte, forma_pago, referencia_pago, usuario_registro, estado_aporte)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $periodo = date('Y-m', strtotime($_POST['fecha']));
                
                $stmtAporte->execute([
                    $_POST['id_socio'],
                    $_POST['fecha'],
                    $periodo,
                    $_POST['monto'],
                    'ordinario',
                    $_POST['forma_pago'] ?? 'efectivo',
                    $_POST['referencia'],
                    $user['nombre'],
                    'Aplicado'
                ]);
                
                // Actualizar saldo del socio en la tabla asociados
                $pdo->exec("UPDATE asociados SET totalaport = totalaport + {$_POST['monto']} 
                            WHERE cod_empleado = '{$_POST['id_socio']}'");
            }
            
            // Actualizar saldos de cuentas contables
            if ($_POST['tipo_movimiento'] === 'ingreso') {
                $pdo->exec("UPDATE cuentas_contables SET saldo_actual = saldo_actual + {$_POST['monto']} 
                            WHERE id_cuenta = {$_POST['cuenta_destino']}");
            } else {
                $pdo->exec("UPDATE cuentas_contables SET saldo_actual = saldo_actual - {$_POST['monto']} 
                            WHERE id_cuenta = {$_POST['cuenta_origen']}");
                if ($_POST['cuenta_destino']) {
                    $pdo->exec("UPDATE cuentas_contables SET saldo_actual = saldo_actual + {$_POST['monto']} 
                                WHERE id_cuenta = {$_POST['cuenta_destino']}");
                }
            }
            
            $pdo->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Movimiento registrado correctamente'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al registrar el movimiento: ' . $e->getMessage()];
        }
        
        header('Location: contabilidad.php');
        exit;
    }
}

// Obtener movimientos contables
$movimientos = [];
$resumen = [
    'total_ingresos' => 0,
    'total_egresos' => 0,
    'saldo_periodo' => 0,
    'saldo_acumulado' => 0
];

try {
    // Consulta para movimientos
    $sql = "SELECT mc.*, 
                   cd.codigo_cuenta as cuenta_debito_codigo, cd.nombre_cuenta as cuenta_debito_nombre,
                   cc.codigo_cuenta as cuenta_credito_codigo, cc.nombre_cuenta as cuenta_credito_nombre,
                   u.nombre as usuario_nombre,
                   s.nombreemp as socio_nombre
            FROM movimientos_contables mc
            LEFT JOIN cuentas_contables cd ON mc.id_cuenta_debito = cd.id_cuenta
            LEFT JOIN cuentas_contables cc ON mc.id_cuenta_credito = cc.id_cuenta
            LEFT JOIN usuarios u ON mc.id_usuario = u.id
            LEFT JOIN socios s ON mc.id_socio = s.codigoemp
            WHERE 1=1";
    
    $params = [];
    
    if ($filtros['periodo']) {
        $periodo = $pdo->prepare("SELECT fecha_inicio, fecha_fin FROM periodos_contables WHERE id_periodo = ?");
        if ($periodo->execute([$filtros['periodo']])) {
            $rango = $periodo->fetch(PDO::FETCH_ASSOC) ?: $rango;
            
            if (!empty($rango['fecha_inicio'])) {
                $sql .= " AND mc.fecha BETWEEN ? AND ?";
                $params[] = $rango['fecha_inicio'];
                $params[] = $rango['fecha_fin'];
            }
        }
    }
    
    if ($filtros['tipo_movimiento'] !== 'todos') {
        $sql .= " AND mc.tipo_movimiento = ?";
        $params[] = $filtros['tipo_movimiento'];
    }
    
    $sql .= " ORDER BY mc.fecha DESC, mc.id_movimiento DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular resumen
    $resumen_query_sql = "SELECT 
        SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
        SUM(CASE WHEN tipo_movimiento = 'egreso' THEN monto ELSE 0 END) as total_egresos,
        (SELECT SUM(saldo_actual) FROM cuentas_contables WHERE tipo_cuenta IN ('activo', 'patrimonio', 'ingreso')) as saldo_acumulado
    FROM movimientos_contables";
    
    if ($filtros['periodo'] && !empty($rango['fecha_inicio'])) {
        $resumen_query_sql .= " WHERE fecha BETWEEN '{$rango['fecha_inicio']}' AND '{$rango['fecha_fin']}'";
    }
    
    $resumen_query = $pdo->query($resumen_query_sql);
    $resumen = $resumen_query->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumen['saldo_periodo'] = ($resumen['total_ingresos'] ?? 0) - ($resumen['total_egresos'] ?? 0);
    
} catch (PDOException $e) {
    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al obtener movimientos: ' . $e->getMessage()];
}

// Obtener cuentas contables
$cuentas = $pdo->query("SELECT * FROM cuentas_contables WHERE activa = true ORDER BY codigo_cuenta")->fetchAll(PDO::FETCH_ASSOC);

// Obtener períodos contables
$periodos = $pdo->query("SELECT * FROM periodos_contables ORDER BY fecha_inicio DESC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener socios (asociados)
$socios = $pdo->query("SELECT cod_empleado as codigoemp, nombre FROM asociados WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Contabilidad - Caja de Ahorro</title>
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
        }
        
        .summary-card, .module-card, .accounting-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .summary-card:hover, .module-card:hover, .accounting-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .summary-card .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .summary-card .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
        }
        
        .module-card .card-body, .accounting-card .card-body {
            text-align: center;
        }
        
        .module-card .module-icon, .accounting-card .module-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .module-card .card-title, .accounting-card .card-title {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .accounting-table {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .accounting-table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .accounting-table td, .accounting-table th {
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
        
        .accounting-actions .btn {
            margin-right: 5px;
        }
        
        .accounting-period-selector {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .accounting-summary {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .accounting-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .accounting-summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
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
                <a href="#contabilidad" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Contabilidad">
                    <i class="fas fa-calculator"></i>Módulo de Contabilidad
                </a>
                <a href="historial.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-history"></i>Módulo Historial</a>
                     <a href="retiros_prestamos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Retiros y Préstamos">
                    <i class="fas fa-hand-holding-usd"></i>Retiros y Préstamos
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
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?= $_SESSION['mensaje']['tipo'] ?> alert-dismissible fade show">
                        <?= $_SESSION['mensaje']['texto'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['mensaje']); ?>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-calculator me-2"></i> Módulo de Contabilidad</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoMovimientoModal">
                        <i class="fas fa-plus me-2"></i>Nuevo Movimiento
                    </button>
                </div>
                
                <!-- Selector de período -->
                <div class="accounting-period-selector">
                    <form method="post">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="periodo" class="form-label">Seleccionar Período:</label>
                                <select class="form-select" id="periodo" name="periodo">
                                    <?php foreach ($periodos as $periodo): ?>
                                        <option value="<?= $periodo['id_periodo'] ?>" <?= $filtros['periodo'] == $periodo['id_periodo'] ? 'selected' : '' ?>>
                                            <?= $periodo['nombre_periodo'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="tipoMovimiento" class="form-label">Filtrar por tipo:</label>
                                <select class="form-select" id="tipoMovimiento" name="tipo_movimiento">
                                    <option value="todos" <?= $filtros['tipo_movimiento'] == 'todos' ? 'selected' : '' ?>>Todos los movimientos</option>
                                    <option value="ingreso" <?= $filtros['tipo_movimiento'] == 'ingreso' ? 'selected' : '' ?>>Ingresos</option>
                                    <option value="egreso" <?= $filtros['tipo_movimiento'] == 'egreso' ? 'selected' : '' ?>>Egresos</option>
                                    <option value="aporte" <?= $filtros['tipo_movimiento'] == 'aporte' ? 'selected' : '' ?>>Aportes</option>
                                    <option value="prestamo" <?= $filtros['tipo_movimiento'] == 'prestamo' ? 'selected' : '' ?>>Préstamos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="filtros" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Resumen contable -->
                <div class="accounting-summary">
                    <h3 class="h5 mb-4">Resumen Contable del Período</h3>
                    <div class="accounting-summary-item">
                        <span>Total Ingresos:</span>
                        <span class="positive-amount">$<?= number_format($resumen['total_ingresos'] ?? 0, 2) ?></span>
                    </div>
                    <div class="accounting-summary-item">
                        <span>Total Egresos:</span>
                        <span class="negative-amount">$<?= number_format($resumen['total_egresos'] ?? 0, 2) ?></span>
                    </div>
                    <div class="accounting-summary-item">
                        <span>Saldo del Período:</span>
                        <span class="<?= ($resumen['saldo_periodo'] ?? 0) >= 0 ? 'positive-amount' : 'negative-amount' ?>">
                            $<?= number_format($resumen['saldo_periodo'] ?? 0, 2) ?>
                        </span>
                    </div>
                    <div class="accounting-summary-item">
                        <span>Saldo Acumulado:</span>
                        <span class="<?= ($resumen['saldo_acumulado'] ?? 0) >= 0 ? 'positive-amount' : 'negative-amount' ?>">
                            $<?= number_format($resumen['saldo_acumulado'] ?? 0, 2) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Reportes rápidos -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card accounting-card h-100">
                            <div class="card-body">
                                <div class="module-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                                <h5 class="card-title">Balance General</h5>
                                <p class="card-text text-muted">Genera el balance general al cierre del período.</p>
                                <a href="reportes/balance_general.php?periodo=<?= $filtros['periodo'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-2"></i>Descargar PDF
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card accounting-card h-100">
                            <div class="card-body">
                                <div class="module-icon"><i class="fas fa-chart-line"></i></div>
                                <h5 class="card-title">Estado de Resultados</h5>
                                <p class="card-text text-muted">Visualiza ingresos y egresos del período.</p>
                                <a href="reportes/estado_resultados.php?periodo=<?= $filtros['periodo'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-chart-pie me-2"></i>Ver Gráfico
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Libro diario -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">Libro Diario</h3>
                        <div>
                            <span class="badge bg-primary me-2">Período: <?= $periodo_actual ? $periodo_actual['nombre_periodo'] : 'No hay período activo' ?></span>
                            <span class="badge bg-secondary"><?= count($movimientos) ?> registros</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover accounting-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>
                                        <th>Cuenta Débito</th>
                                        <th>Cuenta Crédito</th>
                                        <th>Monto</th>
                                        <th>Referencia</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos as $mov): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($mov['descripcion']) ?></td>
                                            <td><?= ucfirst($mov['tipo_movimiento']) ?></td>
                                            <td><?= $mov['cuenta_debito_codigo'] ? "{$mov['cuenta_debito_codigo']} - {$mov['cuenta_debito_nombre']}" : '-' ?></td>
                                            <td><?= $mov['cuenta_credito_codigo'] ? "{$mov['cuenta_credito_codigo']} - {$mov['cuenta_credito_nombre']}" : '-' ?></td>
                                            <td class="<?= $mov['tipo_movimiento'] === 'ingreso' ? 'positive-amount' : 'negative-amount' ?>">
                                                $<?= number_format($mov['monto'], 2) ?>
                                            </td>
                                            <td><?= $mov['referencia'] ?></td>
                                            <td><?= $mov['usuario_nombre'] ?></td>
                                            <td class="accounting-actions">
                                                <button class="btn btn-sm btn-outline-secondary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($movimientos)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">No hay movimientos registrados para este período</td>
                                        </tr>
                                    <?php endif; ?>
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
                
                <!-- Plan de cuentas -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">Plan de Cuentas</h3>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaCuentaModal">
                            <i class="fas fa-plus me-1"></i>Nueva Cuenta
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover accounting-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre de Cuenta</th>
                                        <th>Tipo</th>
                                        <th>Saldo Actual</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cuentas as $cuenta): ?>
                                        <tr>
                                            <td><?= $cuenta['codigo_cuenta'] ?></td>
                                            <td><?= htmlspecialchars($cuenta['nombre_cuenta']) ?></td>
                                            <td><?= ucfirst($cuenta['tipo_cuenta']) ?></td>
                                            <td class="<?= $cuenta['saldo_actual'] >= 0 ? 'positive-amount' : 'negative-amount' ?>">
                                                $<?= number_format($cuenta['saldo_actual'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $cuenta['activa'] ? 'success' : 'danger' ?>">
                                                    <?= $cuenta['activa'] ? 'Activa' : 'Inactiva' ?>
                                                </span>
                                            </td>
                                            <td class="accounting-actions">
                                                <button class="btn btn-sm btn-outline-secondary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Nuevo Movimiento -->
    <div class="modal fade" id="nuevoMovimientoModal" tabindex="-1" aria-labelledby="nuevoMovimientoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoMovimientoModalLabel">Registrar Nuevo Movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fechaMovimiento" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fechaMovimiento" name="fecha" required 
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="tipoMovimiento" class="form-label">Tipo de Movimiento</label>
                                <select class="form-select" id="tipoMovimiento" name="tipo_movimiento" required>
                                    <option value="" selected disabled>Seleccionar...</option>
                                    <option value="ingreso">Ingreso</option>
                                    <option value="egreso">Egreso</option>
                                    <option value="aporte">Aporte de Socio</option>
                                    <option value="prestamo">Préstamo</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cuentaOrigen" class="form-label">Cuenta Origen</label>
                                <select class="form-select" id="cuentaOrigen" name="cuenta_origen">
                                    <option value="" selected disabled>Seleccionar cuenta...</option>
                                    <?php foreach ($cuentas as $cuenta): ?>
                                        <?php if (in_array($cuenta['tipo_cuenta'], ['activo', 'gasto'])): ?>
                                            <option value="<?= $cuenta['id_cuenta'] ?>">
                                                <?= $cuenta['codigo_cuenta'] ?> - <?= $cuenta['nombre_cuenta'] ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cuentaDestino" class="form-label">Cuenta Destino</label>
                                <select class="form-select" id="cuentaDestino" name="cuenta_destino">
                                    <option value="" selected disabled>Seleccionar cuenta...</option>
                                    <?php foreach ($cuentas as $cuenta): ?>
                                        <?php if (in_array($cuenta['tipo_cuenta'], ['activo', 'patrimonio', 'ingreso'])): ?>
                                            <option value="<?= $cuenta['id_cuenta'] ?>">
                                                <?= $cuenta['codigo_cuenta'] ?> - <?= $cuenta['nombre_cuenta'] ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="id_socio" class="form-label">Socio (opcional)</label>
                                <select class="form-select" id="id_socio" name="id_socio">
                                    <option value="" selected>No aplica</option>
                                    <?php foreach ($socios as $socio): ?>
                                        <option value="<?= $socio['codigoemp'] ?>"><?= $socio['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="forma_pago" class="form-label">Forma de Pago (para aportes)</label>
                                <select class="form-select" id="forma_pago" name="forma_pago">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="nomina">Nómina</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="referenciaMovimiento" class="form-label">Número de Referencia</label>
                                <input type="text" class="form-control" id="referenciaMovimiento" name="referencia">
                            </div>
                            <div class="col-md-6">
                                <label for="adjuntoMovimiento" class="form-label">Documento Adjunto (Opcional)</label>
                                <input class="form-control" type="file" id="adjuntoMovimiento" name="adjunto">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcionMovimiento" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionMovimiento" name="descripcion" rows="2" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="montoMovimiento" class="form-label">Monto</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="montoMovimiento" name="monto" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="nuevo_movimiento" class="btn btn-primary">Guardar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Cuenta -->
    <div class="modal fade" id="nuevaCuentaModal" tabindex="-1" aria-labelledby="nuevaCuentaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevaCuentaModalLabel">Nueva Cuenta Contable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formNuevaCuenta" method="post" action="guardar_cuenta.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="codigoCuenta" class="form-label">Código de Cuenta</label>
                            <input type="text" class="form-control" id="codigoCuenta" name="codigo_cuenta" required 
                                   pattern="[0-9]{1,2}-[0-9]{2}-[0-9]{3}" 
                                   title="Formato: XX-XX-XXX (ej. 1-01-001)">
                        </div>
                        <div class="mb-3">
                            <label for="nombreCuenta" class="form-label">Nombre de Cuenta</label>
                            <input type="text" class="form-control" id="nombreCuenta" name="nombre_cuenta" required>
                        </div>
                        <div class="mb-3">
                            <label for="tipoCuenta" class="form-label">Tipo de Cuenta</label>
                            <select class="form-select" id="tipoCuenta" name="tipo_cuenta" required>
                                <option value="" selected disabled>Seleccionar...</option>
                                <option value="activo">Activo</option>
                                <option value="pasivo">Pasivo</option>
                                <option value="patrimonio">Patrimonio</option>
                                <option value="ingreso">Ingreso</option>
                                <option value="gasto">Gasto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descripcionCuenta" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionCuenta" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activaCuenta" name="activa" checked>
                            <label class="form-check-label" for="activaCuenta">Cuenta activa</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Colapsar el menú lateral
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("sidebar-collapsed");
            });

            // Activar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Marcar como activo el link del menú
            $('.list-group-item').on('click', function() {
                $('.list-group-item').removeClass('active');
                $(this).addClass('active');
            });
            
            // Mostrar/ocultar campos según tipo de movimiento
            $('#tipoMovimiento').change(function() {
                const tipo = $(this).val();
                
                // Resetear selects
                $('#cuentaOrigen, #cuentaDestino').val('').prop('disabled', false);
                
                if (tipo === 'ingreso') {
                    $('#cuentaOrigen').prop('disabled', true);
                    $('#cuentaDestino').prop('required', true);
                } else if (tipo === 'egreso') {
                    $('#cuentaOrigen').prop('required', true);
                    $('#cuentaDestino').prop('required', false);
                } else if (tipo === 'transferencia') {
                    $('#cuentaOrigen').prop('required', true);
                    $('#cuentaDestino').prop('required', true);
                } else if (tipo === 'aporte' || tipo === 'prestamo') {
                    $('#id_socio').prop('required', true);
                    $('#cuentaOrigen').prop('required', true);
                    $('#cuentaDestino').prop('required', true);
                    $('#forma_pago').prop('required', true);
                }
            });
            
            // Validación del código de cuenta
            $('#formNuevaCuenta').submit(function(e) {
                const codigo = $('#codigoCuenta').val();
                if (!/^\d{1,2}-\d{2}-\d{3}$/.test(codigo)) {
                    alert('El código de cuenta debe tener el formato XX-XX-XXX (ej. 1-01-001)');
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>