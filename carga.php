<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre'];
$user_id = $user['id'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Datos - Caja de Ahorro</title>

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
        
        .summary-card, .module-card, .upload-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .summary-card:hover, .module-card:hover, .upload-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .upload-card .card-body {
            padding: 2rem;
            text-align: center;
        }
        
        .upload-card .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }
        
        .upload-card .card-title {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
        }
        
        /* Upload specific styles */
        .upload-area {
            border: 2px dashed #ced4da;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(0, 86, 179, 0.05);
        }
        
        .upload-area.active {
            border-color: var(--primary-color);
            background-color: rgba(0, 86, 179, 0.1);
        }
        
        .file-info {
            display: none;
            margin-top: 1rem;
            padding: 0.75rem;
            background-color: #e9ecef;
            border-radius: 0.5rem;
        }
        
        .template-download {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .preview-table {
            margin-top: 2rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .preview-table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50rem;
        }
        
        .upload-progress {
            height: 8px;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .upload-options {
            margin-bottom: 2rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
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
                <a href="pagos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-calculator"></i>Pagos
                    </a>
                    <a href="colaboraciones_creditos.php" class="list-group-item list-group-item-action " data-bs-toggle="tooltip" data-bs-placement="right" title="Colaboraciones y Créditos">
                    <i class="fas fa-handshake"></i>Colaboraciones y Créditos
                </a>
                <a href="consulta.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Consulta">
                    <i class="fas fa-search-dollar"></i>Módulo de Consulta
                </a>
                <a href="carga.php" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Carga de Datos">
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
                <div id="alerts-container"></div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-upload me-2"></i> Carga de Datos</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ayudaModal">
                        <i class="fas fa-question-circle me-2"></i>Ayuda
                    </button>
                </div>
                
                <!-- Pestañas de navegación -->
                <ul class="nav nav-tabs mb-4" id="uploadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="masiva-tab" data-bs-toggle="tab" data-bs-target="#masiva" type="button" role="tab" aria-controls="masiva" aria-selected="true">
                            <i class="fas fa-file-import me-2"></i>Carga Masiva
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button" role="tab" aria-controls="individual" aria-selected="false">
                            <i class="fas fa-user-edit me-2"></i>Carga Individual
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab" aria-controls="historial" aria-selected="false">
                            <i class="fas fa-history me-2"></i>Historial de Cargas
                        </button>
                    </li>
                </ul>
                
                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="uploadTabsContent">
                    <!-- Pestaña Carga Masiva -->
                    <div class="tab-pane fade show active" id="masiva" role="tabpanel" aria-labelledby="masiva-tab">
                        <div class="upload-options">
                            <h3 class="h5 mb-3">Opciones de Carga Masiva</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="tipoCarga" class="form-label">Tipo de Datos a Cargar:</label>
                                    <select class="form-select" id="tipoCarga">
                                        <option value="aportes">Aportes de Socios</option>
                                        <option value="descuentos">Descuentos por Nómina</option>
                                        <option value="prestamos">Préstamos</option>
                                        <option value="pagos">Pagos de Préstamos</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="formatoArchivo" class="form-label">Formato del Archivo:</label>
                                    <select class="form-select" id="formatoArchivo">
                                        <option value="excel">Excel (.xlsx, .xls)</option>
                                        <option value="txt">Archivo de Texto (.txt)</option>
                                        <option value="csv">CSV (.csv)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card upload-card mb-4">
                                    <div class="card-body">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <h3 class="card-title">Cargar Archivo</h3>
                                        
                                        <form id="uploadForm" enctype="multipart/form-data">
                                            <div class="upload-area" id="dropArea">
                                                <input type="file" id="fileInput" name="archivo" class="d-none" accept=".xlsx,.xls,.txt,.csv">
                                                <p class="mb-2">Arrastra y suelta tu archivo aquí</p>
                                                <p class="text-muted small mb-3">o</p>
                                                <button type="button" class="btn btn-primary" id="selectFileBtn">
                                                    <i class="fas fa-folder-open me-2"></i>Seleccionar Archivo
                                                </button>
                                            </div>
                                            
                                            <div class="file-info" id="fileInfo">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span id="fileName"></span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeFileBtn">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                                <div class="progress upload-progress mt-2">
                                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                                </div>
                                            </div>
                                        </form>
                                        
                                        <div class="template-download">
                                            <p class="mb-2">¿Necesitas una plantilla para tu archivo?</p>
                                            <button type="button" class="btn btn-outline-primary" id="downloadExcelTemplate">
                                                <i class="fas fa-file-download me-2"></i>Descargar Plantilla Excel
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary ms-2" id="downloadTextTemplate">
                                                <i class="fas fa-file-alt me-2"></i>Descargar Plantilla TXT
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card upload-card">
                                    <div class="card-body">
                                        <h3 class="card-title mb-4">Instrucciones</h3>
                                        <div class="alert alert-info">
                                            <h4 class="h6"><i class="fas fa-info-circle me-2"></i>Formato Requerido</h4>
                                            <ul class="small mb-0">
                                                <li>Archivos Excel: Máximo 5,000 registros</li>
                                                <li>Archivos TXT/CSV: Delimitado por pipes (|)</li>
                                                <li>Codificación: UTF-8</li>
                                                <li>Tamaño máximo: 10MB</li>
                                            </ul>
                                        </div>
                                        <div class="alert alert-warning">
                                            <h4 class="h6"><i class="fas fa-exclamation-triangle me-2"></i>Recomendaciones</h4>
                                            <ul class="small mb-0">
                                                <li>Verifica los datos antes de cargar</li>
                                                <li>Realiza una copia de seguridad</li>
                                                <li>Carga fuera de horario pico</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vista previa de datos -->
                        <div class="card d-none" id="previewCard">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h3 class="h5 mb-0">Vista Previa de Datos</h3>
                                <span class="badge bg-primary" id="totalRecords">0 registros</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID Socio</th>
                                                <th>Nombre</th>
                                                <th>Monto</th>
                                                <th>Fecha</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewData">
                                            <!-- Datos se cargarán dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmData">
                                    <label class="form-check-label" for="confirmData">
                                        Confirmo que los datos son correctos
                                    </label>
                                </div>
                                <button class="btn btn-success" id="processDataBtn" >
                                    <i class="fas fa-check-circle me-2"></i>Procesar Carga
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pestaña Carga Individual -->
                    <div class="tab-pane fade" id="individual" role="tabpanel" aria-labelledby="individual-tab">
                        <div class="card upload-card">
                            <div class="card-body">
                                <div class="upload-icon">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <h3 class="card-title mb-4">Registro Individual</h3>
                                
                                <form id="individualForm">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="tipoRegistro" class="form-label">Tipo de Registro</label>
                                            <select class="form-select" id="tipoRegistro" name="tipo_registro" required>
                                                <option value="" selected disabled>Seleccionar...</option>
                                                <option value="aporte">Aporte</option>
                                                <option value="descuento">Descuento</option>
                                                <option value="prestamo">Préstamo</option>
                                                <option value="pago">Pago de Préstamo</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="codigoemp" class="form-label">Código de Empleado del Socio</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="codigoemp" name="codigoemp" required>
                                                <button class="btn btn-outline-secondary" type="button" id="searchMemberBtn">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3" id="memberInfo" style="display: none;">
                                        <div class="col-12">
                                            <div class="alert alert-info py-2 mb-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong id="memberName">Nombre del Socio</strong> | 
                                                        <span id="memberId">ID: 0000</span> | 
                                                        <span id="memberBalance">Saldo: $0.00</span>
                                                    </div>
                                                    <button type="button" class="btn-close" id="closeMemberInfo"></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="monto" class="form-label">Monto</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Bs</span>
                                                <input type="number" step="0.01" class="form-control" id="monto" name="monto" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="fecha" class="form-label">Fecha</label>
                                            <input type="date" class="form-control" id="fecha" name="fecha" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="adjunto" class="form-label">Documento Adjunto (Opcional)</label>
                                        <input class="form-control" type="file" id="adjunto" name="adjunto">
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Guardar Registro
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pestaña Historial de Cargas -->
                    <div class="tab-pane fade" id="historial" role="tabpanel" aria-labelledby="historial-tab">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h3 class="h5 mb-0">Historial de Cargas</h3>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-filter me-1"></i>Filtrar
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                                        <li><a class="dropdown-item filter-history" href="#" data-days="30">Últimos 30 días</a></li>
                                        <li><a class="dropdown-item filter-history" href="#" data-month="current">Este mes</a></li>
                                        <li><a class="dropdown-item filter-history" href="#" data-month="previous">Mes anterior</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item filter-history" href="#" data-all="true">Todos los registros</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover preview-table mb-0" id="historialTable">
                                        <thead>
                                            <tr>
                                                <th>Fecha Carga</th>
                                                <th>Tipo</th>
                                                <th>Archivo</th>
                                                <th>Registros</th>
                                                <th>Usuario</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Datos se cargarán dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0" id="pagination">
                                        <!-- Paginación se cargará dinámicamente -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Ayuda -->
    <div class="modal fade" id="ayudaModal" tabindex="-1" aria-labelledby="ayudaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ayudaModalLabel"><i class="fas fa-question-circle me-2"></i>Ayuda - Carga de Datos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="ayudaAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Formatos de Archivo Aceptados
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#ayudaAccordion">
                                <div class="accordion-body">
                                    <h5 class="h6">Archivos Excel (.xlsx, .xls)</h5>
                                    <ul>
                                        <li>Debe contener columnas específicas según el tipo de carga</li>
                                        <li>La primera fila debe contener los encabezados</li>
                                        <li>No incluir fórmulas ni formatos complejos</li>
                                    </ul>
                                    
                                    <h5 class="h6 mt-3">Archivos de Texto (.txt) y CSV</h5>
                                    <ul>
                                        <li>Delimitado por pipes (|)</li>
                                        <li>Codificación UTF-8</li>
                                        <li>Sin comillas alrededor de los campos</li>
                                        <li>Ejemplo: 12345|Juan Pérez|500.00|2023-07-15</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Proceso de Carga Masiva
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#ayudaAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Selecciona el tipo de datos que vas a cargar</li>
                                        <li>Elige el formato del archivo</li>
                                        <li>Selecciona o arrastra el archivo</li>
                                        <li>Revisa la vista previa de los datos</li>
                                        <li>Confirma que los datos son correctos</li>
                                        <li>Procesa la carga</li>
                                        <li>Revisa el reporte de resultados</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Solución de Problemas
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#ayudaAccordion">
                                <div class="accordion-body">
                                    <h5 class="h6">Errores comunes:</h5>
                                    <div class="alert alert-danger mb-2">
                                        <strong>Formato incorrecto:</strong> Verifica que el archivo siga la estructura requerida.
                                    </div>
                                    <div class="alert alert-danger mb-2">
                                        <strong>IDs de socio no válidos:</strong> Asegúrate que todos los IDs existan en el sistema.
                                    </div>
                                    <div class="alert alert-danger">
                                        <strong>Fechas incorrectas:</strong> Las fechas deben estar en formato YYYY-MM-DD.
                                    </div>
                                    <p class="mt-3">Si el problema persiste, contacta al administrador del sistema.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Descargar Manual Completo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalles de Carga -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Detalles de Carga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Tipo de Carga:</strong> <span id="detailTipo"></span></p>
                            <p><strong>Archivo:</strong> <span id="detailArchivo"></span></p>
                            <p><strong>Fecha de Carga:</strong> <span id="detailFechaCarga"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Registros:</strong> <span id="detailTotal"></span></p>
                            <p><strong>Exitosos:</strong> <span id="detailExitosos"></span></p>
                            <p><strong>Fallidos:</strong> <span id="detailFallidos"></span></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Observaciones:</h6>
                        <div id="detailObservaciones" class="alert alert-light"></div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID Socio</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody id="detailTableBody">
                                <!-- Detalles se cargarán dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>

        // En la función renderPreview (para vista previa)
function renderPreview(data) {
    previewData.innerHTML = '';
    data.forEach(item => {
        const row = document.createElement('tr');
        const isDuplicate = item.estado === 'duplicado';
        
        row.innerHTML = `
            <td>${item.codigoemp}</td>
            <td>${item.nombre}</td>
            <td>Bs${item.monto.toFixed(2)}</td>
            <td>${item.fecha_transaccion}</td>
            <td>
                <span class="badge bg-${isDuplicate ? 'warning text-dark' : (item.estado === 'valido' ? 'success' : 'danger')}">
                    ${item.estado}
                </span>
                ${item.mensaje_error ? '<div class="small text-danger mt-1">' + item.mensaje_error + '</div>' : ''}
            </td>
        `;
        
        if (isDuplicate) {
            row.classList.add('table-warning');
        }
        
        previewData.appendChild(row);
    });
}
        $(document).ready(function() {
            // --- Configuración inicial ---
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

            // --- Funcionalidad de carga de archivos ---
            
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('fileInput');
            const selectFileBtn = document.getElementById('selectFileBtn');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const removeFileBtn = document.getElementById('removeFileBtn');
            const previewCard = document.getElementById('previewCard');
            const previewData = document.getElementById('previewData');
            const totalRecords = document.getElementById('totalRecords');
            const confirmData = document.getElementById('confirmData');
            const processDataBtn = document.getElementById('processDataBtn');
            
            // Variables globales
            let currentCargaId = null;
            
            // Seleccionar archivo
            selectFileBtn.addEventListener('click', () => fileInput.click());
            
            // Manejar archivo seleccionado
            fileInput.addEventListener('change', handleFiles);
            
            // Drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            // Efectos visuales
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropArea.classList.add('active');
            }
            
            function unhighlight() {
                dropArea.classList.remove('active');
            }
            
            // Manejar archivos soltados
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                handleFiles();
            }
            
            // Función para analizar archivos TXT
// En tu función parseTXTFile
function parseTXTFile(file, tipoCarga) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const content = e.target.result;
            const lines = content.split('\n');
            const registros = [];
            let lineNumber = 0;
            
            lines.forEach((line) => {
                lineNumber++;
                line = line.trim();
                if (!line || line.startsWith('#')) return;
                
                const fields = line.split('|').map(f => f.trim());
                if (fields.length < 4) {
                    registros.push({
                        lineNumber,
                        error: `Línea ${lineNumber}: Formato incorrecto (faltan campos)`
                    });
                    return;
                }
                
                const codigoemp = fields[0];
                const nombre = fields[1];
                const monto = parseFloat(fields[2]);
                const fecha_str = fields[3];
                
                // Validar fecha
                let fecha_valida = false;
                let fecha_formatted = '';
                
                if (fecha_str.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    try {
                        // Verificar que sea una fecha real (no permite 2023-02-30)
                        const date = new Date(fecha_str);
                        const isoString = date.toISOString().split('T')[0];
                        
                        if (isoString === fecha_str) {
                            fecha_valida = true;
                            fecha_formatted = fecha_str;
                        }
                    } catch (e) {
                        // Fecha inválida
                    }
                }
                
                if (!fecha_valida) {
                    registros.push({
                        lineNumber,
                        error: `Línea ${lineNumber}: Fecha inválida '${fecha_str}'. Formato requerido: YYYY-MM-DD`
                    });
                    return;
                }
                
                // Resto de validaciones...
                registros.push({
                    codigoemp,
                    nombre,
                    monto,
                    fecha_transaccion: fecha_formatted,
                    estado: 'valido',
                    mensaje_error: ''
                });
            });
            
            resolve({
                success: registros.filter(r => !r.error).length > 0,
                data: registros.filter(r => !r.error),
                errors: registros.filter(r => r.error),
                total: registros.filter(r => !r.error).length
            });
        };
        
        reader.onerror = () => reject({
            success: false,
            message: 'Error al leer el archivo'
        });
        
        reader.readAsText(file);
    });
}

