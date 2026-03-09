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


// Procesar formulario de colaboración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_colaboracion'])) {
    try {
        // Validar datos
        $cod_empleado = $_SESSION['es_admin'] ? $_POST['cod_empleado'] : $_SESSION['cod_empleado'];
        $monto_colaboracion = filter_input(INPUT_POST, 'monto_colaboracion', FILTER_VALIDATE_FLOAT);
        $tipo_colaboracion = filter_input(INPUT_POST, 'tipo_colaboracion', FILTER_VALIDATE_INT);
        $metodo_pago = htmlspecialchars($_POST['metodo_pago']);
        $referencia = htmlspecialchars($_POST['referencia_pago'] ?? '');
        $observaciones = htmlspecialchars($_POST['observaciones'] ?? '');
        $periodo = htmlspecialchars($_POST['periodo_colaboracion']);
        
        if (!$cod_empleado || !$monto_colaboracion || $monto_colaboracion <= 0) {
            throw new Exception("Datos de colaboración inválidos");
        }
        
        // Registrar la colaboración (SOLO EL INSERT)
        $stmt = $conn->prepare("INSERT INTO registro_colaboraciones (
            cod_empleado, id_tipo_colaboracion, monto, fecha_colaboracion, 
            periodo_colaboracion, metodo_pago, referencia_pago, observaciones, usuario_registro
        ) VALUES (?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $cod_empleado,
            $tipo_colaboracion ?: null,
            $monto_colaboracion,
            $periodo,
            $metodo_pago,
            $referencia,
            $observaciones,
            $nombre_usuario
        ]);
        
        // 2. Actualizar el total RECALCULANDO TODO (a prueba de duplicaciones)
