<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Configuración de la conexión a PostgreSQL
$host = 'localhost';
$port = '5432';
$dbname = 'CAPCEL';
$dbuser = 'postgres';
$dbpassword = '123';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $dbuser, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener datos del POST
    $cod_empleado = $_POST['cod_empleado'] ?? '';
    $cedula = $_POST['cedula'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $monto_solicitado = floatval($_POST['monto_solicitado'] ?? 0);
    $plazo_meses = intval($_POST['plazo_meses'] ?? 0);
    $con_fiador = isset($_POST['con_fiador']) && $_POST['con_fiador'] == 'on';
    $cod_fiador = $_POST['cod_fiador'] ?? null;
    
    // Verificar que todos los campos necesarios estén presentes
    if (empty($cod_empleado) || empty($cedula) || empty($nombre) || 
        $monto_solicitado <= 0 || $plazo_meses <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
        exit;
    }
    
    // Si requiere fiador pero no lo especificó
    if ($con_fiador && empty($cod_fiador)) {
        echo json_encode(['success' => false, 'message' => 'Debe especificar un fiador']);
        exit;
    }
    
    // Ejecutar la función de préstamo
    $stmt = $pdo->prepare("
        SELECT * FROM solicitar_prestamo(
            :cod_empleado, 
            :monto_solicitado, 
            :plazo_meses, 
            :con_fiador, 
            :cod_fiador, 
            :usuario
        )
    ");
    
    $stmt->execute([
        'cod_empleado' => $cod_empleado,
        'monto_solicitado' => $monto_solicitado,
        'plazo_meses' => $plazo_meses,
        'con_fiador' => $con_fiador,
        'cod_fiador' => $cod_fiador,
        'usuario' => $_SESSION['user']['nombre']
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['exito']) {
        echo json_encode([
            'success' => true,
            'message' => $result['mensaje'],
            'id_prestamo' => $result['id_prestamo'],
            'cuota_mensual' => $result['cuota_mensual'],
            'total_a_pagar' => $result['total_a_pagar']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['mensaje']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>