// Modificar la función handleFiles para incluir procesamiento local
async function handleFiles() {
    const files = fileInput.files;
    if (files.length) {
        const file = files[0];
        const tipoCarga = $('#tipoCarga').val();
        const formatoArchivo = $('#formatoArchivo').val();
        
        // Mostrar información del archivo
        fileName.textContent = file.name;
        fileInfo.style.display = 'block';
        
        try {
            // Procesamiento local para vista previa
            let result;
            if (formatoArchivo === 'txt' || formatoArchivo === 'csv') {
                result = await parseTXTFile(file, tipoCarga);
            } else if (formatoArchivo === 'excel') {
                result = await parseExcelFile(file, tipoCarga);
            } else {
                throw new Error('Formato de archivo no soportado');
            }
            
            if (result.success) {
                previewCard.classList.remove('d-none');
                renderPreview(result.data);
                totalRecords.textContent = `${result.total} registros`;
                
                // Subir al servidor para procesamiento completo
                const uploadResult = await uploadFileToServer(file, tipoCarga, formatoArchivo);
                
                if (uploadResult.success) {
                    currentCargaId = uploadResult.carga_id;
                    processDataBtn.dataset.cargaId = uploadResult.carga_id;
                } else {
                    throw new Error(uploadResult.message);
                }
            } else {
                showAlert('danger', result.message);
                resetUpload();
            }
        } catch (error) {
            showAlert('danger', error.message || 'Error al procesar el archivo');
            resetUpload();
        }
    }
}

