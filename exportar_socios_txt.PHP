<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Incluir la conexión a la base de datos
require_once './/db_connection.php';

// Obtener los filtros del POST
$busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';
$estado = isset($_POST['estado']) ? $_POST['estado'] : '';
$tipo_nomina = isset($_POST['tipo_nomina']) ? $_POST['tipo_nomina'] : '';

// Construir la consulta SQL con los filtros
$sql = "SELECT 
            cod_empleado, 
            nombre, 
            cedula, 
            tipo_nomina, 
            fecha_ingreso, 
            salinicial, 
            statu,
            status,
            cta_empleado,
            tipo_cuenta,
            nombre_banco,
            sueldo,
            nacionalidad
        FROM asociados 
        WHERE 1=1";

$params = array();

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR cedula LIKE ? OR cod_empleado LIKE ?)";
    $searchTerm = "%$busqueda%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($estado)) {
    $sql .= " AND statu = ?";
    $params[] = $estado;
}

if (!empty($tipo_nomina)) {
    $sql .= " AND tipo_nomina = ?";
    $params[] = $tipo_nomina;
}

$sql .= " ORDER BY nombre ASC";

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar las cabeceras para descargar un archivo TXT
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="socios_caja_ahorro_' . date('Y-m-d') . '.txt"');

// Función para mapear el estado
function getEstadoTexto($statu) {
    switch($statu) {
        case 'A': return 'Activo';
        case 'I': return 'Inactivo';
        case 'S': return 'Suspendido';
        case 'J': return 'Jubilado';
        case 'L': return 'Liquidado';
        default: return 'Desconocido';
    }
}

// Generar el contenido del archivo TXT
$contenido = "LISTADO DE SOCIOS - CAJA DE AHORRO\n";
$contenido .= "Generado el: " . date('d/m/Y H:i:s') . "\n\n";
$contenido .= str_pad("ID", 10) . " | ";
$contenido .= str_pad("NOMBRE COMPLETO", 40) . " | ";
$contenido .= str_pad("CÉDULA", 15) . " | ";
$contenido .= str_pad("TIPO NÓMINA", 12) . " | ";
$contenido .= str_pad("FECHA INGRESO", 12) . " | ";
$contenido .= str_pad("SALDO", 15) . " | ";
$contenido .= str_pad("ESTADO", 12) . " | ";
$contenido .= str_pad("SUELDO", 15) . " | ";
$contenido .= str_pad("CUENTA BANCARIA", 20) . "\n";
$contenido .= str_repeat("-", 160) . "\n";

foreach ($socios as $socio) {
    $contenido .= str_pad($socio['cod_empleado'], 10) . " | ";
    $contenido .= str_pad(substr($socio['nombre'], 0, 40), 40) . " | ";
    $contenido .= str_pad($socio['cedula'], 15) . " | ";
    $contenido .= str_pad($socio['tipo_nomina'], 12) . " | ";
    $contenido .= str_pad(date('d/m/Y', strtotime($socio['fecha_ingreso'])), 12) . " | ";
    $contenido .= str_pad(number_format($socio['salinicial'], 2, ',', '.'), 15) . " | ";
    $contenido .= str_pad(getEstadoTexto($socio['statu']), 12) . " | ";
    $contenido .= str_pad(number_format($socio['sueldo'] ?? 0, 2, ',', '.'), 15) . " | ";
    $contenido .= str_pad($socio['cta_empleado'] ?? 'N/A', 20) . "\n";
}

// Agregar resumen al final
$contenido .= "\nRESUMEN:\n";
$contenido .= "Total de socios: " . count($socios) . "\n";
$contenido .= "Socios activos: " . count(array_filter($socios, fn($s) => $s['statu'] === 'A')) . "\n";
$contenido .= "Socios inactivos: " . count(array_filter($socios, fn($s) => $s['statu'] === 'I')) . "\n";
$contenido .= "Socios suspendidos: " . count(array_filter($socios, fn($s) => $s['statu'] === 'S')) . "\n";
$contenido .= "Socios jubilados: " . count(array_filter($socios, fn($s) => $s['statu'] === 'J')) . "\n";

// Imprimir el contenido
echo $contenido;
exit;
?>