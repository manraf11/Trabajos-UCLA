<?php
header('Content-Type: application/json');

require_once './/db_connection.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud no válido');
    }

    // Verificar campos requeridos
    $required_fields = ['nombre', 'usuario', 'email', 'password', 'codigo_empleado'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo $field es requerido");
        }
    }

    // Asignar y limpiar valores
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $codigo_empleado = trim($_POST['codigo_empleado']);

    // Validaciones
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico no es válido');
    }

    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    // 1. Buscar la cédula en la tabla asociados
    $stmt = $pdo->prepare("SELECT cedula FROM asociados WHERE cod_empleado = :codigo");
    $stmt->bindParam(':codigo', $codigo_empleado, PDO::PARAM_STR);
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultado || !isset($resultado['cedula'])) {
        throw new Exception('No se encontró la cédula asociada al código de empleado');
    }

    $cedula = $resultado['cedula'];

    // 2. Verificar si usuario o email ya existen
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email OR usuario = :usuario");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        throw new Exception('El correo electrónico o nombre de usuario ya están registrados');
    }

    // Hash de la contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 3. Insertar nuevo usuario incluyendo la cédula
    $stmt = $pdo->prepare("INSERT INTO usuarios 
                          (nombre, email, password, rol, codigo_empleado, usuario, cedula) 
                          VALUES (:nombre, :email, :password, 'asociado', :codigo_empleado, :usuario, :cedula)");
    
    $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':password', $passwordHash, PDO::PARAM_STR);
    $stmt->bindParam(':codigo_empleado', $codigo_empleado, PDO::PARAM_STR);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Registro exitoso. Ahora puedes iniciar sesión.';
    } else {
        throw new Exception('Error al registrar el usuario');
    }

} catch (PDOException $e) {
    error_log("Error PDO en register.php: " . $e->getMessage());
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    // En producción puedes usar un mensaje genérico:
    // $response['message'] = 'Ocurrió un error al procesar tu registro. Por favor intenta nuevamente.';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>