<?php
// individual.php
session_start();
require_once './/db_connection.php';


$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('No autorizado');
    }

    $tipoRegistro = $_POST['tipo_registro'] ?? '';
    $codigoemp = $_POST['codigoemp'] ?? '';
    $monto = $_POST['monto'] ?? 0;
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $descripcion = $_POST['descripcion'] ?? '';
    $usuario = $_SESSION['user']['nombre'];

    // Validar datos básicos
    if (empty($codigoemp) || empty($monto) || empty($fecha)) {
        throw new Exception('Datos incompletos');
    }

    if ($monto <= 0) {
        throw new Exception('El monto debe ser mayor a cero');
    }

    // Verificar que el socio existe
    $stmt = $pdo->prepare("SELECT nombre FROM asociados WHERE cod_empleado = ?");
    $stmt->execute([$codigoemp]);
    $asociado = $stmt->fetch();

    if (!$asociado) {
        throw new Exception('El código de empleado no existe');
    }

    // Procesar según tipo de registro
    if ($tipoRegistro === 'aporte') {
        try {
            $pdo->beginTransaction();
            
            // Insertar en historial_aportes
            $stmt = $pdo->prepare("INSERT INTO historial_aportes 
                                  (cod_empleado, fecha_aporte, periodo_aporte, monto_aporte, 
                                  tipo_aporte, forma_pago, observaciones, usuario_registro, estado_aporte) 
                                  VALUES (?, ?, ?, ?, 'ordinario', 'manual', ?, ?, 'Aplicado')");
            
            $fechaObj = new DateTime($fecha);
            $periodo = $fechaObj->format('Y-m');
            
            $stmt->execute([
                $codigoemp,
                $fecha,
                $periodo,
                $monto,
                $descripcion,
                $usuario
            ]);
            
            // Insertar en tabla aportes
            $stmt = $pdo->prepare("INSERT INTO aportes 
                                  (cod_empleado, monto_aporte, usuario_registro, fecha_aporte) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $codigoemp,
                $monto,
                $usuario,
                $fecha
            ]);
            
            $pdo->commit();
            $response['message'] = 'Aporte registrado correctamente';
            $response['success'] = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            if ($e->getCode() == '23505') {
                $response['message'] = 'Error: Este socio ya tiene un aporte registrado para este mes';
            } else {
                $response['message'] = 'Error al registrar el aporte: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        // Otros tipos de registros...
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>