<?php
session_start();
header('Content-Type: application/json');

// Configuración de la base de datos (USANDO LOS DATOS ORIGINALES QUE FUNCIONABAN)
$host = 'localhost';
$dbname = 'CAPCEL';
$user = 'postgres';
$password = '123';

try {
    // Conexión a PostgreSQL (versión original que funcionaba)
    $conn = new PDO("pgsql:host=$host;dbname=$dbname;user=$user;password=$password");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificación adicional de conexión
    if (!$conn) {
        throw new PDOException("No se pudo establecer conexión");
    }

    // Obtener datos del formulario
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';

    // Validaciones básicas
    if (empty($usuario) || empty($clave)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        exit;
    }

    // Determinar si es email o cédula
    $isEmail = filter_var($usuario, FILTER_VALIDATE_EMAIL);
    
    // Consulta SQL segura (versión original)
    $query = "SELECT id, nombre, email, password, saldo, rol, cedula 
              FROM usuarios 
              WHERE " . ($isEmail ? "email = :usuario" : "cedula = :usuario");
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':usuario', $usuario);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($clave, $user['password'])) {
            // Datos de sesión (versión original + cod_empleado si existe)
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                'saldo' => $user['saldo'],
                'cedula' => $user['cedula']
            ];
            
            // Si existe cod_empleado en la consulta, lo añadimos
            if (isset($user['cod_empleado'])) {
                $_SESSION['user']['cod_empleado'] = $user['cod_empleado'];
                $_SESSION['cod_empleado'] = $user['cod_empleado'];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    }
} catch (PDOException $e) {
    // Mensaje de error detallado para diagnóstico
    error_log('Error de PostgreSQL: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de conexión con la base de datos',
        'error_details' => $e->getMessage() // Solo para desarrollo, quitar en producción
    ]);
}
?>