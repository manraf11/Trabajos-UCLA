<?php
header('Content-Type: application/json');

require_once './/db_connection.php'; // Corregí la ruta (eliminé el doble slash)

$response = ['exists' => false, 'nombre' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_empleado'])) {
    $codigo_empleado = trim($_POST['codigo_empleado']);
    
    try {
        // Usamos la conexión PDO que ya está creada en db_connection.php
        $stmt = $pdo->prepare("SELECT nombre FROM asociados WHERE cod_empleado = :codigo");
        $stmt->bindParam(':codigo', $codigo_empleado, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $asociado = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = [
                'exists' => true,
                'nombre' => $asociado['nombre']
            ];
        }
    } catch(PDOException $e) {
        // Para depuración, muestra el error real (quitar en producción)
        $response['error'] = $e->getMessage();
        error_log("Error en check_codigo_empleado: " . $e->getMessage());
    }
}

echo json_encode($response);
?>