$stmt = $conn->prepare("UPDATE asociados 
                       SET totalcolab = (
                           SELECT COALESCE(SUM(monto), 0)
                           FROM registro_colaboraciones 
                           WHERE cod_empleado = ?
                       )
                       WHERE cod_empleado = ?");
$stmt->execute([$cod_empleado, $cod_empleado]);
        
        $_SESSION['mensaje'] = "Colaboración registrada exitosamente";
        header("Location: colaboraciones_creditos.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Procesar formulario de solicitud de crédito por convenio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_credito'])) {
    try {
        // Validar datos
        $cod_empleado = $_SESSION['es_admin'] ? $_POST['cod_empleado'] : $_SESSION['cod_empleado'];
        $tipo_credito = filter_input(INPUT_POST, 'tipo_credito', FILTER_VALIDATE_INT);
        $monto_solicitado = filter_input(INPUT_POST, 'monto_solicitado', FILTER_VALIDATE_FLOAT);
        $producto = htmlspecialchars($_POST['producto_adquirido']);
        $proveedor = htmlspecialchars($_POST['proveedor']);
        $numero_factura = htmlspecialchars($_POST['numero_factura'] ?? '');
        $plazo_meses = filter_input(INPUT_POST, 'plazo_meses', FILTER_VALIDATE_INT);
        $observaciones = htmlspecialchars($_POST['observaciones'] ?? '');
        
        if (!$cod_empleado || !$monto_solicitado || $monto_solicitado <= 0 || !$tipo_credito) {
            throw new Exception("Datos de crédito inválidos");
        }
        
        // Obtener información del tipo de crédito
        $stmt = $conn->prepare("SELECT * FROM tipos_credito_convenio WHERE id = ? AND estado = TRUE");
        $stmt->execute([$tipo_credito]);
        $tipo_credito_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tipo_credito_info) {
            throw new Exception("Tipo de crédito no válido");
        }
        
        // Validar monto máximo
        if ($monto_solicitado > $tipo_credito_info['monto_maximo']) {
            throw new Exception("El monto solicitado excede el máximo permitido para este tipo de crédito");
        }
        
        // Calcular cuota mensual
        $tasa_interes = $tipo_credito_info['tasa_interes'];
        $tasa_mensual = $tasa_interes / 100 / 12;
        $cuota_mensual = $monto_solicitado * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo_meses)) / (pow(1 + $tasa_mensual, $plazo_meses) - 1);
        
        // Registrar la solicitud de crédito
        $stmt = $conn->prepare("INSERT INTO registro_creditos_convenio (
            cod_empleado, id_tipo_credito, monto_solicitado, producto_adquirido, 
            proveedor, numero_factura, cuota_mensual, plazo_meses, tasa_interes,
            observaciones, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $cod_empleado,
            $tipo_credito,
            $monto_solicitado,
            $producto,
            $proveedor,
            $numero_factura,
            $cuota_mensual,
            $plazo_meses,
            $tasa_interes,
            $observaciones,
            $nombre_usuario
        ]);
        
        $_SESSION['mensaje'] = "Solicitud de crédito registrada exitosamente";
        header("Location: colaboraciones_creditos.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Procesar formulario para crear nuevo tipo de colaboración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_tipo_colaboracion'])) {
    try {
        $nombre = htmlspecialchars($_POST['nombre_tipo_colaboracion']);
        $descripcion = htmlspecialchars($_POST['descripcion_tipo_colaboracion'] ?? '');
        
        if (empty($nombre)) {
            throw new Exception("El nombre del tipo de colaboración es obligatorio");
        }
        
        // Verificar si ya existe un tipo con ese nombre
        $stmt = $conn->prepare("SELECT id FROM tipos_colaboracion WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetch()) {
            throw new Exception("Ya existe un tipo de colaboración con ese nombre");
        }
        
        // Insertar nuevo tipo de colaboración
        $stmt = $conn->prepare("INSERT INTO tipos_colaboracion (nombre, descripcion, estado) VALUES (?, ?, TRUE)");
        $stmt->execute([$nombre, $descripcion]);
        
        $_SESSION['mensaje'] = "Tipo de colaboración creado exitosamente";
        header("Location: colaboraciones_creditos.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Procesar formulario para crear nuevo tipo de crédito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_tipo_credito'])) {
    try {
        $nombre = htmlspecialchars($_POST['nombre_tipo_credito']);
        $descripcion = htmlspecialchars($_POST['descripcion_tipo_credito'] ?? '');
        $monto_maximo = filter_input(INPUT_POST, 'monto_maximo_tipo_credito', FILTER_VALIDATE_FLOAT);
        $tasa_interes = filter_input(INPUT_POST, 'tasa_interes_tipo_credito', FILTER_VALIDATE_FLOAT);
        
        if (empty($nombre)) {
            throw new Exception("El nombre del tipo de crédito es obligatorio");
        }
        
        if (!$monto_maximo || $monto_maximo <= 0) {
            throw new Exception("El monto máximo debe ser un valor positivo");
        }
        
        if (!$tasa_interes || $tasa_interes < 0) {
            throw new Exception("La tasa de interés debe ser un valor válido");
        }
        
        // Verificar si ya existe un tipo con ese nombre
        $stmt = $conn->prepare("SELECT id FROM tipos_credito_convenio WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetch()) {
            throw new Exception("Ya existe un tipo de crédito con ese nombre");
        }
        
        // Insertar nuevo tipo de crédito
        $stmt = $conn->prepare("INSERT INTO tipos_credito_convenio (nombre, descripcion, monto_maximo, tasa_interes, estado) VALUES (?, ?, ?, ?, TRUE)");
        $stmt->execute([$nombre, $descripcion, $monto_maximo, $tasa_interes]);
        
        $_SESSION['mensaje'] = "Tipo de crédito creado exitosamente";
        header("Location: colaboraciones_creditos.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Obtener tipos de colaboración
$stmt = $conn->prepare("SELECT * FROM tipos_colaboracion WHERE estado = TRUE ORDER BY nombre");
$stmt->execute();
$tipos_colaboracion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de crédito
$stmt = $conn->prepare("SELECT * FROM tipos_credito_convenio WHERE estado = TRUE ORDER BY nombre");
$stmt->execute();
$tipos_credito = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener colaboraciones recientes
if ($_SESSION['es_admin']) {
    $stmt = $conn->prepare("SELECT c.*, a.nombre 
                           FROM registro_colaboraciones c
                           JOIN asociados a ON c.cod_empleado = a.cod_empleado
                           ORDER BY c.fecha_colaboracion DESC, c.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT c.* 
                           FROM registro_colaboraciones c
                           WHERE c.cod_empleado = ?
                           ORDER BY c.fecha_colaboracion DESC, c.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute([$_SESSION['cod_empleado']]);
}
$colaboraciones_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener créditos recientes
if ($_SESSION['es_admin']) {
    $stmt = $conn->prepare("SELECT cr.*, a.nombre, tc.nombre as tipo_credito
                           FROM registro_creditos_convenio cr
                           JOIN asociados a ON cr.cod_empleado = a.cod_empleado
                           JOIN tipos_credito_convenio tc ON cr.id_tipo_credito = tc.id
                           ORDER BY cr.fecha_solicitud DESC, cr.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT cr.*, tc.nombre as tipo_credito
                           FROM registro_creditos_convenio cr
                           JOIN tipos_credito_convenio tc ON cr.id_tipo_credito = tc.id
                           WHERE cr.cod_empleado = ?
                           ORDER BY cr.fecha_solicitud DESC, cr.fecha_registro DESC
                           LIMIT 10");
    $stmt->execute([$_SESSION['cod_empleado']]);
}
$creditos_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de empleados para select (solo admin)
$empleados = [];
if ($_SESSION['es_admin']) {
    $stmt = $conn->prepare("SELECT cod_empleado, nombre FROM asociados ORDER BY nombre");
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboraciones y Créditos - Caja de Ahorro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" href="./logo/capcel.png">
    <style>
        /* Estilos consistentes con el módulo anterior */
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
        
        .nav-tabs .nav-link {
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .tab-content {
            padding: 1.5rem 0;
        }
        
        .badge-estado {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50rem;
        }
        
        .badge-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-aprobado {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-rechazado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-liquidado {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .btn-add-type {
            margin-top: 10px;
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
                 <a href="retiros_prestamos.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Retiros y Préstamos">
                    <i class="fas fa-hand-holding-usd"></i>Retiros y Préstamos
                </a>
                <a href="pagos.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Pagos">
                    <i class="fas fa-calculator"></i>Pagos
                </a>
                <a href="colaboraciones_creditos.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Colaboraciones y Créditos">
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
                <h1 class="mb-4">Colaboraciones y Créditos por Convenios</h1>
                
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
                
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="colaboraciones-tab" data-bs-toggle="tab" data-bs-target="#colaboraciones" type="button" role="tab" aria-controls="colaboraciones" aria-selected="true">
                            <i class="fas fa-donate me-2"></i>Colaboraciones
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="creditos-tab" data-bs-toggle="tab" data-bs-target="#creditos" type="button" role="tab" aria-controls="creditos" aria-selected="false">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Créditos por Convenios
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="myTabContent">
                    <!-- Pestaña de Colaboraciones -->
                    <div class="tab-pane fade show active" id="colaboraciones" role="tabpanel" aria-labelledby="colaboraciones-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Registrar Colaboración</h5>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTipoColaboracion">
                                            <i class="fas fa-plus"></i> Nuevo Tipo
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" id="formColaboracion">
                                            <?php if ($_SESSION['es_admin']): ?>
                                            <div class="mb-3">
                                                <label for="cod_empleado_colab" class="form-label">Empleado</label>
                                                <select class="form-control" id="cod_empleado_colab" name="cod_empleado" required>
                                                    <option value="">Seleccione un empleado</option>
                                                    <?php foreach ($empleados as $empleado): ?>
                                                        <option value="<?= $empleado['cod_empleado'] ?>">
                                                            <?= htmlspecialchars($empleado['cod_empleado'] . ' - ' . $empleado['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="tipo_colaboracion" class="form-label">Tipo de Colaboración</label>
                                                <select class="form-control" id="tipo_colaboracion" name="tipo_colaboracion">
                                                    <option value="">Seleccione un tipo (opcional)</option>
                                                    <?php foreach ($tipos_colaboracion as $tipo): ?>
                                                        <option value="<?= $tipo['id'] ?>">
                                                            <?= htmlspecialchars($tipo['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="periodo_colaboracion" class="form-label">Período</label>
                                                <input type="month" class="form-control" id="periodo_colaboracion" 
                                                       name="periodo_colaboracion" value="<?= date('Y-m') ?>" required>
                                            </div>
                                            
                                            <div class="mb-3 currency-input">
                                                <label for="monto_colaboracion" class="form-label">Monto de la Colaboración</label>
                                                <input type="number" step="0.01" class="form-control" id="monto_colaboracion" 
                                                       name="monto_colaboracion" min="0.01" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="metodo_pago_colab" class="form-label">Método de Pago</label>
                                                <select class="form-control" id="metodo_pago_colab" name="metodo_pago" required>
                                                    <option value="">Seleccione método de pago</option>
                                                    <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                                    <option value="Descuento de Nómina">Descuento de Nómina</option>
                                                    <option value="Domiciliación Bancaria">Domiciliación Bancaria</option>
                                                    <option value="Efectivo">Efectivo</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3" id="referenciaContainerColab" style="display: none;">
                                                <label for="referencia_pago_colab" class="form-label">Número de Referencia/Comprobante</label>
                                                <input type="text" class="form-control" id="referencia_pago_colab" name="referencia_pago">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="observaciones_colab" class="form-label">Observaciones</label>
                                                <textarea class="form-control" id="observaciones_colab" name="observaciones" rows="2"></textarea>
                                            </div>
                                            
                                            <button type="submit" name="registrar_colaboracion" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Registrar Colaboración
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Historial de Colaboraciones Recientes</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($colaboraciones_recientes)): ?>
                                            <div class="alert alert-info">No hay registros de colaboraciones</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Fecha</th>
                                                            <?php if ($_SESSION['es_admin']): ?>
                                                                <th>Empleado</th>
                                                            <?php endif; ?>
                                                            <th>Monto</th>
                                                            <th>Método</th>
                                                            <th>Período</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($colaboraciones_recientes as $colab): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($colab['fecha_colaboracion']) ?></td>
                                                                <?php if ($_SESSION['es_admin']): ?>
                                                                    <td><?= htmlspecialchars($colab['nombre'] ?? $colab['cod_empleado']) ?></td>
                                                                <?php endif; ?>
                                                                <td>Bs<?= number_format($colab['monto'], 2) ?></td>
                                                                <td><?= htmlspecialchars($colab['metodo_pago']) ?></td>
                                                                <td><?= htmlspecialchars($colab['periodo_colaboracion']) ?></td>
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
                    
                    <!-- Pestaña de Créditos por Convenios -->
                    <div class="tab-pane fade" id="creditos" role="tabpanel" aria-labelledby="creditos-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Solicitar Crédito por Convenio</h5>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTipoCredito">
                                            <i class="fas fa-plus"></i> Nuevo Tipo
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" id="formCredito">
                                            <?php if ($_SESSION['es_admin']): ?>
                                            <div class="mb-3">
                                                <label for="cod_empleado_credito" class="form-label">Empleado</label>
                                                <select class="form-control" id="cod_empleado_credito" name="cod_empleado" required>
                                                    <option value="">Seleccione un empleado</option>
                                                    <?php foreach ($empleados as $empleado): ?>
                                                        <option value="<?= $empleado['cod_empleado'] ?>">
                                                            <?= htmlspecialchars($empleado['cod_empleado'] . ' - ' . $empleado['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="tipo_credito" class="form-label">Tipo de Crédito</label>
                                                <select class="form-control" id="tipo_credito" name="tipo_credito" required>
                                                    <option value="">Seleccione un tipo de crédito</option>
                                                    <?php foreach ($tipos_credito as $tipo): ?>
                                                        <option value="<?= $tipo['id'] ?>" 
                                                                data-monto-max="<?= $tipo['monto_maximo'] ?>"
                                                                data-tasa="<?= $tipo['tasa_interes'] ?>">
                                                            <?= htmlspecialchars($tipo['nombre']) ?> 
                                                            (Máx: Bs<?= number_format($tipo['monto_maximo'], 2) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3 currency-input">
                                                <label for="monto_solicitado" class="form-label">Monto Solicitado</label>
                                                <input type="number" step="0.01" class="form-control" id="monto_solicitado" 
                                                       name="monto_solicitado" min="0.01" required>
                                                <small class="form-text text-muted">
                                                    Monto máximo permitido: <span id="monto-maximo">Bs0.00</span>
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="producto_adquirido" class="form-label">Producto a Adquirir</label>
                                                <input type="text" class="form-control" id="producto_adquirido" 
                                                       name="producto_adquirido" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="proveedor" class="form-label">Proveedor</label>
                                                <input type="text" class="form-control" id="proveedor" 
                                                       name="proveedor" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="numero_factura" class="form-label">Número de Factura (opcional)</label>
                                                <input type="text" class="form-control" id="numero_factura" 
                                                       name="numero_factura">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="plazo_meses" class="form-label">Plazo en Meses</label>
                                                <input type="number" class="form-control" id="plazo_meses" 
                                                       name="plazo_meses" min="1" max="60" value="12" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cuota_estimada" class="form-label">Cuota Mensual Estimada</label>
                                                <input type="text" class="form-control" id="cuota_estimada" 
                                                       readonly>
                                                <small class="form-text text-muted">
                                                    Tasa de interés: <span id="tasa-interes">0.00</span>%
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="observaciones_credito" class="form-label">Observaciones</label>
                                                <textarea class="form-control" id="observaciones_credito" name="observaciones" rows="2"></textarea>
                                            </div>
                                            
                                            <button type="submit" name="solicitar_credito" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Solicitar Crédito
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Historial de Créditos Recientes</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($creditos_recientes)): ?>
                                            <div class="alert alert-info">No hay registros de créditos</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Fecha</th>
                                                            <?php if ($_SESSION['es_admin']): ?>
                                                                <th>Empleado</th>
                                                            <?php endif; ?>
                                                            <th>Tipo</th>
                                                            <th>Monto</th>
                                                            <th>Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($creditos_recientes as $credito): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($credito['fecha_solicitud']) ?></td>
                                                                <?php if ($_SESSION['es_admin']): ?>
                                                                    <td><?= htmlspecialchars($credito['nombre'] ?? $credito['cod_empleado']) ?></td>
                                                                <?php endif; ?>
                                                                <td><?= htmlspecialchars($credito['tipo_credito']) ?></td>
                                                                <td>Bs<?= number_format($credito['monto_solicitado'], 2) ?></td>
                                                                <td>
                                                                    <span class="badge badge-estado 
                                                                        <?= 'badge-' . strtolower($credito['estado']) ?>">
                                                                        <?= htmlspecialchars($credito['estado']) ?>
                                                                    </span>
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
            </main>
        </div>
    </div>
    
    <!-- Modal para crear nuevo tipo de colaboración -->
    <div class="modal fade" id="modalTipoColaboracion" tabindex="-1" aria-labelledby="modalTipoColaboracionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoColaboracionLabel">Crear Nuevo Tipo de Colaboración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="formTipoColaboracion">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_tipo_colaboracion" class="form-label">Nombre del Tipo</label>
                            <input type="text" class="form-control" id="nombre_tipo_colaboracion" name="nombre_tipo_colaboracion" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_tipo_colaboracion" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="descripcion_tipo_colaboracion" name="descripcion_tipo_colaboracion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_tipo_colaboracion" class="btn btn-primary">Crear Tipo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para crear nuevo tipo de crédito -->
    <div class="modal fade" id="modalTipoCredito" tabindex="-1" aria-labelledby="modalTipoCreditoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoCreditoLabel">Crear Nuevo Tipo de Crédito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="formTipoCredito">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_tipo_credito" class="form-label">Nombre del Tipo</label>
                            <input type="text" class="form-control" id="nombre_tipo_credito" name="nombre_tipo_credito" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_tipo_credito" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="descripcion_tipo_credito" name="descripcion_tipo_credito" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="monto_maximo_tipo_credito" class="form-label">Monto Máximo (Bs)</label>
                            <input type="number" step="0.01" class="form-control" id="monto_maximo_tipo_credito" name="monto_maximo_tipo_credito" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="tasa_interes_tipo_credito" class="form-label">Tasa de Interés Anual (%)</label>
                            <input type="number" step="0.01" class="form-control" id="tasa_interes_tipo_credito" name="tasa_interes_tipo_credito" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_tipo_credito" class="btn btn-primary">Crear Tipo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mostrar/ocultar campo de referencia según método de pago (colaboraciones)
            $('#metodo_pago_colab').change(function() {
                const metodo = $(this).val();
                const requiereReferencia = ['Transferencia Bancaria', 'Domiciliación Bancaria'].includes(metodo);
                $('#referenciaContainerColab').toggle(requiereReferencia);
                if (requiereReferencia) {
                    $('#referencia_pago_colab').prop('required', true);
                } else {
                    $('#referencia_pago_colab').prop('required', false).val('');
                }
            });
            
            // Actualizar información del tipo de crédito seleccionado
            $('#tipo_credito').change(function() {
                const option = $(this).find('option:selected');
                const montoMaximo = option.data('monto-max') || 0;
                const tasaInteres = option.data('tasa') || 0;
                
                $('#monto-maximo').text('Bs' + montoMaximo.toFixed(2));
                $('#tasa-interes').text(tasaInteres.toFixed(2));
                $('#monto_solicitado').attr('max', montoMaximo);
                
                // Recalcular cuota si ya hay un monto
                calcularCuota();
            });
            
            // Calcular cuota cuando cambia el monto o el plazo
            $('#monto_solicitado, #plazo_meses').on('input', function() {
                calcularCuota();
            });
            
            function calcularCuota() {
                const monto = parseFloat($('#monto_solicitado').val()) || 0;
                const plazo = parseInt($('#plazo_meses').val()) || 1;
                const tasaAnual = parseFloat($('#tasa-interes').text()) || 0;
                
                if (monto > 0 && plazo > 0 && tasaAnual >= 0) {
                    const tasaMensual = tasaAnual / 100 / 12;
                    const cuota = monto * (tasaMensual * Math.pow(1 + tasaMensual, plazo)) / (Math.pow(1 + tasaMensual, plazo) - 1);
                    $('#cuota_estimada').val('Bs' + cuota.toFixed(2));
                } else {
                    $('#cuota_estimada').val('');
                }
            }
            
            // Validación del formulario de colaboración
            $('#formColaboracion').submit(function(e) {
                const monto = parseFloat($('#monto_colaboracion').val()) || 0;
                
                if (monto <= 0) {
                    Swal.fire('Error', 'El monto debe ser mayor a cero', 'error');
                    return false;
                }
                
                return true;
            });
            
            // Validación del formulario de crédito
            $('#formCredito').submit(function(e) {
                const monto = parseFloat($('#monto_solicitado').val()) || 0;
                const montoMaximo = parseFloat($('#monto-maximo').text().replace('Bs', '')) || 0;
                
                if (monto <= 0) {
                    Swal.fire('Error', 'El monto debe ser mayor a cero', 'error');
                    return false;
                }
                
                if (monto > montoMaximo) {
                    Swal.fire('Error', 'El monto solicitado excede el máximo permitido para este tipo de crédito', 'error');
                    return false;
                }
                
                return true;
            });
            
            // Validación del formulario de tipo de colaboración
            $('#formTipoColaboracion').submit(function(e) {
                const nombre = $('#nombre_tipo_colaboracion').val().trim();
                
                if (nombre.length === 0) {
                    Swal.fire('Error', 'El nombre del tipo de colaboración es obligatorio', 'error');
                    return false;
                }
                
                return true;
            });
            
            // Validación del formulario de tipo de crédito
            $('#formTipoCredito').submit(function(e) {
                const nombre = $('#nombre_tipo_credito').val().trim();
                const montoMaximo = parseFloat($('#monto_maximo_tipo_credito').val()) || 0;
                const tasaInteres = parseFloat($('#tasa_interes_tipo_credito').val()) || 0;
                
                if (nombre.length === 0) {
                    Swal.fire('Error', 'El nombre del tipo de crédito es obligatorio', 'error');
                    return false;
                }
                
                if (montoMaximo <= 0) {
                    Swal.fire('Error', 'El monto máximo debe ser mayor a cero', 'error');
                    return false;
                }
                
                if (tasaInteres < 0) {
                    Swal.fire('Error', 'La tasa de interés no puede ser negativa', 'error');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>