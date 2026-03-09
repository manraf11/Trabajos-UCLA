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
    
    $id_retiro = $_POST['id'] ?? 0;
    
    if ($id_retiro <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de retiro inválido']);
        exit;
    }
    
    // Verificar que el retiro exista y esté pendiente
    $stmt = $pdo->prepare("SELECT * FROM historico_retiros_haberes WHERE id = :id AND estado = 'Pendiente'");
    $stmt->execute(['id' => $id_retiro]);
    $retiro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$retiro) {
        echo json_encode(['success' => false, 'message' => 'El retiro no existe o ya fue procesado']);
        exit;
    }
    
    // Actualizar estado a Rechazado
    $stmt = $pdo->prepare("
        UPDATE historico_retiros_haberes 
        SET estado = 'Rechazado', 
            motivo_rechazo = 'Cancelado por el solicitante',
            aprobado_por = :usuario
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $id_retiro,
        'usuario' => $_SESSION['user']['nombre']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Retiro cancelado exitosamente'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>