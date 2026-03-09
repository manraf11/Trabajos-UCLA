<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre'];

// Configuración de la conexión a PostgreSQL
$host = 'localhost';
$port = '5432';
$dbname = 'CAPCEL';
$dbuser = 'postgres';
$dbpassword = '123';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$asociados = [];
$search_term = '';

// Lógica de búsqueda
if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $search_term = trim($_GET['q']);
    try {
        // Conexión usando PDO
        $pdo = new PDO($dsn, $dbuser, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Consulta preparada para evitar inyección SQL - INCLUYENDO montorhabe
        $sql = "SELECT *, montorhabe FROM asociados WHERE nombre ILIKE :term OR cedula ILIKE :term ORDER BY nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['term' => '%' . $search_term . '%']);
        $asociados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "No se pudo conectar a la base de datos: " . $e->getMessage();
    }
}

function format_status($statu) {
    switch (strtoupper($statu)) {
        case 'A': return '<span class="badge bg-success">Activo</span>';
        case 'J': return '<span class="badge bg-info">Jubilado</span>';
        case 'L': return '<span class="badge bg-danger">Liquidado</span>';
        case 'S': return '<span class="badge bg-warning text-dark">Suspendido</span>';
        default: return '<span class="badge bg-secondary">Desconocido</span>';
    }
}

function format_currency($number) {
    if ($number === null || !is_numeric($number)) {
        return 'Bs. 0,00';
    }
    return 'Bs. ' . number_format($number, 2, ',', '.');
}

function format_date($date) {
    if (!$date) return 'N/A';
    return date('d/m/Y', strtotime($date));
}

