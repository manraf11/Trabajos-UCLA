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
    
    $cod_fiador = $_POST['cod_fiador'] ?? '';
    $monto_solicitado = floatval($_POST['monto_solicitado'] ?? 0);
    $cod_empleado = $_POST['cod_empleado'] ?? '';
    
    if (empty($cod_fiador)) {
        echo json_encode(['success' => false, 'message' => 'Código de fiador no especificado']);
        exit;
    }
    
    // Verificar que el fiador no sea el mismo solicitante
    if ($cod_fiador === $cod_empleado) {
        echo json_encode(['success' => false, 'message' => 'No puede ser fiador de sí mismo']);
        exit;
    }
    
    // Verificar que el fiador exista
    $stmt = $pdo->prepare("SELECT * FROM asociados WHERE cod_empleado = :cod_fiador");
    $stmt->execute(['cod_fiador' => $cod_fiador]);
    $fiador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fiador) {
        echo json_encode(['success' => false, 'message' => 'El fiador no existe']);
        exit;
    }
    
    // Verificar elegibilidad del fiador
    $stmt = $pdo->prepare("
        SELECT * FROM verificar_elegibilidad_prestamo(
            :cod_fiador, 
            :monto_solicitado, 
            false, 
            null
        )
    ");
    $stmt->execute([
        'cod_fiador' => $cod_fiador,
        'monto_solicitado' => $monto_solicitado
    ]);
    
    $elegibilidad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($elegibilidad['elegible']) {
        echo json_encode([
            'success' => true,
            'message' => 'Fiador válido',
            'nombre' => $fiador['nombre'],
            'disponibilidad' => $fiador['totalcaja'] * 0.80
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $elegibilidad['mensaje']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>