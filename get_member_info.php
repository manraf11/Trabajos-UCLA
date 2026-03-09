<?php
session_start();
require_once './/db_connection.php'; // Asegúrate de que esta ruta sea correcta

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('No autorizado');
    }

    $codigoemp = $_GET['codigoemp'] ?? '';

    if (empty($codigoemp)) {
        throw new Exception('Código de empleado no proporcionado');
    }

    // Consulta para obtener información del socio
    $stmt = $pdo->prepare("SELECT cod_empleado, nombre, salinicial FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$codigoemp]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$socio) {
        throw new Exception('Socio no encontrado');
    }

    $response = [
        'success' => true,
        'codigoemp' => $socio['cod_empleado'],
        'nombre' => $socio['nombre'],
        'salinicial' => $socio['salinicial']
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>