function format_boolean($value) {
    return $value ? '<span class="badge bg-primary">Sí</span>' : '<span class="badge bg-secondary">No</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Consulta - Caja de Ahorro</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                <a href="pagos.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-calculator"></i>Pagos
                    </a>
                    <a href="colaboraciones_creditos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Colaboraciones y Créditos">
                    <i class="fas fa-handshake"></i>Colaboraciones y Créditos
                </a>
                <a href="#consulta" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Consulta">
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
                <h1 class="mb-4">Módulo de Consulta de Asociados</h1>

                <!-- Tarjeta de Búsqueda -->
                <div class="card search-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Buscar Asociado</h5>
                        <p class="card-text text-muted">Ingrese el nombre o la cédula del asociado que desea consultar.</p>
                        <form action="consulta.php" method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg" name="q" placeholder="Ej: Juan Pérez o 12345678" value="<?php echo htmlspecialchars($search_term); ?>">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search me-2"></i>Buscar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tarjeta de Resultados -->
                <div class="card results-card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Resultados de la Búsqueda</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php elseif (isset($_GET['q'])): ?>
                            <?php if (!empty($asociados)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Código</th>
                                                <th>Cédula</th>
                                                <th>Nombre Completo</th>
                                                <th>Saldo Inicial</th>
                                                <th>Deuda Inicial</th>
                                                <th>Estatus</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($asociados as $asociado): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($asociado['cod_empleado']); ?></td>
                                                    <td><?php echo htmlspecialchars($asociado['cedula']); ?></td>
                                                    <td><?php echo htmlspecialchars($asociado['nombre']); ?></td>
                                                    <td><?php echo format_currency($asociado['salinicial']); ?></td>
                                                    <td><?php echo format_currency($asociado['deuda_inic']); ?></td>
                                                    <td><?php echo format_status($asociado['statu']); ?></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-info btn-view-details" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#asociadoDetailModal"
                                                                data-asociado='<?php echo htmlspecialchars(json_encode($asociado), ENT_QUOTES, 'UTF-8'); ?>'>
                                                            <i class="fas fa-eye me-1"></i>Ver Detalles
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    No se encontraron asociados que coincidan con "<strong><?php echo htmlspecialchars($search_term); ?></strong>".
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Ingrese un término de búsqueda para ver los resultados.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Detalles del Asociado -->
    <div class="modal fade" id="asociadoDetailModal" tabindex="-1" aria-labelledby="asociadoDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="asociadoDetailModalLabel">Detalles del Asociado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="container-fluid">
                        <!-- Sección de Información Principal -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3" id="modal-nombre"></h5>
                                <div class="row">
                                    <div class="col-md-4"><strong>Cédula:</strong> <span id="modal-cedula"></span></div>
                                    <div class="col-md-4"><strong>Código:</strong> <span id="modal-codigo"></span></div>
                                    <div class="col-md-4"><strong>Estatus:</strong> <span id="modal-statu"></span></div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-4"><strong>Tipo Nómina:</strong> <span id="modal-tipo_nomina"></span></div>
                                    <div class="col-md-4"><strong>Nacionalidad:</strong> <span id="modal-nacionalidad"></span></div>
                                    <div class="col-md-4"><strong>Status:</strong> <span id="modal-status"></span></div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-4"><strong>Fecha Ingreso:</strong> <span id="modal-fecha_ingreso"></span></div>
                                    <div class="col-md-4"><strong>Sueldo:</strong> <span id="modal-sueldo"></span></div>
                                    <div class="col-md-4"><strong>Estado:</strong> <span id="modal-estado"></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Información Bancaria -->
                        <h6 class="text-muted">Información Bancaria</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Cuenta Empleado:</strong> <span id="modal-cta_empleado"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Tipo Cuenta:</strong> <span id="modal-tipo_cuenta"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Banco:</strong> <span id="modal-nombre_banco"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Cuenta Empresa:</strong> <span id="modal-cta_empresa"></span></div></div>
                        </div>

                        <!-- Sección de Saldos y Aportes -->
                        <h6 class="text-muted">Saldos y Aportes</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Saldo Inicial:</strong> <span id="modal-salinicial"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Deuda Inicial:</strong> <span id="modal-deuda_inic"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Total Aportes:</strong> <span id="modal-totalaport"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Aporte:</strong> <span id="modal-aporte"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Aporte 2:</strong> <span id="modal-aporte2"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Total Caja:</strong> <span id="modal-totalcaja"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Haberes Disponibles (75%):</strong> <span id="modal-montorhabe-calculado"></span></div></div>
                        </div>

                        <!-- Sección de Préstamos y Pagos -->
                        <h6 class="text-muted">Préstamos y Pagos</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Total Préstamos:</strong> <span id="modal-totalprest"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Pago de Préstamos:</strong> <span id="modal-pag_prest"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Total Abonos:</strong> <span id="modal-totalabon"></span></div></div>
                            <div class="col-md-4"><div class="p-3 bg-white rounded shadow-sm"><strong>Préstamo Disponible (80%):</strong> <span id="modal-credito"></span></div></div>
                        </div>

                        <!-- NUEVA SECCIÓN: Retiros de Haberes -->
                        <h6 class="text-muted">Retiros y Liquidaciones</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded shadow-sm">
                                    <strong>Total Retiros de Haberes o Liquidación:</strong> 
                                    <span id="modal-montorhabe" class="text-primary fw-bold"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Retenciones y Otros -->
                        <h6 class="text-muted">Retenciones y Otros Conceptos</h6>
                        <div class="row g-3">
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Total Retenciones:</strong> <span id="modal-totalrete"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Retención:</strong> <span id="modal-retencion"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Descuento Seguro:</strong> <span id="modal-dctoseguro"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Total Colaboración:</strong> <span id="modal-totalcolab"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Colaboración:</strong> <span id="modal-colab"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Total Reintegros:</strong> <span id="modal-totalreint"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Reintegro Intereses:</strong> <span id="modal-reint_int"></span></div></div>
                            
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Total Liquidación:</strong> <span id="modal-totalliqui"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Total Cancelaciones:</strong> <span id="modal-totalcance"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Cuentas por Cobrar:</strong> <span id="modal-totalctasx"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Fianza:</strong> <span id="modal-fianza"></span></div></div>
                            <div class="col-md-3"><div class="p-2 bg-white rounded shadow-sm"><strong>Negativo:</strong> <span id="modal-negativo"></span></div></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="print-pdf-btn">
                        <i class="fas fa-file-pdf me-2"></i>Imprimir Voucher
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <script>
    $(document).ready(function() {
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
        
        // Función para formatear moneda
        const formatCurrencyJS = (num) => {
            if (num === null || num === undefined || isNaN(parseFloat(num))) {
                return 'Bs. 0,00';
            }
            const number = parseFloat(num);
            return 'Bs. ' + number.toLocaleString('es-VE', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
        };
        
        // Función para formatear estado
        const formatStatusJS = (statu) => {
            switch(statu ? statu.toUpperCase() : '') {
                case 'A': return '<span class="badge bg-success">Activo</span>';
                case 'J': return '<span class="badge bg-info">Jubilado</span>';
                case 'L': return '<span class="badge bg-danger">Liquidado</span>';
                case 'S': return '<span class="badge bg-warning text-dark">Suspendido</span>';
                default: return '<span class="badge bg-secondary">Desconocido</span>';
            }
        };

        // Función para formatear fecha
        const formatDateJS = (dateStr) => {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) return 'N/A';
                return date.toLocaleDateString('es-ES');
            } catch (e) {
                return 'N/A';
            }
        };

        // Función para formatear booleano
        const formatBooleanJS = (value) => {
            return value ? '<span class="badge bg-primary">Sí</span>' : '<span class="badge bg-secondary">No</span>';
        };
        
        // Función para imprimir PDF
        const printAsociadoPDF = (asociadoData) => {
            // Abrir una nueva ventana con los datos formateados
            const printWindow = window.open('', '_blank');
            
            // Formatear la fecha actual
            const today = new Date();
            const formattedDate = today.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            // Calcular valores importantes
            const totalCaja = parseFloat(asociadoData.totalcaja) || 0;
            const montoHaber = totalCaja * 0.75;
            const credito = totalCaja * 0.80;
            const deudaInicial = parseFloat(asociadoData.deuda_inic) || 0;
            const totalColaboraciones = parseFloat(asociadoData.totalcolab) || 0;
            const colaboracionActual = parseFloat(asociadoData.colab) || 0;
            const totalRetirosHaberes = parseFloat(asociadoData.montorhabe) || 0;
            
            // Crear el contenido HTML para el PDF
            const content = `
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <title>Voucher de Asociado - ${asociadoData.nombre}</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 15px; 
                            font-size: 12px;
                            line-height: 1.4;
                        }
                        .header { 
                            text-align: center; 
                            margin-bottom: 20px;
                            border-bottom: 2px solid #333;
                            padding-bottom: 10px;
                        }
                        .logo { 
                            max-width: 120px; 
                            margin-bottom: 10px;
                        }
                        .title { 
                            font-size: 16px; 
                            font-weight: bold; 
                            margin: 5px 0;
                            color: #2c3e50;
                        }
                        .subtitle { 
                            font-size: 14px; 
                            margin: 3px 0;
                            color: #34495e;
                        }
                        .voucher-info { 
                            margin: 15px 0;
                            background: #f8f9fa;
                            padding: 10px;
                            border-radius: 5px;
                        }
                        .section { 
                            margin-bottom: 12px;
                            page-break-inside: avoid;
                        }
                        .section-title { 
                            font-weight: bold; 
                            border-bottom: 1px solid #000; 
                            margin-bottom: 8px;
                            padding-bottom: 3px;
                            font-size: 13px;
                            background: #e9ecef;
                            padding: 5px;
                            border-radius: 3px;
                        }
                        .row { 
                            display: flex; 
                            margin-bottom: 4px;
                            flex-wrap: wrap;
                        }
                        .col { 
                            flex: 1; 
                            min-width: 200px;
                            padding: 2px 5px;
                        }
                        .footer { 
                            margin-top: 25px; 
                            text-align: center; 
                            font-size: 10px;
                            border-top: 1px solid #ccc;
                            padding-top: 10px;
                            color: #666;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin: 8px 0;
                            font-size: 11px;
                        }
                        table, th, td { 
                            border: 1px solid #000; 
                        }
                        th, td { 
                            padding: 6px; 
                            text-align: left; 
                        }
                        th { 
                            background-color: #f1f1f1;
                            font-weight: bold;
                        }
                        .highlight { 
                            background-color: #fff3cd; 
                            font-weight: bold;
                        }
                        .deuda-section {
                            background-color: #f8d7da;
                            padding: 8px;
                            border-radius: 4px;
                            margin: 5px 0;
                        }
                        .colaboracion-section {
                            background-color: #d1ecf1;
                            padding: 8px;
                            border-radius: 4px;
                            margin: 5px 0;
                        }
                        .retiros-section {
                            background-color: #e8f5e8;
                            padding: 8px;
                            border-radius: 4px;
                            margin: 5px 0;
                        }
                        .total-row {
                            font-weight: bold;
                            background-color: #e9ecef;
                        }
                        @media print {
                            body { margin: 10px; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="title">CAJA DE AHORRO CAPCEL</div>
                        <div class="subtitle">VOUCHER DE ASOCIADO - COMPROBANTE OFICIAL</div>
                        <div><strong>Fecha:</strong> ${formattedDate}</div>
                    </div>
                    
                    <div class="voucher-info">
                        <div class="section-title">INFORMACIÓN PERSONAL</div>
                        <div class="row">
                            <div class="col"><strong>Nombre Completo:</strong> ${asociadoData.nombre || 'N/A'}</div>
                            <div class="col"><strong>Cédula de Identidad:</strong> ${asociadoData.cedula || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col"><strong>Código de Empleado:</strong> ${asociadoData.cod_empleado || 'N/A'}</div>
                            <div class="col"><strong>Estatus Laboral:</strong> ${asociadoData.statu || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col"><strong>Tipo de Nómina:</strong> ${asociadoData.tipo_nomina || 'N/A'}</div>
                            <div class="col"><strong>Fecha de Ingreso:</strong> ${formatDateJS(asociadoData.fecha_ingreso)}</div>
                        </div>
                    </div>
                    
                    <!-- Sección de Deuda Inicial -->
                    <div class="section">
                        <div class="section-title">INFORMACIÓN DE DEUDA</div>
                        <div class="deuda-section">
                            <div class="row">
                                <div class="col"><strong>Deuda Inicial:</strong> ${formatCurrencyJS(deudaInicial)}</div>
                                <div class="col"><strong>Estado de Deuda:</strong> ${asociadoData.estado ? 'ACTIVA' : 'INACTIVA'}</div>
                            </div>
                            <div class="row">
                                <div class="col"><strong>Total Préstamos:</strong> ${formatCurrencyJS(parseFloat(asociadoData.totalprest) || 0)}</div>
                                <div class="col"><strong>Pagos Realizados:</strong> ${formatCurrencyJS(parseFloat(asociadoData.pag_prest) || 0)}</div>
                            </div>
                            <div class="row">
                                <div class="col"><strong>Saldo Pendiente:</strong> ${formatCurrencyJS((parseFloat(asociadoData.totalprest) || 0) - (parseFloat(asociadoData.pag_prest) || 0))}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Colaboraciones -->
                    <div class="section">
                        <div class="section-title">COLABORACIONES</div>
                        <div class="colaboracion-section">
                            <div class="row">
                                <div class="col"><strong>Total Colaboraciones:</strong> ${formatCurrencyJS(totalColaboraciones)}</div>
                                <div class="col"><strong>Colaboración Actual:</strong> ${formatCurrencyJS(colaboracionActual)}</div>
                            </div>
                            <div class="row">
                                <div class="col"><strong>Total Abonos:</strong> ${formatCurrencyJS(parseFloat(asociadoData.totalabon) || 0)}</div>
                                <div class="col"><strong>Total Reintegros:</strong> ${formatCurrencyJS(parseFloat(asociadoData.totalreint) || 0)}</div>
                            </div>
                        </div>
                    </div>

                    <!-- NUEVA SECCIÓN: Retiros de Haberes -->
                    <div class="section">
                        <div class="section-title">RETIROS DE HABERES Y LIQUIDACIONES</div>
                        <div class="retiros-section">
                            <div class="row">
                                <div class="col"><strong>Total Retiros de Haberes o Liquidación:</strong> ${formatCurrencyJS(totalRetirosHaberes)}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">DETALLE FINANCIERO COMPLETO</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>CONCEPTO</th>
                                    <th>VALOR ACTUAL</th>
                                    <th>DETALLE ADICIONAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Saldo Inicial</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.salinicial) || 0)}</td>
                                    <td>Fondo base del asociado</td>
                                </tr>
                                <tr>
                                    <td><strong>Total en Caja</strong></td>
                                    <td>${formatCurrencyJS(totalCaja)}</td>
                                    <td>Acumulado total</td>
                                </tr>
                                <tr class="highlight">
                                    <td><strong>Haberes Disponibles (75%)</strong></td>
                                    <td>${formatCurrencyJS(montoHaber)}</td>
                                    <td>Monto disponible para retiro</td>
                                </tr>
                                <tr class="highlight">
                                    <td><strong>Línea de Crédito (80%)</strong></td>
                                    <td>${formatCurrencyJS(credito)}</td>
                                    <td>Máximo para préstamos</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Aportes</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalaport) || 0)}</td>
                                    <td>Aportes acumulados</td>
                                </tr>
                                <tr>
                                    <td><strong>Aporte Actual</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.aporte) || 0)}</td>
                                    <td>Aporte del período</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Retiros de Haberes</strong></td>
                                    <td>${formatCurrencyJS(totalRetirosHaberes)}</td>
                                    <td>Retiros y liquidaciones realizadas</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Retenciones</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalrete) || 0)}</td>
                                    <td>Retenciones aplicadas</td>
                                </tr>
                                <tr>
                                    <td><strong>Descuento por Seguro</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.dctoseguro) || 0)}</td>
                                    <td>Deducción de seguro</td>
                                </tr>
                                <tr>
                                    <td><strong>Fianza</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.fianza) || 0)}</td>
                                    <td>Garantía constituida</td>
                                </tr>
                                <tr>
                                    <td><strong>Saldo Negativo</strong></td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.negativo) || 0)}</td>
                                    <td>Posición negativa</td>
                                </tr>
                                <tr class="total-row">
                                    <td><strong>SALDO NETO DISPONIBLE</strong></td>
                                    <td><strong>${formatCurrencyJS(totalCaja - (parseFloat(asociadoData.totalprest) || 0))}</strong></td>
                                    <td>Saldo líquido actual</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">RESUMEN DE MOVIMIENTOS</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>TIPO DE MOVIMIENTO</th>
                                    <th>TOTAL ACUMULADO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Préstamos Otorgados</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalprest) || 0)}</td>
                                </tr>
                                <tr>
                                    <td>Pagos a Préstamos</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.pag_prest) || 0)}</td>
                                </tr>
                                <tr>
                                    <td>Colaboraciones</td>
                                    <td>${formatCurrencyJS(totalColaboraciones)}</td>
                                </tr>
                                <tr>
                                    <td>Abonos Realizados</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalabon) || 0)}</td>
                                </tr>
                                <tr>
                                    <td>Reintegros</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalreint) || 0)}</td>
                                </tr>
                                <tr>
                                    <td>Retiros de Haberes/Liquidación</td>
                                    <td>${formatCurrencyJS(totalRetirosHaberes)}</td>
                                </tr>
                                <tr>
                                    <td>Cancelaciones</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalcance) || 0)}</td>
                                </tr>
                                <tr>
                                    <td>Liquidaciones</td>
                                    <td>${formatCurrencyJS(parseFloat(asociadoData.totalliqui) || 0)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="footer">
                        <p><strong>Este documento es un comprobante oficial generado por el Sistema de Caja de Ahorro CAPCEL</strong></p>
                        <p>Fecha y hora de generación: ${new Date().toLocaleString('es-ES')} | 
                           Código de transacción: ${asociadoData.cod_empleado}_${Date.now()}</p>
                        <p>Para consultas o aclaratorias contacte a la administración de la Caja de Ahorro</p>
                    </div>
                </body>
                </html>
            `;
           
            // Escribir el contenido y llamar a imprimir
            printWindow.document.open();
            printWindow.document.write(content);
            printWindow.document.close();
            
            // Esperar a que se cargue el contenido antes de imprimir
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                    // Cerrar la ventana después de imprimir (opcional)
                    // printWindow.close();
                }, 500);
            };
        };
        
        // Lógica para poblar y mostrar el modal
        $('.btn-view-details').on('click', function() {
            const asociadoData = $(this).data('asociado');
            
            // Convertir valores numéricos
            const totalCaja = parseFloat(asociadoData.totalcaja) || 0;
            
            // Calcular nuevos valores
            const montoHaber = totalCaja * 0.75;
            const credito = totalCaja * 0.80;
            
            // Poblamos los campos del modal
            $('#modal-nombre').text(asociadoData.nombre || 'N/A');
            $('#modal-cedula').text(asociadoData.cedula || 'N/A');
            $('#modal-codigo').text(asociadoData.cod_empleado || 'N/A');
            $('#modal-statu').html(formatStatusJS(asociadoData.statu));
            $('#modal-tipo_nomina').text(asociadoData.tipo_nomina || 'N/A');
            $('#modal-nacionalidad').text(asociadoData.nacionalidad || 'N/A');
            $('#modal-status').text(asociadoData.status || 'N/A');
            $('#modal-fecha_ingreso').text(formatDateJS(asociadoData.fecha_ingreso));
            $('#modal-sueldo').text(formatCurrencyJS(asociadoData.sueldo));
            $('#modal-estado').html(formatBooleanJS(asociadoData.estado));
            
            // Información bancaria
            $('#modal-cta_empleado').text(asociadoData.cta_empleado || 'N/A');
            $('#modal-tipo_cuenta').text(asociadoData.tipo_cuenta || 'N/A');
            $('#modal-nombre_banco').text(asociadoData.nombre_banco || 'N/A');
            $('#modal-cta_empresa').text(asociadoData.cta_empresa || 'N/A');

            // Poblamos todos los campos numéricos (INCLUYENDO montorhabe)
            const fields = [
                'salinicial', 'deuda_inic', 'totalaport', 'totalprest', 'totalrete', 
                'totalcaja', 'totalabon', 'totalcolab', 'totalliqui', 
                'totalreint', 'totalcance', 'totalctasx', 'retencion', 'pag_prest', 
                'fianza', 'negativo', 'dctoseguro', 'reint_int', 'aporte', 'colab', 
                'aporte2', 'montorhabe'  // AGREGADO: montorhabe
            ];
            
            fields.forEach(field => {
                $('#modal-' + field).text(formatCurrencyJS(asociadoData[field]));
            });
            
            // Mostrar valores calculados
            $('#modal-montorhabe-calculado').text(formatCurrencyJS(montoHaber));
            $('#modal-credito').text(formatCurrencyJS(credito));
            
            // Configurar el botón de impresión
            $('#print-pdf-btn').off('click').on('click', function() {
                printAsociadoPDF(asociadoData);
            });
        });
    });
    </script>
</body>
</html>