<?php
// Verificar autenticación
session_start();
if (!isset($_SESSION['user'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso denegado');
}

// Ruta del archivo
$rutaArchivo = __DIR__ . '/plantilla.txt';


if (!file_exists($rutaArchivo)) {
    header('HTTP/1.0 404 Not Found');
    exit('Archivo de plantilla no encontrado');
}

// Configurar headers para la descarga
header('Content-Description: File Transfer');
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="plantilla.txt"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($rutaArchivo));

// Limpiar el buffer de salida y leer el archivo
ob_clean();
flush();
readfile($rutaArchivo);
exit;
?>