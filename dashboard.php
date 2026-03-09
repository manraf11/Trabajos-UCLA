<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Conexión a la base de datos
$db = new PDO('pgsql:host=localhost;dbname=CAPCEL', 'postgres', '123');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Obtener estadísticas reales
try {
   // Total de socios activos (statu = 'A') o jubilados (statu = 'J')
$query = "SELECT COUNT(*) as total_socios FROM asociados WHERE statu = 'A' OR statu = 'J'";
$stmt = $db->query($query);
$total_socios = $stmt->fetch(PDO::FETCH_ASSOC)['total_socios'];
    
    // Saldo general (suma de salinicial de todos los asociados)
$query = "SELECT SUM(salinicial) as saldo_general FROM asociados";
$stmt = $db->query($query);
$saldo_general = $stmt->fetch(PDO::FETCH_ASSOC)['saldo_general'];
    
   // Contar asociados con préstamos activos (totalprest > 0)
$query = "SELECT COUNT(*) as prestamos_activos 
          FROM asociados 
          WHERE (statu = 'A' OR statu = 'J') 
          AND totalprest > 0";
$stmt = $db->query($query);
$prestamos_activos = $stmt->fetch(PDO::FETCH_ASSOC)['prestamos_activos'];
  // Formatear el saldo general para mostrarlo como moneda (en bolívares)
$saldo_formateado = 'Bs. ' . number_format($saldo_general, 2, ',', '.');
    
} catch(PDOException $e) {
    // En caso de error, usar valores por defecto
    $total_socios = "N/D";
    $saldo_formateado = "$0.00";
    $prestamos_activos = "N/D";
}

$user = $_SESSION['user'];
$nombre_usuario = $user['nombre']; // Obtenemos el nombre de la sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Caja de Ahorro</title>

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
        
        .summary-card, .module-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .summary-card:hover, .module-card:hover {
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
        
        .module-card .card-body {
            text-align: center;
        }
        
        .module-card .module-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .module-card .card-title {
            font-weight: 600;
            color: var(--dark-gray);
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
                <a href="#" class="list-group-item list-group-item-action active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
                
                <a href="historial.php" class="list-group-item list-group-item-action" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="fas fa-history"></i>Módulo Historial
                </a>

                
                <!-- Nuevo módulo agregado aquí -->
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
                <h1 class="mb-4">Dashboard Principal</h1>
                
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <div>
                                    <h5 class="card-title text-muted">Total de Socios</h5>
                                    <h2 class="fw-bold"><?php echo $total_socios; ?></h2>
                                </div>
                                <div class="icon-circle bg-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card summary-card">
                             <div class="card-body">
                                <div>
                                    <h5 class="card-title text-muted">Saldo General</h5>
                                    <h2 class="fw-bold"><?php echo $saldo_formateado; ?></h2>
                                </div>
                                <div class="icon-circle bg-success">
                                    <i class="fas fa-wallet"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="col-md-4">
                        <div class="card summary-card">
                             <div class="card-body">
                                <div>
                                    <h5 class="card-title text-muted">Préstamos Activos</h5>
                                    <h2 class="fw-bold"><?php echo $prestamos_activos; ?></h2>
                                </div>
                                <div class="icon-circle bg-warning text-dark">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="h4 mb-4">Módulos del Sistema</h2>
                 <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <a href="#" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-calculator"></i></div>
                                    <h5 class="card-title">Pagos</h5>
                                    <p class="card-text text-muted">Gestión de Pagos.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                     <div class="col-md-6 col-lg-4">
                        <a href="historial.php" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-history"></i></div>
                                    <h5 class="card-title">Historial</h5>
                                    <p class="card-text text-muted">Revisa transacciones, ahorros y movimientos pasados.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="consulta.php" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-search-dollar"></i></div>
                                    <h5 class="card-title">Consultas</h5>
                                    <p class="card-text text-muted">Consulta de saldos, estados de cuenta y préstamos.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="carga.php" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-upload"></i></div>
                                    <h5 class="card-title">Carga de Datos</h5>
                                    <p class="card-text text-muted">Carga individual o masiva de aportes y descuentos.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                     <div class="col-md-6 col-lg-4">
                        <a href="socios.php" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-users-cog"></i></div>
                                    <h5 class="card-title">Gestión de Socios</h5>
                                    <p class="card-text text-muted">Agrega nuevos socios y administra la información existente.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <!-- Puedes agregar también una tarjeta para el nuevo módulo si lo deseas -->
                    <div class="col-md-6 col-lg-4">
                        <a href="retiros_prestamos.php" class="text-decoration-none">
                            <div class="card module-card h-100">
                                <div class="card-body">
                                    <div class="module-icon"><i class="fas fa-hand-holding-usd"></i></div>
                                    <h5 class="card-title">Retiros y Préstamos</h5>
                                    <p class="card-text text-muted">Gestión de retiros de haberes y solicitudes de préstamos.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                 </div>
            </main>
        </div>
        </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
                
                // Opcional: Cargar contenido dinámicamente con AJAX
                // var page = $(this).attr('href').substring(1);
                // $('#page-content-wrapper .main-content').load(page + '.php');
            });
        });
    </script>
</body>
</html>