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
    $monto_retiro = floatval($_POST['monto_retiro'] ?? 0);
    
    // Verificar que todos los campos necesarios estén presentes
    if (empty($cod_empleado) || empty($cedula) || empty($nombre) || $monto_retiro <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
        exit;
    }
    
    // Ejecutar la función de retiro
    $stmt = $pdo->prepare("SELECT * FROM realizar_retiro_haberes(:cod_empleado, :monto, :usuario)");
    $stmt->execute([
        'cod_empleado' => $cod_empleado,
        'monto' => $monto_retiro,
        'usuario' => $_SESSION['user']['nombre']
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['exito']) {
        echo json_encode([
            'success' => true,
            'message' => $result['mensaje'],
            'id_retiro' => $result['id_retiro']
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