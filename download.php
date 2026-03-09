<?php
// download.php
require_once './/db_connection.php';


// Verificar autenticación
if (!isset($_SESSION['user'])) {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

try {
    $tipo = $_GET['tipo'] ?? 'aportes';
    
    // Contenido de la plantilla
    $contenido = "";
    
    if ($tipo === 'aportes') {
        $contenido = "CODIGO_EMPLEADO|NOMBRE|MONTO|FECHA\n";
        $contenido .= "EMP001|Ejemplo Empleado|100.00|" . date('Y-m-d') . "\n";
        $contenido .= "EMP002|Otro Empleado|150.50|" . date('Y-m-d') . "\n";
    }
    
    // Configurar headers para descarga
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="plantilla_aportes.txt"');
    header('Content-Length: ' . strlen($contenido));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $contenido;
    exit;

} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo "Error al generar plantilla: " . $e->getMessage();
}
?>