// Función para subir el archivo al servidor
async function uploadFileToServer(file, tipoCarga, formatoArchivo) {
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('tipo_carga', tipoCarga);
    formData.append('formato_archivo', formatoArchivo);
    
    try {
        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    } catch (error) {
        console.error('Error al subir archivo:', error);
        return { success: false, message: 'Error al subir archivo al servidor' };
    }
}

// Función para analizar archivos Excel (simplificada)
async function parseExcelFile(file, tipoCarga) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet);
            
            const registros = jsonData.map(row => {
                return {
                    codigoemp: row['ID Socio'] || row['codigoemp'] || '',
                    nombre: row['Nombre'] || '',
                    monto: parseFloat(row['Monto'] || 0),
                    fecha_transaccion: row['Fecha'] || '',
                    estado: 'valido',
                    mensaje_error: ''
                };
            });
            
            resolve({
                success: true,
                data: registros,
                total: registros.length
            });
        };
        
        reader.readAsArrayBuffer(file);
    });
}
            // Obtener vista previa de los datos
            function getPreviewData(carga_id) {
                $.ajax({
                    url: 'preview.php',
                    type: 'GET',
                    data: { carga_id: carga_id },
                    success: function(response) {
                        if (response.success) {
                            renderPreview(response.data);
                            totalRecords.textContent = `${response.total} registros`;
                            processDataBtn.data('carga-id', carga_id);
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Error al obtener vista previa');
                    }
                });
            }
            
            // Renderizar vista previa
            function renderPreview(data) {
    previewData.innerHTML = '';
    data.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.codigoemp}</td>
            <td>${item.nombre}</td>
            <td>Bs${item.monto.toFixed(2)}</td>
            <td>${item.fecha_transaccion}</td>
            <td>
                <span class="badge bg-${item.estado === 'valido' ? 'success' : 'danger'}">
                    ${item.estado}
                </span>
                ${item.mensaje_error ? '<div class="small text-danger mt-1">' + item.mensaje_error + '</div>' : ''}
            </td>
        `;
        previewData.appendChild(row);
    });
}
            // Procesar datos
processDataBtn.addEventListener('click', function() {
    const carga_id = $(this).data('carga-id');
    
    if (!confirmData.checked) {
        showAlert('warning', 'Debe confirmar que los datos son correctos');
        return;
    }
    
    processDataBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
    
    $.ajax({
        url: 'process.php',
        type: 'POST',
        data: { 
            carga_id: carga_id,
            confirmacion: true 
        },
        success: function(response) {
            if (response.success) {
                let message = `Carga completada: ${response.exitosos} registros exitosos`;
                if (response.fallidos > 0) {
                    message += `, ${response.fallidos} registros fallidos`;
                    
                    // Mostrar detalles de los errores
                    let errorDetails = '';
                    response.data.forEach(item => {
                        if (item.estado === 'invalido' && item.mensaje_error) {
                            errorDetails += `<div class="mb-2">• Socio ${item.codigoemp}: ${item.mensaje_error}</div>`;
                        }
                    });
                    
                    if (errorDetails) {
                        message += `<div class="mt-3 alert alert-warning">${errorDetails}</div>`;
                    }
                }
                
                showAlert('success', message);
                
                // Actualizar la vista previa con los nuevos estados
                if (response.data) {
                    renderPreview(response.data);
                }
            } else {
                showAlert('danger', response.message);
            }
            resetUpload();
        },
        error: function() {
            showAlert('danger', 'Error al procesar los datos');
            resetUpload();
        }
    });
});
            // Eliminar archivo seleccionado
            removeFileBtn.addEventListener('click', resetUpload);
            
          function resetUpload() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('previewCard').classList.add('d-none');
    document.getElementById('confirmData').checked = false;
    
    const processBtn = document.getElementById('processDataBtn');
    processBtn.disabled = true;
    processBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Procesar Carga';
    
    currentCargaId = null;
}
            document.getElementById('processDataBtn').addEventListener('click', async function() {
    // Verificación de seguridad
    if (!currentCargaId) {
        showAlert('danger', 'No hay una carga seleccionada para procesar');
        return;
    }

    if (!document.getElementById('confirmData').checked) {
        showAlert('warning', 'Debe confirmar que los datos son correctos');
        return;
    }

    // Mostrar estado de procesamiento
    const processBtn = this;
    processBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
    processBtn.disabled = true;

    try {
        const response = await procesarCarga(currentCargaId);
        
        if (response.success) {
            let message = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-2 fs-4 text-success"></i>
                    <div>
                        <strong>Carga completada exitosamente</strong><br>
                        Registros exitosos: ${response.exitosos}
                        ${response.fallidos > 0 ? `| Fallidos: ${response.fallidos}` : ''}
                    </div>
                </div>
            `;
            
            if (response.fallidos > 0) {
                message += `<div class="mt-2 small">Detalle de errores: ${response.errores || 'No hay detalles disponibles'}</div>`;
            }
            
            showAlert('success', message);
        } else {
            let errorMessage = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-times-circle me-2 fs-4 text-danger"></i>
                    <div>
                        <strong>Error al procesar la carga</strong><br>
                        ${response.message || 'No se pudo completar la operación'}
                    </div>
                </div>
            `;
            
            if (response.errores) {
                errorMessage += `<div class="mt-2 small">${response.errores}</div>`;
            }
            
            showAlert('danger', errorMessage);
        }
    } catch (error) {
        console.error('Error inesperado:', error);
        showAlert('danger', `
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2 fs-4 text-warning"></i>
                <div>
                    <strong>Error inesperado</strong><br>
                    ${error.message || 'Ocurrió un problema al procesar la carga'}
                </div>
            </div>
        `);
    } finally {
        resetUpload();
    }
});
function showAlert(type, message) {
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const container = document.getElementById('alerts-container');
    container.innerHTML = alertHTML;
    
    // Auto cerrar después de 10 segundos solo para mensajes de éxito
    if (type === 'success') {
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                bootstrap.Alert.getInstance(alert)?.close();
            }
        }, 10000);
    }
}
            // Descargar plantillas
$('#downloadExcelTemplate').click(function() {
    const tipo = $('#tipoCarga').val();
    window.location.href = `download_template.php?tipo=${tipo}&formato=excel`;
});

$('#downloadTextTemplate').click(function() {
    const tipo = $('#tipoCarga').val();
    window.location.href = `download_template.php?tipo=${tipo}&formato=txt`;
});
            
            // Mostrar/ocultar info de socio en carga individual
            $('#searchMemberBtn').click(function() {
                const codigoemp = $('#codigoemp').val().trim();
                
                if (!codigoemp) {
                    showAlert('warning', 'Ingrese un código de socio');
                    return;
                }
                
                $.ajax({
                    url: 'get_member_info.php',
                    type: 'GET',
                    data: { codigoemp: codigoemp },
                    success: function(response) {
                        if (response.success) {
                            $('#memberName').text(response.nombre);
                            $('#memberId').text(`ID: ${codigoemp}`);
                            $('#memberBalance').text(`Saldo: Bs${response.saldo.toFixed(2)}`);
                            $('#memberInfo').show();
                        } else {
                            showAlert('danger', response.message || 'Socio no encontrado');
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Error al buscar socio');
                    }
                });
            });
            
            $('#closeMemberInfo').click(function() {
                $('#memberInfo').hide();
            });
            
           // Validar formulario individual
$('#individualForm').submit(function(e) {
    e.preventDefault();
    const submitBtn = $(this).find('button[type="submit"]');
    submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...');
    submitBtn.prop('disabled', true);
    
    const formData = new FormData(this);
    
    $.ajax({
        url: 'individual.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#individualForm')[0].reset();
                $('#memberInfo').hide();
            } else {
                // Mostrar error específico para aportes duplicados
                if (response.message.includes('ya tiene un aporte registrado')) {
                    showAlert('danger', `<i class="fas fa-exclamation-triangle me-2"></i>${response.message}`);
                } else {
                    showAlert('danger', response.message);
                }
            }
            submitBtn.html('<i class="fas fa-save me-2"></i>Guardar Registro');
            submitBtn.prop('disabled', false);
        },
        error: function() {
            showAlert('danger', 'Error al guardar el registro');
            submitBtn.html('<i class="fas fa-save me-2"></i>Guardar Registro');
            submitBtn.prop('disabled', false);
        }
    });
});
            // Cargar historial al abrir la pestaña
            $('#historial-tab').on('click', function() {
                loadHistory();
            });
            
            // Filtrar historial
            $('.filter-history').click(function(e) {
                e.preventDefault();
                const days = $(this).data('days');
                const month = $(this).data('month');
                const all = $(this).data('all');
                
                loadHistory(1, days, month, all);
            });
            
            // Función para cargar historial
            function loadHistory(page = 1, days = null, month = null, all = false) {
                let url = 'history.php?pagina=' + page;
                
                if (days) {
                    url += '&days=' + days;
                } else if (month) {
                    url += '&month=' + month;
                } else if (all) {
                    url += '&all=true';
                }
                
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            renderHistory(response.data);
                            renderPagination(response.paginacion);
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Error al cargar el historial');
                    }
                });
            }
            
          function renderHistory(data) {
    const tbody = $('#historialTable tbody');
    tbody.empty();
    
    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center">No se encontraron registros</td></tr>');
        return;
    }

    data.forEach(item => {
        const estadoClass = {
            'completado': 'bg-success',
            'pendiente': 'bg-info',
            'procesando': 'bg-primary',
            'parcial': 'bg-warning text-dark',
            'fallido': 'bg-danger'
        }[item.estado.toLowerCase()] || 'bg-secondary';
        
        const fecha = new Date(item.fecha_carga);
        const fechaFormatted = fecha.toLocaleDateString() + ' ' + fecha.toLocaleTimeString();
        
        const row = `
            <tr>
                <td>${fechaFormatted}</td>
                <td>${item.tipo_carga || 'N/A'}</td>
                <td>${item.nombre_archivo || 'N/A'}</td>
                <td>${item.total_registros || 0}</td>
                <td>${item.usuario_nombre || 'N/A'}</td>
                <td><span class="badge ${estadoClass}">${item.estado || 'Desconocido'}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-download" data-id="${item.id}" title="Descargar">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary btn-details" data-id="${item.id}" title="Ver Detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });

    // Agregar event listeners a los nuevos botones
    $('.btn-download').off('click').on('click', function() {
        const cargaId = $(this).data('id');
        window.location.href = `download_original.php?carga_id=${cargaId}`;
    });
    
    $('.btn-details').off('click').on('click', function() {
        const cargaId = $(this).data('id');
        viewDetails(cargaId);
    });
}
            // Renderizar paginación
            function renderPagination(pagination) {
                const paginationEl = $('#pagination');
                paginationEl.empty();
                
                const { pagina, por_pagina, total, total_paginas } = pagination;
                
                // Botón Anterior
                const prevDisabled = pagina <= 1 ? 'disabled' : '';
                paginationEl.append(`
                    <li class="page-item ${prevDisabled}">
                        <a class="page-link" href="#" onclick="loadHistory(${pagina - 1})" tabindex="-1">Anterior</a>
                    </li>
                `);
                
                // Números de página
                const startPage = Math.max(1, pagina - 2);
                const endPage = Math.min(total_paginas, pagina + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const active = i === pagina ? 'active' : '';
                    paginationEl.append(`
                        <li class="page-item ${active}"><a class="page-link" href="#" onclick="loadHistory(${i})">${i}</a></li>
                    `);
                }
                
                // Botón Siguiente
                const nextDisabled = pagina >= total_paginas ? 'disabled' : '';
                paginationEl.append(`
                    <li class="page-item ${nextDisabled}">
                        <a class="page-link" href="#" onclick="loadHistory(${pagina + 1})">Siguiente</a>
                    </li>
                `);
            }
            async function loadHistory(page = 1, days = null, month = null, all = false) {
    try {
        // Mostrar loader
        $('#historialTable tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border" role="status"></div></td></tr>');
        
        // Construir URL con parámetros
        let url = `history.php?pagina=${page}`;
        if (days) url += `&days=${days}`;
        if (month) url += `&month=${month}`;
        if (all) url += `&all=true`;

        // Hacer la petición
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar el historial');
        }

        renderHistory(data.data);
        renderPagination(data.paginacion);
        
    } catch (error) {
        console.error('Error en loadHistory:', error);
        $('#historialTable tbody').html(`<tr><td colspan="7" class="text-center text-danger">${error.message}</td></tr>`);
    }
}async function viewDetails(cargaId) {
    try {
        // Mostrar loader en el modal
        $('#detailTableBody').html('<tr><td colspan="5" class="text-center"><div class="spinner-border" role="status"></div></td></tr>');
        
        const response = await fetch(`get_load_details.php?carga_id=${cargaId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Error al obtener detalles');
        }
        
        // Llenar modal con los detalles
        $('#detailTipo').text(data.data.tipo_carga || 'N/A');
        $('#detailArchivo').text(data.data.nombre_archivo || 'N/A');
        $('#detailFechaCarga').text(new Date(data.data.fecha_carga).toLocaleString());
        $('#detailTotal').text(data.data.total_registros || 0);
        $('#detailExitosos').text(data.data.registros_exitosos || 0);
        $('#detailFallidos').text(data.data.registros_fallidos || 0);
        $('#detailObservaciones').text(data.data.observaciones || 'Ninguna');
        
        // Llenar tabla de detalles
        const tbody = $('#detailTableBody');
        tbody.empty();
        
        if (data.detalles && data.detalles.length > 0) {
            data.detalles.forEach(detalle => {
                const row = `
                    <tr>
                        <td>${detalle.codigoemp || 'N/A'}</td>
                        <td>Bs${(detalle.monto || 0).toFixed(2)}</td>
                        <td>${detalle.fecha_transaccion || 'N/A'}</td>
                        <td><span class="badge bg-${detalle.estado === 'procesado' ? 'success' : 'danger'}">${detalle.estado || 'Desconocido'}</span></td>
                        <td>${detalle.mensaje_error || ''}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        } else {
            tbody.html('<tr><td colspan="5" class="text-center">No hay detalles disponibles</td></tr>');
        }
        
        // Mostrar modal
        const modal = new bootstrap.Modal('#detailsModal');
        modal.show();
        
    } catch (error) {
        console.error('Error en viewDetails:', error);
        $('#detailTableBody').html(`<tr><td colspan="5" class="text-center text-danger">${error.message}</td></tr>`);
    }
}
            // Ver detalles de carga
            window.viewDetails = function(carga_id) {
                $.ajax({
                    url: 'get_load_details.php',
                    type: 'GET',
                    data: { carga_id: carga_id },
                    success: function(response) {
                        if (response.success) {
                            // Llenar modal con los detalles
                            $('#detailTipo').text(capitalizeFirstLetter(response.data.tipo_carga));
                            $('#detailArchivo').text(response.data.nombre_archivo);
                            
                            const fechaCarga = new Date(response.data.fecha_carga);
                            $('#detailFechaCarga').text(fechaCarga.toLocaleString());
                            
                            $('#detailTotal').text(response.data.total_registros || 0);
                            $('#detailExitosos').text(response.data.registros_exitosos || 0);
                            $('#detailFallidos').text(response.data.registros_fallidos || 0);
                            
                            $('#detailObservaciones').text(response.data.observaciones || 'Ninguna');
                            
                            // Llenar tabla de detalles
                            const tbody = $('#detailTableBody');
                            tbody.empty();
                            
                            if (response.detalles && response.detalles.length > 0) {
                                response.detalles.forEach(detalle => {
                                    const row = `
                                        <tr>
                                            <td>${detalle.codigoemp}</td>
                                            <td>Bs${detalle.monto.toFixed(2)}</td>
                                            <td>${detalle.fecha_transaccion}</td>
                                            <td><span class="badge bg-${detalle.estado === 'procesado' ? 'success' : 'danger'}">${capitalizeFirstLetter(detalle.estado)}</span></td>
                                            <td>${detalle.mensaje_error || ''}</td>
                                        </tr>
                                    `;
                                    tbody.append(row);
                                });
                            }
                            
                            // Mostrar modal
                            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                            modal.show();
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Error al obtener detalles');
                    }
                });
            };
            
            // Descargar archivo original
            window.downloadFile = function(carga_id) {
                window.location.href = `download_original.php?carga_id=${carga_id}`;
            };
            document.getElementById('processDataBtn').addEventListener('click', async function() {
    // Verificación de seguridad
    if (!currentCargaId) {
        showAlert('danger', 'No hay una carga seleccionada para procesar');
        return;
    }

    if (!document.getElementById('confirmData').checked) {
        showAlert('warning', 'Debe confirmar que los datos son correctos');
        return;
    }

    // Mostrar estado de procesamiento
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
    this.disabled = true;

    try {
        const response = await procesarCarga(currentCargaId);
        
        if (response.success) {
            let message = `Carga completada: ${response.exitosos} registros exitosos`;
            if (response.fallidos > 0) {
                message += `, ${response.fallidos} registros fallidos`;
            }
            showAlert('success', message);
        } else {
            showAlert('danger', response.message || 'Error al procesar la carga');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('danger', 'Error inesperado al procesar la carga');
    } finally {
        resetUpload();
    }
});
async function procesarCarga(cargaId) {
    try {
        const response = await fetch('process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `carga_id=${cargaId}&confirmacion=true`
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Error en la respuesta del servidor');
        }
        
        return data;
    } catch (error) {
        console.error('Error en procesarCarga:', error);
        return { 
            success: false, 
            message: error.message || 'Error de conexión con el servidor' 
        };
    }
}
            // Funciones auxiliares
            function showAlert(type, message) {
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    `;
    $('#alerts-container').html(alertHTML);
    
    // Auto cerrar alerta después de 8 segundos (más tiempo para leer)
    setTimeout(() => {
        $('.alert').alert('close');
    }, 8000);
}
            
            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Cargar historial al iniciar si está en esa pestaña
            if (window.location.hash === '#historial') {
                loadHistory();
            }
        });
        
    </script>
</body>
</html>