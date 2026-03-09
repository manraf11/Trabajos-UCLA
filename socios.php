<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre']; // Obtenemos el nombre de la sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Socios - Caja de Ahorro</title>

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

    /* Estilos para el estado de deuda */
    .deuda-activa {
        background-color: #d4edda;
        color: #155724;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .deuda-inactiva {
        background-color: #f8d7da;
        color: #721c24;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: bold;
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
                <a href="socios.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Gestión de Socios">
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
                    <h1><i class="fas fa-users-cog me-2"></i> Gestión de Socios</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoSocioModal">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Socio
                    </button>
                </div>
                
                
               <!-- Resumen -->
<div class="member-summary">
    <div class="row">
        <div class="col-md-3">
            <div class="summary-item">
                <span>Total Socios:</span>
                <span class="fw-bold" id="total-socios">0</span>
            </div>
            <div class="summary-item">
                <span>Activos :</span>
                <span class="fw-bold" id="total-activos">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-item">
                <span>Nuevos este mes:</span>
                <span class="fw-bold" id="nuevos-mes">0</span>
            </div>
            <div class="summary-item">
                <span>Inactivos :</span>
                <span class="fw-bold" id="total-inactivos">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-item">
                <span>Aportes promedio:</span>
                <span class="fw-bold" id="aporte-promedio">Bs0.00</span>
            </div>
            <div class="summary-item">
                <span>Suspendidos :</span>
                <span class="fw-bold" id="total-suspendidos">0</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-item">
                <span>Saldo promedio:</span>
                <span class="fw-bold" id="saldo-promedio">Bs0.00</span>
            </div>
            <div class="summary-item">
                <span>Jubilados:</span>
                <span class="fw-bold" id="total-jubilados">0</span>
            </div>
        </div>
    </div>
</div>
                
                <!-- Filtros y búsqueda -->
                <div class="filter-section">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="busqueda" class="form-label">Búsqueda</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="busqueda" placeholder="Nombre, ID o Cédula">
                                <button class="btn btn-outline-secondary" type="button" id="btnBuscar">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label for="estadoSocio" class="form-label">Estado</label>
                            <select class="form-select" id="estadoSocio">
                                <option value="">Todos</option>
                                <option value="A">Activo</option>
                                <option value="I">Inactivo</option>
                                <option value="S">Suspendido</option>
                                <option value="J">Jubilado</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                            <select class="form-select" id="tipo_nomina">
                                <option value="">Todos</option>
                                <option value="01">Nómina 01</option>
                                <option value="02">Nómina 02</option>
                                <option value="03">Nómina 03</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">&nbsp;</label>
                            <button class="btn btn-outline-primary w-100" id="btnFiltrar">
                                <i class="fas fa-filter me-2"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones rápidas -->
                <div class="quick-actions">
                    <button class="btn btn-outline-secondary" id="btnExportar">
                        <i class="fas fa-file-export me-2"></i>Exportar
                    </button>
                    <button class="btn btn-outline-secondary" id="btnImprimir">
                        <i class="fas fa-print me-2"></i>Imprimir Listado
                    </button>
                    <button class="btn btn-outline-secondary" id="btnComunicado">
                        <i class="fas fa-envelope me-2"></i>Enviar Comunicado
                    </button>
                    <button class="btn btn-outline-secondary" id="btnActualizarEstados">
                        <i class="fas fa-sync-alt me-2"></i>Actualizar Estados
                    </button>
                </div>
                
                <!-- Listado de socios -->
                <div class="card members-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">Listado de Socios</h3>
                        <span class="badge bg-primary" id="total-registros">0 registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover members-table mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Cédula</th>
                                        <th>Tipo Nómina</th>
                                        <th>Fecha Ingreso</th>
                                        <th>Saldo</th>
                                        <th>Estado</th>
                                        <th>Estado Deuda</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-socios">
                                    <!-- Los datos se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0" id="paginacion">
                                <!-- La paginación se cargará dinámicamente -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Nuevo Socio -->
    <div class="modal fade" id="nuevoSocioModal" tabindex="-1" aria-labelledby="nuevoSocioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoSocioModalLabel"><i class="fas fa-user-plus me-2"></i>Registrar Nuevo Socio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevoSocio">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cod_empleado" class="form-label">Código de Empleado*</label>
                                <input type="text" class="form-control" id="cod_empleado" required>
                            </div>
                            <div class="col-md-6">
                                <label for="cedula" class="form-label">Cédula*</label>
                                <input type="text" class="form-control" id="cedula" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="nombre" class="form-label">Nombre Completo*</label>
                                <input type="text" class="form-control" id="nombre" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="tipo_nomina" class="form-label">Tipo de Nómina*</label>
                                <select class="form-select" id="tipo_nomina_modal" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="01">Nómina 01</option>
                                    <option value="02">Nómina 02</option>
                                    <option value="03">Nómina 03</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="nacionalidad" class="form-label">Nacionalidad</label>
                                <input type="text" class="form-control" id="nacionalidad">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Estado Laboral</label>
                                <select class="form-select" id="status">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Jubilado">Jubilado</option>
                                    <option value="Suspendido">Suspendido</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="fecha_ingreso" class="form-label">Fecha de Ingreso*</label>
                                <input type="date" class="form-control" id="fecha_ingreso" required>
                            </div>
                            <div class="col-md-4">
                                <label for="cta_empleado" class="form-label">Cuenta Bancaria</label>
                                <input type="text" class="form-control" id="cta_empleado">
                            </div>
                            <div class="col-md-4">
                                <label for="tipo_cuenta" class="form-label">Tipo de Cuenta</label>
                                <select class="form-select" id="tipo_cuenta">
                                    <option value="">Seleccionar...</option>
                                    <option value="Ahorro">Ahorro</option>
                                    <option value="Corriente">Corriente</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre_banco" class="form-label">Nombre del Banco</label>
                                <input type="text" class="form-control" id="nombre_banco">
                            </div>
                            <div class="col-md-6">
                                <label for="sueldo" class="form-label">Sueldo Base</label>
                                <input type="number" step="0.01" class="form-control" id="sueldo">
                            </div>
                        </div>
                        
                        <!-- Campos de Saldo Inicial y Deuda Inicial -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="salinicial" class="form-label">Saldo Inicial*</label>
                                <input type="number" step="0.01" class="form-control" id="salinicial" required>
                            </div>
                            <div class="col-md-6">
                                <label for="deuda_inic" class="form-label">Deuda Inicial</label>
                                <input type="number" step="0.01" class="form-control" id="deuda_inic" value="0.00">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="foto" class="form-label">Foto (Opcional)</label>
                                <input type="file" class="form-control" id="foto" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarSocio">
                        <i class="fas fa-save me-2"></i>Guardar Socio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle Socio -->
<div class="modal fade" id="detalleSocioModal" tabindex="-1" aria-labelledby="detalleSocioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleSocioModalLabel"><i class="fas fa-user-circle me-2"></i>Detalle Completo del Socio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3 text-center">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Foto Socio" class="img-thumbnail mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <h5 class="mb-1" id="detalle-nombre">Juan Pérez Gómez</h5>
                        <p class="text-muted mb-1" id="detalle-id">ID: 10025</p>
                        <span class="badge bg-success" id="detalle-estado">Activo</span>
                    </div>
                    <div class="col-md-9">
                        <ul class="nav nav-tabs" id="socioTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                                    <i class="fas fa-info-circle me-2"></i>Información Básica
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="finanzas-tab" data-bs-toggle="tab" data-bs-target="#finanzas" type="button" role="tab" aria-controls="finanzas" aria-selected="false">
                                    <i class="fas fa-wallet me-2"></i>Información Financiera
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="bancaria-tab" data-bs-toggle="tab" data-bs-target="#bancaria" type="button" role="tab" aria-controls="bancaria" aria-selected="false">
                                    <i class="fas fa-university me-2"></i>Datos Bancarios
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content p-3 border border-top-0" id="socioTabsContent">
                            <!-- Pestaña Información Básica -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Código de Empleado:</label>
                                            <p class="fw-bold" id="detalle-cod-empleado"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Cédula:</label>
                                            <p class="fw-bold" id="detalle-cedula"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo Nómina:</label>
                                            <p class="fw-bold" id="detalle-tipo-nomina"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nacionalidad:</label>
                                            <p class="fw-bold" id="detalle-nacionalidad"></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Estado Laboral:</label>
                                            <p class="fw-bold" id="detalle-status"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Fecha Ingreso:</label>
                                            <p class="fw-bold" id="detalle-fecha-ingreso"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Estado en Sistema:</label>
                                            <p class="fw-bold" id="detalle-statu"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observaciones:</label>
                                            <p class="fw-bold" id="detalle-observaciones"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña Información Financiera -->
                            <div class="tab-pane fade" id="finanzas" role="tabpanel" aria-labelledby="finanzas-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Saldo Inicial:</label>
                                            <p class="fw-bold" id="detalle-saldo-inicial"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Deuda Inicial:</label>
                                            <p class="fw-bold" id="detalle-deuda-inicial"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Estado de Deuda:</label>
                                            <p class="fw-bold" id="detalle-estado-deuda"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Sueldo Base:</label>
                                            <p class="fw-bold" id="detalle-sueldo"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Total Aportes:</label>
                                            <p class="fw-bold" id="detalle-total-aportes"></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Total Préstamos:</label>
                                            <p class="fw-bold" id="detalle-total-prestamos"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Pago de Préstamos:</label>
                                            <p class="fw-bold" id="detalle-pago-prestamos"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Total Retenciones:</label>
                                            <p class="fw-bold" id="detalle-total-retenciones"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Monto a Haber:</label>
                                            <p class="fw-bold" id="detalle-monto-haber"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Crédito:</label>
                                            <p class="fw-bold" id="detalle-credito"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña Datos Bancarios -->
                            <div class="tab-pane fade" id="bancaria" role="tabpanel" aria-labelledby="bancaria-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cuenta Bancaria:</label>
                                            <p class="fw-bold" id="detalle-cuenta"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Cuenta:</label>
                                            <p class="fw-bold" id="detalle-tipo-cuenta"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Banco:</label>
                                            <p class="fw-bold" id="detalle-banco"></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cuenta Empresa:</label>
                                            <p class="fw-bold" id="detalle-cuenta-empresa"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Fianza:</label>
                                            <p class="fw-bold" id="detalle-fianza"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Negativo:</label>
                                            <p class="fw-bold" id="detalle-negativo"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnImprimirFicha">
                    <i class="fas fa-print me-2"></i>Imprimir Ficha
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Socio -->
<div class="modal fade" id="editarSocioModal" tabindex="-1" aria-labelledby="editarSocioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarSocioModalLabel"><i class="fas fa-edit me-2"></i>Editar Socio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarSocio">
                    <input type="hidden" id="edit-cod-empleado">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit-cedula" class="form-label">Cédula*</label>
                            <input type="text" class="form-control" id="edit-cedula" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-nombre" class="form-label">Nombre Completo*</label>
                            <input type="text" class="form-control" id="edit-nombre" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit-tipo-nomina" class="form-label">Tipo de Nómina*</label>
                            <select class="form-select" id="edit-tipo-nomina" required>
                                <option value="01">Nómina 01</option>
                                <option value="02">Nómina 02</option>
                                <option value="03">Nómina 03</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-nacionalidad" class="form-label">Nacionalidad</label>
                            <input type="text" class="form-control" id="edit-nacionalidad">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado Laboral</label>
                            <select class="form-select" id="edit-status">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Jubilado">Jubilado</option>
                                <option value="Suspendido">Suspendido</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit-fecha-ingreso" class="form-label">Fecha de Ingreso*</label>
                            <input type="date" class="form-control" id="edit-fecha-ingreso" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-statu" class="form-label">Estado en Sistema*</label>
                            <select class="form-select" id="edit-statu" required>
                                <option value="A">Activo</option>
                                <option value="I">Inactivo</option>
                                <option value="S">Suspendido</option>
                                <option value="J">Jubilado</option>
                                <option value="L">Liquidado</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-saldo-inicial" class="form-label">Saldo Inicial*</label>
                            <input type="number" step="0.01" class="form-control" id="edit-saldo-inicial" required>
                        </div>
                    </div>
                    
                    <!-- Campos de Sueldo y Deuda Inicial -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit-sueldo" class="form-label">Sueldo Base</label>
                            <input type="number" step="0.01" class="form-control" id="edit-sueldo">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-deuda-inic" class="form-label">Deuda Inicial</label>
                            <input type="number" step="0.01" class="form-control" id="edit-deuda-inic">
                        </div>
                    </div>
                    
                    <!-- Nuevo campo: Estado de Deuda -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-estado-deuda">
                                <label class="form-check-label" for="edit-estado-deuda">
                                    <strong>Deuda Activa</strong> (Al marcar, la deuda se considera ACTIVA independientemente del monto)
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Si está marcado: Deuda ACTIVA (estado = true) | 
                                Si no está marcado: Deuda INACTIVA (estado = false)
                            </small>
                            <div id="info-estado-deuda" class="form-text"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit-foto" class="form-label">Foto (Opcional)</label>
                            <input type="file" class="form-control" id="edit-foto" accept="image/*">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="edit-observaciones" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnActualizarSocio">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
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
        
        // Variables para paginación
        let currentPage = 1;
        const itemsPerPage = 10;
        let totalSocios = 0;
        let sociosData = [];
        
        // Cargar datos iniciales
        cargarSocios();
        cargarResumen();
        
        // Función para cargar los socios
        function cargarSocios(pagina = 1) {
            currentPage = pagina;
            
            // Mostrar loader
            $('#tabla-socios').html('<tr><td colspan="9" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>');
            
            $.ajax({
                url: 'obtener_asociados.php',
                type: 'GET',
                data: {
                    pagina: pagina,
                    itemsPorPagina: itemsPerPage,
                    busqueda: $('#busqueda').val(),
                    estado: $('#estadoSocio').val(),
                    tipo_nomina: $('#tipo_nomina').val()
                },
                success: function(response) {
                    console.log("Respuesta del servidor:", response);
                    
                    if(response.success) {
                        sociosData = response.data;
                        totalSocios = response.total;
                        
                        // Actualizar contador
                        $('#total-registros').text(totalSocios + ' registros');
                        $('#tabla-socios').empty();
                        
                        if(response.data.length === 0) {
                            $('#tabla-socios').html('<tr><td colspan="9" class="text-center">No se encontraron socios</td></tr>');
                            return;
                        }
                        
                        // Llenar tabla
                        response.data.forEach(function(asociado) {
                            // Convertir statu a texto descriptivo
                            let estadoClass, estadoText;
                            switch(asociado.statu) {
                                case 'A':
                                    estadoClass = 'bg-success';
                                    estadoText = 'Activo';
                                    break;
                                case 'S':
                                    estadoClass = 'bg-warning text-dark';
                                    estadoText = 'Suspendido';
                                    break;
                                case 'J':
                                    estadoClass = 'bg-info';
                                    estadoText = 'Jubilado';
                                    break;
                                case 'L':
                                    estadoClass = 'bg-secondary';
                                    estadoText = 'Liquidado';
                                    break;
                                default:
                                    estadoClass = 'bg-secondary';
                                    estadoText = 'Inactivo';
                            }
                            
                            // VERSIÓN COMPACTA CORREGIDA
                            const estadoDeuda = convertirABooleano(asociado.estado);
                            const deudaClass = estadoDeuda ? 'deuda-activa' : 'deuda-inactiva';
                            const deudaText = estadoDeuda ? 'ACTIVA' : 'INACTIVA';
                            
                            console.log(`Socio: ${asociado.nombre}, Estado Deuda: ${asociado.estado}, Interpretado como: ${estadoDeuda}`);
                            
                            // Formatear fecha
                            const fechaIngreso = asociado.fecha_ingreso ? 
                                new Date(asociado.fecha_ingreso).toLocaleDateString('es-ES') : 
                                'No especificada';
                            
                            // Formatear saldo
                            const saldo = asociado.salinicial ? 
                                parseFloat(asociado.salinicial).toFixed(2) : 
                                '0.00';
                            
                            $('#tabla-socios').append(`
                                <tr>
                                    <td>${asociado.cod_empleado}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://randomuser.me/api/portraits/men/${Math.floor(Math.random() * 100)}.jpg" 
                                                 alt="Avatar" class="member-avatar">
                                            <span>${asociado.nombre}</span>
                                        </div>
                                    </td>
                                    <td>${asociado.cedula}</td>
                                    <td>${asociado.tipo_nomina}</td>
                                    <td>${fechaIngreso}</td>
                                    <td>Bs${saldo}</td>
                                    <td><span class="member-status ${estadoClass}">${estadoText}</span></td>
                                    <td><span class="${deudaClass}">${deudaText}</span></td>
                                    <td class="member-actions">
                                        <button class="btn btn-sm btn-outline-primary ver-detalle" 
                                                title="Ver Detalle" data-id="${asociado.cod_empleado}">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary editar-socio" 
                                                title="Editar" data-id="${asociado.cod_empleado}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        ${asociado.statu === 'A' ? 
                                            `<button class="btn btn-sm btn-outline-danger cambiar-estado" 
                                                     title="Desactivar" data-id="${asociado.cod_empleado}" data-estado="I">
                                                <i class="fas fa-user-slash"></i>
                                            </button>` : 
                                            `<button class="btn btn-sm btn-outline-success cambiar-estado" 
                                                     title="Activar" data-id="${asociado.cod_empleado}" data-estado="A">
                                                <i class="fas fa-user-check"></i>
                                            </button>`}
                                    </td>
                                </tr>
                            `);
                        });
                        
                        // Configurar paginación
                        configurarPaginacion(response.total, pagina);
                    } else {
                        $('#tabla-socios').html(`<tr><td colspan="9" class="text-center text-danger">${response.message}</td></tr>`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error en AJAX:", status, error);
                    $('#tabla-socios').html('<tr><td colspan="9" class="text-center text-danger">Error al cargar los datos</td></tr>');
                }
            });
        }
        
        // Función para cargar el resumen
        function cargarResumen() {
            $.ajax({
                url: 'obtener_resumen.php',
                type: 'GET',
                success: function(response) {
                    if(response.success) {
                        // Actualizar todos los valores del resumen
                        $('#total-socios').text(response.totalSocios);
                        $('#total-activos').text(response.totalActivos);
                        
                        // Calcular inactivos (suma de I + S + J + L)
                        const totalInactivos = response.totalInactivos ;
                        $('#total-inactivos').text(totalInactivos);
                        
                        $('#nuevos-mes').text(response.nuevosEsteMes);
                        $('#aporte-promedio').text('Bs' + response.aportePromedio.toFixed(2));
                        $('#prestamos-activos').text(response.prestamosActivos);
                        $('#saldo-promedio').text('Bs' + response.saldoPromedio.toFixed(2));
                        
                        // Mostrar otros estados si los has añadido al HTML
                        $('#total-suspendidos').text(response.totalSuspendidos);
                        $('#total-jubilados').text(response.totalJubilados);
                        $('#total-liquidados').text(response.totalLiquidados);
                    } else {
                        console.error('Error al cargar el resumen:', response.message);
                        // Mostrar valores por defecto en caso de error
                        $('#total-socios, #total-activos, #total-inactivos, #nuevos-mes, #prestamos-activos').text('0');
                        $('#aporte-promedio, #saldo-promedio').text('Bs0.00');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error en la solicitud AJAX del resumen:', status, error);
                    // Mostrar valores por defecto en caso de error
                    $('#total-socios, #total-activos, #total-inactivos, #nuevos-mes, #prestamos-activos').text('0');
                    $('#aporte-promedio, #saldo-promedio').text('Bs0.00');
                }
            });
        }
        
        // Función auxiliar para convertir cualquier valor a booleano
        function convertirABooleano(valor) {
            if (valor === null || valor === undefined) return false;
            if (typeof valor === 'boolean') return valor;
            if (typeof valor === 'number') return valor !== 0;
            if (typeof valor === 'string') {
                const str = valor.toLowerCase().trim();
                return str === 'true' || str === '1' || str === 't' || str === 'yes' || str === 'y';
            }
            return Boolean(valor);
        }
        
        // Función para formatear fecha
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES');
        }
        
        // Función para configurar la paginación
        function configurarPaginacion(totalItems, currentPage) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            let paginationHtml = '';
            
            if (currentPage > 1) {
                paginationHtml += `
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${currentPage - 1}">Anterior</a>
                    </li>
                `;
            } else {
                paginationHtml += `
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Anterior</a>
                    </li>
                `;
            }
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    paginationHtml += `
                        <li class="page-item active"><a class="page-link" href="#" data-page="${i}">${i}</a></li>
                    `;
                } else {
                    paginationHtml += `
                        <li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>
                    `;
                }
            }
            
            if (currentPage < totalPages) {
                paginationHtml += `
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${currentPage + 1}">Siguiente</a>
                    </li>
                `;
            } else {
                paginationHtml += `
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Siguiente</a>
                    </li>
                `;
            }
            
            $('#paginacion').html(paginationHtml);
        }
        
        // Manejar clic en paginación
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page) {
                cargarSocios(page);
            }
        });
        
        // Filtrar socios
        $('#btnFiltrar').click(function() {
            // Mostrar spinner
            $(this).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Filtrando...');
            
            cargarSocios(1);
            
            // Restaurar botón
            setTimeout(() => {
                $(this).html('<i class="fas fa-filter me-2"></i>Filtrar');
            }, 500);
        });
        
        // Buscar socios
        $('#btnBuscar').click(function() {
            const termino = $('#busqueda').val();
            if (termino.length > 2) {
                // Mostrar spinner
                $(this).html('<i class="fas fa-spinner fa-spin me-2"></i>Buscando...');
                
                cargarSocios(1);
                
                // Restaurar botón
                setTimeout(() => {
                    $(this).html('<i class="fas fa-search"></i>');
                }, 500);
            } else if (termino.length === 0) {
                cargarSocios(1);
            }
        });
        
        // Guardar nuevo socio
        $('#btnGuardarSocio').click(function() {
            const form = $('#formNuevoSocio');
            if (form[0].checkValidity()) {
                // Mostrar spinner
                $(this).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...');
                
                // Obtener los datos del formulario
                const formData = {
                    cod_empleado: $('#cod_empleado').val(),
                    cedula: $('#cedula').val(),
                    nombre: $('#nombre').val(),
                    tipo_nomina: $('#tipo_nomina_modal').val(),
                    nacionalidad: $('#nacionalidad').val(),
                    status: $('#status').val(),
                    fecha_ingreso: $('#fecha_ingreso').val(),
                    cta_empleado: $('#cta_empleado').val(),
                    tipo_cuenta: $('#tipo_cuenta').val(),
                    nombre_banco: $('#nombre_banco').val(),
                    sueldo: $('#sueldo').val() || 0,
                    salinicial: $('#salinicial').val() || 0,
                    deuda_inic: $('#deuda_inic').val() || 0,
                    observaciones: $('#observaciones').val()
                };

                // Enviar datos al servidor
                $.ajax({
                    url: 'guardar_asociado.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if(response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Socio registrado',
                                text: 'El nuevo socio ha sido registrado correctamente',
                                confirmButtonText: 'Aceptar'
                            });
                            
                            // Cerrar modal y resetear formulario
                            $('#nuevoSocioModal').modal('hide');
                            form[0].reset();
                            $('#btnGuardarSocio').html('<i class="fas fa-save me-2"></i>Guardar Socio');
                            
                            // Recargar la tabla de socios y resumen
                            cargarSocios();
                            cargarResumen();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Ocurrió un error al guardar el socio',
                                confirmButtonText: 'Aceptar'
                            });
                            $('#btnGuardarSocio').html('<i class="fas fa-save me-2"></i>Guardar Socio');
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo conectar con el servidor',
                            confirmButtonText: 'Aceptar'
                        });
                        $('#btnGuardarSocio').html('<i class="fas fa-save me-2"></i>Guardar Socio');
                    }
                });
            } else {
                form[0].reportValidity();
            }
        });
        
        // Ver detalle del socio
        $(document).on('click', '.ver-detalle', function() {
            const codEmpleado = $(this).data('id');
            cargarDetalleSocio(codEmpleado);
        });
        
        // Cambiar estado del socio
        $(document).on('click', '.cambiar-estado', function() {
            const codEmpleado = $(this).data('id');
            const nuevoEstado = $(this).data('estado');
            const socio = sociosData.find(s => s.cod_empleado === codEmpleado);
            
            if (socio) {
                const accion = nuevoEstado === 'A' ? 'activar' : 'desactivar';
                const estadoTexto = nuevoEstado === 'A' ? 'Activo' : 'Inactivo';
                
                Swal.fire({
                    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Socio?`,
                    text: `¿Está seguro que desea ${accion} a ${socio.nombre} (ID: ${codEmpleado})?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: `Sí, ${accion}`,
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar spinner en el botón
                        const $btn = $(this);
                        $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                        
                        // Enviar solicitud al servidor
                        $.ajax({
                            url: 'cambiar_estado.php',
                            type: 'POST',
                            data: {
                                cod_empleado: codEmpleado,
                                nuevo_estado: nuevoEstado
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Actualizar interfaz
                                    $btn.closest('tr').find('.member-status')
                                        .removeClass('bg-success bg-warning bg-secondary bg-info')
                                        .addClass(nuevoEstado === 'A' ? 'bg-success' : 
                                                 nuevoEstado === 'S' ? 'bg-warning' :
                                                 nuevoEstado === 'J' ? 'bg-info' : 'bg-secondary')
                                        .text(estadoTexto);
                                    
                                    if (nuevoEstado === 'A') {
                                        $btn.removeClass('btn-outline-success').addClass('btn-outline-danger')
                                            .attr('title', 'Desactivar')
                                            .data('estado', 'I')
                                            .html('<i class="fas fa-user-slash"></i>');
                                    } else {
                                        $btn.removeClass('btn-outline-danger').addClass('btn-outline-success')
                                            .attr('title', 'Activar')
                                            .data('estado', 'A')
                                            .html('<i class="fas fa-user-check"></i>');
                                    }
                                    
                                    Swal.fire(
                                        '¡Éxito!',
                                        `El socio ha sido ${accion}do.`,
                                        'success'
                                    );
                                    
                                    // Actualizar resumen
                                    cargarResumen();
                                } else {
                                    Swal.fire(
                                        'Error',
                                        response.message || `No se pudo ${accion} el socio.`,
                                        'error'
                                    );
                                    $btn.html(nuevoEstado === 'A' ? '<i class="fas fa-user-check"></i>' : '<i class="fas fa-user-slash"></i>');
                                }
                            },
                            error: function() {
                                Swal.fire(
                                    'Error',
                                    `No se pudo conectar con el servidor para ${accion} el socio.`,
                                    'error'
                                );
                                $btn.html(nuevoEstado === 'A' ? '<i class="fas fa-user-check"></i>' : '<i class="fas fa-user-slash"></i>');
                            }
                        });
                    }
                });
            }
        });
        
        // Exportar datos
        $('#btnExportar').click(function() {
            // Mostrar spinner
            $(this).html('<i class="fas fa-spinner fa-spin me-2"></i>Exportando...');
            
            // Simular exportación
            setTimeout(() => {
                $(this).html('<i class="fas fa-file-export me-2"></i>Exportar');
                Swal.fire({
                    icon: 'success',
                    title: 'Exportación completada',
                    text: 'Los datos han sido exportados correctamente',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }, 1500);
        });
        
        // Imprimir listado
        $('#btnImprimir').click(function() {
            window.print();
        });
        
        // Actualizar estados
        $('#btnActualizarEstados').click(function() {
            // Mostrar spinner
            $(this).html('<i class="fas fa-spinner fa-spin me-2"></i>Actualizando...');
            
            // Simular actualización
            setTimeout(() => {
                $(this).html('<i class="fas fa-sync-alt me-2"></i>Actualizar Estados');
                Swal.fire({
                    icon: 'success',
                    title: 'Estados actualizados',
                    text: 'Los estados de los socios han sido actualizados correctamente',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                
                // Recargar datos
                cargarSocios();
                cargarResumen();
            }, 2000);
        });
        
        // Inicializar tooltips para botones de acción
        $('[title]').tooltip();
    });
    
    // Función para cargar los detalles completos del socio
    function cargarDetalleSocio(codEmpleado) {
        $.ajax({
            url: 'obtener_socio_completo.php',
            type: 'GET',
            data: { cod_empleado: codEmpleado },
            success: function(response) {
                if(response.success) {
                    const socio = response.data;
                    
                    // Información básica
                    $('#detalle-nombre').text(socio.nombre);
                    $('#detalle-id').text(`ID: ${socio.cod_empleado}`);
                    $('#detalle-cod-empleado').text(socio.cod_empleado);
                    $('#detalle-cedula').text(socio.cedula);
                    $('#detalle-tipo-nomina').text(socio.tipo_nomina);
                    $('#detalle-nacionalidad').text(socio.nacionalidad || 'No especificado');
                    $('#detalle-status').text(socio.status || 'No especificado');
                    $('#detalle-fecha-ingreso').text(socio.fecha_ingreso ? new Date(socio.fecha_ingreso).toLocaleDateString('es-ES') : 'No especificada');
                    $('#detalle-observaciones').text(socio.observaciones || 'Ninguna');
                    
                    // Estado en sistema
                    let estadoClass, estadoText;
                    switch(socio.statu) {
                        case 'A': estadoClass = 'bg-success'; estadoText = 'Activo'; break;
                        case 'I': estadoClass = 'bg-secondary'; estadoText = 'Inactivo'; break;
                        case 'S': estadoClass = 'bg-warning text-dark'; estadoText = 'Suspendido'; break;
                        case 'J': estadoClass = 'bg-info'; estadoText = 'Jubilado'; break;
                        case 'L': estadoClass = 'bg-dark'; estadoText = 'Liquidado'; break;
                        default: estadoClass = 'bg-secondary'; estadoText = 'Desconocido';
                    }
                    $('#detalle-estado').removeClass().addClass(`badge ${estadoClass}`).text(estadoText);
                    $('#detalle-statu').text(estadoText);
                    
                    // Estado de la deuda en información financiera
                    const estadoDeuda = convertirABooleano(socio.estado);
                    $('#detalle-estado-deuda').text(estadoDeuda ? 'ACTIVA' : 'INACTIVA');
                    
                    // Información financiera
                    $('#detalle-saldo-inicial').text(socio.salinicial ? `Bs${parseFloat(socio.salinicial).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-deuda-inicial').text(socio.deuda_inic ? `Bs${parseFloat(socio.deuda_inic).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-sueldo').text(socio.sueldo ? `Bs${parseFloat(socio.sueldo).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-total-aportes').text(socio.totalaport ? `Bs${parseFloat(socio.totalaport).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-total-prestamos').text(socio.totalprest ? `Bs${parseFloat(socio.totalprest).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-pago-prestamos').text(socio.pag_prest ? `Bs${parseFloat(socio.pag_prest).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-total-retenciones').text(socio.totalrete ? `Bs${parseFloat(socio.totalrete).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-monto-haber').text(socio.montorhabe ? `Bs${parseFloat(socio.montorhabe).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-credito').text(socio.credito ? `Bs${parseFloat(socio.credito).toFixed(2)}` : 'Bs0.00');
                    
                    // Datos bancarios
                    $('#detalle-cuenta').text(socio.cta_empleado || 'No especificado');
                    $('#detalle-tipo-cuenta').text(socio.tipo_cuenta || 'No especificado');
                    $('#detalle-banco').text(socio.nombre_banco || 'No especificado');
                    $('#detalle-cuenta-empresa').text(socio.cta_empresa || 'No especificado');
                    $('#detalle-fianza').text(socio.fianza ? `Bs${parseFloat(socio.fianza).toFixed(2)}` : 'Bs0.00');
                    $('#detalle-negativo').text(socio.negativo ? `Bs${parseFloat(socio.negativo).toFixed(2)}` : 'Bs0.00');
                    
                    // Mostrar modal
                    $('#detalleSocioModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'No se pudo cargar la información del socio', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });
    }

    // Función optimizada para cargar datos en el formulario de edición
    function cargarFormularioEdicion(codEmpleado) {
        $.ajax({
            url: 'obtener_socio_completo.php',
            type: 'GET',
            data: { cod_empleado: codEmpleado },
            success: function(response) {
                if(response.success) {
                    const socio = response.data;
                    
                    // Llenar formulario
                    $('#edit-cod-empleado').val(socio.cod_empleado);
                    $('#edit-cedula').val(socio.cedula);
                    $('#edit-nombre').val(socio.nombre);
                    $('#edit-tipo-nomina').val(socio.tipo_nomina);
                    $('#edit-nacionalidad').val(socio.nacionalidad || '');
                    $('#edit-status').val(socio.status || 'Activo');
                    $('#edit-fecha-ingreso').val(socio.fecha_ingreso);
                    $('#edit-statu').val(socio.statu || 'A');
                    $('#edit-saldo-inicial').val(parseFloat(socio.salinicial || 0).toFixed(2));
                    $('#edit-deuda-inic').val(parseFloat(socio.deuda_inic || 0).toFixed(2));
                    $('#edit-sueldo').val(socio.sueldo ? parseFloat(socio.sueldo).toFixed(2) : '');
                    $('#edit-observaciones').val(socio.observaciones || '');
                    
                    // MANEJO ROBUSTO DEL ESTADO DE DEUDA
                    const estadoDeuda = convertirABooleano(socio.estado);
                    console.log('Estado de deuda:', {
                        valor_original: socio.estado,
                        valor_convertido: estadoDeuda,
                        tipo_original: typeof socio.estado
                    });
                    
                    // Establecer el checkbox
                    $('#edit-estado-deuda').prop('checked', estadoDeuda);
                    
                    // Actualizar la información visual
                    actualizarInfoEstadoDeuda();
                    
                    // Mostrar modal
                    $('#editarSocioModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'No se pudo cargar la información del socio', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });
    }

    // Función auxiliar para convertir cualquier valor a booleano
    function convertirABooleano(valor) {
        if (valor === null || valor === undefined) return false;
        if (typeof valor === 'boolean') return valor;
        if (typeof valor === 'number') return valor !== 0;
        if (typeof valor === 'string') {
            const str = valor.toLowerCase().trim();
            return str === 'true' || str === '1' || str === 't' || str === 'yes' || str === 'y';
        }
        return Boolean(valor);
    }

    // Función mejorada para actualizar la información del estado de deuda
    function actualizarInfoEstadoDeuda() {
        const estaActiva = $('#edit-estado-deuda').is(':checked');
        const deudaMonto = parseFloat($('#edit-deuda-inic').val()) || 0;
        
        let mensaje = '';
        let color = '';
        let icono = '';
        
        if (estaActiva) {
            icono = '✅';
            mensaje = 'Deuda ACTIVA - Se guardará con estado = true';
            color = 'text-success';
        } else {
            icono = '❌';
            mensaje = 'Deuda INACTIVA - Se guardará con estado = false';
            color = 'text-danger';
        }
        
        // Añadir información sobre el monto si existe
        if (deudaMonto > 0 && !estaActiva) {
            mensaje += ' (⚠️ Hay monto de deuda pero está marcado como INACTIVO)';
            color = 'text-warning';
        }
        
        // Actualizar o crear elemento de información
        let $infoElement = $('#info-estado-deuda');
        if ($infoElement.length === 0) {
            $infoElement = $('<div id="info-estado-deuda" class="form-text mt-2"></div>');
            $('#edit-estado-deuda').closest('.form-check').append($infoElement);
        }
        
        $infoElement.removeClass('text-success text-danger text-warning')
                    .addClass(color)
                    .html(`<strong>${icono} ${mensaje}</strong>`);
    }

    // Función optimizada para guardar cambios
    $('#btnActualizarSocio').click(function() {
        const form = $('#formEditarSocio');
        if (form[0].checkValidity()) {
            // Mostrar spinner
            const $btn = $(this);
            $btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...');
            $btn.prop('disabled', true);
            
            // Obtener datos del formulario
            const formData = {
                cod_empleado: $('#edit-cod-empleado').val(),
                cedula: $('#edit-cedula').val(),
                nombre: $('#edit-nombre').val(),
                tipo_nomina: $('#edit-tipo-nomina').val(),
                nacionalidad: $('#edit-nacionalidad').val(),
                status: $('#edit-status').val(),
                fecha_ingreso: $('#edit-fecha-ingreso').val(),
                statu: $('#edit-statu').val(),
                salinicial: $('#edit-saldo-inicial').val(),
                deuda_inic: $('#edit-deuda-inic').val() || 0,
                sueldo: $('#edit-sueldo').val(),
                observaciones: $('#edit-observaciones').val(),
                estado_forzado: $('#edit-estado-deuda').is(':checked') ? '1' : '0'
            };

            console.log('Datos a enviar:', formData);

            // Enviar datos al servidor
            $.ajax({
                url: 'actualizar_socio.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    $btn.html('<i class="fas fa-save me-2"></i>Guardar Cambios');
                    $btn.prop('disabled', false);
                    
                    if(response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: 'Los datos del socio han sido actualizados correctamente',
                            confirmButtonText: 'Aceptar'
                        });
                        
                        // Cerrar modal y actualizar lista
                        $('#editarSocioModal').modal('hide');
                        cargarSocios();
                        cargarResumen();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar el socio',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $btn.html('<i class="fas fa-save me-2"></i>Guardar Cambios');
                    $btn.prop('disabled', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con el servidor: ' + error,
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
        } else {
            form[0].reportValidity();
        }
    });

    // Eventos para el formulario de edición
    $(document).on('change', '#edit-estado-deuda', function() {
        actualizarInfoEstadoDeuda();
    });

    $(document).on('input', '#edit-deuda-inic', function() {
        actualizarInfoEstadoDeuda();
    });

    // Inicializar información al abrir el modal
    $('#editarSocioModal').on('shown.bs.modal', function() {
        actualizarInfoEstadoDeuda();
    });

    // Eventos para los botones de acción
    $(document).on('click', '.editar-socio', function() {
        const codEmpleado = $(this).data('id');
        cargarFormularioEdicion(codEmpleado);
    });

    // Exportar datos en formato TXT
    $('#btnExportar').click(function() {
        // Mostrar spinner
        $(this).html('<i class="fas fa-spinner fa-spin me-2"></i>Exportando...');
        
        // Obtener los filtros actuales
        const filtros = {
            busqueda: $('#busqueda').val(),
            estado: $('#estadoSocio').val(),
            tipo_nomina: $('#tipo_nomina').val()
        };
        
        // Crear un formulario temporal para enviar los datos
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'exportar_socios_txt.php';
        
        // Agregar los filtros como campos ocultos
        for (const key in filtros) {
            if (filtros[key]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = filtros[key];
                form.appendChild(input);
            }
        }
        
        // Agregar el formulario al documento y enviarlo
        document.body.appendChild(form);
        form.submit();
        
        // Limpiar después de enviar
        document.body.removeChild(form);
        
        // Restaurar el botón después de un breve retraso
        setTimeout(() => {
            $(this).html('<i class="fas fa-file-export me-2"></i>Exportar');
        }, 1000);
    });
</script>
</body>
</html>